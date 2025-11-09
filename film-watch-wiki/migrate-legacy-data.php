<?php
/**
 * CLI Script to Migrate Legacy Data
 *
 * This script migrates data from wp_fwd_* legacy tables to the new WordPress
 * post types and relationships system.
 *
 * USAGE:
 *
 * Dry Run (Preview only - no changes):
 *   php migrate-legacy-data.php --dry-run
 *
 * Live Migration (Actually migrates data):
 *   php migrate-legacy-data.php --live
 *
 * With verbose output:
 *   php migrate-legacy-data.php --dry-run --verbose
 *
 * Quiet mode (only summary):
 *   php migrate-legacy-data.php --live --quiet
 */

// Determine WordPress path
$wordpress_path = dirname(__FILE__) . '/../../../wp-load.php';

if (!file_exists($wordpress_path)) {
    // Try alternate path
    $wordpress_path = dirname(__FILE__) . '/../../wp-load.php';
}

if (!file_exists($wordpress_path)) {
    echo "ERROR: Could not find WordPress installation.\n";
    echo "Please run this script from the plugin directory or specify WP path.\n";
    exit(1);
}

// Load WordPress
require_once($wordpress_path);

// Load migration class
require_once(dirname(__FILE__) . '/includes/migration.php');

// Parse command line arguments
$dry_run = true;  // Default to dry run for safety
$verbose = true;

$args = $_SERVER['argv'] ?? array();

foreach ($args as $arg) {
    if ($arg === '--live') {
        $dry_run = false;
    }
    if ($arg === '--dry-run') {
        $dry_run = true;
    }
    if ($arg === '--verbose') {
        $verbose = true;
    }
    if ($arg === '--quiet') {
        $verbose = false;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "\nFilm Watch Wiki - Legacy Data Migration\n";
        echo "========================================\n\n";
        echo "USAGE:\n";
        echo "  php migrate-legacy-data.php [OPTIONS]\n\n";
        echo "OPTIONS:\n";
        echo "  --dry-run    Preview migration without making changes (default)\n";
        echo "  --live       Actually perform the migration\n";
        echo "  --verbose    Show detailed progress (default)\n";
        echo "  --quiet      Show only summary\n";
        echo "  --help, -h   Show this help message\n\n";
        echo "EXAMPLES:\n";
        echo "  php migrate-legacy-data.php --dry-run\n";
        echo "  php migrate-legacy-data.php --live --verbose\n\n";
        echo "WHAT IT MIGRATES:\n";
        echo "  1. Brands (wp_fwd_brands → fww_brand posts)\n";
        echo "  2. Watches (wp_fwd_watches → fww_watch posts)\n";
        echo "  3. Actors (wp_fwd_actors → fww_actor posts)\n";
        echo "  4. Movies (wp_fwd_films → fww_movie posts)\n";
        echo "  5. Sightings (wp_fwd_film_actor_watch → wp_fww_sightings table)\n\n";
        echo "SAFETY:\n";
        echo "  - Always run with --dry-run first to preview\n";
        echo "  - Script will skip existing posts (no duplicates)\n";
        echo "  - Legacy tables are NOT modified (read-only)\n";
        echo "  - All created posts store legacy_id for reference\n\n";
        exit(0);
    }
}

// Display header
echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║       FILM WATCH WIKI - LEGACY DATA MIGRATION             ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

// Confirm live migration
if (!$dry_run) {
    echo "WARNING: You are about to perform a LIVE migration!\n";
    echo "This will create WordPress posts and database records.\n";
    echo "\n";
    echo "Type 'yes' to continue or anything else to cancel: ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirmation = trim($line);
    fclose($handle);

    if (strtolower($confirmation) !== 'yes') {
        echo "\nMigration cancelled.\n\n";
        exit(0);
    }
    echo "\n";
}

// Run migration
$migration = new FWW_Migration($dry_run, $verbose);

try {
    $stats = $migration->run_migration();

    // Exit with success
    echo "\n";
    if ($dry_run) {
        echo "Dry run completed successfully. Run with --live to perform actual migration.\n";
    } else {
        echo "Migration completed successfully!\n";
    }
    echo "\n";

    exit(0);

} catch (Exception $e) {
    echo "\n";
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    echo "\n";
    exit(1);
}
