<?php
defined( 'ABSPATH' ) || exit;

class Epiza {
    /**
	 * The single instance of the class
	 */
	protected static $_instance = null;

    /**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

    /**
	 * Constructor
	 */
    public function __construct() {
		add_action('admin_menu', array($this, 'register_page'));
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'scripts'));
		add_action('wp_ajax_epizaSearchMovies', array($this, 'searchMovies'));
		add_action('wp_ajax_epizaSearchTV', array($this, 'searchTV'));
        add_action('wp_ajax_epizaImport', array($this, 'import'));
        add_filter('the_content', array($this, 'content'));
    }

    /**
	 * Init
	 */
    public function init() {
        load_plugin_textdomain( 'epiza', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

	/**
	 * Register Admin Page
	 */
    public function register_page(){
        add_menu_page( 
            esc_html__( 'Epiza', 'epiza' ),
            esc_html__( 'Epiza', 'epiza' ),
            'manage_options',
            'epiza',
            array($this, 'admin_page_output'),
            'dashicons-video-alt3',
            10
        );
    }

	/* Admin Scripts */
    public function admin_scripts($hook){
        wp_enqueue_style('epiza-admin-general', EPIZA_PLUGIN_URL . 'css/admin-general.css', false, EPIZA_VERSION);
		if ('toplevel_page_epiza' == $hook) {
            wp_enqueue_style('epiza-admin', EPIZA_PLUGIN_URL . 'css/admin.css', false, EPIZA_VERSION);
            if (is_rtl()) {
                wp_enqueue_style('epiza-rtl-admin', EPIZA_PLUGIN_URL . 'css/admin-rtl.css', false, EPIZA_VERSION);
            }
			wp_enqueue_script('epiza-admin', EPIZA_PLUGIN_URL . 'js/admin.js', array( 'jquery' ), EPIZA_VERSION, true);
			wp_localize_script(
				'epiza-admin',
				'epizaParams',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce('epiza-nonce'),
					'error' => esc_html__('Error', 'epiza'),
					'wrong' => esc_html__('Something went wrong.', 'epiza'),
					'loading' => esc_html__('Loading...', 'epiza'),
					'loadmore' => esc_html__('Load More', 'epiza'),
					'import' => esc_html__('Import', 'epiza'),
                    'importing' => esc_html__('Importing...', 'epiza'),
					'remove' => esc_html__('Remove', 'epiza'),
					'movie' => esc_html__('Movie', 'epiza'),
					'tv' => esc_html__('TV', 'epiza'),
					'action' => esc_html__('Please select at least one item to perform this action on.', 'epiza'),
                    'done' => esc_html__('All work is complete!', 'epiza'),
                    'started' => esc_html__('The import process has started. Please do not close the page and wait until the process is completed', 'epiza'),
				]
			);
		}
	}

    /* Front-end Scripts */
    public function scripts(){
        if (is_singular()) {
            $post_type = get_post_type();
            if ('epizamovies' === $post_type || 'epizatvshows' === $post_type) {
                $sticky_poster = EpizaSettings::get_option('epiza_sticky_poster', '');
                $poster_width = (int) EpizaSettings::get_option('epiza_poster_width', 30);
                $content_width = 100 - $poster_width;
                $breakpoint = EpizaSettings::get_option('epiza_breakpoint', 782);

                wp_enqueue_style('lity', EPIZA_PLUGIN_URL . 'css/lity.min.css', false, '2.4.1');
                wp_enqueue_style('epiza', EPIZA_PLUGIN_URL . 'css/style.css', false, EPIZA_VERSION);
                wp_enqueue_script('lity', EPIZA_PLUGIN_URL . 'js/lity.min.js', array( 'jquery' ), '2.4.1', true);
                if ($sticky_poster == 'epiza-sticky') {
                    wp_enqueue_script('theia-sticky-sidebar', EPIZA_PLUGIN_URL . 'js/sticky-sidebar.min.js', array( 'jquery' ), '2.0', true);
                }

                $inline_style = '';

                if ($poster_width != 30) {
                    $inline_style .= '#epiza-post-content {width: ' . $content_width . '%;} #epiza-poster {width: ' . $poster_width . '%;}';
                }

                if ($poster_width != 30 || $breakpoint != 782) {
                    $inline_style .= '@media only screen and (max-width: ' . $breakpoint . 'px) { #epiza-post-wrapper { flex-direction: column; } #epiza-post-wrapper #epiza-post-content { width:100%; } #epiza-post-wrapper #epiza-poster, #epiza-post-wrapper.epiza-one-column #epiza-poster { width:100%; padding:0 0 2rem 0; } }';
                }

                wp_add_inline_style('epiza', $inline_style);
            }
        }
    }

	/**
	 * Admin Page Output
	 */
    public function admin_page_output() { 
		$getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
		if (empty($getApiKey)) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('TMDB API KEY is required. Please read the documentation for more information.', 'epiza') . '</p></div>';
			return;
		}
        $movie_base = get_option( 'epiza_movies_slug', 'movies');
        $tv_base = get_option( 'epiza_tv_slug', 'tv-shows');
		?>
		<div id="epiza">
            <div id="epiza-header">
                <h2><?php echo esc_html__('Epiza: Movie & TV Show Importer', 'epiza'); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url(get_site_url() . '/' . $movie_base . '/'); ?>" target="_blank"><?php echo esc_html__('Movies', 'epiza'); ?><span class="dashicons dashicons-external"></span></a></li>
                    <li><a href="<?php echo esc_url(get_site_url() . '/' . $tv_base . '/'); ?>" target="_blank"><?php echo esc_html__('TV Shows', 'epiza'); ?><span class="dashicons dashicons-external"></span></a></li>
                </ul>
            </div>
			<div class="epiza-tabs">
                <ul id="epiza-tabs-menu" class="epiza-tabs-menu">
                    <li data-target="#epiza-movies" class="active"><?php echo esc_html__('Movies', 'epiza'); ?></li>
					<li data-target="#epiza-tv-shows"><?php echo esc_html__('TV Shows', 'epiza'); ?></li>
					<li data-target="#epiza-bulk-import"><?php echo esc_html__('Bulk Import', 'epiza'); ?><span id="epiza-import-count">0</span></li>
                </ul>
				<div id="epiza-movies" class="epiza-tab active">
					<div class="epiza-search-box tmdb-search-box">
						<input id="epiza-movies-search-input" type="search" class="epiza-form-field" placeholder="<?php echo esc_attr__('Search by title...', 'epiza'); ?>" autocomplete="off" />
						<button id="epiza-movies-search" type="button" class="button button-primary"><span class="dashicons dashicons-search"></span></button>
					</div>
					<div id="epiza-movies-output">
						<?php Epiza::popularMovies(); ?>
					</div>
                    <a class="epiza-tmdb-credit" href="https://www.themoviedb.org/" target="_blank"><?php echo esc_html__('Data provided by TMDB', 'epiza'); ?></a>
				</div>
				<div id="epiza-tv-shows" class="epiza-tab">
					<div class="epiza-search-box tmdb-search-box">
						<input id="epiza-tv-search-input" type="search" class="epiza-form-field" placeholder="<?php echo esc_attr__('Search by title...', 'epiza'); ?>" autocomplete="off" />
						<button id="epiza-tv-search" type="button" class="button button-primary"><span class="dashicons dashicons-search"></span></button>
					</div>
					<div id="epiza-tv-output">
						<?php Epiza::popularTV(); ?>
					</div>
                    <a class="epiza-tmdb-credit" href="https://www.themoviedb.org/" target="_blank"><?php echo esc_html__('Data provided by TMDB', 'epiza'); ?></a>
				</div>
				<div id="epiza-bulk-import" class="epiza-tab">
					<div id="epiza-bulk-import-notice" class="notice notice-warning"><p><?php echo esc_html__('No movies or TV Shows have been selected for import yet.', 'epiza'); ?></p></div>
					<div id="epiza-bulk-import-table-wrap">
						<div class="epiza-tablenav-top">
							<select id="epiza-action-top">
								<option value=""><?php echo esc_html__('Bulk actions', 'epiza'); ?></option>
								<option value="import" class="hide-if-no-js"><?php echo esc_html__('Import', 'epiza'); ?></option>
								<option value="remove"><?php echo esc_html__('Remove', 'epiza'); ?></option>
							</select>
							<input type="submit" id="epiza-action-top-submit" class="button action" value="<?php echo esc_attr__('Apply', 'epiza'); ?>">
						</div>
						<table id="epiza-bulk-import-table" class="epiza-table widefat striped">
							<thead>
								<tr>
									<td class="manage-column check-column">
										<input id="cb-select-all-1" type="checkbox">
									</td>
									<th scope="col" class="manage-column">Title</th>
									<th scope="col" class="manage-column epiza-table-actions"></th>
								</tr>
							</thead>
							<tbody id="epiza-bulk-import-tbody">
							</tbody>
							<tfoot>
								<tr>
									<td class="manage-column check-column">
										<input id="cb-select-all-2" type="checkbox">
									</td>
									<th scope="col" class="manage-column">Title</th>
									<th scope="col" class="manage-column epiza-table-actions"></th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			</div>
		</div>
	<?php }
	/**
	 * Get Popular Movies
	 */
	static function popularMovies() {
        $getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
        $apiKey = trim($getApiKey);
        $error = '';
        $caching =  EpizaSettings::get_option('epiza_caching', 24);
        $lang =  EpizaSettings::get_option('epiza_lang', 'en');
        $curlURL = "https://api.themoviedb.org/3/movie/popular?language=" . $lang . "&page=1";

        $transient_value = get_transient($curlURL); 

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                $error = esc_html__( 'Error connecting to API: ', 'epiza' ) . curl_error($ch);
            }
        
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                $error = "HTTP {$responseCode}";
            }
            if ($responseCode === 429) {
                $error = esc_html__( 'Too many requests!', 'epiza' );
            } 
            if (empty($error)) {
                set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
            }
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            $error = esc_html__( 'Error parsing response.', 'epiza' );
        }

        if (!empty($error)) {
            echo '<div class="notice notice-danger">' . $error . '</div>';
        } else {
            $results = $data->results;

            echo '<div class="epiza-grid">';

            foreach ( $results as $result ) {
                $id = $result->id;
                $title = $result->title;
				$release = substr($result->release_date, 0, 4);
                $poster_path = $result->poster_path;
                $poster_url = EPIZA_PLUGIN_URL . 'images/poster-placeholder.png';
                if (!empty($poster_path)) {
                    $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                }

                echo '<div class="epiza-masonry-item m-' . esc_attr($id) . '">';
                echo '<div class="epiza-masonry-item-inner">';
				echo '<div class="epiza-masonry-img-wrap">';
				echo '<div class="epiza-masonry-btn-wrap"><div class="epiza-masonry-btn epiza-add-btn" data-id="m-' . esc_attr($id) . '" title="' . esc_attr__('Add to queue', 'epiza') . '"><span class="dashicons dashicons-plus-alt"></span></div><div class="epiza-masonry-btn epiza-remove-btn" data-id="m-' . esc_attr($id) . '" title="' . esc_attr__('Remove from the queue', 'epiza') . '"><span class="dashicons dashicons-dismiss"></span></div></div>';
                echo '<img src="' . esc_url($poster_url) . '" />';
				if (!empty($title)) {
					echo '<div class="epiza-masonry-item-title">' . esc_html($title) . ' (' . esc_html($release) . ')</div>';
				}
				echo '</div>';
                echo '</div></div>';
            }

            echo '</div>';

            echo '<button id="epiza-movies-loadmore" type="button" class="epiza-loadmore button button-primary" autocomplete="off" data-page="1">' . esc_html__('Load More', 'epiza') . '</button>';

        }
    }

	/**
	 * Search Movies
	 */
	public function searchMovies() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'epiza-nonce' ) ) {
            wp_die(esc_html__('Security Error!', 'epiza'));
        }
        $getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
        $apiKey = trim($getApiKey);
        $error = '';
        $curlURL = '';
        $caching =  EpizaSettings::get_option('epiza_caching', 24);
        $lang =  EpizaSettings::get_option('epiza_lang', 'en');
        $include_adult =  EpizaSettings::get_option('epiza_include_adult', 'false');
        $query = sanitize_text_field($_POST['keyword']);
        $page = sanitize_text_field($_POST['page']);

        if (empty($query)) {
            $curlURL = "https://api.themoviedb.org/3/movie/popular?language=" . $lang . "&page=" . $page;
        } else {
            $curlURL = "https://api.themoviedb.org/3/search/movie?";
            $curlURL .= 'language=' . $lang . '&';
            if (!empty($query)) {
                $query = str_replace(' ', '%20', $query);
                $curlURL .= 'query=' . $query . '&';
            }
            if (!empty($include_adult)) {
                $curlURL .= 'include_adult=' . $include_adult . '&';
            }
            $curlURL .= 'page=' . $page;
        }

        $transient_value = get_transient($curlURL);

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                $error = esc_html__( 'Error connecting to API: ', 'epiza' ) . curl_error($ch);
            }
        
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                $error = "HTTP {$responseCode}";
            }
            if ($responseCode === 429) {
                $error = esc_html__( 'Too many requests!', 'epiza' );
            }
            if (empty($error)) {
                set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
            }
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            $error = esc_html__( 'Error parsing response.', 'epiza' );
        }

        if (!empty($error)) {
            echo '<div class="notice notice-danger">' . $error . '</div>';
        } else {
            $results = $data->results;
            $total_pages = $data->total_pages;

            if ($results == array()) {
                echo '<div class="notice notice-warning">' . esc_html__('Nothing Found.', 'epiza') . '</div>';
            } else {

                echo '<div class="epiza-grid">';

                foreach ( $results as $result ) {
                    $id = $result->id;
                    $title = $result->title;
					$release = substr($result->release_date, 0, 4);
                    $poster_path = $result->poster_path;
                    $poster_url = EPIZA_PLUGIN_URL . 'images/poster-placeholder.png';
                    if (!empty($poster_path)) {
                        $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                    }

                    echo '<div class="epiza-masonry-item m-' . esc_attr($id) . '">';
					echo '<div class="epiza-masonry-item-inner">';
					echo '<div class="epiza-masonry-img-wrap">';
					echo '<div class="epiza-masonry-btn-wrap"><div class="epiza-masonry-btn epiza-add-btn" data-id="m-' . esc_attr($id) . '" title="' . esc_attr__('Add to queue', 'epiza') . '"><span class="dashicons dashicons-plus-alt"></span></div><div class="epiza-masonry-btn epiza-remove-btn" data-id="m-' . esc_attr($id) . '" title="' . esc_attr__('Remove from the queue', 'epiza') . '"><span class="dashicons dashicons-dismiss"></span></div></div>';
					echo '<img src="' . esc_url($poster_url) . '" />';
					if (!empty($title)) {
					echo '<div class="epiza-masonry-item-title">' . esc_html($title) . ' (' . esc_html($release) . ')</div>';
				}
					echo '</div>';
					echo '</div></div>';
                }

                echo '</div>';

                if ($total_pages > $page) {
                    echo '<button id="epiza-movies-loadmore" type="button" class="epiza-loadmore button button-primary" autocomplete="off" data-page="' . $page . '">' . esc_html__('Load More', 'epiza') . '</button>';
                }
            }

        }
        wp_die();
    }

	/**
	 * Get Popular TV Shows
	 */
	static function popularTV() {
        $getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
        $apiKey = trim($getApiKey);
        $error = '';
        $caching =  EpizaSettings::get_option('epiza_caching', 24);
        $lang =  EpizaSettings::get_option('epiza_lang', 'en');
        $curlURL = "https://api.themoviedb.org/3/tv/popular?language=" . $lang . "&page=1";

        $transient_value = get_transient($curlURL); 

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                $error = esc_html__( 'Error connecting to API: ', 'epiza' ) . curl_error($ch);
            }
        
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                $error = "HTTP {$responseCode}";
            }
            if ($responseCode === 429) {
                $error = esc_html__( 'Too many requests!', 'epiza' );
            } 
            if (empty($error)) {
                set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
            }
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            $error = esc_html__( 'Error parsing response.', 'epiza' );
        }

        if (!empty($error)) {
            echo '<div class="notice notice-danger">' . $error . '</div>';
        } else {
            $results = $data->results;

            echo '<div class="epiza-grid tmdb-grid">';

            foreach ( $results as $result ) {
                $id = $result->id;
                $title = $result->name;
				$release = substr($result->first_air_date, 0, 4);
                $poster_path = $result->poster_path;
                $poster_url = EPIZA_PLUGIN_URL . 'images/poster-placeholder.png';
                if (!empty($poster_path)) {
                    $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                }

                echo '<div class="epiza-masonry-item t-' . esc_attr($id) . '">';
                echo '<div class="epiza-masonry-item-inner">';
				echo '<div class="epiza-masonry-img-wrap">';
				echo '<div class="epiza-masonry-btn-wrap"><div class="epiza-masonry-btn epiza-add-btn" data-id="t-' . esc_attr($id) . '" title="' . esc_attr__('Add to queue', 'epiza') . '"><span class="dashicons dashicons-plus-alt"></span></div><div class="epiza-masonry-btn epiza-remove-btn" data-id="t-' . esc_attr($id) . '" title="' . esc_attr__('Remove from the queue', 'epiza') . '"><span class="dashicons dashicons-dismiss"></span></div></div>';
                echo '<img src="' . esc_url($poster_url) . '" />';
				if (!empty($title)) {
					echo '<div class="epiza-masonry-item-title">' . esc_html($title) . ' (' . esc_html($release) . ')</div>';
				}
				echo '</div>';
                echo '</div></div>';
            }

            echo '</div>';

            echo '<button id="epiza-tv-loadmore" type="button" class="epiza-loadmore button button-primary" autocomplete="off" data-page="1">' . esc_html__('Load More', 'epiza') . '</button>';

        }
    }

	/**
	 * Search TV Shows
	 */
	public function searchTV() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'epiza-nonce' ) ) {
            wp_die(esc_html__('Security Error!', 'epiza'));
        }
        $getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
        $apiKey = trim($getApiKey);
        $error = '';
        $curlURL = '';
        $caching =  EpizaSettings::get_option('epiza_caching', 24);
        $lang =  EpizaSettings::get_option('epiza_lang', 'en');
        $include_adult =  EpizaSettings::get_option('epiza_include_adult', 'false');
        $query = sanitize_text_field($_POST['keyword']);
        $page = sanitize_text_field($_POST['page']);

        if (empty($query)) {
            $curlURL = "https://api.themoviedb.org/3/tv/popular?language=" . $lang . "&page=" . $page;
        } else {
            $curlURL = "https://api.themoviedb.org/3/search/tv?";
            $curlURL .= 'language=' . $lang . '&';
            if (!empty($query)) {
                $query = str_replace(' ', '%20', $query);
                $curlURL .= 'query=' . $query . '&';
            }
            if (!empty($include_adult)) {
                $curlURL .= 'include_adult=' . $include_adult . '&';
            }
            $curlURL .= 'page=' . $page;
        }

        $transient_value = get_transient($curlURL);

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                $error = esc_html__( 'Error connecting to API: ', 'epiza' ) . curl_error($ch);
            }
        
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                $error = "HTTP {$responseCode}";
            }
            if ($responseCode === 429) {
                $error = esc_html__( 'Too many requests!', 'epiza' );
            }
            if (empty($error)) {
                set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
            }
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            $error = esc_html__( 'Error parsing response.', 'epiza' );
        }

        if (!empty($error)) {
            echo '<div class="notice notice-danger">' . $error . '</div>';
        } else {
            $results = $data->results;
            $total_pages = $data->total_pages;

            if ($results == array()) {
                echo '<div class="notice notice-warning">' . esc_html__('Nothing Found.', 'epiza') . '</div>';
            } else {

                echo '<div class="epiza-grid tmdb-grid">';

                foreach ( $results as $result ) {
                    $id = $result->id;
                    $title = $result->name;
					$release = substr($result->first_air_date, 0, 4);
                    $poster_path = $result->poster_path;
                    $poster_url = EPIZA_PLUGIN_URL . 'images/poster-placeholder.png';
                    if (!empty($poster_path)) {
                        $poster_url = "https://image.tmdb.org/t/p/w500" . $poster_path;
                    }

                    echo '<div class="epiza-masonry-item t-' . esc_attr($id) . '">';
					echo '<div class="epiza-masonry-item-inner">';
					echo '<div class="epiza-masonry-img-wrap">';
					echo '<div class="epiza-masonry-btn-wrap"><div class="epiza-masonry-btn epiza-add-btn" data-id="t-' . esc_attr($id) . '" title="' . esc_attr__('Add to queue', 'epiza') . '"><span class="dashicons dashicons-plus-alt"></span></div><div class="epiza-masonry-btn epiza-remove-btn" data-id="t-' . esc_attr($id) . '" title="' . esc_attr__('Remove from the queue', 'epiza') . '"><span class="dashicons dashicons-dismiss"></span></div></div>';
					echo '<img src="' . esc_url($poster_url) . '" />';
					if (!empty($title)) {
						echo '<div class="epiza-masonry-item-title">' . esc_html($title) . ' (' . esc_html($release) . ')</div>';
					}
					echo '</div>';
					echo '</div></div>';
                }

                echo '</div>';

                if ($total_pages > $page) {
                    echo '<button id="epiza-tv-loadmore" type="button" class="epiza-loadmore button button-primary" autocomplete="off" data-page="' . $page . '">' . esc_html__('Load More', 'epiza') . '</button>';
                }
            }

        }
        wp_die();
    }

    /**
	 * Get Actors
	 */
	public static function get_actors($apiKey, $type, $id) {
        $max =  EpizaSettings::get_option('epiza_actors', 5);
        $count = 0;
        $curlURL = "https://api.themoviedb.org/3/tv/" . $id . "/aggregate_credits";
        if ($type == 'movie') {
            $curlURL = "https://api.themoviedb.org/3/movie/" . $id . "/credits";
        }
        $transient_value = get_transient($curlURL);
        $caching =  EpizaSettings::get_option('epiza_caching', 24);

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                return false;
            }
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                return false;
            }
            if ($responseCode === 429) {
                return false;
            }
            set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if ($data == array()) {
            return false;
        }

        $cast = $data->cast;
        $actors = array();

        foreach ( $cast as $actor ) {
            if ($max > $count) {
                array_push($actors, $actor->name);
                $count++;
            } else {
                break;
            }
        }
        return $actors;    
    }

    /**
	 * Get Videos
	 */
	public static function get_videos($apiKey, $type, $id) {
        $max =  EpizaSettings::get_option('epiza_videos', 5);
        $count = 0;
        $curlURL = "https://api.themoviedb.org/3/" . $type . "/" . $id . "/videos";
        $transient_value = get_transient($curlURL);
        $caching =  EpizaSettings::get_option('epiza_caching', 24);

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                return false;
            }
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                return false;
            }
            if ($responseCode === 429) {
                return false;
            }
            set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if ($data == array()) {
            return false;
        }

        $results = $data->results;
        $videos = array();

        foreach ( $results as $result ) {
            if ($max > $count) {
                if ($result->site == 'YouTube') {
                    $array = array();
                    $array['name'] = $result->name;
                    $array['url'] = 'https://www.youtube.com/watch?v=' . $result->key;
                    array_push($videos, $array);
                    $count++;
                }
            } else {
                break;
            }
        }
        return $videos;    
    }

    /**
	 * Import Movies
	 */
	public function import() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'epiza-nonce' ) ) {
            wp_die(esc_html__('Security Error!', 'epiza'));
        }

        // WordPress core functions for media handling
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        $getApiKey =  EpizaSettings::get_option('epiza_api_key', '');
        $apiKey = trim($getApiKey);
        $error = '';
        $curlURL = '';
        $caching =  EpizaSettings::get_option('epiza_caching', 24);
        $post_status =  EpizaSettings::get_option('epiza_post_status', 'publish');
        $lang =  EpizaSettings::get_option('epiza_lang', 'en');
        $id = sanitize_text_field($_POST['id']);
        $realID = substr($id, 2);
        $selected_featured =  EpizaSettings::get_option('epiza_featured', 'backdrop');
        $featured_size =  EpizaSettings::get_option('epiza_featured_size', 'original');
        $import_poster =  EpizaSettings::get_option('epiza_import_poster', 'yes');
        $poster_size =  EpizaSettings::get_option('epiza_poster_size', 'w500');
        $tagline_tag =  EpizaSettings::get_option('epiza_tagline_tag', 'p');

        if (str_starts_with($id, 't-')) {
            $curlURL = "https://api.themoviedb.org/3/tv/" . $realID . "?language=" . $lang;
        } else {
            $curlURL = "https://api.themoviedb.org/3/movie/" . $realID . "?language=" . $lang;
        }

        $transient_value = get_transient($curlURL);

        if (false !== $transient_value){
            $response =	get_transient($curlURL);
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $curlURL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$apiKey}"
                )
            ));
        
            $response = curl_exec($ch);
            if (curl_errno($ch) > 0) { 
                $error = esc_html__( 'Error connecting to API: ', 'epiza' ) . curl_error($ch);
            }
        
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($responseCode !== 200) {
                $error = "HTTP {$responseCode}";
            }
            if ($responseCode === 429) {
                $error = esc_html__( 'Too many requests!', 'epiza' );
            }
            if (empty($error)) {
                set_transient( $curlURL, $response, intval($caching) * HOUR_IN_SECONDS );
            }
        }

        $data = json_decode($response);
        if ($data === false && json_last_error() !== JSON_ERROR_NONE) {
            $error = esc_html__( 'Error parsing response.', 'epiza' );
        }

        if ($data == array()) {
            $error = esc_html__( 'Nothing Found.', 'epiza' );
        }

        if (!empty($error)) {
            wp_send_json_error(array('message' => $error));
        } else {
            if (str_starts_with($id, 't-')) {
                $fields = EpizaSettings::get_option('epiza_tv_show_data', array('tagline', 'first_air_date', 'runtime', 'budget', 'revenue', 'production_companies', 'production_countries'));
                $name = $data->name;
                $overview = $data->overview;
                $tagline = $data->tagline;
                $vote_average = $data->vote_average;
                $backdrop_path = $data->backdrop_path;
                $poster_path = $data->poster_path;
                $genres = $data->genres;
                $first_air_date = $data->first_air_date;
                $number_of_episodes = $data->number_of_episodes;
                $number_of_seasons = $data->number_of_seasons;
                $created_by = $data->created_by;
                $homepage = $data->homepage;
                $production_companies = $data->production_companies;
                $production_countries = $data->production_countries;
                $networks = $data->networks;
                $original_name = $data->original_name;
                $original_language = $data->original_language;

                $content = '';

                if (in_array("tagline", $fields) && !empty($tagline)) {
                    if ($tagline_tag == 'p') {
                        $content .= '<p><strong>' .  esc_html($tagline). '</strong></p>';
                    } else {
                        $content .= '<' . $tagline_tag . '>' .  esc_html($tagline). '</' . $tagline_tag . '>';
                    }
                }

                if (!empty($overview)) {
                    $content .= '<p>' .  esc_html($overview). '</p>';
                }

                $content .= '<ul class="epiza-details-list">';
                if (in_array("first_air_date", $fields) && !empty($first_air_date)) {
                    $content .= '<li><strong>' . esc_html__( 'First air date:', 'epiza' ) . '</strong> ' . date(get_option('date_format'), strtotime($first_air_date)) . '</li>';
                }
                if (in_array("number_of_seasons", $fields) && !empty($number_of_seasons)) {
                    $content .= '<li><strong>' . esc_html__( 'Number of seasons:', 'epiza' ) . '</strong> ' . esc_html($number_of_seasons) . '</li>';
                }
                if (in_array("number_of_episodes", $fields) && !empty($number_of_episodes)) {
                    $content .= '<li><strong>' . esc_html__( 'Number of episodes:', 'epiza' ) . '</strong> ' . esc_html($number_of_episodes) . '</li>';
                }
                if (in_array("created_by", $fields) && !empty($created_by) && is_array($created_by)) {
                    $created_by_names = array();
                    foreach ( $created_by as $person ) {
                        array_push($created_by_names, $person->name);
                    }
                    $created_by_names = implode(', ', $created_by_names);
                    $content .= '<li><strong>' . esc_html__( 'Created by:', 'epiza' ) . '</strong> ' . esc_html($created_by_names) . '</li>';
                }
                if (in_array("homepage", $fields) && !empty($homepage)) {
                    $content .= '<li><strong>' . esc_html__( 'Visit homepage:', 'epiza' ) . '</strong> <a href="' . esc_url($homepage) . '" target="_blank" rel="nofollow">' . esc_html($homepage) . '</a></li>';
                }
                if (in_array("networks", $fields) && !empty($networks) && is_array($networks)) {
                    $networks_names = array();
                    foreach ( $networks as $company ) {
                        array_push($networks_names, $company->name);
                    }
                    $networks_names = implode(', ', $networks_names);
                    $content .= '<li><strong>' . esc_html__( 'Networks:', 'epiza' ) . '</strong> ' . esc_html($networks_names) . '</li>';
                }
                if (in_array("production_companies", $fields) && !empty($production_companies) && is_array($production_companies)) {
                    $production_companies_names = array();
                    foreach ( $production_companies as $company ) {
                        array_push($production_companies_names, $company->name);
                    }
                    $production_companies_names = implode(', ', $production_companies_names);
                    $content .= '<li><strong>' . esc_html__( 'Production companies:', 'epiza' ) . '</strong> ' . esc_html($production_companies_names) . '</li>';
                }
                if (in_array("production_countries", $fields) && !empty($production_countries) && is_array($production_countries)) {
                    $production_countries_names = array();
                    foreach ( $production_countries as $country ) {
                        array_push($production_countries_names, $country->name);
                    }
                    $production_countries_names = implode(', ', $production_countries_names);
                    $content .= '<li><strong>' . esc_html__( 'Production countries:', 'epiza' ) . '</strong> ' . esc_html($production_countries_names) . '</li>';
                }
                if (in_array("original_name", $fields) && !empty($original_name)) {
                    $content .= '<li><strong>' . esc_html__( 'Original Name:', 'epiza' ) . '</strong> ' . esc_html($original_name) . '</li>';
                }
                if (in_array("original_language", $fields) && !empty($original_language)) {
                    $iso = new Matriphe\ISO639\ISO639;
                    $lang_output = $iso->nativeByCode1($original_language) . ' (' . $iso->languageByCode1($original_language) . ')';
                    if ($original_language == 'en') {
                        $lang_output = $iso->languageByCode1($original_language);
                    }
                    $content .= '<li><strong>' . esc_html__( 'Original Language:', 'epiza' ) . '</strong> ' . esc_html($lang_output) . '</li>';
                }
                $content .= '</ul>';

                $post_id = wp_insert_post(array (
                    'post_title' => sanitize_text_field($name),
                    'post_type' => 'epizatvshows',
                    'post_status' => $post_status,
                    'post_excerpt' => sanitize_text_field($overview),
                    'post_content' => wp_kses_post($content)
                ));

                if ( is_wp_error( $post_id ) ) {
                    wp_send_json_error(array('message' => esc_html__( 'Post could not be imported.', 'epiza' )));
                    wp_die();
                }

                update_post_meta($post_id, 'epiza_rating', sanitize_text_field($vote_average));

                if (!empty($genres) && is_array($genres)) {
                    $genres_names = array();
                    foreach ( $genres as $genre ) {
                        array_push($genres_names, $genre->name);
                    }
                    wp_set_post_terms( $post_id, $genres_names, 'epizatvgenres');
                }

                $actors = self::get_actors($apiKey, 'tv', $realID);

                if ($actors) {
                    wp_set_post_terms( $post_id, $actors, 'epizacast');
                }

                $videos = self::get_videos($apiKey, 'tv', $realID);
                update_post_meta($post_id, 'epiza_video_group', $videos);

                if (!empty($backdrop_path) && $selected_featured == 'backdrop') {
                    $featured_url = "https://image.tmdb.org/t/p/" . $featured_size . $backdrop_path;
                    $featured_file = download_url( $featured_url );

                    if ( is_wp_error( $featured_file ) ) {
                        @unlink( $featured_file );
                    } else {
                        $featured_file_name = basename( parse_url( $featured_url, PHP_URL_PATH ) );
                        $featured_file_data = array(
                            'name'     => $featured_file_name,
                            'tmp_name' => $featured_file,
                        );
                        $featured_attachment_id = media_handle_sideload( $featured_file_data, $post_id );
                        if ( is_wp_error( $featured_attachment_id ) ) {
                            @unlink( $featured_file );
                        } else if ( $featured_attachment_id > 0 ) {
                            set_post_thumbnail( $post_id, $featured_attachment_id );
                        }
                    }
                }

                if (!empty($poster_path) && $import_poster == 'yes') {
                    $poster_url = "https://image.tmdb.org/t/p/" . $poster_size . $poster_path;
                    $poster_file = download_url( $poster_url );

                    if ( is_wp_error( $poster_file ) ) {
                        @unlink( $poster_file );
                    } else {
                        $poster_file_name = basename( parse_url( $poster_url, PHP_URL_PATH ) );
                        $poster_file_data = array(
                            'name'     => $poster_file_name,
                            'tmp_name' => $poster_file,
                        );
                        $poster_attachment_id = media_handle_sideload( $poster_file_data, $post_id );
                        if ( is_wp_error( $poster_attachment_id ) ) {
                            @unlink( $poster_file );
                        } else if ( $poster_attachment_id > 0 ) {
                            $poster_attachment_url = wp_get_attachment_url($poster_attachment_id);
                            update_post_meta($post_id, 'epiza_poster', esc_url($poster_attachment_url));
                            if ($selected_featured == 'poster') {
                                set_post_thumbnail( $post_id, $poster_attachment_id );
                            }
                        }
                    }
                }
            } else {
                $fields = EpizaSettings::get_option('epiza_movie_data', array('tagline', 'release_date', 'runtime', 'budget', 'revenue', 'production_companies', 'production_countries'));
                $name = $data->title;
                $overview = $data->overview;
                $tagline = $data->tagline;
                $vote_average = $data->vote_average;
                $backdrop_path = $data->backdrop_path;
                $poster_path = $data->poster_path;
                $genres = $data->genres;
                $release_date = $data->release_date;
                $runtime = $data->runtime;
                $budget = $data->budget;
                if (!empty($budget)) {
                    $budget = number_format($budget, 0, '', '.');
                }
                $revenue = $data->revenue;
                if (!empty($revenue)) {
                    $revenue = number_format($revenue, 0, '', '.');
                }
                $homepage = $data->homepage;
                $production_companies = $data->production_companies;
                $production_countries = $data->production_countries;
                $original_title = $data->original_title;
                $original_language = $data->original_language;

                $content = '';

                if (in_array("tagline", $fields) && !empty($tagline)) {
                    if ($tagline_tag == 'p') {
                        $content .= '<p><strong>' .  esc_html($tagline). '</strong></p>';
                    } else {
                        $content .= '<' . $tagline_tag . '>' .  esc_html($tagline). '</' . $tagline_tag . '>';
                    }
                }

                if (!empty($overview)) {
                    $content .= '<p>' .  esc_html($overview). '</p>';
                }

                $content .= '<ul class="epiza-details-list">';
                if (in_array("release_date", $fields) && !empty($release_date)) {
                    $content .= '<li><strong>' . esc_html__( 'Release Date:', 'epiza' ) . '</strong> ' . date(get_option('date_format'), strtotime($release_date)) . '</li>';
                }

                if (in_array("runtime", $fields) && !empty($runtime)) {
                    $content .= '<li><strong>' . esc_html__( 'Runtime:', 'epiza' ) . '</strong> ' . esc_html($runtime) . ' ' . esc_html__( 'minutes', 'epiza' ) . '</li>';
                }

                if (in_array("budget", $fields) && !empty($budget)) {
                    $content .= '<li><strong>' . esc_html__( 'Budget:', 'epiza' ) . '</strong> $' . esc_html($budget) . '</li>';
                }

                if (in_array("revenue", $fields) && !empty($revenue)) {
                    $content .= '<li><strong>' . esc_html__( 'Revenue:', 'epiza' ) . '</strong> $' . esc_html($revenue) . '</li>';
                }

                if (in_array("homepage", $fields) && !empty($homepage)) {
                    $content .= '<li><strong>' . esc_html__( 'Visit homepage:', 'epiza' ) . '</strong> <a href="' . esc_url($homepage) . '" target="_blank" rel="nofollow">' . esc_html($homepage) . '</a></li>';
                }

                if (in_array("production_companies", $fields) && !empty($production_companies) && is_array($production_companies)) {
                    $production_companies_names = array();
                    foreach ( $production_companies as $company ) {
                        array_push($production_companies_names, $company->name);
                    }
                    $production_companies_names = implode(', ', $production_companies_names);
                    $content .= '<li><strong>' . esc_html__( 'Production companies:', 'epiza' ) . '</strong> ' . esc_html($production_companies_names) . '</li>';
                }
                if (in_array("production_countries", $fields) && !empty($production_countries) && is_array($production_countries)) {
                    $production_countries_names = array();
                    foreach ( $production_countries as $country ) {
                        array_push($production_countries_names, $country->name);
                    }
                    $production_countries_names = implode(', ', $production_countries_names);
                    $content .= '<li><strong>' . esc_html__( 'Production countries:', 'epiza' ) . '</strong> ' . esc_html($production_countries_names) . '</li>';
                }
                if (in_array("original_title", $fields) && !empty($original_title)) {
                    $content .= '<li><strong>' . esc_html__( 'Original Title:', 'epiza' ) . '</strong> ' . esc_html($original_title) . '</li>';
                }
                if (in_array("original_language", $fields) && !empty($original_language)) {
                    $iso = new Matriphe\ISO639\ISO639;
                    $lang_output = $iso->nativeByCode1($original_language) . ' (' . $iso->languageByCode1($original_language) . ')';
                    if ($original_language == 'en') {
                        $lang_output = $iso->languageByCode1($original_language);
                    }
                    $content .= '<li><strong>' . esc_html__( 'Original Language:', 'epiza' ) . '</strong> ' . esc_html($lang_output) . '</li>';
                }
                $content .= '</ul>';

                $post_id = wp_insert_post(array (
                    'post_title' => sanitize_text_field($name),
                    'post_type' => 'epizamovies',
                    'post_status' => $post_status,
                    'post_excerpt' => sanitize_text_field($overview),
                    'post_content' => wp_kses_post($content)
                ));

                if ( is_wp_error( $post_id ) ) {
                    wp_send_json_error(array('message' => esc_html__( 'Post could not be imported.', 'epiza' )));
                    wp_die();
                }

                update_post_meta($post_id, 'epiza_rating', sanitize_text_field($vote_average));

                if (!empty($genres) && is_array($genres)) {
                    $genres_names = array();
                    foreach ( $genres as $genre ) {
                        array_push($genres_names, $genre->name);
                    }
                    wp_set_post_terms( $post_id, $genres_names, 'epizamoviegenres');
                }

                $actors = self::get_actors($apiKey, 'movie', $realID);

                if ($actors) {
                    wp_set_post_terms( $post_id, $actors, 'epizacast');
                }

                $videos = self::get_videos($apiKey, 'movie', $realID);
                update_post_meta($post_id, 'epiza_video_group', $videos);

                if (!empty($backdrop_path) && $selected_featured == 'backdrop') {
                    $featured_url = "https://image.tmdb.org/t/p/" . $featured_size . $backdrop_path;
                    $featured_file = download_url( $featured_url );

                    if ( is_wp_error( $featured_file ) ) {
                        @unlink( $featured_file );
                    } else {
                        $featured_file_name = basename( parse_url( $featured_url, PHP_URL_PATH ) );
                        $featured_file_data = array(
                            'name'     => $featured_file_name,
                            'tmp_name' => $featured_file,
                        );
                        $featured_attachment_id = media_handle_sideload( $featured_file_data, $post_id );
                        if ( is_wp_error( $featured_attachment_id ) ) {
                            @unlink( $featured_file );
                        } else if ( $featured_attachment_id > 0 ) {
                            set_post_thumbnail( $post_id, $featured_attachment_id );
                        }
                    }
                }

                if (!empty($poster_path) && $import_poster == 'yes') {
                    $poster_url = "https://image.tmdb.org/t/p/" . $poster_size . $poster_path;
                    $poster_file = download_url( $poster_url );

                    if ( is_wp_error( $poster_file ) ) {
                        @unlink( $poster_file );
                    } else {
                        $poster_file_name = basename( parse_url( $poster_url, PHP_URL_PATH ) );
                        $poster_file_data = array(
                            'name'     => $poster_file_name,
                            'tmp_name' => $poster_file,
                        );
                        $poster_attachment_id = media_handle_sideload( $poster_file_data, $post_id );
                        if ( is_wp_error( $poster_attachment_id ) ) {
                            @unlink( $poster_file );
                        } else if ( $poster_attachment_id > 0 ) {
                            $poster_attachment_url = wp_get_attachment_url($poster_attachment_id);
                            update_post_meta($post_id, 'epiza_poster', esc_url($poster_attachment_url));
                            if ($selected_featured == 'poster') {
                                set_post_thumbnail( $post_id, $poster_attachment_id );
                            }
                        }
                    }
                }

            }
            wp_send_json_success(array(
                'message' => esc_html__( 'Success!', 'epiza' ),
                'post_id' => $post_id
            ));
        }
        wp_die();
    }

    /**
	 * Post Content
	 */
	public function content($content) {
        if ( is_singular() && in_the_loop() && is_main_query() ) {
            $post_type = get_post_type();
            if ('epizamovies' === $post_type || 'epizatvshows' === $post_type) {
                $post_id = get_the_ID();
                $user_score = EpizaSettings::get_option('epiza_user_score', 'enable');
                $layout = EpizaSettings::get_option('epiza_post_layout', 'epiza-two-column');
                $sticky_poster = EpizaSettings::get_option('epiza_sticky_poster', '');
                $show_poster = EpizaSettings::get_option('epiza_show_poster', 'yes');
                $poster = get_post_meta( $post_id, 'epiza_poster', true );
                $rating = get_post_meta( $post_id, 'epiza_rating', true );
                $videos = get_post_meta( $post_id, 'epiza_video_group', true );
                $video_tag = EpizaSettings::get_option('epiza_video_title_tag', 'h4');
                $genre_link = EpizaSettings::get_option('epiza_genre_link', 'yes');
                $actor_link = EpizaSettings::get_option('epiza_actor_link', 'yes');
                $tmdb_link = EpizaSettings::get_option('epiza_tmdb_credits', 'yes');
                $modified_content = '<div id="epiza-post-wrapper" class="' . esc_attr($layout) .'">';
                if (!empty($poster) && $show_poster == 'yes') {
                    $modified_content .= '<div id="epiza-poster" class="' . esc_attr($sticky_poster) .'">';
                    $modified_content .= '<a href="' . esc_url($poster) . '" data-lity><img src="' . esc_url($poster) . '" /></a>';
                    $modified_content .= '</div>';
                }
                $modified_content .= '<div id="epiza-post-content">';
                if ($user_score == 'enable') {
                    $star_svg = EPIZA_PLUGIN_URL . 'images/star.svg';
                    $modified_content .= '<p class="epiza-user-score"><img src="' . $star_svg . '" /><strong>' . round($rating, 1) . ' / 10</strong></p>';
                }
                $modified_content .= $content;
                if ('epizamovies' === $post_type) {
                    if ($genre_link == 'yes') {
                        $genres = get_the_term_list($post_id, 'epizamoviegenres', '<strong>' . esc_html__( 'Genres:', 'epiza' ) . '</strong> ', ', ', '');
                    } else {
                        $genres = wp_get_post_terms($post_id, 'epizamoviegenres');
                    }
                } else if ('epizatvshows' === $post_type) {
                    if ($genre_link == 'yes') {
                        $genres = get_the_term_list($post_id, 'epizatvgenres', '<strong>' . esc_html__( 'Genres:', 'epiza' ) . '</strong> ', ', ', '');
                    } else {
                        $genres = wp_get_post_terms($post_id, 'epizatvgenres');
                    }
                }
                if (!empty($genres) && !is_wp_error($genres)) {
                    if ($genre_link == 'yes') {
                        $modified_content .= '<div class="epiza-genres"><p>' . $genres . '</p></div>';
                    } else {
                        $genre_names = array();
                        foreach ( $genres as $genre ) {
                            $genre_names[] = $genre->name;
                        }
                        $modified_content .= '<div class="epiza-genres"><p><strong>' . esc_html__( 'Genres:', 'epiza' ) . '</strong> ' . implode( ', ', $genre_names) . '</p></div>';
                    }
                }
                $actors = wp_get_post_terms($post_id, 'epizacast', array( 'orderby' => 'term_id' ));
                if (!empty($actors) && !is_wp_error($actors)) {
                    if ($actor_link == 'yes') {
                        $actor_term_links = array();
                        foreach ( $actors as $actor ) {
                            $actor_term_link = get_term_link( $actor );
                            if ( ! is_wp_error( $actor_term_link ) ) {
                                $actor_term_links[] = '<a href="' . esc_url( $actor_term_link ) . '">' . esc_html( $actor->name ) . '</a>';
                            }
                        }
                        $modified_content .= '<div class="epiza-actors"><p><strong>' . esc_html__( 'Actors:', 'epiza' ) . '</strong> ' . implode( ', ', $actor_term_links) . '</p></div>';
                    } else {
                        $actor_names = array();
                        foreach ( $actors as $actor ) {
                            $actor_names[] = $actor->name;
                        }
                        $modified_content .= '<div class="epiza-actors"><p><strong>' . esc_html__( 'Actors:', 'epiza' ) . '</strong> ' . implode( ', ', $actor_names) . '</p></div>';
                    }
                }

                if (!empty($videos) && is_array($videos)) {
                    $modified_content .= '<div class="epiza-videos">';
                    if ($video_tag == 'p') {
                        $modified_content .= '<p><strong>' .  esc_html__( 'Videos', 'epiza' ) . '</strong></p>';
                    } else {
                        $modified_content .= '<' . $video_tag . '>' .  esc_html__( 'Videos', 'epiza' ) . '</' . $video_tag . '>';
                    }
                    $modified_content .= '<ul class="epiza-details-list">';
                    foreach ( $videos as $key => $entry ) {
                        $video_name = $video_url = '';
                        if ( isset( $entry['name'] ) ) {
                            $video_name = esc_html( $entry['name'] );
                        }
                        if ( isset( $entry['url'] ) ) {
                            $video_url = esc_url( $entry['url'] );
                        }
                        $modified_content .= '<li><a href="' . $video_url . '" data-lity>' . $video_name . '</a></li>';
                    }
                    $modified_content .= '</ul></div>';
                }

                if ($tmdb_link == 'yes') {
                    $modified_content .= '<p><a class="epiza-tmdb-credit" href="https://www.themoviedb.org/" rel="nofollow" target="_blank">' . esc_html__('Data provided by TMDB', 'epiza') . '</a></p>';
                }

                $modified_content .= '</div></div>';
                return $modified_content;
            }
        }
        return $content;
    }
}

/**
 * Returns the main instance of the class
 */
function Epiza() {  
	return Epiza::instance();
}
// Global for backwards compatibility
$GLOBALS['Epiza'] = Epiza();