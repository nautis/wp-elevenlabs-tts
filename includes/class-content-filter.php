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
        // Remove References section first (before other processing)
        $content = self::remove_references_section($content);

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

        // Apply watch-specific terminology fixes
        $content = self::apply_watch_terminology_fixes($content);

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

    /**
     * Remove References section from content
     * Removes everything after a heading containing "References"
     *
     * @param string $content HTML content
     * @return string Content without References section
     */
    private static function remove_references_section($content) {
        // Look for References heading (h2, h3, h4) and remove everything after it
        // This needs to be done before HTML is stripped
        $patterns = array(
            '/<h[2-4][^>]*>\s*References\s*<\/h[2-4]>.*$/is',  // Exact match
            '/<h[2-4][^>]*>[^<]*References[^<]*<\/h[2-4]>.*$/is'  // Contains "References"
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, '', $content);
                break;
            }
        }

        return $content;
    }

    /**
     * Apply watch-specific terminology fixes for better pronunciation
     *
     * @param string $content Plain text content (after HTML removal)
     * @return string Content with pronunciation fixes applied
     */
    private static function apply_watch_terminology_fixes($content) {
        // Define replacements array with patterns and their spoken equivalents
        $replacements = array(
            // Brand names
            '/\bPanerai\b/i' => 'pah-neh-RYE',

            // Common watch terms
            '/\bmare nostrum\b/i' => 'mah-ray nos-trum',
            '/\bRef\.\s*/i' => 'Reference ',
            '/\bETA\b/' => 'eeta',  // Like "Etta" James

            // Material codes
            '/\bAISI\s*316L\b/i' => 'A I S I three one six L',

            // PAM model numbers (e.g., PAM00716 → PAM oh oh seven one six)
            '/\bPAM(\d)(\d)(\d)(\d)(\d)\b/' => function($matches) {
                $digits = array('oh', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine');
                $spoken = 'PAM ';
                for ($i = 1; $i <= 5; $i++) {
                    $digit = (int)$matches[$i];
                    $spoken .= $digits[$digit] . ' ';
                }
                return trim($spoken);
            },

            // Measurements with mm (e.g., 42mm or 42 mm → forty-two millimeters)
            '/\b(\d+)\s*mm\b/i' => '$1 millimeters',

            // Roman numerals in caliber names (e.g., OP XXXIII → O P thirty-three)
            '/\b(OP|Calibre|Caliber)\s+([A-Z]{1,3})\s+(X{0,3})(IX|IV|V?I{0,3})\b/i' => function($matches) {
                $prefix = $matches[1];
                $letters = $matches[2];
                $roman = $matches[3] . $matches[4];

                // Convert letters to individual spoken letters
                $spoken_letters = implode(' ', str_split($letters));

                // Convert Roman numerals to number
                $roman_values = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
                                     'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
                                     'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
                $number = 0;
                $roman = strtoupper($roman);
                foreach ($roman_values as $key => $value) {
                    while (strpos($roman, $key) === 0) {
                        $number += $value;
                        $roman = substr($roman, strlen($key));
                    }
                }

                return $prefix . ' ' . $spoken_letters . ' ' . $number;
            }
        );

        // Apply all replacements
        foreach ($replacements as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $content = preg_replace_callback($pattern, $replacement, $content);
            } else {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

        return $content;
    }
}
