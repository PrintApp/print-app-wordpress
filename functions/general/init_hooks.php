<?php

    namespace printapp\functions\general;

    function init_hooks() {

        if (\printapp\functions\general\request_type('admin')) {
            
            // creates the PrintApp link and page in admin
            add_action('admin_menu', 'printapp\\functions\\admin\\actions');

            // creates the settings fields in the PrintApp page
            add_action('admin_init', 'printapp\\functions\\admin\\settings_api_init');

            // adds the link to the plugin listing page
            add_filter('plugin_action_links_printapp/printapp.php',  'printapp\\functions\\admin\\add_settings_link');

            // add a tab to the product data metabox
            add_filter('woocommerce_product_data_tabs', 'printapp\\functions\\admin\\add_design_selection_tab', 10, 1);
            
            // add the design selection form to the product data metabox
			add_action('woocommerce_product_data_panels', 'printapp\\functions\\admin\\assign_design_form', 10, 1);

            // rename the customization key to PrintApp Customization
			add_filter('woocommerce_order_item_display_meta_key', 'printapp\\functions\\admin\\format_print_app_order_key', 20, 3);
			
            // extract and inject the customization data into the order item
            add_filter('woocommerce_order_item_get_formatted_meta_data', 'printapp\\functions\\admin\\format_print_app_order_value', 20, 2);

            // for backward compatibility
			add_filter('woocommerce_order_item_display_meta_value', 'printapp\\functions\\admin\\print_app_filter_wc_order_item_display_meta_value', 20, 2);

        } else if (\printapp\functions\general\request_type('frontend')) {

            add_action('woocommerce_before_add_to_cart_button', 'printapp\\functions\\front\\customize_button');

            // add the product customization data to the cart item
            add_filter('woocommerce_add_cart_item_data', 'printapp\\functions\\front\\add_cart_item_data', 10, 4);

            // change the cart thumbnail to the customized image
            add_filter('woocommerce_cart_item_thumbnail', 'printapp\\functions\\front\\cart_item_thumbnail', 10, 3);

            // add the customization data to the order item
            add_filter('woocommerce_checkout_create_order_line_item', 'printapp\\functions\\front\\add_order_item_meta', 70, 2);
            
        }
        
        // save project for both authenticated and guest users
        add_action('wp_ajax_nopriv_print_app_save_project', 'printapp\\functions\\front\\save_project_sess');
        add_action('wp_ajax_print_app_save_project', 'printapp\\functions\\front\\save_project_sess');
        add_action('wp_ajax_nopriv_print_app_reset_project', 'printapp\\functions\\front\\reset_project_sess');
        add_action('wp_ajax_print_app_reset_project', 'printapp\\functions\\front\\reset_project_sess');

        // add the customization info to the order email
        add_action('woocommerce_email_order_details', 'printapp\\functions\\general\\order_email', 10, 4);
    }
    
        