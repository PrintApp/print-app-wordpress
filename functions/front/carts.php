<?php

    namespace printapp\functions\front;

    use printapp\functions\general as General;

    function add_cart_item_data($cart_item_data, $product_id, $variation_id, $qty) {
    
        $value = General\get_customization_data($product_id);

        if (isset($value) && $value !== FALSE) {
            $cart_item_data[PRINT_APP_CUSTOMIZATION_KEY] = $value;
            
            // Don't delete during cart simulation or validation checks
            $is_simulation = isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'ppc-simulate-cart';
            
            if (!$is_simulation) {
                General\delete_customization_data($product_id);
            }
        }

        return $cart_item_data;
    }
    