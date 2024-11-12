<?php

    namespace printapp\functions\front;

    function save_project_sess() {

        if (!isset($_POST['value']) || empty($_POST['value'])) {
            wp_send_json_error('No customization data provided');
        }

        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('No product ID provided');
        }

        if (!session_id()) {
            session_start();
            if (!session_id()) wp_send_json_error('Failed to start session.');
        }
		
        $value = json_decode(stripslashes(html_entity_decode($_POST['value'])), true);
        if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error(json_last_error());

		$product_id	= intval($_POST['product_id']);
        $session_key = PRINT_APP_SESSION_PREFIX . $product_id;

		$_SESSION[$session_key] = $value;
		wp_send_json_success('Customization data saved successfully');
        
    }

    function reset_project_sess() {
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('No product ID provided.');
        }
    
        if (!session_id()) {
            session_start();
            if (!session_id()) wp_send_json_error('Failed to start session');
        }
    
        // Sanitize product_id
        $product_id = intval($_POST['product_id']);
        $session_key = PRINT_APP_SESSION_PREFIX . $product_id;
    
        // Check if session key exists and unset it
        if (isset($_SESSION[$session_key])) {
            unset($_SESSION[$session_key]);
            wp_send_json_success('Customization data cleared successfully.');
        } else {
            wp_send_json_error('No customization data found for this product');
        }
    }
