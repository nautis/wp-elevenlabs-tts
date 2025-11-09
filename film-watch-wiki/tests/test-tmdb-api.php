<?php
/**
 * TMDB API Tests
 * Tests for the FWW_TMDB_API class
 */

class Test_FWW_TMDB_API extends WP_UnitTestCase {

    /**
     * Test API key retrieval
     */
    public function test_get_api_key_empty() {
        delete_option('fww_tmdb_api_key');

        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('get_api_key');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertEmpty($result, 'API key should be empty when not set');
    }

    /**
     * Test API key retrieval with value
     */
    public function test_get_api_key_with_value() {
        $test_key = 'test_api_key_12345';
        update_option('fww_tmdb_api_key', $test_key);

        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('get_api_key');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertEquals($test_key, $result, 'API key should match the stored value');

        delete_option('fww_tmdb_api_key');
    }

    /**
     * Test language default value
     */
    public function test_get_language_default() {
        delete_option('fww_tmdb_language');

        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('get_language');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertEquals('en-US', $result, 'Default language should be en-US');
    }

    /**
     * Test cache duration default
     */
    public function test_get_cache_duration_default() {
        delete_option('fww_cache_duration');

        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('get_cache_duration');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertEquals(86400, $result, 'Default cache duration should be 86400 seconds');
    }

    /**
     * Test cache duration with custom value
     */
    public function test_get_cache_duration_custom() {
        $custom_duration = 3600;
        update_option('fww_cache_duration', $custom_duration);

        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('get_cache_duration');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertEquals($custom_duration, $result, 'Cache duration should match custom value');

        delete_option('fww_cache_duration');
    }

    /**
     * Test search movies with empty query
     */
    public function test_search_movies_empty_query() {
        $result = FWW_TMDB_API::search_movies('');

        $this->assertWPError($result, 'Empty query should return WP_Error');
        $this->assertEquals('empty_query', $result->get_error_code(), 'Error code should be empty_query');
    }

    /**
     * Test search movies without API key
     */
    public function test_search_movies_no_api_key() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::search_movies('test movie');

        $this->assertWPError($result, 'Missing API key should return WP_Error');
        $this->assertEquals('no_api_key', $result->get_error_code(), 'Error code should be no_api_key');
    }

    /**
     * Test search people with empty query
     */
    public function test_search_people_empty_query() {
        $result = FWW_TMDB_API::search_people('');

        $this->assertWPError($result, 'Empty query should return WP_Error');
        $this->assertEquals('empty_query', $result->get_error_code(), 'Error code should be empty_query');
    }

    /**
     * Test search people without API key
     */
    public function test_search_people_no_api_key() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::search_people('test actor');

        $this->assertWPError($result, 'Missing API key should return WP_Error');
        $this->assertEquals('no_api_key', $result->get_error_code(), 'Error code should be no_api_key');
    }

    /**
     * Test get_image_url with valid path
     */
    public function test_get_image_url_valid() {
        $path = '/test_image.jpg';
        $size = 'w500';

        $result = FWW_TMDB_API::get_image_url($path, $size);

        $expected = 'https://image.tmdb.org/t/p/w500/test_image.jpg';
        $this->assertEquals($expected, $result, 'Image URL should be correctly formatted');
    }

    /**
     * Test get_image_url with empty path
     */
    public function test_get_image_url_empty_path() {
        $result = FWW_TMDB_API::get_image_url('', 'w500');

        $this->assertNull($result, 'Empty path should return null');
    }

    /**
     * Test get_image_url with null path
     */
    public function test_get_image_url_null_path() {
        $result = FWW_TMDB_API::get_image_url(null, 'w500');

        $this->assertNull($result, 'Null path should return null');
    }

    /**
     * Test get_image_url with original size
     */
    public function test_get_image_url_original_size() {
        $path = '/test_image.jpg';

        $result = FWW_TMDB_API::get_image_url($path, 'original');

        $expected = 'https://image.tmdb.org/t/p/original/test_image.jpg';
        $this->assertEquals($expected, $result, 'Original size should work correctly');
    }

    /**
     * Test get_movie with invalid ID
     */
    public function test_get_movie_invalid_id() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::get_movie(0);

        $this->assertWPError($result, 'Invalid movie ID should return error');
    }

    /**
     * Test get_movie with negative ID
     */
    public function test_get_movie_negative_id() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::get_movie(-1);

        $this->assertWPError($result, 'Negative movie ID should return error');
    }

    /**
     * Test get_person with invalid ID
     */
    public function test_get_person_invalid_id() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::get_person(0);

        $this->assertWPError($result, 'Invalid person ID should return error');
    }

    /**
     * Test clear_cache functionality
     */
    public function test_clear_cache() {
        // Set a test transient
        set_transient('fww_tmdb_test', 'test_value', 3600);

        $result = FWW_TMDB_API::clear_cache();

        $this->assertTrue($result, 'clear_cache should return true');

        // Check if transient was cleared
        $transient = get_transient('fww_tmdb_test');
        $this->assertFalse($transient, 'Test transient should be cleared');
    }

    /**
     * Test format_known_for with empty array
     */
    public function test_format_known_for_empty() {
        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('format_known_for');
        $method->setAccessible(true);

        $result = $method->invoke(null, array());

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertEmpty($result, 'Result should be empty for empty input');
    }

    /**
     * Test format_known_for with movie data
     */
    public function test_format_known_for_with_movies() {
        $reflection = new ReflectionClass('FWW_TMDB_API');
        $method = $reflection->getMethod('format_known_for');
        $method->setAccessible(true);

        $input = array(
            array(
                'media_type' => 'movie',
                'title' => 'Test Movie',
                'release_date' => '2023-01-15'
            ),
            array(
                'media_type' => 'tv',
                'name' => 'Test TV Show'
            )
        );

        $result = $method->invoke(null, $input);

        $this->assertCount(1, $result, 'Should only return movie entries');
        $this->assertEquals('Test Movie', $result[0]['title'], 'Movie title should match');
        $this->assertEquals('2023', $result[0]['year'], 'Year should be extracted correctly');
    }

    /**
     * Test get_us_certification with invalid movie ID
     */
    public function test_get_us_certification_no_api_key() {
        delete_option('fww_tmdb_api_key');

        $result = FWW_TMDB_API::get_us_certification(12345);

        $this->assertEquals('', $result, 'Should return empty string when API call fails');
    }
}
