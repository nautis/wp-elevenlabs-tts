<?php
/**
 * Plugin Name: Epiza
 * Plugin URI: https://palleon.website/epiza/
 * Description: Movie & TV Show Importer
 * Version: 1.0
 * Requires PHP: 7.0
 * Author: Egemenerd
 * Author URI: http://codecanyon.net/user/egemenerd
 * License: http://codecanyon.net/licenses
 * Text Domain: epiza
 * Domain Path: /languages
 *
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EPIZA_VERSION' ) ) {
	define( 'EPIZA_VERSION', '1.0');
}

if ( ! defined( 'EPIZA_PLUGIN_URL' ) ) {
    define( 'EPIZA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/* ---------------------------------------------------------
Include required files
----------------------------------------------------------- */

$epiza_dir = ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) ? __DIR__ : dirname( __FILE__ );

if ( file_exists( $epiza_dir . '/cmb2/init.php' ) ) {
    require_once($epiza_dir . '/cmb2/init.php');
} else if ( file_exists(  $epiza_dir . '/CMB2/init.php' ) ) {
    require_once($epiza_dir . '/CMB2/init.php');
}

require_once('ISO639.php');
require_once('mainClass.php');
require_once('settingsClass.php');
require_once('cpt.php');