<?php
/**
 * AI-Powered Natural Language Parser
 * Falls back to Claude API when regex parsing fails or has low confidence
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Claude API key from constant or option
 *
 * SECURITY BEST PRACTICE: For production environments, store API keys in wp-config.php:
 * define('FWD_CLAUDE_API_KEY', 'sk-ant-api03-...');
 *
 * This prevents keys from being stored in the database where they could be:
 * - Exposed in database backups
 * - Accessed by SQL injection vulnerabilities
 * - Visible to users with database access
 *
 * @return string|false API key or false if not configured
 */
function fwd_get_claude_api_key() {
    // Prefer constant (more secure)
    if (defined('FWD_CLAUDE_API_KEY') && !empty(FWD_CLAUDE_API_KEY)) {
        return FWD_CLAUDE_API_KEY;
    }

    // Fall back to option (less secure, but more user-friendly)
    return get_option('fwd_claude_api_key');
}

/**
 * Parse entry using AI (Claude API)
 *
 * @param string $text Natural language entry
 * @return array Parsed data with keys: actor, character, brand, model, title, year, confidence
 * @throws Exception If API call fails
 */
function fwd_parse_with_ai($text) {
    // Security: Check user capability before allowing AI parsing
    if (!current_user_can('edit_posts')) {
        throw new Exception('Unauthorized: You must have edit_posts capability to use AI parsing.');
    }

    $api_key = fwd_get_claude_api_key();

    if (empty($api_key)) {
        throw new Exception('Claude API key not configured. Please add it in Settings or define FWD_CLAUDE_API_KEY in wp-config.php.');
    }

    // Rate limiting: max 10 calls per minute per user/IP
    $user_id = get_current_user_id();

    // Security: Validate and sanitize IP address
    $ip_address = 'unknown';
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $raw_ip = $_SERVER['REMOTE_ADDR'];
        // Validate IP format (IPv4 or IPv6)
        if (filter_var($raw_ip, FILTER_VALIDATE_IP)) {
            $ip_address = $raw_ip;
        } else {
            // Log without raw input to prevent log injection
            error_log('FWD Security: Invalid IP address format in REMOTE_ADDR (length: ' . strlen($raw_ip) . ')');
        }
    }

    $rate_key = 'fwd_ai_rate_limit_' . ($user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($ip_address));

    $calls = (int) get_transient($rate_key);
    $max_calls = 10; // Max calls per minute

    if ($calls >= $max_calls) {
        throw new Exception('AI parsing rate limit exceeded. Please wait a minute before trying again.');
    }

    set_transient($rate_key, $calls + 1, MINUTE_IN_SECONDS);

    // Get brand list for context
    $brands = get_transient('fwd_brands_list');
    if (false === $brands) {
        global $wpdb;
        $db = fwd_db();
        $table_brands = $wpdb->prefix . 'fwd_brands';
        $brands = $wpdb->get_col("SELECT brand_name FROM {$table_brands} ORDER BY LENGTH(brand_name) DESC LIMIT 100");
        set_transient('fwd_brands_list', $brands, DAY_IN_SECONDS);
    }

    $brands_context = !empty($brands) ? "\n\nKnown watch brands (prioritize these): " . implode(', ', array_slice($brands, 0, 50)) : '';

    $prompt = "You are a film watch database parser. Extract structured data from this text about a watch worn in a movie:

\"{$text}\"
{$brands_context}

Return ONLY valid JSON (no markdown, no explanation) in this exact format:
{
    \"actor\": \"actor's full name\",
    \"character\": \"character name (if mentioned, otherwise use actor's last name)\",
    \"brand\": \"watch brand name\",
    \"model\": \"watch model/reference\",
    \"title\": \"film title\",
    \"year\": 1922,
    \"confidence\": 0.95
}

Rules:
- Split \"Actor as Character\" into separate fields
- Use known brands from the list when possible
- Extract year as integer
- Set confidence 0.9-1.0 if certain, 0.7-0.9 if likely, 0.5-0.7 if guessing
- If character not mentioned, use actor's last name as character";

    $body = array(
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 500,
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        )
    );

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ),
        'body' => json_encode($body),
        'timeout' => 30,
        'sslverify' => true
    ));

    if (is_wp_error($response)) {
        throw new Exception('API request failed: ' . $response->get_error_message());
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        $response_body = wp_remote_retrieve_body($response);
        error_log('Claude API error (HTTP ' . $status_code . '): ' . $response_body);

        // Try to parse error message from response
        $error_data = json_decode($response_body, true);
        $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : $response_body;

        throw new Exception('API returned error ' . $status_code . ': ' . $error_message);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['content'][0]['text'])) {
        fwd_update_ai_stats(false, 0);
        throw new Exception('Unexpected API response format');
    }

    $json_text = $body['content'][0]['text'];

    // Track API usage
    $tokens_used = ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0);
    fwd_update_ai_stats(true, $tokens_used);

    // Clean up response (remove markdown code blocks if present)
    $json_text = preg_replace('/^```json\s*/i', '', $json_text);
    $json_text = preg_replace('/\s*```$/i', '', $json_text);

    $parsed = json_decode($json_text, true);

    if (!$parsed) {
        throw new Exception('Failed to parse AI response as JSON');
    }

    // Validate required fields
    $required = array('actor', 'character', 'brand', 'model', 'title', 'year');
    foreach ($required as $field) {
        if (!isset($parsed[$field])) {
            throw new Exception("AI response missing required field: {$field}");
        }
    }

    // Add metadata
    $parsed['parsed_by'] = 'ai';
    $parsed['narrative'] = $parsed['narrative'] ?? 'Watch worn in film.';
    $parsed['image_url'] = $parsed['image_url'] ?? '';

    return $parsed;
}

