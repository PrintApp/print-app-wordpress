<?php

    namespace printapp\functions\front;

    function my_recent_order() {
			
        $user_id = get_current_user_id();
        if ($user_id === 0) return;

        // load the client-class script...
		$lang_code = substr(get_bloginfo('language'), 0, 2);
		if (!$lang_code) $lang_code = 'en';
		$run_url = PRINT_APP_RUN_BASE_URL . $printapp_domain_key . '/user_' . $user_id . '/wp?lang=' . $lang_code;
		wp_enqueue_script('print_app_class', $run_url);

        echo '<div id="print-app-user-projects" class="print-app-user-projects"></div>';
    }
    