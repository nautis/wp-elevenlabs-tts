<?php
/**
 * Post Types Tests
 * Tests for custom post type registration
 */

class Test_FWW_Post_Types extends WP_UnitTestCase {

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Re-register post types to ensure they're available
        FWW_Post_Types::register_post_types();
    }

    /**
     * Test movie post type is registered
     */
    public function test_movie_post_type_registered() {
        $this->assertTrue(post_type_exists('fww_movie'), 'Movie post type should be registered');
    }

    /**
     * Test actor post type is registered
     */
    public function test_actor_post_type_registered() {
        $this->assertTrue(post_type_exists('fww_actor'), 'Actor post type should be registered');
    }

    /**
     * Test watch post type is registered
     */
    public function test_watch_post_type_registered() {
        $this->assertTrue(post_type_exists('fww_watch'), 'Watch post type should be registered');
    }

    /**
     * Test movie post type properties
     */
    public function test_movie_post_type_properties() {
        $post_type = get_post_type_object('fww_movie');

        $this->assertNotNull($post_type, 'Movie post type object should exist');
        $this->assertTrue($post_type->public, 'Movie post type should be public');
        $this->assertTrue($post_type->publicly_queryable, 'Movie post type should be publicly queryable');
        $this->assertTrue($post_type->show_ui, 'Movie post type should show UI');
        $this->assertTrue($post_type->show_in_menu, 'Movie post type should show in menu');
        $this->assertTrue($post_type->has_archive, 'Movie post type should have archive');
        $this->assertTrue($post_type->show_in_rest, 'Movie post type should show in REST API');
        $this->assertFalse($post_type->hierarchical, 'Movie post type should not be hierarchical');
    }

    /**
     * Test movie post type supports
     */
    public function test_movie_post_type_supports() {
        $supports = get_all_post_type_supports('fww_movie');

        $this->assertArrayHasKey('title', $supports, 'Movie should support title');
        $this->assertArrayHasKey('editor', $supports, 'Movie should support editor');
        $this->assertArrayHasKey('thumbnail', $supports, 'Movie should support thumbnail');
        $this->assertArrayHasKey('excerpt', $supports, 'Movie should support excerpt');
    }

    /**
     * Test movie post type slug
     */
    public function test_movie_post_type_slug() {
        $post_type = get_post_type_object('fww_movie');

        $this->assertEquals('movie', $post_type->rewrite['slug'], 'Movie post type slug should be "movie"');
    }

    /**
     * Test actor post type properties
     */
    public function test_actor_post_type_properties() {
        $post_type = get_post_type_object('fww_actor');

        $this->assertNotNull($post_type, 'Actor post type object should exist');
        $this->assertTrue($post_type->public, 'Actor post type should be public');
        $this->assertTrue($post_type->publicly_queryable, 'Actor post type should be publicly queryable');
        $this->assertTrue($post_type->show_ui, 'Actor post type should show UI');
        $this->assertTrue($post_type->show_in_menu, 'Actor post type should show in menu');
        $this->assertTrue($post_type->has_archive, 'Actor post type should have archive');
        $this->assertTrue($post_type->show_in_rest, 'Actor post type should show in REST API');
        $this->assertFalse($post_type->hierarchical, 'Actor post type should not be hierarchical');
    }

    /**
     * Test actor post type supports
     */
    public function test_actor_post_type_supports() {
        $supports = get_all_post_type_supports('fww_actor');

        $this->assertArrayHasKey('title', $supports, 'Actor should support title');
        $this->assertArrayHasKey('editor', $supports, 'Actor should support editor');
        $this->assertArrayHasKey('thumbnail', $supports, 'Actor should support thumbnail');
        $this->assertArrayNotHasKey('excerpt', $supports, 'Actor should not support excerpt');
    }

    /**
     * Test actor post type slug
     */
    public function test_actor_post_type_slug() {
        $post_type = get_post_type_object('fww_actor');

        $this->assertEquals('actor', $post_type->rewrite['slug'], 'Actor post type slug should be "actor"');
    }

    /**
     * Test watch post type properties
     */
    public function test_watch_post_type_properties() {
        $post_type = get_post_type_object('fww_watch');

        $this->assertNotNull($post_type, 'Watch post type object should exist');
        $this->assertTrue($post_type->public, 'Watch post type should be public');
        $this->assertTrue($post_type->publicly_queryable, 'Watch post type should be publicly queryable');
        $this->assertTrue($post_type->show_ui, 'Watch post type should show UI');
        $this->assertTrue($post_type->show_in_menu, 'Watch post type should show in menu');
        $this->assertTrue($post_type->has_archive, 'Watch post type should have archive');
        $this->assertTrue($post_type->show_in_rest, 'Watch post type should show in REST API');
        $this->assertFalse($post_type->hierarchical, 'Watch post type should not be hierarchical');
    }

    /**
     * Test watch post type supports
     */
    public function test_watch_post_type_supports() {
        $supports = get_all_post_type_supports('fww_watch');

        $this->assertArrayHasKey('title', $supports, 'Watch should support title');
        $this->assertArrayHasKey('editor', $supports, 'Watch should support editor');
        $this->assertArrayHasKey('thumbnail', $supports, 'Watch should support thumbnail');
        $this->assertArrayNotHasKey('excerpt', $supports, 'Watch should not support excerpt');
    }

    /**
     * Test watch post type slug
     */
    public function test_watch_post_type_slug() {
        $post_type = get_post_type_object('fww_watch');

        $this->assertEquals('watch', $post_type->rewrite['slug'], 'Watch post type slug should be "watch"');
    }

    /**
     * Test creating a movie post
     */
    public function test_create_movie_post() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie',
            'post_status' => 'publish'
        ));

        $this->assertGreaterThan(0, $post_id, 'Movie post should be created');

        $post = get_post($post_id);
        $this->assertEquals('fww_movie', $post->post_type, 'Post type should be fww_movie');
        $this->assertEquals('Test Movie', $post->post_title, 'Post title should match');

        wp_delete_post($post_id, true);
    }

    /**
     * Test creating an actor post
     */
    public function test_create_actor_post() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_actor',
            'post_title' => 'Test Actor',
            'post_status' => 'publish'
        ));

        $this->assertGreaterThan(0, $post_id, 'Actor post should be created');

        $post = get_post($post_id);
        $this->assertEquals('fww_actor', $post->post_type, 'Post type should be fww_actor');
        $this->assertEquals('Test Actor', $post->post_title, 'Post title should match');

        wp_delete_post($post_id, true);
    }

    /**
     * Test creating a watch post
     */
    public function test_create_watch_post() {
        $post_id = $this->factory->post->create(array(
            'post_type' => 'fww_watch',
            'post_title' => 'Test Watch',
            'post_status' => 'publish'
        ));

        $this->assertGreaterThan(0, $post_id, 'Watch post should be created');

        $post = get_post($post_id);
        $this->assertEquals('fww_watch', $post->post_type, 'Post type should be fww_watch');
        $this->assertEquals('Test Watch', $post->post_title, 'Post title should match');

        wp_delete_post($post_id, true);
    }

    /**
     * Test movie post type labels
     */
    public function test_movie_post_type_labels() {
        $post_type = get_post_type_object('fww_movie');

        $this->assertEquals('Movies', $post_type->labels->name, 'Movie plural label should be Movies');
        $this->assertEquals('Movie', $post_type->labels->singular_name, 'Movie singular label should be Movie');
        $this->assertEquals('Add New Movie', $post_type->labels->add_new_item, 'Add new label should be correct');
    }

    /**
     * Test actor post type labels
     */
    public function test_actor_post_type_labels() {
        $post_type = get_post_type_object('fww_actor');

        $this->assertEquals('Actors', $post_type->labels->name, 'Actor plural label should be Actors');
        $this->assertEquals('Actor', $post_type->labels->singular_name, 'Actor singular label should be Actor');
        $this->assertEquals('Add New Actor', $post_type->labels->add_new_item, 'Add new label should be correct');
    }

    /**
     * Test watch post type labels
     */
    public function test_watch_post_type_labels() {
        $post_type = get_post_type_object('fww_watch');

        $this->assertEquals('Watches', $post_type->labels->name, 'Watch plural label should be Watches');
        $this->assertEquals('Watch', $post_type->labels->singular_name, 'Watch singular label should be Watch');
        $this->assertEquals('Add New Watch', $post_type->labels->add_new_item, 'Add new label should be correct');
    }

    /**
     * Test movie post type menu icon
     */
    public function test_movie_menu_icon() {
        $post_type = get_post_type_object('fww_movie');

        $this->assertEquals('dashicons-video-alt3', $post_type->menu_icon, 'Movie should have correct menu icon');
    }

    /**
     * Test actor post type menu icon
     */
    public function test_actor_menu_icon() {
        $post_type = get_post_type_object('fww_actor');

        $this->assertEquals('dashicons-groups', $post_type->menu_icon, 'Actor should have correct menu icon');
    }

    /**
     * Test watch post type menu icon
     */
    public function test_watch_menu_icon() {
        $post_type = get_post_type_object('fww_watch');

        $this->assertEquals('dashicons-clock', $post_type->menu_icon, 'Watch should have correct menu icon');
    }

    /**
     * Test movie post type capability type
     */
    public function test_movie_capability_type() {
        $post_type = get_post_type_object('fww_movie');

        $this->assertEquals('post', $post_type->capability_type, 'Movie should use post capability type');
    }

    /**
     * Test querying movies
     */
    public function test_query_movies() {
        // Create test movies
        $movie1 = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie 1',
            'post_status' => 'publish'
        ));

        $movie2 = $this->factory->post->create(array(
            'post_type' => 'fww_movie',
            'post_title' => 'Test Movie 2',
            'post_status' => 'publish'
        ));

        // Query movies
        $query = new WP_Query(array(
            'post_type' => 'fww_movie',
            'posts_per_page' => -1
        ));

        $this->assertGreaterThanOrEqual(2, $query->post_count, 'Should find at least 2 movies');

        wp_delete_post($movie1, true);
        wp_delete_post($movie2, true);
    }

    /**
     * Test post type archive URLs
     */
    public function test_post_type_archive_urls() {
        $this->assertStringContainsString('movie', get_post_type_archive_link('fww_movie'), 'Movie archive URL should contain "movie"');
        $this->assertStringContainsString('actor', get_post_type_archive_link('fww_actor'), 'Actor archive URL should contain "actor"');
        $this->assertStringContainsString('watch', get_post_type_archive_link('fww_watch'), 'Watch archive URL should contain "watch"');
    }
}
