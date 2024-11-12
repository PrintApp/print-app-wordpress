<?php

    namespace printapp\functions\front;

    function add_order_item_meta($order_item, $cart_item_key) {
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

        if (empty($cart_item) || empty($cart_item[PRINT_APP_CUSTOMIZATION_KEY])) return;

        $order_item->add_meta_data(PRINT_APP_CUSTOMIZATION_KEY, $cart_item[PRINT_APP_CUSTOMIZATION_KEY], true);

        $url = $cart_item[PRINT_APP_CUSTOMIZATION_KEY]['previews'][0]['url'];
        if (empty($url)) return;
        
        $preview = '<img class="pa-preview-image" style="width:120px; margin-left: 5px; margin-right:5px" src="' . $url . '">';
        $order_item->add_meta_data(PRINT_APP_CUSTOMIZATION_PREVIEWS_KEY, $preview, true);
		
	}