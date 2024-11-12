<?php

	namespace printapp\functions\front;

	function customize_button() {
		global $post;
		$printapp_domain_key = get_option('print_app_domain_key');

		// load the client-class script...
		$lang_code = substr(get_bloginfo('language'), 0, 2);
		if (!$lang_code) $lang_code = 'en';
		$run_url = PRINT_APP_RUN_BASE_URL . $printapp_domain_key . '/' . $post->ID . '/wp?lang=' . $lang_code;
		wp_enqueue_script('print_app_class', $run_url, '', '', true);
		
		// get user data
		$user_data = get_user_data();

		if (!session_id()) session_start();

		// get project data
		if (session_id()) {
			$session_key = PRINT_APP_SESSION_PREFIX . $post->ID;
			if (isset($_SESSION[$session_key])) $project_data = $_SESSION[$session_key];
		}

		// initialize the data to pass to print_app_class
		$pa_project_id 	= '';
		$pa_previews	= '';
		$pa_mode		= 'new-project';
		$pa_user_id		= (get_current_user_id() === 0) ? 'guest' : get_current_user_id();

		if (isset($project_data)) {
			$pa_project_id = $project_data['projectId'];
			$pa_mode		= isset($project_data['mode']) ? $project_data['mode'] : 'edit-project';
			$pa_previews	= isset($project_data['previews']) ? json_encode($project_data['previews']) : '';
		}
		
		wp_localize_script('print_app_class', 'printAppParams', array(
			'mode' 			=> $pa_mode,
			'langCode' 		=> $lang_code,
			'previews' 		=> $pa_previews,
			'projectId' 	=> $pa_project_id,
			'pluginRoot' 	=> site_url() . '/print_app',
			'product' 		=> array(
								'id' 	=> $post->ID,
								'name' 	=> $post->post_name
							),
			'userId' 		=> $pa_user_id,
			'launchData' 	=> $user_data,
			'wp_ajax_url' 	=> admin_url('admin-ajax.php'),
		));

		echo '<div id="pa-buttons"></div>';
	}

	function get_user_data() {
		if (!is_user_logged_in()) return 'null';

		$customer = WC()->customer;
		$current_user = wp_get_current_user();
		
		$fname = esc_js($customer->get_billing_first_name());
		$lname = esc_js($customer->get_billing_last_name());
		
		$address = $customer->get_billing_address_1() . "<br>";
		if ( !empty($customer->get_billing_address_2()) ) {
			$address .= $customer->get_billing_address_2() . "<br>";
		}
		$address .= $customer->get_billing_city() . " " . $customer->get_billing_postcode() . "<br>";
		if ( !empty($customer->get_billing_state()) ) {
			$address .= $customer->get_billing_state() . "<br>";
		}
		$address .= $customer->get_billing_country();
		$address = esc_js($address);
		
		return "{
			email: '" . esc_js($current_user->user_email) . "',
			name: '{$fname} {$lname}',
			firstname: '{$fname}',
			lastname: '{$lname}',
			phone: '" . esc_js($customer->get_billing_phone()) . "',
			address: '{$address}'.split('<br>').join('\\n')
		}";
	}