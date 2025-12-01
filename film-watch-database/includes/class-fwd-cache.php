<?php
/**
 * Cache Abstraction Layer
 * Provides a unified interface for caching with WordPress transients
 *
 * CACHING DISABLED - All cache operations are pass-through
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWD_Cache {

    /**
     * Cache key prefix
     */
    private $prefix = 'fwd_';

    /**
     * Default TTL (15 minutes)
     */
    private $default_ttl = 900;

    /**
     * Remember a value in cache, or execute callback if not cached
     * CACHING DISABLED - Always executes callback
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @param callable $callback Function to execute if cache miss
     * @param int $ttl Time to live in seconds (default: 900)
     * @return mixed Cached or computed value
     */
    public function remember($key, $callback, $ttl = null) {
        // Cache disabled - always execute callback
        return $callback();
    }

    /**
     * Get a value from cache
     * CACHING DISABLED - Always returns false
     *
     * @param string $key Cache key
     * @return mixed|false Cached value or false if not found
     */
    public function get($key) {
        // Cache disabled
        return false;
    }

    /**
     * Set a value in cache
     * CACHING DISABLED - Does nothing
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool True on success
     */
    public function set($key, $value, $ttl = null) {
        // Cache disabled
        return true;
    }

    /**
     * Forget (delete) a specific cache key
     *
     * @param string $key Cache key
     * @return bool True on success
     */
    public function forget($key) {
        return delete_transient($this->prefix . $key);
    }

    /**
     * Flush all caches matching a pattern
     *
     * @param string $pattern Pattern to match (e.g., 'actor_*', 'brand_*')
     * @return int Number of cache entries deleted
     */
    public function flush($pattern = '*') {
        global $wpdb;

        $pattern = $this->prefix . $pattern;

        // Delete matching transients
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
            '_transient_' . $wpdb->esc_like($pattern),
            '_transient_timeout_' . $wpdb->esc_like($pattern)
        ));

        return $deleted;
    }

    /**
     * Flush all plugin caches
     *
     * @return int Number of cache entries deleted
     */
    public function flush_all() {
        return $this->flush('*');
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function stats() {
        global $wpdb;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            '_transient_' . $wpdb->esc_like($this->prefix) . '%'
        ));

        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            '_transient_' . $wpdb->esc_like($this->prefix) . '%'
        ));

        return array(
            'total_entries' => $total,
            'total_size_bytes' => $size,
            'total_size_kb' => round($size / 1024, 2)
        );
    }
}

/**
 * Get global cache instance
 */
function fwd_cache() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FWD_Cache();
    }
    return $instance;
}
