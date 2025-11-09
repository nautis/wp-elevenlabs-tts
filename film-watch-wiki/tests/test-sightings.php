<?php
/**
 * Sightings Tests
 *
 * Comprehensive tests for the FWW_Sightings class including:
 * - Normal expected inputs
 * - Invalid inputs
 * - Edge cases
 * - Data integrity
 */

class Test_Sightings extends WP_UnitTestCase {

    protected $movie_id;
    protected $actor_id;
    protected $watch_id;
    protected $brand_id;

    public function setUp(): void {
        parent::setUp();

        // Create test posts
        $this->movie_id = $this->factory->post->create([
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie',
            'post_status' => 'publish'
        ]);

        $this->actor_id = $this->factory->post->create([
            'post_type' => 'fww_actor',
            'post_title' => 'Test Actor',
            'post_status' => 'publish'
        ]);

        $this->brand_id = $this->factory->post->create([
            'post_type' => 'fww_brand',
            'post_title' => 'Test Brand',
            'post_status' => 'publish'
        ]);

        $this->watch_id = $this->factory->post->create([
            'post_type' => 'fww_watch',
            'post_title' => 'Test Watch',
            'post_status' => 'publish'
        ]);

        // Ensure table exists
        FWW_Sightings::create_table();
        FWW_Sightings::upgrade_table();
    }

    public function tearDown(): void {
        // Clean up sightings
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}fww_sightings WHERE 1=1");

