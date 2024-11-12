<?php

    namespace printapp\functions\front;

    function add_cart_item_data($cart_item_data, $product_id, $variation_id, $qty) {
        if (!session_id()) session_start();
    
        $session_key = PRINT_APP_SESSION_PREFIX . $product_id;

        if (isset($_SESSION[$session_key])) {
            $cart_item_data[PRINT_APP_CUSTOMIZATION_KEY] = $_SESSION[$session_key];
            unset($_SESSION[$session_key]);
        }

        return $cart_item_data;
    }
    