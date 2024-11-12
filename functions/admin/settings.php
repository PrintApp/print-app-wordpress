<?php

	namespace printapp\functions\admin;

	function print_app_create_settings() {
		echo '<p>' . __("You can generate your DomainKey and AuthKey from the <a target=\"_blank\" href=\"https://admin.print.app/domains\">Print.App Dashboard</a>", "printapp") . '</p>';
	}

	function settings_api_init() {
		add_settings_section('print_app_settings_section', 'PrintApp Settings', 'printapp\\functions\\admin\\print_app_create_settings', 'print_app');
		add_settings_field('print_app_domain_key', 'Domain Key', 'printapp\\functions\\admin\\print_app_domain_key', 'print_app', 'print_app_settings_section', array());
		add_settings_field('print_app_secret_key', 'Auth Key', 'printapp\\functions\\admin\\print_app_secret_key', 'print_app', 'print_app_settings_section', array());
		register_setting('print_app', 'print_app_domain_key');
		register_setting('print_app', 'print_app_secret_key');
	}

	// input for capturing the PrintApp Domain Key
	function print_app_domain_key() {
		echo  '<input class="regular-text" id="print_app_domain_key" name="print_app_domain_key" type="text" value="' . esc_html( get_option('print_app_domain_key') ) . '" />';
	}

	// input for capturing the PrintApp Auth Key
	function print_app_secret_key() {
		echo '<input class="regular-text" id="print_app_secret_key" name="print_app_secret_key" type="text" value="' . esc_html( get_option('print_app_secret_key') ) . '" />';
	}

	// creates the PrintApp settings link in admin
	function add_settings_link($links) {
		$settings_link = array(
			'<a href="/wp-admin/admin.php?page=printapp" rel="noopener">Settings</a>',
		);
		$actions = array_merge( $links, $settings_link );
		return $actions;
	}