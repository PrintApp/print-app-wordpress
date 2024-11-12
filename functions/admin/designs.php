<?php

	namespace printapp\functions\admin;

	// show design selection form
	function assign_design_form() {
		global $post;
		$printapp_domain_key = get_option('print_app_domain_key');

		echo '<div id="print_app_tab" style="padding:1rem" class="panel woocommerce_options_panel hidden"></div>';

		wp_enqueue_script('print_app_design_tree', PRINT_APP_DESIGN_SELECT_JS);
		wp_localize_script('print_app_design_tree', 'pa_admin_values', array( 
			'domain_key' => $printapp_domain_key,
			'product_id' => $post->ID,
			'product_title' => $post->post_title,
		));
	}

	// add design selection tab
	function add_design_selection_tab($default_tabs) {
		$default_tabs['print_app_tab'] = array(
	        'label'   =>  __('PrintApp Design', 'domain'),
	        'target'  =>  'print_app_tab',
	        'priority' => 60,
	        'class'   => array()
	    );
	    return $default_tabs;
	}