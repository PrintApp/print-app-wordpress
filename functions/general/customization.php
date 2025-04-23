<?php

    namespace printapp\functions\general;

    function get_user_token() {
        if (isset($_COOKIE[PRINT_APP_CUSTOMIZATION_KEY]))
            return $_COOKIE[PRINT_APP_CUSTOMIZATION_KEY];
    
        // Generate a random token for the user (guest or signed-in)
        if (!headers_sent()) {
            $token = bin2hex(random_bytes(16));
            $cookie_set = setcookie(PRINT_APP_CUSTOMIZATION_KEY, $token, time() + PRINT_APP_CUSTOMIZATION_DURATION, '/');
            
            if (!$cookie_set) {
                // Handle error if cookie cannot be set
                error_log('[PRINTAPP] Failed to set cookie: ' . PRINT_APP_CUSTOMIZATION_KEY);
            } else {
                return $token;
            }
        }
    }

    // Sanitize and validate inputs for better security
    function save_customization_data($product_id, $customization_data) {
        $product_id = absint($product_id); // Ensure product_id is an integer
        $customization_data = wp_unslash($customization_data); // Remove slashes from input

        $user_token = get_user_token();
        if (!is_string($user_token) || empty($user_token)) {
            return false; // Invalid token
        }
        $key = 'print_app_' . $user_token . '_' . $product_id;

        // Try saving to transient first
        delete_transient($key);
        $transient_result = set_transient($key, $customization_data, PRINT_APP_CUSTOMIZATION_KEY);

        // Also save to session as a backup
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$key] = $customization_data;

        // Return the key if transient save was successful, otherwise FALSE
        return $transient_result !== FALSE ? $key : FALSE;
    }

    function get_customization_data($product_id) {
        $user_token = get_user_token();
        if (!is_string($user_token) || empty($user_token)) {
            return false; // Invalid token
        }
        $key = 'print_app_' . $user_token . '_' . $product_id;

        // Try getting from transient first
        $data = get_transient($key);

        if ($data !== false) {
            return $data;
        }

        // If transient failed or expired, try getting from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[$key])) {
            // Optionally, restore the transient if found in session
            return $_SESSION[$key];
        }

        return false; // Return false if not found in either
    }

    function delete_customization_data($product_id) {
        $user_token = get_user_token();
        if (!is_string($user_token) || empty($user_token)) {
            return false; // Invalid token
        }
        $key = 'print_app_' . $user_token . '_' . $product_id;

        // Delete from transient
        delete_transient($key);

        // Delete from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }

        return TRUE;
    }
