<?php

    namespace printapp\functions\general;

    function set_cookie() {
        if (!isset($_COOKIE[PRINT_APP_CUSTOMIZATION_KEY])) {
            $token = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie(PRINT_APP_CUSTOMIZATION_KEY, $token, time() + PRINT_APP_CUSTOMIZATION_DURATION, '/');
            }
        }
    }

    function get_user_token() {
        if (isset($_COOKIE[PRINT_APP_CUSTOMIZATION_KEY]))
            return $_COOKIE[PRINT_APP_CUSTOMIZATION_KEY];
    
        // Generate a random token for the user (guest or signed-in)
        $token = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie(PRINT_APP_CUSTOMIZATION_KEY, $token, time() + PRINT_APP_CUSTOMIZATION_DURATION, '/');
        }
        return $token;
    }

    // Sanitize and validate inputs for better security
    function save_customization_data($product_id, $customization_data) {
        $product_id = absint($product_id); // Ensure product_id is an integer
        $customization_data = wp_unslash($customization_data); // Remove slashes from input

        $user_token = get_user_token();
        $transient_key = 'print_app_' . $user_token . '_' . $product_id;
    
        delete_transient($transient_key);
        $result = set_transient($transient_key, $customization_data, PRINT_APP_CUSTOMIZATION_DURATION);
        return $result !== FALSE ? $transient_key : FALSE;
    }

    function get_customization_data($product_id) {
        $user_token = get_user_token();
        $transient_key = 'print_app_' . $user_token . '_' . $product_id;
    
        return get_transient($transient_key);
    }

    function delete_customization_data($product_id) {
        $user_token = get_user_token();
        $transient_key = 'print_app_' . $user_token . '_' . $product_id;
    
        delete_transient($transient_key);
        return TRUE;
    }
