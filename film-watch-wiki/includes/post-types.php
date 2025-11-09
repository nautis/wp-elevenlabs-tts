<?php
/**
 * Register Custom Post Types
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FWW_Post_Types {

    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
    }

    /**
     * Register all custom post types
     */
    public static function register_post_types() {
        self::register_movie_post_type();
        self::register_actor_post_type();
        self::register_watch_post_type();
        self::register_brand_post_type();
    }

    /**
     * Register Movie Post Type
     */
    private static function register_movie_post_type() {
        $labels = array(
            'name'                  => _x('Movies', 'Post type general name', 'film-watch-wiki'),
            'singular_name'         => _x('Movie', 'Post type singular name', 'film-watch-wiki'),
            'menu_name'             => _x('Movies', 'Admin Menu text', 'film-watch-wiki'),
            'name_admin_bar'        => _x('Movie', 'Add New on Toolbar', 'film-watch-wiki'),
            'add_new'               => __('Add New', 'film-watch-wiki'),
            'add_new_item'          => __('Add New Movie', 'film-watch-wiki'),
            'new_item'              => __('New Movie', 'film-watch-wiki'),
            'edit_item'             => __('Edit Movie', 'film-watch-wiki'),
            'view_item'             => __('View Movie', 'film-watch-wiki'),
            'all_items'             => __('All Movies', 'film-watch-wiki'),
            'search_items'          => __('Search Movies', 'film-watch-wiki'),
            'parent_item_colon'     => __('Parent Movies:', 'film-watch-wiki'),
            'not_found'             => __('No movies found.', 'film-watch-wiki'),
            'not_found_in_trash'    => __('No movies found in Trash.', 'film-watch-wiki'),
            'featured_image'        => _x('Movie Poster', 'Overrides the "Featured Image" phrase', 'film-watch-wiki'),
            'set_featured_image'    => _x('Set poster', 'Overrides the "Set featured image" phrase', 'film-watch-wiki'),
            'remove_featured_image' => _x('Remove poster', 'Overrides the "Remove featured image" phrase', 'film-watch-wiki'),
            'use_featured_image'    => _x('Use as poster', 'Overrides the "Use as featured image" phrase', 'film-watch-wiki'),
            'archives'              => _x('Movie archives', 'The post type archive label', 'film-watch-wiki'),
            'insert_into_item'      => _x('Insert into movie', 'Overrides the "Insert into post"/"Insert into page" phrase', 'film-watch-wiki'),
            'uploaded_to_this_item' => _x('Uploaded to this movie', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'film-watch-wiki'),
            'filter_items_list'     => _x('Filter movies list', 'Screen reader text for the filter links', 'film-watch-wiki'),
            'items_list_navigation' => _x('Movies list navigation', 'Screen reader text for the pagination', 'film-watch-wiki'),
            'items_list'            => _x('Movies list', 'Screen reader text for the items list', 'film-watch-wiki'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'movie'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-video-alt3',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'       => true,
        );

        register_post_type('fww_movie', $args);
    }

    /**
     * Register Actor Post Type
     */
    private static function register_actor_post_type() {
        $labels = array(
            'name'                  => _x('Actors', 'Post type general name', 'film-watch-wiki'),
            'singular_name'         => _x('Actor', 'Post type singular name', 'film-watch-wiki'),
            'menu_name'             => _x('Actors', 'Admin Menu text', 'film-watch-wiki'),
            'name_admin_bar'        => _x('Actor', 'Add New on Toolbar', 'film-watch-wiki'),
            'add_new'               => __('Add New', 'film-watch-wiki'),
            'add_new_item'          => __('Add New Actor', 'film-watch-wiki'),
            'new_item'              => __('New Actor', 'film-watch-wiki'),
            'edit_item'             => __('Edit Actor', 'film-watch-wiki'),
            'view_item'             => __('View Actor', 'film-watch-wiki'),
            'all_items'             => __('All Actors', 'film-watch-wiki'),
            'search_items'          => __('Search Actors', 'film-watch-wiki'),
            'not_found'             => __('No actors found.', 'film-watch-wiki'),
            'not_found_in_trash'    => __('No actors found in Trash.', 'film-watch-wiki'),
            'featured_image'        => _x('Actor Photo', 'Overrides the "Featured Image" phrase', 'film-watch-wiki'),
            'set_featured_image'    => _x('Set photo', 'Overrides the "Set featured image" phrase', 'film-watch-wiki'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'actor'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('fww_actor', $args);
    }

    /**
     * Register Watch Post Type
     */
    private static function register_watch_post_type() {
        $labels = array(
            'name'                  => _x('Watches', 'Post type general name', 'film-watch-wiki'),
            'singular_name'         => _x('Watch', 'Post type singular name', 'film-watch-wiki'),
            'menu_name'             => _x('Watches', 'Admin Menu text', 'film-watch-wiki'),
            'name_admin_bar'        => _x('Watch', 'Add New on Toolbar', 'film-watch-wiki'),
            'add_new'               => __('Add New', 'film-watch-wiki'),
            'add_new_item'          => __('Add New Watch', 'film-watch-wiki'),
            'new_item'              => __('New Watch', 'film-watch-wiki'),
            'edit_item'             => __('Edit Watch', 'film-watch-wiki'),
            'view_item'             => __('View Watch', 'film-watch-wiki'),
            'all_items'             => __('All Watches', 'film-watch-wiki'),
            'search_items'          => __('Search Watches', 'film-watch-wiki'),
            'not_found'             => __('No watches found.', 'film-watch-wiki'),
            'not_found_in_trash'    => __('No watches found in Trash.', 'film-watch-wiki'),
            'featured_image'        => _x('Watch Photo', 'Overrides the "Featured Image" phrase', 'film-watch-wiki'),
            'set_featured_image'    => _x('Set photo', 'Overrides the "Set featured image" phrase', 'film-watch-wiki'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'watch'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 7,
            'menu_icon'          => 'dashicons-clock',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('fww_watch', $args);
    }

    /**
     * Register Brand Post Type
     */
    private static function register_brand_post_type() {
        $labels = array(
            'name'                  => _x('Brands', 'Post type general name', 'film-watch-wiki'),
            'singular_name'         => _x('Brand', 'Post type singular name', 'film-watch-wiki'),
            'menu_name'             => _x('Brands', 'Admin Menu text', 'film-watch-wiki'),
            'name_admin_bar'        => _x('Brand', 'Add New on Toolbar', 'film-watch-wiki'),
            'add_new'               => __('Add New', 'film-watch-wiki'),
            'add_new_item'          => __('Add New Brand', 'film-watch-wiki'),
            'new_item'              => __('New Brand', 'film-watch-wiki'),
            'edit_item'             => __('Edit Brand', 'film-watch-wiki'),
            'view_item'             => __('View Brand', 'film-watch-wiki'),
            'all_items'             => __('All Brands', 'film-watch-wiki'),
            'search_items'          => __('Search Brands', 'film-watch-wiki'),
            'not_found'             => __('No brands found.', 'film-watch-wiki'),
            'not_found_in_trash'    => __('No brands found in Trash.', 'film-watch-wiki'),
            'featured_image'        => _x('Brand Logo', 'Overrides the "Featured Image" phrase', 'film-watch-wiki'),
            'set_featured_image'    => _x('Set logo', 'Overrides the "Set featured image" phrase', 'film-watch-wiki'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'brand'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 8,
            'menu_icon'          => 'dashicons-awards',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('fww_brand', $args);
    }
}

// Initialize
FWW_Post_Types::init();
