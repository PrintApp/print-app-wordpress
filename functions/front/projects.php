<?php

    namespace printapp\functions\front;

    use printapp\functions\general as General;

    function save_project_sess() {

        if (!isset($_POST['value']) || empty($_POST['value'])) {
            wp_send_json_error('No customization data provided');
        }

        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('No product ID provided');
        }
		
        $value = json_decode(stripslashes(html_entity_decode($_POST['value'])), true);
        if (json_last_error() !== JSON_ERROR_NONE) wp_send_json_error(json_last_error());

		$product_id	= $_POST['product_id'];
        $result = General\save_customization_data($product_id, $value);
		if ($result !== FALSE)
            return wp_send_json_success('customization data saved successfully: ' . $result);

        wp_send_json_error('Failed to save customization data');
    }

    function reset_project_sess() {
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('No product ID provided.');
        }
    
        $product_id = $_POST['product_id'];
    
        General\delete_customization_data($product_id);
        wp_send_json_success('Customization data cleared successfully.');
        
    }
