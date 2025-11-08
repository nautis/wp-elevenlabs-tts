<?php
defined( 'ABSPATH' ) || exit;

class EpizaSettings {
    /* The single instance of the class */
	protected static $_instance = null;

    /* Main Instance */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

    /* Constructor */
    public function __construct() {
        add_action( 'cmb2_admin_init', array($this, 'register_metabox') );
        add_action( 'admin_enqueue_scripts',array($this, 'colorpicker_labels'), 99 );
        add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts') );
        add_filter( 'cmb2_override_meta_value', array($this, 'cmb2_override'), 10, 4 );
    }

    /* Admin Scripts */
    public function admin_scripts($hook){
        if ('epiza_page_epiza_options' == $hook)  {
            wp_enqueue_style('epiza-settings', EPIZA_PLUGIN_URL . 'css/admin-settings.css', false, EPIZA_VERSION);
            wp_enqueue_script('epiza-settings', EPIZA_PLUGIN_URL . 'js/admin-settings.js', array( 'jquery' ), EPIZA_VERSION, true);
        }
    }

    /**
    * Hook in and register a metabox to handle a plugin options page and adds a menu item.
    */
    public function register_metabox() {
        $args = array(
            'id'           => 'epiza_options',
            'title'        => esc_html__('Epiza Settings', 'epiza') . ' <span><a href="https://palleon.website/epiza/documentation/" target="_blank">' . esc_html__( 'Help Docs', 'epiza' ) . ' - v' . EPIZA_VERSION . '<span class="dashicons dashicons-external"></span></a></span>',
            'menu_title'   => esc_html__('Settings', 'epiza'),
            'object_types' => array( 'options-page' ),
            'option_key'   => 'epiza_options',
            'capability'      => 'manage_options',
            'parent_slug'     => 'epiza',
            'save_button'     => esc_html__( 'Save Settings', 'epiza' )
        );

        $options = new_cmb2_box( $args );

        $options->add_field( array(
            'name'    => esc_html__( 'API Read Access Token (Required)', 'epiza' ),
            'description' => esc_html__( 'You must get a free API key from TMDB to use the API. For more information, please read the documentation.', 'epiza' ),
            'id'      => 'epiza_api_key',
            'type'    => 'text',
            'attributes' => array(
                'autocomplete' => 'off'
            ),
            'default' => '',
            'before_row' => '<div id="epiza-tab-boxes"><div class="epiza-tab-content">',
        ) );

        $options->add_field( array(
            'name' => esc_html__( 'Language', 'epiza' ),
            'description' => esc_html__( 'The language of the search you are performing.', 'epiza' ),
            'id'   => 'epiza_lang',
            'type' => 'select',
            'options' => array(
                'en' => esc_html__( 'English', 'epiza' ),
                'pt' => esc_html__( 'Portuguese', 'epiza' ),
                'es' => esc_html__( 'Spanish', 'epiza' ),
                'de' => esc_html__( 'German', 'epiza' ),
                'it' => esc_html__( 'Italian', 'epiza' ),
                'fr' => esc_html__( 'French', 'epiza' ),
                'sv' => esc_html__( 'Swedish', 'epiza' ),
                'pl' => esc_html__( 'Polish', 'epiza' ),
                'nl' => esc_html__( 'Dutch', 'epiza' ),
                'hu' => esc_html__( 'Hungarian', 'epiza' ),
                'cs' => esc_html__( 'Czech', 'epiza' ),
                'da' => esc_html__( 'Danish', 'epiza' ),
                'fi' => esc_html__( 'Finnish', 'epiza' ),
                'no' => esc_html__( 'Norwegian', 'epiza' ),
                'tr' => esc_html__( 'Turkish', 'epiza' ),
                'bg' => esc_html__( 'Bulgarian', 'epiza' ),
                'el' => esc_html__( 'Greek', 'epiza' ),
                'ro' => esc_html__( 'Romanian', 'epiza' ),
                'sk' => esc_html__( 'Slovak', 'epiza' ),
                'ru' => esc_html__( 'Russian', 'epiza' ),
                'ja' => esc_html__( 'Japanese', 'epiza' ),
                'zh' => esc_html__( 'Chinese', 'epiza' ),
                'ko' => esc_html__( 'Korean', 'epiza' ),
                'th' => esc_html__( 'Thai', 'epiza' ),
                'id' => esc_html__( 'Indonesian', 'epiza' ),
                'vi' => esc_html__( 'Vietnamese', 'epiza' )
            ),
            'attributes' => array(
                'autocomplete' => 'off'
            ),
            'default' => 'en'
        ) );

        $options->add_field(
            array(
                'name' => esc_html__( 'Include Adult', 'epiza'),
                'description'    => esc_html__( 'Include adult movies and tv shows in results.', 'epiza'),
                'id' => 'epiza_include_adult',
                'type' => 'radio_inline',
                'options' => array(
                    'true' => esc_html__( 'Yes', 'epiza' ),
                    'false'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'false',
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Post Status', 'epiza'),
                'description'    => esc_html__( 'The default post status when importing a movie or TV show.', 'epiza'),
                'id' => 'epiza_post_status',
                'type' => 'radio_inline',
                'options' => array(
                    'publish' => esc_html__( 'Publish', 'epiza' ),
                    'draft'   => esc_html__( 'Draft', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'publish'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Featured Image', 'epiza'),
                'description'    => esc_html__( 'The default featured image when importing a movie or TV show. To not import the featured image, select "None".', 'epiza'),
                'id' => 'epiza_featured',
                'type' => 'radio_inline',
                'options' => array(
                    'backdrop' => esc_html__( 'Backdrop', 'epiza' ),
                    'poster'   => esc_html__( 'Poster', 'epiza' ),
                    'none'   => esc_html__( 'None', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'backdrop'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Import Poster', 'epiza'),
                'id' => 'epiza_import_poster',
                'type' => 'radio_inline',
                'options' => array(
                    'yes' => esc_html__( 'Yes', 'epiza' ),
                    'no'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'yes'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Show Poster', 'epiza'),
                'description'    => esc_html__('Show poster on single movie/tv show page.', 'epiza'),
                'id' => 'epiza_show_poster',
                'type' => 'radio_inline',
                'options' => array(
                    'yes' => esc_html__( 'Yes', 'epiza' ),
                    'no'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'yes'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Backdrop Size', 'epiza'),
                'description'    => esc_html__( 'Size of image to be imported.', 'epiza'),
                'id' => 'epiza_featured_size',
                'type' => 'radio_inline',
                'options' => array(
                    'original' => esc_html__( 'Original', 'epiza' ),
                    'w500'   => esc_html__( 'Large', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'original'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Poster Size', 'epiza'),
                'description'    => esc_html__( 'Size of image to be imported.', 'epiza'),
                'id' => 'epiza_poster_size',
                'type' => 'radio_inline',
                'options' => array(
                    'original' => esc_html__( 'Original', 'epiza' ),
                    'w500'   => esc_html__( 'Large', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'w500'
            )
        );

        $options->add_field( array(
            'name' => esc_html__( 'Movie Data', 'epiza' ),
            'desc' => esc_html__( 'Optional movie data to import.', 'epiza' ),
            'id'   => 'epiza_movie_data',
            'type' => 'multicheck_inline',
            'select_all_button' => true,
            'options' => array(
                'tagline'   => esc_html__( 'Tagline', 'epiza' ),
                'release_date'   => esc_html__( 'Release Date', 'epiza' ),
                'runtime'   => esc_html__( 'Runtime', 'epiza' ),
                'budget' => esc_html__( 'Budget', 'epiza' ),
                'revenue'   => esc_html__( 'Revenue', 'epiza' ),
                'homepage'   => esc_html__( 'Official Site', 'epiza' ),
                'production_companies'   => esc_html__( 'Production Companies', 'epiza' ),
                'production_countries'   => esc_html__( 'Production Countries', 'epiza' ),
                'original_language'   => esc_html__( 'Original Language', 'epiza' ),
                'original_title'   => esc_html__( 'Original Title', 'epiza' )
            ),
            'attributes' => array(
                'autocomplete' => 'off'
            ),
            'default' => array('tagline', 'release_date', 'runtime', 'budget', 'revenue', 'production_companies', 'production_countries')
        ) );

        $options->add_field( array(
            'name' => esc_html__( 'TV Show Data', 'epiza' ),
            'desc' => esc_html__( 'Optional tv show data to import.', 'epiza' ),
            'id'   => 'epiza_tv_show_data',
            'type' => 'multicheck_inline',
            'select_all_button' => true,
            'options' => array(
                'tagline'   => esc_html__( 'Tagline', 'epiza' ),
                'first_air_date'   => esc_html__( 'First Air Date', 'epiza' ),
                'number_of_episodes'   => esc_html__( 'Number of Episodes', 'epiza' ),
                'number_of_seasons' => esc_html__( 'Number of Seasons', 'epiza' ),
                'created_by'   => esc_html__( 'Created by', 'epiza' ),
                'homepage'   => esc_html__( 'Official Site', 'epiza' ),
                'production_companies'   => esc_html__( 'Production Companies', 'epiza' ),
                'production_countries'   => esc_html__( 'Production Countries', 'epiza' ),
                'networks'   => esc_html__( 'Networks', 'epiza' ),
                'original_language'   => esc_html__( 'Original Language', 'epiza' ),
                'original_name'   => esc_html__( 'Original Name', 'epiza' )
            ),
            'attributes' => array(
                'autocomplete' => 'off'
            ),
            'default' => array('tagline', 'first_air_date', 'number_of_episodes', 'number_of_seasons', 'created_by', 'production_countries', 'networks')
        ) );

        $options->add_field(
            array(
                'name' => esc_html__( 'User Score', 'epiza'),
                'id' => 'epiza_user_score',
                'type' => 'radio_inline',
                'options' => array(
                    'enable' => esc_html__( 'Enable', 'epiza' ),
                    'disable'   => esc_html__( 'Disable', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'enable'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Tagline HTML Tag', 'epiza'),
                'id' => 'epiza_tagline_tag',
                'type' => 'select',
                'options' => array(
                    'h1'   => esc_html__( 'Heading 1', 'epiza' ),
                    'h2'   => esc_html__( 'Heading 2', 'epiza' ),
                    'h3'   => esc_html__( 'Heading 3', 'epiza' ),
                    'h4'   => esc_html__( 'Heading 4', 'epiza' ),
                    'h5'   => esc_html__( 'Heading 5', 'epiza' ),
                    'h6'   => esc_html__( 'Heading 6', 'epiza' ),
                    'p'    => esc_html__( 'Paragraph', 'epiza' ),
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'h3'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Video Title HTML Tag', 'epiza'),
                'id' => 'epiza_video_title_tag',
                'type' => 'select',
                'options' => array(
                    'h1'   => esc_html__( 'Heading 1', 'epiza' ),
                    'h2'   => esc_html__( 'Heading 2', 'epiza' ),
                    'h3'   => esc_html__( 'Heading 3', 'epiza' ),
                    'h4'   => esc_html__( 'Heading 4', 'epiza' ),
                    'h5'   => esc_html__( 'Heading 5', 'epiza' ),
                    'h6'   => esc_html__( 'Heading 6', 'epiza' ),
                    'p'    => esc_html__( 'Paragraph', 'epiza' ),
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'h4'
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Show genres as links', 'epiza'),
                'id' => 'epiza_genre_link',
                'type' => 'radio_inline',
                'options' => array(
                    'yes' => esc_html__( 'Yes', 'epiza' ),
                    'no'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'yes'
            )
        );

        $options->add_field( array(
            'name'    => esc_html__( 'Number of Actors', 'epiza' ),
            'description' => esc_html__( 'Maximum number of actors to import for a single movie/tv show. Enter 0 to not import actors.', 'epiza' ),
            'id'      => 'epiza_actors',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'pattern' => '\d*',
                'autocomplete' => 'off'
            ),
            'default' => 5
        ) );

        $options->add_field(
            array(
                'name' => esc_html__( 'Show actors as links', 'epiza'),
                'id' => 'epiza_actor_link',
                'type' => 'radio_inline',
                'options' => array(
                    'yes' => esc_html__( 'Yes', 'epiza' ),
                    'no'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'yes'
            )
        );

        $options->add_field( array(
            'name'    => esc_html__( 'Number of Videos', 'epiza' ),
            'description' => esc_html__( 'Maximum number of videos to import for a single movie/tv show. Enter 0 to not import videos.', 'epiza' ),
            'id'      => 'epiza_videos',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'pattern' => '\d*',
                'autocomplete' => 'off'
            ),
            'default' => 5,
        ) );

        $options->add_field(
            array(
                'name' => esc_html__( 'Single Post Layout', 'epiza'),
                'description'    => esc_html__( "If your theme's content area is narrow, a two-column layout may appear too cramped. In this case, it's recommended to choose the one column layout.", 'epiza'),
                'id' => 'epiza_post_layout',
                'type' => 'radio_inline',
                'options' => array(
                    'epiza-two-column' => esc_html__( '2 Column', 'epiza' ),
                    'epiza-one-column'   => esc_html__( '1 Column', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'epiza-two-column',
            )
        );

        $options->add_field(
            array(
                'name' => esc_html__( 'Sticky Poster', 'epiza'),
                'description'    => esc_html__( "For 2 column layout only.", 'epiza'),
                'id' => 'epiza_sticky_poster',
                'type' => 'radio_inline',
                'options' => array(
                    'epiza-sticky' => esc_html__( 'Enable', 'epiza' ),
                    ''   => esc_html__( 'Disable', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => '',
            )
        );

        $options->add_field( array(
            'name'    => esc_html__( 'Poster column width (%)', 'epiza' ),
            'description' => esc_html__( "For 2 column layout only.", 'epiza'),
            'id'      => 'epiza_poster_width',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'pattern' => '\d*',
                'max' => 90,
                'autocomplete' => 'off'
            ),
            'default' => 30,
        ) );

        $options->add_field( array(
            'name'    => esc_html__( 'Mobile Breakpoint (px)', 'epiza' ),
            'description' => esc_html__( "For 2 column layout only.", 'epiza'),
            'id'      => 'epiza_breakpoint',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'pattern' => '\d*',
                'autocomplete' => 'off'
            ),
            'default' => 782,
        ) );

        $options->add_field(
            array(
                'name' => esc_html__( 'TMDB Link', 'epiza'),
                'description' => esc_html__( "For reference, add a TMDB link below the post content.", 'epiza'),
                'id' => 'epiza_tmdb_credits',
                'type' => 'radio_inline',
                'options' => array(
                    'yes' => esc_html__( 'Yes', 'epiza' ),
                    'no'   => esc_html__( 'No', 'epiza' )
                ),
                'attributes' => array(
                    'autocomplete' => 'off'
                ),
                'default' => 'yes'
            )
        );

        $options->add_field( array(
            'name'    => esc_html__( 'Caching (hour)', 'epiza' ),
            'description' => esc_html__( 'For back-end only. Minimum 24 hours is recommended.', 'epiza' ),
            'id'      => 'epiza_caching',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'pattern' => '\d*',
                'autocomplete' => 'off'
            ),
            'default' => 24,
            'after_row' => '</div></div>',
        ) );
    }
    /**
    * Colorpicker Labels
    */
    public function colorpicker_labels( $hook ) {
        global $wp_version;
        if( version_compare( $wp_version, '5.4.2' , '>=' ) ) {
            wp_localize_script(
            'wp-color-picker',
            'wpColorPickerL10n',
            array(
                'clear'            => esc_html__( 'Clear', 'epiza' ),
                'clearAriaLabel'   => esc_html__( 'Clear color', 'epiza' ),
                'defaultString'    => esc_html__( 'Default', 'epiza' ),
                'defaultAriaLabel' => esc_html__( 'Select default color', 'epiza' ),
                'pick'             => esc_html__( 'Select Color', 'epiza' ),
                'defaultLabel'     => esc_html__( 'Color value', 'epiza' )
            )
            );
        }
    }

    /**
    * Set default blank canvas field values
    */
    public function cmb2_override( $value, $object_id, $args, $field ) {
        static $defaults = null;
        if ( 'cmb2_field_no_override_val' !== $value ) {
            return $value;
        }
        // Get the value for the field.
        $data = 'options-page' === $args['type']
        ? cmb2_options( $args['id'] )->get( $args['field_id'] )
        : get_metadata( $args['type'], $args['id'], $args['field_id'], ( $args['single'] || $args['repeat'] ) );
    
        return $value;
    }

    /**
    * Epiza get option
    */
    static function get_option( $key = '', $default = false ) {
        if ( function_exists( 'cmb2_get_option' ) ) {
            return cmb2_get_option( 'epiza_options', $key, $default );
        }
        $opts = get_option( 'epiza_options', $default );
        $val = $default;
        if ( 'all' == $key ) {
            $val = $opts;
        } elseif ( is_array( $opts ) && array_key_exists( $key, $opts ) && false !== $opts[ $key ] ) {
            $val = $opts[ $key ];
        }
        return $val;
    }

}

/**
 * Returns the main instance of the class.
 */
function EpizaSettings() {  
	return EpizaSettings::instance();
}
// Global for backwards compatibility.
$GLOBALS['EpizaSettings'] = EpizaSettings();