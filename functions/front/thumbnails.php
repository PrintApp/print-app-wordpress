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

    // The same preview in the Cart and Checkout blocks, which take their images from the
    // Store API instead (WooCommerce 9.6+; older versions never call this filter).
    function store_api_cart_item_images($product_images, $cart_item, $cart_item_key) {
        $url = $cart_item[PRINT_APP_CUSTOMIZATION_KEY]['previews'][0]['url'] ?? '';
        if (empty($url)) return $product_images;

        return [(object) [
            'id'               => 0,
            'src'              => esc_url_raw($url),
            'thumbnail'        => esc_url_raw($url),
            'srcset'           => '',
            'sizes'            => '',
            'thumbnail_srcset' => '',
            'thumbnail_sizes'  => '',
            'name'             => 'PrintApp Preview',
            'alt'              => 'PrintApp Preview',
        ]];
    }