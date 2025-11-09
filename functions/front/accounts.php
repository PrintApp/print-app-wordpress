<?php

    namespace printapp\functions\front;

    function my_recent_order() {
			
        $user_id = get_current_user_id();
        if ($user_id === 0) return;

        $printapp_domain_key = get_option('print_app_domain_key');
        $credentials = \printapp\functions\general\fetch_credentials();

        if (empty($printapp_domain_key) || empty($credentials['signature']) || empty($credentials['timestamp'])) return;

        // load the client-class script...
		$lang_code = substr(get_bloginfo('language'), 0, 2);
		if (!$lang_code) $lang_code = 'en';

		$run_url = PRINT_APP_RUN_BASE_URL . "user/{$printapp_domain_key}/{$user_id}/wp?signature={$credentials['signature']}&time={$credentials['timestamp']}&lang={$lang_code}";
		wp_enqueue_script('print_app_class', $run_url, '', '', true);
        
		wp_localize_script('print_app_class', 'printAppParams', array(
			'pluginRoot' 	=> site_url() . '/print_app',
			'cookieKey' 	=> PRINT_APP_CUSTOMIZATION_KEY,
			'wp_ajax_url' 	=> admin_url('admin-ajax.php'),
		));

        echo '<div id="print-app-user-projects" class="print-app-user-projects"></div>';
    }
