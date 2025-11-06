<?php
/**
 * Content Filter Class
 * Filters post content to prepare it for text-to-speech conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabs_TTS_Content_Filter {

    /**
     * Filter post content for TTS
     *
     * @param string $content Post content
     * @return string Filtered content
     */
    public static function filter_content($content) {
        // Remove shortcodes
        $content = strip_shortcodes($content);

        // Remove HTML comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

        // Remove script and style tags with their content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // Remove code blocks (pre and code tags)
        $content = preg_replace('/<pre\b[^>]*>(.*?)<\/pre>/is', '', $content);
        $content = preg_replace('/<code\b[^>]*>(.*?)<\/code>/is', '', $content);

        // Remove figure captions
        $content = preg_replace('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', '', $content);

        // Remove figure tags but keep alt text from images
        $content = preg_replace('/<figure\b[^>]*>/i', '', $content);
        $content = preg_replace('/<\/figure>/i', '', $content);

        // Extract alt text from images and replace images with alt text
        $content = preg_replace_callback('/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', function($matches) {
            return !empty($matches[1]) ? $matches[1] . '. ' : '';
        }, $content);

        // Remove remaining images without alt text
        $content = preg_replace('/<img[^>]*>/i', '', $content);

        // Remove iframes (embedded videos, etc.)
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);

        // Remove tables (optional - you may want to keep table data)
        $content = preg_replace('/<table\b[^>]*>(.*?)<\/table>/is', '', $content);

        // Remove forms
        $content = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $content);

        // Remove buttons
        $content = preg_replace('/<button\b[^>]*>(.*?)<\/button>/is', '', $content);

        // Convert headings to plain text with a period for natural pauses
        $content = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', '$1. ', $content);

        // Convert list items to plain text with better formatting
        $content = preg_replace('/<li[^>]*>(.*?)<\/li>/is', '$1. ', $content);
        $content = preg_replace('/<[uo]l[^>]*>/i', '', $content);
        $content = preg_replace('/<\/[uo]l>/i', ' ', $content);

        // Convert line breaks to spaces
        $content = preg_replace('/<br\s*\/?>/i', ' ', $content);

        // Convert paragraphs to text with periods
        $content = preg_replace('/<p[^>]*>(.*?)<\/p>/is', '$1. ', $content);

        // Convert divs and spans to plain text
        $content = preg_replace('/<div[^>]*>/i', '', $content);
        $content = preg_replace('/<\/div>/i', ' ', $content);
        $content = preg_replace('/<span[^>]*>/i', '', $content);
        $content = preg_replace('/<\/span>/i', '', $content);

        // Convert links to text with context
        $content = preg_replace('/<a[^>]+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '$2', $content);

        // Remove any remaining HTML tags
        $content = strip_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove multiple periods
        $content = preg_replace('/\.{2,}/', '.', $content);

        // Remove spaces before punctuation
        $content = preg_replace('/\s+([.,!?;:])/', '$1', $content);

        // Trim
        $content = trim($content);

        // Apply character limit if needed (ElevenLabs has limits based on plan)
        // Default to 100,000 characters to be safe
        $max_chars = apply_filters('elevenlabs_tts_max_characters', 100000);
        if (strlen($content) > $max_chars) {
            $content = substr($content, 0, $max_chars);
            // Try to end at a sentence
            $last_period = strrpos($content, '.');
            if ($last_period !== false && $last_period > ($max_chars - 200)) {
                $content = substr($content, 0, $last_period + 1);
            }
        }

        return $content;
    }

    /**
     * Get post content for TTS
     *
     * @param int|WP_Post $post Post ID or object
     * @return string|WP_Error Filtered content or error
     */
    public static function get_post_content_for_tts($post) {
        $post = get_post($post);

        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post');
        }

        // Get post content
        $content = $post->post_content;

        // Apply WordPress content filters (but not 'the_content' to avoid recursion)
        $content = apply_filters('elevenlabs_tts_pre_filter_content', $content, $post);

        // Filter the content
        $content = self::filter_content($content);

        // Add title at the beginning
        $title = get_the_title($post);
        if (!empty($title)) {
            $content = $title . '. ' . $content;
        }

        // Allow other plugins to modify the content
        $content = apply_filters('elevenlabs_tts_filtered_content', $content, $post);

        return $content;
    }

    /**
     * Estimate audio duration
     *
     * @param string $text Text content
     * @return int Estimated duration in seconds
     */
    public static function estimate_duration($text) {
        // Average speaking rate is about 150 words per minute
        $word_count = str_word_count($text);
        $duration = ceil(($word_count / 150) * 60);

        return $duration;
    }

    /**
     * Count characters in text
     *
     * @param string $text Text content
     * @return int Character count
     */
    public static function count_characters($text) {
        return strlen($text);
    }
}
