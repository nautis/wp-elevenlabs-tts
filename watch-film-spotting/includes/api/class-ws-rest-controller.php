<?php
/**
 * Base REST controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class WS_REST_Controller {
    
    const NAMESPACE = 'watchspotting/v1';
    
    /**
     * Check if user is logged in
     */
    public static function check_logged_in($request) {
        return is_user_logged_in();
    }
    
    /**
     * Check if user can moderate
     */
    public static function check_can_moderate($request) {
        return current_user_can('moderate_comments');
    }
    
    /**
     * Standard success response
     */
    public static function success($data = null, $message = 'Success') {
        return new WP_REST_Response([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }
    
    /**
     * Standard error response
     */
    public static function error($message, $code = 400, $data = null) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
    
    /**
     * Convert WP_Error to response
     */
    public static function wp_error_response($wp_error) {
        $code = 400;
        $error_code = $wp_error->get_error_code();
        
        if ($error_code === 'unauthorized') {
            $code = 401;
        } elseif ($error_code === 'not_found') {
            $code = 404;
        } elseif ($error_code === 'forbidden') {
            $code = 403;
        }
        
        return self::error($wp_error->get_error_message(), $code);
    }
}
