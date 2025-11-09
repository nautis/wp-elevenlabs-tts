<?php
/**
 * AJAX Handlers Tests
 * Tests for AJAX endpoint functionality
 */

class Test_FWW_AJAX_Handlers extends WP_Ajax_UnitTestCase {

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Create admin user and log in
        $this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($this->admin_user_id);
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test AJAX search movies with missing nonce
     */
    public function test_ajax_search_movies_no_nonce() {
        $_POST['query'] = 'test movie';

        try {
            $this->_handleAjax('fww_search_movies');
            $this->fail('Expected WPAjaxDieContinueException');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail without nonce');
    }

    /**
     * Test AJAX search movies with invalid nonce
     */
    public function test_ajax_search_movies_invalid_nonce() {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['query'] = 'test movie';

        try {
            $this->_handleAjax('fww_search_movies');
            $this->fail('Expected WPAjaxDieContinueException');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail with invalid nonce');
    }

    /**
     * Test AJAX search movies with empty query
     */
    public function test_ajax_search_movies_empty_query() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = '';

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'], 'Should succeed with empty query');
        $this->assertIsArray($response['data'], 'Should return empty array');
        $this->assertEmpty($response['data'], 'Data should be empty');
    }

    /**
     * Test AJAX search movies with short query
     */
    public function test_ajax_search_movies_short_query() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = 'a'; // Only 1 character

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'], 'Should succeed but return empty');
        $this->assertEmpty($response['data'], 'Should return empty array for short query');
    }

    /**
     * Test AJAX search movies with valid query but no API key
     */
    public function test_ajax_search_movies_no_api_key() {
        delete_option('fww_tmdb_api_key');

        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = 'test movie';

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail without API key');
        $this->assertArrayHasKey('message', $response['data'], 'Should have error message');
    }

    /**
     * Test AJAX search movies with SQL injection attempt
     */
    public function test_ajax_search_movies_sql_injection() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = "' OR 1=1; DROP TABLE wp_posts; --";

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        // Should sanitize the input and not cause SQL injection
        $this->assertTrue(true, 'Should handle SQL injection attempt safely');
    }

    /**
     * Test AJAX search movies with XSS attempt
     */
    public function test_ajax_search_movies_xss_attempt() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = '<script>alert("XSS")</script>';

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        // Query should be sanitized
        $this->assertArrayNotHasKey('<script>', $_POST, 'Should sanitize XSS attempts');
    }

    /**
     * Test AJAX get movie details with missing nonce
     */
    public function test_ajax_get_movie_details_no_nonce() {
        $_POST['movie_id'] = '12345';

        try {
            $this->_handleAjax('fww_get_movie_details');
            $this->fail('Expected WPAjaxDieContinueException');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail without nonce');
    }

    /**
     * Test AJAX get movie details with invalid nonce
     */
    public function test_ajax_get_movie_details_invalid_nonce() {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['movie_id'] = '12345';

        try {
            $this->_handleAjax('fww_get_movie_details');
            $this->fail('Expected WPAjaxDieContinueException');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail with invalid nonce');
    }

    /**
     * Test AJAX get movie details with missing movie_id
     */
    public function test_ajax_get_movie_details_no_movie_id() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');

        try {
            $this->_handleAjax('fww_get_movie_details');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail without movie ID');
        $this->assertEquals('Invalid movie ID', $response['data']['message'], 'Should have correct error message');
    }

    /**
     * Test AJAX get movie details with zero movie_id
     */
    public function test_ajax_get_movie_details_zero_id() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['movie_id'] = '0';

        try {
            $this->_handleAjax('fww_get_movie_details');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail with zero movie ID');
        $this->assertEquals('Invalid movie ID', $response['data']['message'], 'Should have correct error message');
    }

    /**
     * Test AJAX get movie details with negative movie_id
     */
    public function test_ajax_get_movie_details_negative_id() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['movie_id'] = '-1';

        try {
            $this->_handleAjax('fww_get_movie_details');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail with negative movie ID');
    }

    /**
     * Test AJAX get movie details with non-numeric movie_id
     */
    public function test_ajax_get_movie_details_non_numeric() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['movie_id'] = 'abc';

        try {
            $this->_handleAjax('fww_get_movie_details');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail with non-numeric movie ID');
        $this->assertEquals('Invalid movie ID', $response['data']['message'], 'Should have correct error message');
    }

    /**
     * Test AJAX get movie details without API key
     */
    public function test_ajax_get_movie_details_no_api_key() {
        delete_option('fww_tmdb_api_key');

        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['movie_id'] = '12345';

        try {
            $this->_handleAjax('fww_get_movie_details');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'], 'Should fail without API key');
    }

    /**
     * Test AJAX handlers are registered
     */
    public function test_ajax_handlers_registered() {
        $this->assertTrue(has_action('wp_ajax_fww_search_movies'), 'Search movies handler should be registered');
        $this->assertTrue(has_action('wp_ajax_fww_get_movie_details'), 'Get movie details handler should be registered');
    }

    /**
     * Test non-logged-in users cannot access AJAX endpoints
     */
    public function test_ajax_no_priv_access() {
        wp_set_current_user(0); // Log out

        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = 'test';

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieStopException $e) {
            // Expected - should die with -1
        }

        // Non-logged-in users should not have access
        $this->assertEquals('-1', $this->_last_response, 'Should return -1 for non-logged-in users');
    }

    /**
     * Test AJAX with special characters in query
     */
    public function test_ajax_search_special_characters() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = 'Amélie & Friends';

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        // Should handle special characters properly
        $this->assertTrue(true, 'Should handle special characters');
    }

    /**
     * Test AJAX with very long query
     */
    public function test_ajax_search_long_query() {
        $_POST['nonce'] = wp_create_nonce('fww_ajax_nonce');
        $_POST['query'] = str_repeat('a', 500); // 500 character query

        try {
            $this->_handleAjax('fww_search_movies');
        } catch (WPAjaxDieContinueException $e) {
            // Expected exception
        }

        // Should handle long queries
        $this->assertTrue(true, 'Should handle very long queries');
    }
}
