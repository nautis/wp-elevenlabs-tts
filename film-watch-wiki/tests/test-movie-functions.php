<?php
/**
 * Movie Functions Tests
 * Tests for movie-related helper functions
 */

class Test_FWW_Movie_Functions extends WP_UnitTestCase {

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test fww_get_movie_data with valid post
     */
    public function test_get_movie_data_valid_post() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie'
        ));

        update_post_meta($post_id, '_fww_tmdb_id', '12345');
        update_post_meta($post_id, '_fww_year', '2023');
        update_post_meta($post_id, '_fww_film_id', '100');

        $result = fww_get_movie_data($post_id);

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertEquals('12345', $result['tmdb_id'], 'TMDB ID should match');
        $this->assertEquals('2023', $result['year'], 'Year should match');
        $this->assertEquals('100', $result['film_id'], 'Film ID should match');

        wp_delete_post($post_id, true);
    }

    /**
     * Test fww_get_movie_data with missing meta
     */
    public function test_get_movie_data_missing_meta() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie'
        ));

        $result = fww_get_movie_data($post_id);

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertEmpty($result['tmdb_id'], 'TMDB ID should be empty');
        $this->assertEmpty($result['year'], 'Year should be empty');
        $this->assertEmpty($result['film_id'], 'Film ID should be empty');

        wp_delete_post($post_id, true);
    }

    /**
     * Test fww_format_runtime with valid input
     */
    public function test_format_runtime_valid() {
        $this->assertEquals('2h 23m', fww_format_runtime(143), 'Should format 143 minutes correctly');
        $this->assertEquals('1h 30m', fww_format_runtime(90), 'Should format 90 minutes correctly');
        $this->assertEquals('2h 0m', fww_format_runtime(120), 'Should format 120 minutes correctly');
    }

    /**
     * Test fww_format_runtime with less than 1 hour
     */
    public function test_format_runtime_under_hour() {
        $this->assertEquals('45m', fww_format_runtime(45), 'Should format minutes only');
        $this->assertEquals('30m', fww_format_runtime(30), 'Should format 30 minutes');
        $this->assertEquals('1m', fww_format_runtime(1), 'Should format 1 minute');
    }

    /**
     * Test fww_format_runtime with zero
     */
    public function test_format_runtime_zero() {
        $this->assertEquals('', fww_format_runtime(0), 'Zero should return empty string');
    }

    /**
     * Test fww_format_runtime with empty value
     */
    public function test_format_runtime_empty() {
        $this->assertEquals('', fww_format_runtime(''), 'Empty string should return empty');
        $this->assertEquals('', fww_format_runtime(null), 'Null should return empty');
    }

    /**
     * Test fww_format_money with billions
     */
    public function test_format_money_billions() {
        $this->assertEquals('$2.8B', fww_format_money(2800000000), 'Should format billions correctly');
        $this->assertEquals('$1.0B', fww_format_money(1000000000), 'Should format 1 billion');
    }

    /**
     * Test fww_format_money with millions
     */
    public function test_format_money_millions() {
        $this->assertEquals('$150.0M', fww_format_money(150000000), 'Should format millions correctly');
        $this->assertEquals('$1.5M', fww_format_money(1500000), 'Should format 1.5 million');
    }

    /**
     * Test fww_format_money with thousands
     */
    public function test_format_money_thousands() {
        $this->assertEquals('$500.0K', fww_format_money(500000), 'Should format thousands correctly');
        $this->assertEquals('$1.5K', fww_format_money(1500), 'Should format 1.5 thousand');
    }

    /**
     * Test fww_format_money with small amounts
     */
    public function test_format_money_small() {
        $this->assertEquals('$500', fww_format_money(500), 'Should format small amounts');
        $this->assertEquals('$100', fww_format_money(100), 'Should format hundreds');
    }

    /**
     * Test fww_format_money with zero
     */
    public function test_format_money_zero() {
        $this->assertEquals('N/A', fww_format_money(0), 'Zero should return N/A');
    }

    /**
     * Test fww_format_money with empty value
     */
    public function test_format_money_empty() {
        $this->assertEquals('N/A', fww_format_money(''), 'Empty string should return N/A');
        $this->assertEquals('N/A', fww_format_money(null), 'Null should return N/A');
    }

    /**
     * Test fww_get_movie_poster with valid data
     */
    public function test_get_movie_poster_valid() {
        $tmdb_data = array(
            'poster_path' => '/test_poster.jpg',
            'title' => 'Test Movie'
        );

        $result = fww_get_movie_poster($tmdb_data, 'w342');

        $this->assertStringContainsString('<img', $result, 'Should return img tag');
        $this->assertStringContainsString('test_poster.jpg', $result, 'Should contain poster path');
        $this->assertStringContainsString('Test Movie', $result, 'Should contain movie title in alt');
        $this->assertStringContainsString('fww-movie-poster', $result, 'Should have correct CSS class');
    }

    /**
     * Test fww_get_movie_poster with missing poster
     */
    public function test_get_movie_poster_missing() {
        $tmdb_data = array(
            'title' => 'Test Movie'
        );

        $result = fww_get_movie_poster($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string when poster_path is missing');
    }

    /**
     * Test fww_get_movie_poster with empty poster path
     */
    public function test_get_movie_poster_empty_path() {
        $tmdb_data = array(
            'poster_path' => '',
            'title' => 'Test Movie'
        );

        $result = fww_get_movie_poster($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string when poster_path is empty');
    }

    /**
     * Test fww_get_movie_backdrop with valid data
     */
    public function test_get_movie_backdrop_valid() {
        $tmdb_data = array(
            'backdrop_path' => '/test_backdrop.jpg',
            'title' => 'Test Movie'
        );

        $result = fww_get_movie_backdrop($tmdb_data, 'w1280');

        $this->assertStringContainsString('<img', $result, 'Should return img tag');
        $this->assertStringContainsString('test_backdrop.jpg', $result, 'Should contain backdrop path');
        $this->assertStringContainsString('Test Movie', $result, 'Should contain movie title in alt');
        $this->assertStringContainsString('fww-movie-backdrop', $result, 'Should have correct CSS class');
    }

    /**
     * Test fww_get_movie_backdrop with missing backdrop
     */
    public function test_get_movie_backdrop_missing() {
        $tmdb_data = array(
            'title' => 'Test Movie'
        );

        $result = fww_get_movie_backdrop($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string when backdrop_path is missing');
    }

    /**
     * Test fww_get_movie_cast with empty tmdb_id
     */
    public function test_get_movie_cast_empty_id() {
        $result = fww_get_movie_cast('');

        $this->assertIsArray($result, 'Should return array');
        $this->assertEmpty($result, 'Should return empty array for empty ID');
    }

    /**
     * Test fww_get_movie_cast with null tmdb_id
     */
    public function test_get_movie_cast_null_id() {
        $result = fww_get_movie_cast(null);

        $this->assertIsArray($result, 'Should return array');
        $this->assertEmpty($result, 'Should return empty array for null ID');
    }

    /**
     * Test fww_get_movie_cast with zero tmdb_id
     */
    public function test_get_movie_cast_zero_id() {
        $result = fww_get_movie_cast(0);

        $this->assertIsArray($result, 'Should return array');
        $this->assertEmpty($result, 'Should return empty array for zero ID');
    }

    /**
     * Test fww_get_movie_watch_sightings with empty film_id
     */
    public function test_get_movie_watch_sightings_empty() {
        $result = fww_get_movie_watch_sightings('');

        $this->assertIsArray($result, 'Should return array');
        $this->assertEmpty($result, 'Should return empty array for empty ID');
    }

    /**
     * Test fww_get_movie_watch_sightings with null film_id
     */
    public function test_get_movie_watch_sightings_null() {
        $result = fww_get_movie_watch_sightings(null);

        $this->assertIsArray($result, 'Should return array');
        $this->assertEmpty($result, 'Should return empty array for null ID');
    }

    /**
     * Test fww_get_movie_watch_sightings with zero film_id
     */
    public function test_get_movie_watch_sightings_zero() {
        $result = fww_get_movie_watch_sightings(0);

        $this->assertIsArray($result, 'Should return array for zero ID');
        // Note: This might return results if there's a film with ID 0
    }

    /**
     * Test fww_download_and_set_poster with empty path
     */
    public function test_download_and_set_poster_empty_path() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
        ));

        $result = fww_download_and_set_poster($post_id, '', 'Test Movie');

        $this->assertFalse($result, 'Should return false for empty poster path');

        wp_delete_post($post_id, true);
    }

    /**
     * Test fww_download_and_set_poster with existing thumbnail
     */
    public function test_download_and_set_poster_existing_thumbnail() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
        ));

        // Create a dummy attachment and set as thumbnail
        $attachment_id = $this->factory->post->create(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg'
        ));
        set_post_thumbnail($post_id, $attachment_id);

        $result = fww_download_and_set_poster($post_id, '/test.jpg', 'Test Movie');

        $this->assertEquals($attachment_id, $result, 'Should return existing thumbnail ID');

        wp_delete_post($attachment_id, true);
        wp_delete_post($post_id, true);
    }

    /**
     * Test fww_get_directors with empty data
     */
    public function test_get_directors_empty() {
        $tmdb_data = array();

        $result = fww_get_directors($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string for empty data');
    }

    /**
     * Test fww_get_directors with no crew
     */
    public function test_get_directors_no_crew() {
        $tmdb_data = array(
            'credits' => array()
        );

        $result = fww_get_directors($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string when no crew');
    }

    /**
     * Test fww_get_directors with crew but no directors
     */
    public function test_get_directors_no_directors() {
        $tmdb_data = array(
            'credits' => array(
                'crew' => array(
                    array('job' => 'Producer', 'name' => 'Test Producer'),
                    array('job' => 'Writer', 'name' => 'Test Writer')
                )
            )
        );

        $result = fww_get_directors($tmdb_data);

        $this->assertEquals('', $result, 'Should return empty string when no directors in crew');
    }

    /**
     * Test fww_get_directors with single director
     */
    public function test_get_directors_single() {
        $tmdb_data = array(
            'credits' => array(
                'crew' => array(
                    array('job' => 'Director', 'name' => 'Test Director'),
                    array('job' => 'Producer', 'name' => 'Test Producer')
                )
            )
        );

        $result = fww_get_directors($tmdb_data);

        $this->assertEquals('Test Director', $result, 'Should return single director name');
    }

    /**
     * Test fww_get_directors with multiple directors
     */
    public function test_get_directors_multiple() {
        $tmdb_data = array(
            'credits' => array(
                'crew' => array(
                    array('job' => 'Director', 'name' => 'Director One'),
                    array('job' => 'Producer', 'name' => 'Test Producer'),
                    array('job' => 'Director', 'name' => 'Director Two')
                )
            )
        );

        $result = fww_get_directors($tmdb_data);

        $this->assertEquals('Director One, Director Two', $result, 'Should return comma-separated director names');
    }

    /**
     * Test edge case: Very large runtime
     */
    public function test_format_runtime_very_large() {
        $this->assertEquals('10h 0m', fww_format_runtime(600), 'Should handle 10 hours');
        $this->assertEquals('24h 30m', fww_format_runtime(1470), 'Should handle 24+ hours');
    }

    /**
     * Test edge case: Negative runtime (invalid input)
     */
    public function test_format_runtime_negative() {
        $this->assertEquals('', fww_format_runtime(-60), 'Negative runtime should return empty string');
    }

    /**
     * Test edge case: Very large money amount
     */
    public function test_format_money_very_large() {
        $this->assertEquals('$100.0B', fww_format_money(100000000000), 'Should handle 100 billion');
    }
}
