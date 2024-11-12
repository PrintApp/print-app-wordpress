<?php

    namespace printapp\functions\front;

    function cart_item_thumbnail($product_thumbnail, $cart_item_data, $cart_item_key) {
        if (!empty($cart_item_data[PRINT_APP_CUSTOMIZATION_KEY])) {
            $url = $cart_item_data[PRINT_APP_CUSTOMIZATION_KEY]['previews'][0]['url'];

            if (isset($url) && !empty($url))
                $product_thumbnail = '<img src="' . esc_url($url) . '" alt="PrintApp Preview" class="attachment-shop_thumbnail wp-post-image" />';
        }
        return $product_thumbnail;
    }