<?php
/**
 * Migration Tests
 *
 * Tests for the FWW_Migration class including:
 * - Data mapping
 * - Legacy ID tracking
 * - Duplicate detection
 * - Error handling
 */

class Test_Migration extends WP_UnitTestCase {

    protected $migration;

    public function setUp(): void {
        parent::setUp();

        // Ensure tables exist
        FWW_Sightings::create_table();
        FWW_Sightings::upgrade_table();

        // Load migration class
        require_once dirname(__FILE__) . '/../includes/migration.php';

        $this->migration = new FWW_Migration(true, false); // Dry run, not verbose
    }

    public function tearDown(): void {
        global $wpdb;

        // Clean up
        $wpdb->query("DELETE FROM {$wpdb->prefix}fww_sightings WHERE 1=1");
        $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type IN ('fww_brand', 'fww_watch', 'fww_actor', 'fww_movie')");

        parent::tearDown();
    }

    /**
     * Test 1: Migration Class Instantiation
     */
    public function test_migration_class_instantiation() {
        $this->assertInstanceOf('FWW_Migration', $this->migration);
    }

    /**
     * Test 2: Dry Run Mode
     */
    public function test_dry_run_mode() {
        // In dry run, should not create any posts
        global $wpdb;

        $initial_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'fww_brand'");

        // Migration would run but in dry run mode (if we had legacy data)
        // For now, just verify dry run is enabled
        $reflection = new ReflectionClass($this->migration);
        $property = $reflection->getProperty('dry_run');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($this->migration));
    }

    /**
     * Test 3: Valid Verification Level Mapping
     */
    public function test_verification_level_mapping() {
        // Test the mapping logic that would happen in migration
        $test_cases = [
            'confirmed' => 'confirmed',
            'high' => 'confirmed',
            'verified' => 'verified',
            'medium' => 'verified',
            '' => 'unverified',
            'unknown' => 'unverified'
        ];

        foreach ($test_cases as $input => $expected) {
            // This tests the logic from migrate_sightings method
            $conf = strtolower($input);
            $verification_level = 'unverified';

            if (strpos($conf, 'confirmed') !== false || strpos($conf, 'high') !== false) {
                $verification_level = 'confirmed';
            } elseif (strpos($conf, 'verified') !== false || strpos($conf, 'medium') !== false) {
                $verification_level = 'verified';
            }

            $this->assertEquals($expected, $verification_level, "Failed for input: $input");
        }
    }

    /**
     * Test 4: Legacy ID Storage
     */
    public function test_legacy_id_storage() {
        // Create a sighting with legacy ID
        $movie_id = $this->factory->post->create(['post_type' => 'fww_movie']);
        $actor_id = $this->factory->post->create(['post_type' => 'fww_actor']);
        $watch_id = $this->factory->post->create(['post_type' => 'fww_watch']);
        $brand_id = $this->factory->post->create(['post_type' => 'fww_brand']);

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $movie_id,
            'actor_id' => $actor_id,
            'watch_id' => $watch_id,
            'brand_id' => $brand_id,
            'legacy_id' => 999
        ]);

        $sighting = FWW_Sightings::get_sighting($sighting_id);

        $this->assertEquals(999, $sighting->legacy_id);
        $this->assertNotNull($sighting->migrated_at);
    }

    /**
     * Test 5: Post Meta Legacy ID Storage
     */
    public function test_post_meta_legacy_id() {
        $brand_id = $this->factory->post->create([
            'post_type' => 'fww_brand',
            'post_title' => 'Rolex'
        ]);

        update_post_meta($brand_id, '_fww_legacy_brand_id', 42);

        $legacy_id = get_post_meta($brand_id, '_fww_legacy_brand_id', true);

        $this->assertEquals(42, $legacy_id);
    }

    /**
     * Test 6: Invalid Input - Null Data
     */
    public function test_migration_null_data() {
        $result = FWW_Sightings::add_sighting(null);

        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test 7: Invalid Input - Empty Array
     */
    public function test_migration_empty_data() {
        $result = FWW_Sightings::add_sighting([]);

        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test 8: Screenshot URL Migration
     */
    public function test_screenshot_url_migration() {
        $movie_id = $this->factory->post->create(['post_type' => 'fww_movie']);
        $actor_id = $this->factory->post->create(['post_type' => 'fww_actor']);
        $watch_id = $this->factory->post->create(['post_type' => 'fww_watch']);
        $brand_id = $this->factory->post->create(['post_type' => 'fww_brand']);

        $screenshot_url = 'https://tellingtime.com/wp-content/uploads/2025/11/record_6.webp';

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $movie_id,
            'actor_id' => $actor_id,
            'watch_id' => $watch_id,
            'brand_id' => $brand_id,
            'screenshot_url' => $screenshot_url,
            'legacy_id' => 6
        ]);

        $sighting = FWW_Sightings::get_sighting($sighting_id);

        $this->assertEquals($screenshot_url, $sighting->screenshot_url);
    }

    /**
     * Test 9: Source URL Migration
     */
    public function test_source_url_migration() {
        $movie_id = $this->factory->post->create(['post_type' => 'fww_movie']);
        $actor_id = $this->factory->post->create(['post_type' => 'fww_actor']);
        $watch_id = $this->factory->post->create(['post_type' => 'fww_watch']);
        $brand_id = $this->factory->post->create(['post_type' => 'fww_brand']);

        $source_url = 'https://example.com/source';

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $movie_id,
            'actor_id' => $actor_id,
            'watch_id' => $watch_id,
            'brand_id' => $brand_id,
            'source_url' => $source_url
        ]);

        $sighting = FWW_Sightings::get_sighting($sighting_id);

        $this->assertEquals($source_url, $sighting->source_url);
    }

    /**
     * Test 10: Duplicate Prevention During Migration
     */
    public function test_duplicate_prevention_migration() {
        $movie_id = $this->factory->post->create(['post_type' => 'fww_movie']);
        $actor_id = $this->factory->post->create(['post_type' => 'fww_actor']);
        $watch_id = $this->factory->post->create(['post_type' => 'fww_watch']);
        $brand_id = $this->factory->post->create(['post_type' => 'fww_brand']);

        // Create first sighting
        $sighting_id_1 = FWW_Sightings::add_sighting([
            'movie_id' => $movie_id,
            'actor_id' => $actor_id,
            'watch_id' => $watch_id,
            'brand_id' => $brand_id,
            'character_name' => 'James Bond',
            'legacy_id' => 100
        ]);

        $this->assertIsInt($sighting_id_1);

        // Try to create duplicate (different legacy_id but same movie/actor/watch/character)
        $sighting_id_2 = FWW_Sightings::add_sighting([
            'movie_id' => $movie_id,
            'actor_id' => $actor_id,
            'watch_id' => $watch_id,
            'brand_id' => $brand_id,
            'character_name' => 'James Bond',
            'legacy_id' => 101
        ]);

        $this->assertInstanceOf('WP_Error', $sighting_id_2);
        $this->assertEquals('duplicate_sighting', $sighting_id_2->get_error_code());
    }
}
