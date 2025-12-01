<?php
/**
 * Search Service
 * Provides fast search using denormalized search index with FULLTEXT
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Search_Service {

    private $search_table;
    private $wpdb;
    private $cache;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->search_table = $wpdb->prefix . 'fwd_search_index';
        $this->cache = fwd_cache();
    }

    /**
     * Fast search across all fields using FULLTEXT index
     */
    public function search($query, $use_cache = true) {
        if ($use_cache) {
            $cache_key = 'search_' . md5(strtolower(trim($query)));
            return $this->cache->remember($cache_key, function() use ($query) {
                return $this->execute_search($query);
            });
        }

        return $this->execute_search($query);
    }

    /**
     * Execute the actual search
     */
    private function execute_search($query) {
        // Use FULLTEXT search if query is 3+ characters
        if (strlen($query) >= 3) {
            $results = $this->fulltext_search($query);

            // If FULLTEXT returns results, use them
            if (!empty($results)) {
                return $this->format_results($results);
            }
        }

        // Fallback to LIKE search
        return $this->like_search($query);
    }

    /**
     * FULLTEXT search (fast) - Uses AND logic for multiple words
     */
    private function fulltext_search($query) {
        // Convert query to BOOLEAN MODE with AND logic by prefixing each word with +
        $words = preg_split('/\s+/', trim($query));
        $boolean_query = '+' . implode(' +', array_filter($words));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->search_table}
             WHERE MATCH(search_text) AGAINST(%s IN BOOLEAN MODE)
             AND deleted_at IS NULL
             ORDER BY created_at DESC
             LIMIT 100",
            $boolean_query
        ), ARRAY_A);
    }

    /**
     * LIKE search (fallback for short queries) - Uses AND logic for multiple words
     */
    private function like_search($query) {
        // Split query into words and build AND conditions
        $words = preg_split('/\s+/', trim($query));
        $words = array_filter($words);

        if (empty($words)) {
            return $this->format_results(array());
        }

        // Build WHERE clause with AND logic: each word must appear in at least one field
        $where_clauses = array();
        $params = array();

        foreach ($words as $word) {
            $like = '%' . $this->wpdb->esc_like($word) . '%';
            $where_clauses[] = "(film_title LIKE %s OR actor_name LIKE %s OR brand_name LIKE %s OR model_reference LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = "SELECT * FROM {$this->search_table}
                WHERE {$where_sql}
                AND deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT 100";

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->format_results($results);
    }

    /**
     * Format search results into structured data
     */
    private function format_results($results) {
        // Group by film
        $by_film = array();
        // Group by actor
        $by_actor = array();
        // Group by brand
        $by_brand = array();

        foreach ($results as $row) {
            // Group by film
            $film_key = $row['film_title'] . '_' . $row['film_year'];
            if (!isset($by_film[$film_key])) {
                $by_film[$film_key] = array(
                    'title' => $row['film_title'],
                    'year' => $row['film_year'],
                    'watches' => array()
                );
            }
            $by_film[$film_key]['watches'][] = $this->format_watch($row);

            // Group by actor
            if (!isset($by_actor[$row['actor_name']])) {
                $by_actor[$row['actor_name']] = array();
            }
            $by_actor[$row['actor_name']][] = $this->format_watch($row);

            // Group by brand
            if (!isset($by_brand[$row['brand_name']])) {
                $by_brand[$row['brand_name']] = array(
                    'brand' => $row['brand_name'],
                    'films' => array()
                );
            }

            $brand_film_key = $row['film_title'] . '_' . $row['film_year'];
            if (!isset($by_brand[$row['brand_name']]['films'][$brand_film_key])) {
                $by_brand[$row['brand_name']]['films'][$brand_film_key] = array(
                    'title' => $row['film_title'],
                    'year' => $row['film_year'],
                    'watches' => array()
                );
            }
            $by_brand[$row['brand_name']]['films'][$brand_film_key]['watches'][] = $this->format_watch($row);
        }

        // Convert to arrays and add counts
        foreach ($by_brand as $brand => &$data) {
            $data['films'] = array_values($data['films']);
            $data['film_count'] = count($data['films']);
            $total_watches = 0;
            foreach ($data['films'] as $film) {
                $total_watches += count($film['watches']);
            }
            $data['count'] = $total_watches;
        }

        return array(
            'success' => true,
            'total_results' => count($results),
            'films' => array_values($by_film),
            'actors' => $by_actor,
            'brands' => array_values($by_brand)
        );
    }

    /**
     * Format a single watch entry
     */
    private function format_watch($row) {
        return array(
            'title' => $row['film_title'],
            'year' => $row['film_year'],
            'actor' => $row['actor_name'],
            'brand' => $row['brand_name'],
            'model' => $row['model_reference'],
            'character' => $row['character_name'],
            'narrative' => $row['narrative_role'],
            'image_url' => $row['image_url'],
            'gallery_ids' => isset($row['gallery_ids']) ? $row['gallery_ids'] : '',
            'regallery_id' => isset($row['regallery_id']) ? $row['regallery_id'] : null,
            'image_caption' => !empty($row['image_url']) ? fwd_get_image_caption($row['image_url']) : '',
            'source' => $row['source_url'],
            'confidence_level' => $row['confidence_level']
        );
    }

    /**
     * Rebuild entire search index
     */
    public function rebuild_index() {
        // Truncate search index
        $this->wpdb->query("TRUNCATE TABLE {$this->search_table}");

        // Rebuild from film_actor_watch
        $faw_table = $this->wpdb->prefix . 'fwd_film_actor_watch';
        $films_table = $this->wpdb->prefix . 'fwd_films';
        $actors_table = $this->wpdb->prefix . 'fwd_actors';
        $brands_table = $this->wpdb->prefix . 'fwd_brands';
        $watches_table = $this->wpdb->prefix . 'fwd_watches';
        $characters_table = $this->wpdb->prefix . 'fwd_characters';

        $inserted = $this->wpdb->query("
            INSERT INTO {$this->search_table}
                (faw_id, film_title, film_year, actor_name, brand_name,
                 model_reference, character_name, narrative_role,
                 image_url, gallery_ids, regallery_id, source_url, confidence_level, search_text,
                 created_at, deleted_at)
            SELECT
                faw.faw_id,
                f.title,
                f.year,
                a.actor_name,
                b.brand_name,
                w.model_reference,
                c.character_name,
                faw.narrative_role,
                faw.image_url,
                faw.gallery_ids,
                faw.regallery_id,
                faw.source_url,
                faw.confidence_level,
                CONCAT_WS(' ',
                    f.title,
                    f.year,
                    a.actor_name,
                    b.brand_name,
                    w.model_reference,
                    c.character_name,
                    faw.narrative_role
                ),
                faw.created_at,
                faw.deleted_at
            FROM {$faw_table} faw
            JOIN {$films_table} f ON faw.film_id = f.film_id
            JOIN {$actors_table} a ON faw.actor_id = a.actor_id
            JOIN {$characters_table} c ON faw.character_id = c.character_id
            JOIN {$watches_table} w ON faw.watch_id = w.watch_id
            JOIN {$brands_table} b ON w.brand_id = b.brand_id
        ");

        // Clear search cache
        $this->cache->flush('search_*');

        // Return structured result
        $errors = ($inserted === false) ? 1 : 0;
        return array(
            'indexed' => ($inserted === false) ? 0 : intval($inserted),
            'errors' => $errors
        );
    }

    /**
     * Add single entry to search index
     */
    public function index_entry($faw_id) {
        $faw_table = $this->wpdb->prefix . 'fwd_film_actor_watch';
        $films_table = $this->wpdb->prefix . 'fwd_films';
        $actors_table = $this->wpdb->prefix . 'fwd_actors';
        $brands_table = $this->wpdb->prefix . 'fwd_brands';
        $watches_table = $this->wpdb->prefix . 'fwd_watches';
        $characters_table = $this->wpdb->prefix . 'fwd_characters';

        return $this->wpdb->query($this->wpdb->prepare("
            INSERT INTO {$this->search_table}
                (faw_id, film_title, film_year, actor_name, brand_name,
                 model_reference, character_name, narrative_role,
                 image_url, gallery_ids, regallery_id, source_url, confidence_level, search_text,
                 created_at, deleted_at)
            SELECT
                faw.faw_id,
                f.title,
                f.year,
                a.actor_name,
                b.brand_name,
                w.model_reference,
                c.character_name,
                faw.narrative_role,
                faw.image_url,
                faw.gallery_ids,
                faw.regallery_id,
                faw.source_url,
                faw.confidence_level,
                CONCAT_WS(' ',
                    f.title,
                    f.year,
                    a.actor_name,
                    b.brand_name,
                    w.model_reference,
                    c.character_name,
                    faw.narrative_role
                ),
                faw.created_at,
                faw.deleted_at
            FROM {$faw_table} faw
            JOIN {$films_table} f ON faw.film_id = f.film_id
            JOIN {$actors_table} a ON faw.actor_id = a.actor_id
            JOIN {$characters_table} c ON faw.character_id = c.character_id
            JOIN {$watches_table} w ON faw.watch_id = w.watch_id
            JOIN {$brands_table} b ON w.brand_id = b.brand_id
            WHERE faw.faw_id = %d
        ", $faw_id));
    }

    /**
     * Update entry in search index
     */
    public function update_entry($faw_id) {
        // Delete old entry
        $this->wpdb->delete($this->search_table, array('faw_id' => $faw_id));

        // Re-index
        return $this->index_entry($faw_id);
    }

    /**
     * Remove entry from search index
     */
    public function remove_entry($faw_id) {
        return $this->wpdb->delete($this->search_table, array('faw_id' => $faw_id));
    }
}

/**
 * Get global search service instance
 */
function fwd_search() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FWD_Search_Service();
    }
    return $instance;
}
