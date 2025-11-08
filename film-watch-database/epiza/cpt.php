<?php
defined( 'ABSPATH' ) || exit;

class EpizaCPT {
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
        add_action('init', array($this, 'register_movies'));
        add_action('init', array($this, 'register_tv_shows'));
        add_action('init', array($this, 'register_taxonomies'), 0);
        add_filter('cmb2_meta_boxes', array($this, 'metaboxes'));
        add_action('admin_init', array($this, 'permalink_settings'));
        add_action('admin_init', array($this, 'permalink_save'));
        add_filter('register_post_type_args', array($this, 'set_custom_cpt_base'), 10, 2);
        add_filter('register_taxonomy_args', array($this, 'apply_custom_taxonomy_slug'), 10, 2 );
        add_filter('get_the_archive_title', array($this, 'remove_archive_prefix'));
    }

    /**
	 * Register Movies Post Type
	 */
    public function register_movies() {
        $slug =  EpizaSettings::get_option('movie_slug', 'movies');

        $labels = array(
            'name'              => esc_html__( 'Movies', 'epiza' ),
            'singular_name'     => esc_html__( 'Movie', 'epiza' ),
            'add_new'           => esc_html__( 'Add new movie', 'epiza' ),
            'add_new_item'      => esc_html__( 'Add new movie', 'epiza' ),
            'edit_item'         => esc_html__( 'Edit movie', 'epiza' ),
            'new_item'          => esc_html__( 'New movie', 'epiza' ),
            'view_item'         => esc_html__( 'View movie', 'epiza' ),
            'search_items'      => esc_html__( 'Search movies', 'epiza' ),
            'not_found'         => esc_html__( 'No movie found', 'epiza' ),
            'not_found_in_trash'=> esc_html__( 'No movie found in trash', 'epiza' ),
            'parent_item_colon' => esc_html__( 'Parent movie:', 'epiza' ),
            'menu_name'         => esc_html__( 'Movies', 'epiza' )
        );
    
        $taxonomies = array();
     
        $supports = array('title', 'thumbnail', 'editor', 'excerpt', 'comments');
     
        $post_type_args = array(
            'labels'            => $labels,
            'singular_label'    => esc_html__('Movie', 'epiza'),
            'public'            => true,
            'exclude_from_search' => false,
            'show_ui'           => true,
            'show_in_nav_menus' => true,
            'publicly_queryable'=> true,
            'query_var'         => true,
            'capability_type'   => 'post',
            'capabilities' => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'delete_posts'       => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options'
            ),
            'has_archive'       => true,
            'hierarchical'      => false,
            'show_in_rest'      => false,
            'rewrite'           => true,
            'supports'          => $supports,
            'menu_position'     => 10,
            'menu_icon'         => 'dashicons-video-alt3',
            'taxonomies'        => $taxonomies
        );
        register_post_type('epizamovies',$post_type_args);
    }

    /**
	 * Register TV Show Post Type
	 */
    public function register_tv_shows() {
        $slug =  EpizaSettings::get_option('tv_slug', 'tv-shows');

        $labels = array(
            'name'              => esc_html__( 'TV Shows', 'epiza' ),
            'singular_name'     => esc_html__( 'TV Show', 'epiza' ),
            'add_new'           => esc_html__( 'Add new TV show', 'epiza' ),
            'add_new_item'      => esc_html__( 'Add new TV show', 'epiza' ),
            'edit_item'         => esc_html__( 'Edit TV show', 'epiza' ),
            'new_item'          => esc_html__( 'New TV show', 'epiza' ),
            'view_item'         => esc_html__( 'View TV show', 'epiza' ),
            'search_items'      => esc_html__( 'Search TV shows', 'epiza' ),
            'not_found'         => esc_html__( 'No TV show found', 'epiza' ),
            'not_found_in_trash'=> esc_html__( 'No TV show found in trash', 'epiza' ),
            'parent_item_colon' => esc_html__( 'Parent TV show:', 'epiza' ),
            'menu_name'         => esc_html__( 'TV Shows', 'epiza' )
        );
    
        $taxonomies = array();
     
        $supports = array('title', 'thumbnail', 'editor', 'excerpt', 'comments');
     
        $post_type_args = array(
            'labels'            => $labels,
            'singular_label'    => esc_html__('TV Show', 'epiza'),
            'public'            => true,
            'exclude_from_search' => false,
            'show_ui'           => true,
            'show_in_nav_menus' => true,
            'publicly_queryable'=> true,
            'query_var'         => true,
            'capability_type'   => 'post',
            'capabilities' => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'delete_posts'       => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options'
            ),
            'has_archive'       => true,
            'hierarchical'      => false,
            'show_in_rest'      => false,
            'rewrite'           => true,
            'supports'          => $supports,
            'menu_position'     => 10,
            'menu_icon'         => 'dashicons-video-alt3',
            'taxonomies'        => $taxonomies
        );
        register_post_type('epizatvshows',$post_type_args);
    }

    /**
	 * Register Taxonomy
	 */
    public function register_taxonomies() {
        $movie_genre_slug =  EpizaSettings::get_option('movie_genre_slug', 'movie-genre');
        $tv_genre_slug =  EpizaSettings::get_option('tv_genre_slug', 'tv-genre');
        $actors_slug =  EpizaSettings::get_option('actors_slug', 'actor');

        register_taxonomy(
            'epizamoviegenres',
            'epizamovies',
            array(
                'labels' => array(
                    'name' => esc_html__( 'Movie Genres', 'epiza' ),
                    'singular_name' => esc_html__( 'Genre', 'epiza' ),
                    'add_new_item' => esc_html__( 'Add new genre', 'epiza' ),
                    'new_item_name' => esc_html__( 'New genre', 'epiza' )
                ),
                'show_ui' => true,
                'show_tagcloud' => false,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'hierarchical' => false,
                'rewrite' => true,
                'query_var' => true
            )
        );

        register_taxonomy(
            'epizatvgenres',
            'epizatvshows',
            array(
                'labels' => array(
                    'name' => esc_html__( 'TV Show Genres', 'epiza' ),
                    'singular_name' => esc_html__( 'Genre', 'epiza' ),
                    'add_new_item' => esc_html__( 'Add new genre', 'epiza' ),
                    'new_item_name' => esc_html__( 'New genre', 'epiza' )
                ),
                'show_ui' => true,
                'show_tagcloud' => false,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'hierarchical' => false,
                'rewrite' => true,
                'query_var' => true
            )
        );

        register_taxonomy(
            'epizacast',
            array('epizatvshows','epizamovies'),
            array(
                'labels' => array(
                    'name' => esc_html__( 'Actors', 'epiza' ),
                    'singular_name' => esc_html__( 'Actor', 'epiza' ),
                    'add_new_item' => esc_html__( 'Add new actor', 'epiza' ),
                    'new_item_name' => esc_html__( 'New actor', 'epiza' )
                ),
                'show_ui' => true,
                'show_tagcloud' => false,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'hierarchical' => false,
                'rewrite' => true,
                'query_var' => true
            )
        );
    }

    /**
	 * Permalink Settings
	 */
    public function permalink_settings() {
        add_settings_section(
            'epiza_settings_section',
            esc_html__( 'Epiza', 'epiza' ),
            '__return_false',
            'permalink'
        );

        register_setting(
            'permalink',
            'epiza_movies_slug',
            'sanitize_text_field'
        );

        register_setting(
            'permalink',
            'epiza_tv_slug',
            'sanitize_text_field'
        );

        register_setting(
            'permalink',
            'epiza_movie_genres_slug',
            'sanitize_text_field'
        );

        register_setting(
            'permalink',
            'epiza_tv_genres_slug',
            'sanitize_text_field'
        );

        register_setting(
            'permalink',
            'epiza_actors_slug',
            'sanitize_text_field'
        );

        add_settings_field(
            'epiza_movies_slug_field',
            esc_html__( 'Movie Base', 'epiza' ),
            array( $this, 'epiza_movies_slug_field_output' ),
            'permalink',
            'epiza_settings_section'
        );

        add_settings_field(
            'epiza_tv_slug_field',
            esc_html__( 'TV Show Base', 'epiza' ),
            array( $this, 'epiza_tv_slug_field_output' ),
            'permalink',
            'epiza_settings_section'
        );

        add_settings_field(
            'epiza_movie_genres_slug_field',
            esc_html__( 'Movie Genres Base', 'epiza' ),
            array( $this, 'epiza_movie_genres_slug_output' ),
            'permalink',
            'epiza_settings_section'
        );

        add_settings_field(
            'epiza_tv_genres_slug_field',
            esc_html__( 'TV Show Genres Base', 'epiza' ),
            array( $this, 'epiza_tv_genres_slug_output' ),
            'permalink',
            'epiza_settings_section'
        );

        add_settings_field(
            'epiza_actors_slug_field',
            esc_html__( 'Actors Base', 'epiza' ),
            array( $this, 'epiza_actors_slug_output' ),
            'permalink',
            'epiza_settings_section'
        );
    }

    /**
	 * Permalink Settings Callbacks
	 */
    public function epiza_movies_slug_field_output() {
        $base_slug = get_option( 'epiza_movies_slug', 'movies' );
        ?>
        <input type="text" value="<?php echo esc_attr( $base_slug ); ?>" name="epiza_movies_slug" id="epiza_movies_slug" class="regular-text code" />
        <?php
    }

    public function epiza_tv_slug_field_output() {
        $base_slug = get_option( 'epiza_tv_slug', 'tv-shows' );
        ?>
        <input type="text" value="<?php echo esc_attr( $base_slug ); ?>" name="epiza_tv_slug" id="epiza_tv_slug" class="regular-text code" />
        <?php
    }

    public function epiza_movie_genres_slug_output() {
        $base_slug = get_option( 'epiza_movie_genres_slug', 'movie-genre' );
        ?>
        <input type="text" value="<?php echo esc_attr( $base_slug ); ?>" name="epiza_movie_genres_slug" id="epiza_movie_genres_slug" class="regular-text code" />
        <?php
    }

    public function epiza_tv_genres_slug_output() {
        $base_slug = get_option( 'epiza_tv_genres_slug', 'tv-genre' );
        ?>
        <input type="text" value="<?php echo esc_attr( $base_slug ); ?>" name="epiza_tv_genres_slug" id="epiza_tv_genres_slug" class="regular-text code" />
        <?php
    }

    public function epiza_actors_slug_output() {
        $base_slug = get_option( 'epiza_actors_slug', 'actor' );
        ?>
        <input type="text" value="<?php echo esc_attr( $base_slug ); ?>" name="epiza_actors_slug" id="epiza_actors_slug" class="regular-text code" />
        <?php
    }

    /**
	 * Permalink Save
	 */
    public function permalink_save() {
        if ( isset( $_POST['permalink_structure'] ) && isset( $_POST['epiza_movies_slug'] ) && isset( $_POST['epiza_tv_slug'] ) && isset( $_POST['epiza_movie_genres_slug'] ) && isset( $_POST['epiza_tv_genres_slug'] ) && isset( $_POST['epiza_actors_slug'] ) ) {
            $movie_base = sanitize_text_field( wp_unslash( $_POST['epiza_movies_slug'] ) );
            $tv_base = sanitize_text_field( wp_unslash( $_POST['epiza_tv_slug'] ) );
            $movie_genres_base = sanitize_text_field( wp_unslash( $_POST['epiza_movie_genres_slug'] ) );
            $tv_genres_base = sanitize_text_field( wp_unslash( $_POST['epiza_tv_genres_slug'] ) );
            $actors_base = sanitize_text_field( wp_unslash( $_POST['epiza_actors_slug'] ) );
            update_option( 'epiza_movies_slug', $movie_base );
            update_option( 'epiza_tv_slug', $tv_base );
            update_option( 'epiza_movie_genres_slug', $movie_genres_base );
            update_option( 'epiza_tv_genres_slug', $tv_genres_base );
            update_option( 'epiza_actors_slug', $actors_base );
            flush_rewrite_rules();
        }
    }

    /**
	 * Set CPT Base
	 */
    public function set_custom_cpt_base( $args, $post_type ) {
        if ( 'epizamovies' === $post_type ) {
            if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
                $args['rewrite'] = array();
            }
            $custom_base = get_option( 'epiza_movies_slug', 'movies');
            $args['rewrite']['slug'] = sanitize_title_with_dashes( $custom_base );
        } else if ( 'epizatvshows' === $post_type ) {
            if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
                $args['rewrite'] = array();
            }
            $custom_base = get_option( 'epiza_tv_slug', 'tv-shows');
            $args['rewrite']['slug'] = sanitize_title_with_dashes( $custom_base );
        }
        return $args;
    }

    /**
	 * Set CPT Base
	 */
    public function apply_custom_taxonomy_slug( $args, $taxonomy ) {
    if ( 'epizamoviegenres' === $taxonomy ) {
        $custom_base = get_option( 'epiza_movie_genres_slug', 'movie-genre' ); 
        if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
            $args['rewrite'] = array();
        }
        $args['rewrite']['slug'] = sanitize_title_with_dashes( $custom_base );
    } else if ( 'epizatvgenres' === $taxonomy ) {
        $custom_base = get_option( 'epiza_tv_genres_slug', 'tv-genre' ); 
        if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
            $args['rewrite'] = array();
        }
        $args['rewrite']['slug'] = sanitize_title_with_dashes( $custom_base );
    } else if ( 'epizacast' === $taxonomy ) {
        $custom_base = get_option( 'epiza_actors_slug', 'actor' ); 
        if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
            $args['rewrite'] = array();
        }
        $args['rewrite']['slug'] = sanitize_title_with_dashes( $custom_base );
    }
    return $args;
}

    /**
	 * Custom Metaboxes
	 */
    public function metaboxes( $meta_boxes ) {
        $poster = new_cmb2_box( array(
            'id' => 'epiza_poster_metabox',
            'title' => esc_attr__( 'Poster', 'epiza'),
            'object_types' => array('epizamovies','epizatvshows'),
            'context' => 'side',
            'priority' => 'default',
            'show_names' => false,
            'cmb_styles' => true
        ));

        $poster->add_field( array(
            'name'    => esc_html__( 'Poster', 'epiza' ),
            'id'      => 'epiza_poster',
            'type' => 'file',
            'preview_size' => array( 100, 100 ),
            'query_args' => array(
                'type' => array(
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ),
            )
        ));

        $videos = new_cmb2_box( array(
            'id' => 'epiza_videos',
            'title' => esc_attr__( 'Videos', 'epiza'),
            'object_types' => array('epizamovies','epizatvshows'),
            'context' => 'normal',
            'priority' => 'default',
            'show_names' => true,
            'cmb_styles' => true
        ));

        $video_group = $videos->add_field( array(
            'id'          => 'epiza_video_group',
            'type'        => 'group',
            'options'     => array(
                'group_title'       => esc_html__( 'Video {#}', 'epiza' ),
                'add_button'        => esc_html__( 'Add Another Video', 'epiza' ),
                'remove_button'     => esc_html__( 'Remove Video', 'epiza' ),
                'sortable'          => true,
                'closed'         => true,
            )
        ) );

        $videos->add_group_field( $video_group, array(
            'name' => esc_html__( 'Name', 'epiza' ),
            'id'   => 'name',
            'type' => 'text'
        ) );

        $videos->add_group_field( $video_group, array(
            'name' => esc_html__( 'YouTube URL', 'epiza' ),
            'id'   => 'url',
            'type' => 'oembed'
        ) );

        $rating = new_cmb2_box( array(
            'id' => 'epiza_rating_metabox',
            'title' => esc_attr__( 'User Score', 'epiza'),
            'object_types' => array('epizamovies','epizatvshows'),
            'context' => 'side',
            'priority' => 'default',
            'show_names' => false,
            'cmb_styles' => true
        ));

        $rating->add_field( array(
            'name'    => esc_html__( 'User Score', 'epiza' ),
            'desc' => esc_html__( 'Only numbers and a single decimal point are allowed, max value is 10.', 'epiza' ),
            'id'      => 'epiza_rating',
            'type' => 'text',
            'attributes' => array(
                'inputmode' => 'numeric',
                'title' => esc_html__( 'Only numbers and a single decimal point are allowed, max value is 10.', 'epiza' ),
                'pattern' => '^\d*\.?\d*$',
                'autocomplete' => 'off'
            ),
        ));
    }

    /**
	 * Remove prefix from our custom post types
	 */
    public function remove_archive_prefix( $title ) {
        $cpts_to_modify = array('epizatvshows','epizamovies');
        if ( is_post_type_archive() && in_array( get_query_var( 'post_type' ), $cpts_to_modify ) ) {
            $title = post_type_archive_title( '', false );
        }
        return $title;
    }
}

/**
 * Returns the main instance of the class
 */
function EpizaCPT() {  
	return EpizaCPT::instance();
}
// Global for backwards compatibility
$GLOBALS['EpizaCPT'] = EpizaCPT();