        parent::tearDown();
    }

    /**
     * Test 1: Normal Expected Input - Basic Sighting Creation
     */
    public function test_create_basic_sighting() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => 'James Bond',
            'scene_description' => 'Wearing the watch during car chase',
            'verification_level' => 'confirmed'
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertIsInt($result, 'Should return sighting ID');
        $this->assertGreaterThan(0, $result, 'Sighting ID should be positive');

        // Verify it was created
        $sighting = FWW_Sightings::get_sighting($result);
        $this->assertNotNull($sighting);
        $this->assertEquals('James Bond', $sighting->character_name);
        $this->assertEquals('confirmed', $sighting->verification_level);
    }

    /**
     * Test 2: Normal Expected Input - With Screenshot URL
     */
    public function test_create_sighting_with_screenshot() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'screenshot_url' => 'https://example.com/image.jpg',
            'source_url' => 'https://example.com/source'
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);
        $sighting = FWW_Sightings::get_sighting($result);

        $this->assertEquals('https://example.com/image.jpg', $sighting->screenshot_url);
        $this->assertEquals('https://example.com/source', $sighting->source_url);
    }

    /**
     * Test 3: Normal Expected Input - Legacy Migration
     */
    public function test_create_sighting_with_legacy_id() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'legacy_id' => 123
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);
        $sighting = FWW_Sightings::get_sighting($result);

        $this->assertEquals(123, $sighting->legacy_id);
        $this->assertNotNull($sighting->migrated_at);
    }

    /**
     * Test 4: Invalid Input - Missing Required Fields
     */
    public function test_create_sighting_missing_movie_id() {
        $sighting_data = [
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_movie_id', $result->get_error_code());
    }

    /**
     * Test 5: Invalid Input - Missing Actor ID
     */
    public function test_create_sighting_missing_actor_id() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_actor_id', $result->get_error_code());
    }

    /**
     * Test 6: Invalid Input - Missing Watch ID
     */
    public function test_create_sighting_missing_watch_id() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'brand_id' => $this->brand_id
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_watch_id', $result->get_error_code());
    }

    /**
     * Test 7: Invalid Input - Missing Brand ID
     */
    public function test_create_sighting_missing_brand_id() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_brand_id', $result->get_error_code());
    }

    /**
     * Test 8: Invalid Input - Invalid Movie ID
     */
    public function test_create_sighting_invalid_movie_id() {
        $sighting_data = [
            'movie_id' => 99999999, // Non-existent
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_movie_id', $result->get_error_code());
    }

    /**
     * Test 9: Invalid Input - Invalid Screenshot URL
     */
    public function test_create_sighting_invalid_screenshot_url() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'screenshot_url' => 'not-a-valid-url'
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_screenshot_url', $result->get_error_code());
    }

    /**
     * Test 10: Invalid Input - Invalid Verification Level
     */
    public function test_create_sighting_invalid_verification_level() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'verification_level' => 'super-duper-confirmed' // Invalid
        ];

        $result = FWW_Sightings::add_sighting($sighting_data);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_verification_level', $result->get_error_code());
    }

    /**
     * Test 11: Duplicate Detection
     */
    public function test_duplicate_sighting_prevention() {
        $sighting_data = [
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => 'James Bond'
        ];

        // Create first sighting
        $result1 = FWW_Sightings::add_sighting($sighting_data);
        $this->assertIsInt($result1);

        // Try to create duplicate
        $result2 = FWW_Sightings::add_sighting($sighting_data);
        $this->assertInstanceOf('WP_Error', $result2);
        $this->assertEquals('duplicate_sighting', $result2->get_error_code());
    }

    /**
     * Test 12: Get Sightings by Movie
     */
    public function test_get_sightings_by_movie() {
        // Create multiple sightings
        for ($i = 1; $i <= 3; $i++) {
            $actor_id = $this->factory->post->create([
                'post_type' => 'fww_actor',
                'post_title' => "Actor $i"
            ]);

            FWW_Sightings::add_sighting([
                'movie_id' => $this->movie_id,
                'actor_id' => $actor_id,
                'watch_id' => $this->watch_id,
                'brand_id' => $this->brand_id,
                'character_name' => "Character $i"
            ]);
        }

        $sightings = FWW_Sightings::get_sightings_by_movie($this->movie_id);

        $this->assertCount(3, $sightings);
        $this->assertEquals('Test Movie', $sightings[0]->movie_title);
    }

    /**
     * Test 13: Get Sightings by Actor
     */
    public function test_get_sightings_by_actor() {
        // Create multiple movie sightings for one actor
        for ($i = 1; $i <= 3; $i++) {
            $movie_id = $this->factory->post->create([
                'post_type' => 'fww_movie',
                'post_title' => "Movie $i"
            ]);

            FWW_Sightings::add_sighting([
                'movie_id' => $movie_id,
                'actor_id' => $this->actor_id,
                'watch_id' => $this->watch_id,
                'brand_id' => $this->brand_id
            ]);
        }

        $sightings = FWW_Sightings::get_sightings_by_actor($this->actor_id);

        $this->assertCount(3, $sightings);
        $this->assertEquals('Test Actor', $sightings[0]->actor_name);
    }

    /**
     * Test 14: Get Sightings by Watch
     */
    public function test_get_sightings_by_watch() {
        // Create multiple sightings with same watch
        for ($i = 1; $i <= 3; $i++) {
            $movie_id = $this->factory->post->create([
                'post_type' => 'fww_movie',
                'post_title' => "Movie $i"
            ]);

            $actor_id = $this->factory->post->create([
                'post_type' => 'fww_actor',
                'post_title' => "Actor $i"
            ]);

            FWW_Sightings::add_sighting([
                'movie_id' => $movie_id,
                'actor_id' => $actor_id,
                'watch_id' => $this->watch_id,
                'brand_id' => $this->brand_id
            ]);
        }

        $sightings = FWW_Sightings::get_sightings_by_watch($this->watch_id);

        $this->assertCount(3, $sightings);
        $this->assertEquals('Test Watch', $sightings[0]->watch_name);
    }

    /**
     * Test 15: Get Sightings by Brand
     */
    public function test_get_sightings_by_brand() {
        // Create multiple sightings with same brand
        for ($i = 1; $i <= 3; $i++) {
            $movie_id = $this->factory->post->create([
                'post_type' => 'fww_movie',
                'post_title' => "Movie $i"
            ]);

            $actor_id = $this->factory->post->create([
                'post_type' => 'fww_actor',
                'post_title' => "Actor $i"
            ]);

            $watch_id = $this->factory->post->create([
                'post_type' => 'fww_watch',
                'post_title' => "Watch $i"
            ]);

            FWW_Sightings::add_sighting([
                'movie_id' => $movie_id,
                'actor_id' => $actor_id,
                'watch_id' => $watch_id,
                'brand_id' => $this->brand_id
            ]);
        }

        $sightings = FWW_Sightings::get_sightings_by_brand($this->brand_id);

        $this->assertCount(3, $sightings);
        $this->assertEquals('Test Brand', $sightings[0]->brand_name);
    }

    /**
     * Test 16: Soft Delete Functionality
     */
    public function test_soft_delete_sighting() {
        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ]);

        // Soft delete
        $result = FWW_Sightings::delete_sighting($sighting_id);
        $this->assertTrue($result);

        // Should not appear in normal queries
        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertNull($sighting);

        // Should appear when including deleted
        $sighting = FWW_Sightings::get_sighting($sighting_id, true);
        $this->assertNotNull($sighting);
        $this->assertNotNull($sighting->deleted_at);
    }

    /**
     * Test 17: Restore Soft-Deleted Sighting
     */
    public function test_restore_sighting() {
        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ]);

        // Delete and restore
        FWW_Sightings::delete_sighting($sighting_id);
        $result = FWW_Sightings::restore_sighting($sighting_id);

        $this->assertTrue($result);

        // Should now appear normally
        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertNotNull($sighting);
        $this->assertNull($sighting->deleted_at);
    }

    /**
     * Test 18: Update Sighting
     */
    public function test_update_sighting() {
        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => 'Original Name',
            'verification_level' => 'unverified'
        ]);

        $result = FWW_Sightings::update_sighting($sighting_id, [
            'character_name' => 'Updated Name',
            'verification_level' => 'confirmed',
            'scene_description' => 'New description'
        ]);

        $this->assertTrue($result);

        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertEquals('Updated Name', $sighting->character_name);
        $this->assertEquals('confirmed', $sighting->verification_level);
        $this->assertEquals('New description', $sighting->scene_description);
    }

    /**
     * Test 19: Get Statistics
     */
    public function test_get_statistics() {
        // Create various sightings
        FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'verification_level' => 'confirmed'
        ]);

        FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => 'Different Character',
            'verification_level' => 'verified'
        ]);

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => 'Another Character',
            'verification_level' => 'unverified',
            'legacy_id' => 123
        ]);

        // Delete one
        FWW_Sightings::delete_sighting($sighting_id);

        $stats = FWW_Sightings::get_statistics();

        $this->assertEquals(2, $stats['total_active']);
        $this->assertEquals(1, $stats['total_deleted']);
        $this->assertEquals(1, $stats['total_migrated']);
        $this->assertEquals(1, $stats['confirmed']);
        $this->assertEquals(1, $stats['verified']);
        $this->assertEquals(0, $stats['unverified']); // Because the unverified one was deleted
    }

    /**
     * Test 20: Edge Case - Very Long Text Fields
     */
    public function test_long_text_fields() {
        $long_description = str_repeat('A', 1000);

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'scene_description' => $long_description
        ]);

        $this->assertIsInt($sighting_id);

        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertEquals($long_description, $sighting->scene_description);
    }

    /**
     * Test 21: Edge Case - Special Characters in Text
     */
    public function test_special_characters() {
        $special_chars = "Test with special chars: <script>alert('xss')</script> & \"quotes\" & 'apostrophes'";

        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => $special_chars
        ]);

        $this->assertIsInt($sighting_id);

        $sighting = FWW_Sightings::get_sighting($sighting_id);
        // Should be sanitized
        $this->assertStringNotContainsString('<script>', $sighting->character_name);
    }

    /**
     * Test 22: Edge Case - Empty String vs Null
     */
    public function test_empty_string_handling() {
        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id,
            'character_name' => '', // Empty string
            'scene_description' => null
        ]);

        $this->assertIsInt($sighting_id);

        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertEquals('', $sighting->character_name);
    }

    /**
     * Test 23: Orphaned Sightings Cleanup
     */
    public function test_orphaned_sightings_cleanup() {
        $sighting_id = FWW_Sightings::add_sighting([
            'movie_id' => $this->movie_id,
            'actor_id' => $this->actor_id,
            'watch_id' => $this->watch_id,
            'brand_id' => $this->brand_id
        ]);

        // Delete the movie
        wp_delete_post($this->movie_id, true);

        // Sighting should be soft-deleted
        $sighting = FWW_Sightings::get_sighting($sighting_id);
        $this->assertNull($sighting); // Soft deleted
    }
}
