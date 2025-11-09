<?php
/**
 * PHPUnit Bootstrap File
 * Sets up the WordPress testing environment
 */

// Composer autoload
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// WordPress test suite location
$_tests_dir = getenv('WP_TESTS_DIR');

// Fallback to default locations if not set
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// If WordPress test suite not found, try to find it in common locations
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    $possible_locations = array(
        '/tmp/wordpress-tests-lib',
        '/var/www/wordpress-tests-lib',
        dirname(__DIR__, 4) . '/wordpress-tests-lib',
        '/usr/local/wordpress-tests-lib'
    );

    foreach ($possible_locations as $location) {
        if (file_exists($location . '/includes/functions.php')) {
            $_tests_dir = $location;
            break;
        }
    }
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    throw new Exception(
        'WordPress test suite not found. ' . PHP_EOL .
        'Please set WP_TESTS_DIR environment variable or install WordPress test suite.' . PHP_EOL .
        'See: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/'
    );
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Load the main plugin file
    require dirname(__DIR__) . '/film-watch-wiki.php';
}

// Load plugin before WordPress test suite
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test utilities if available
if (file_exists($_tests_dir . '/includes/class-wp-ajax-unittestcase.php')) {
    require_once $_tests_dir . '/includes/class-wp-ajax-unittestcase.php';
}

echo PHP_EOL;
echo 'Film Watch Wiki Plugin Test Suite' . PHP_EOL;
echo 'PHP Version: ' . phpversion() . PHP_EOL;
echo 'WordPress Test Suite: ' . $_tests_dir . PHP_EOL;
echo PHP_EOL;