/**
 * Calculate confidence score for regex-parsed entry
 *
 * @param array $parsed Parsed data from regex
 * @return float Confidence score 0.0 to 1.0
 */
function fwd_calculate_parse_confidence($parsed) {
    $confidence = 1.0;

    // Check for common issues

    // 1. Actor name looks suspicious
    if (preg_match('/\bas\b|\bplays\b/i', $parsed['actor'])) {
        $confidence -= 0.3; // Still has "as" in actor name
    }

    // 1b. Character name has "as" or "plays" (indicates parsing issue with multiple "as")
    if (preg_match('/\bas\b|\bplays\b/i', $parsed['character'])) {
        $confidence -= 0.4; // Likely multiple "as" in original input
    }

    // 2. Character is just last name (default fallback)
    $actor_parts = explode(' ', $parsed['actor']);
    if (count($actor_parts) > 1 && $parsed['character'] === end($actor_parts)) {
        $confidence -= 0.1; // Used default character
    }

    // 3. Brand not in known brands list
    $brands = get_transient('fwd_brands_list');
    if ($brands && !in_array($parsed['brand'], $brands)) {
        $confidence -= 0.2; // Unknown brand
    }

    // 4. Model looks suspicious (too short, too long, contains brand name)
    $model_len = strlen($parsed['model']);
    if ($model_len < 2 || $model_len > 100) {
        $confidence -= 0.2;
    }

    // 5. Year is unrealistic for films (1888 = first motion picture)
    // Allow only 1 year in the future for announced films
    if ($parsed['year'] < 1888 || $parsed['year'] > date('Y') + 1) {
        $confidence -= 0.4; // Increased penalty for unrealistic years
    }

    return max(0.0, min(1.0, $confidence));
}

/**
 * Smart parser with AI fallback
 *
 * @param string $text Natural language entry
 * @return array Parsed data with 'method' field indicating parser used
 */
function fwd_smart_parse($text) {
    $db = fwd_db();

    // Try regex first
    try {
        $parsed = $db->parse_entry($text);
        $confidence = fwd_calculate_parse_confidence($parsed);

        $parsed['confidence'] = $confidence;
        $parsed['parsed_by'] = 'regex';

        // If high confidence, use regex result
        $ai_threshold = floatval(get_option('fwd_ai_confidence_threshold', 0.7));

        if ($confidence >= $ai_threshold) {
            return $parsed;
        }

        // Low confidence - try AI if available
        $api_key = fwd_get_claude_api_key();
        if (empty($api_key)) {
            // No AI available, return regex result with warning
            $parsed['warning'] = "Parse confidence is low ({$confidence}). Consider using AI parser or Quick Entry format.";
            return $parsed;
        }

        // Try AI parser
        try {
            $ai_parsed = fwd_parse_with_ai($text);

            // Compare AI confidence to regex confidence
            $ai_confidence = $ai_parsed['confidence'] ?? 0.8;

            if ($ai_confidence > $confidence) {
                return $ai_parsed; // AI is more confident
            } else {
                return $parsed; // Stick with regex
            }

        } catch (Exception $e) {
            // AI failed, return regex result
            error_log('AI parser fallback failed: ' . $e->getMessage());
            $parsed['warning'] = 'AI parser unavailable. Using regex parse.';
            return $parsed;
        }

    } catch (Exception $e) {
        // Regex completely failed - try AI as last resort
        $api_key = fwd_get_claude_api_key();
        if (empty($api_key)) {
            throw new Exception('Could not parse entry. Try using Quick Entry format (pipe-delimited).');
        }

        try {
            return fwd_parse_with_ai($text);
        } catch (Exception $ai_error) {
            throw new Exception('Both regex and AI parsing failed: ' . $ai_error->getMessage());
        }
    }
}

/**
 * AJAX handler for testing AI parser
 */
function fwd_ajax_test_ai_parser() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $test_text = sanitize_text_field(wp_unslash($_POST['test_text']));

    try {
        $result = fwd_smart_parse($test_text);
        wp_send_json_success($result);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_fwd_test_ai_parser', 'fwd_ajax_test_ai_parser');

/**
 * Get API usage statistics
 */
function fwd_get_ai_stats() {
    $stats = get_option('fwd_ai_stats', array(
        'total_calls' => 0,
        'successful_calls' => 0,
        'failed_calls' => 0,
        'last_call' => null,
        'estimated_cost' => 0.0
    ));

    return $stats;
}

/**
 * Update API usage statistics
 */
function fwd_update_ai_stats($success = true, $tokens_used = 500) {
    $stats = fwd_get_ai_stats();

    $stats['total_calls']++;
    if ($success) {
        $stats['successful_calls']++;
    } else {
        $stats['failed_calls']++;
    }
    $stats['last_call'] = current_time('mysql');

    // Claude pricing: ~$3 per million input tokens, ~$15 per million output tokens
    // Average: ~$0.01 per 1000 tokens
    $stats['estimated_cost'] += ($tokens_used / 1000) * 0.01;

    update_option('fwd_ai_stats', $stats);
}
