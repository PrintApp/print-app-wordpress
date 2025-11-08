<?php

	namespace printapp\functions\admin;

	function print_app_create_settings() {
		echo '<p>' . __("You can generate your DomainKey and AuthKey from the <a target=\"_blank\" href=\"https://admin.print.app/domains\">Print.App Dashboard</a>", "printapp") . '</p>';
	}

	function settings_api_init() {
		add_settings_section(
			'print_app_settings_section',
			__('PrintApp Settings', 'printapp'),
			'printapp\\functions\\admin\\print_app_create_settings',
			'print_app'
		);
		add_settings_field(
			'print_app_domain_key',
			__('Domain Key:', 'printapp'),
			'printapp\\functions\\admin\\print_app_domain_key',
			'print_app',
			'print_app_settings_section'
		);
		add_settings_field(
			'print_app_secret_key',
			__('Auth Key:', 'printapp'),
			'printapp\\functions\\admin\\print_app_secret_key',
			'print_app',
			'print_app_settings_section'
		);
		add_settings_field(
			'print_app_cust_download_link',
			__('Include PDF Link in Customer Email:', 'printapp'),
			'printapp\\functions\\admin\\print_app_cust_download_link',
			'print_app',
			'print_app_settings_section'
		);

		register_setting('print_app', 'print_app_domain_key');
		register_setting('print_app', 'print_app_secret_key');
		register_setting('print_app', 'print_app_cust_download_link');
	}

	// input for capturing the PrintApp Domain Key
	// Escape output for better security
	function print_app_domain_key() {
		$domain_key = esc_html(get_option('print_app_domain_key'));
		echo '<input class="regular-text" id="print_app_domain_key" name="print_app_domain_key" type="text" value="' . $domain_key . '" />';
	}

	// input for capturing the PrintApp Auth Key
	// Escape output for better security
	function print_app_secret_key() {
		$secret_key = esc_html(get_option('print_app_secret_key'));
		echo '<input class="regular-text" id="print_app_secret_key" name="print_app_secret_key" type="text" value="' . $secret_key . '" />';
	}

	function print_app_cust_download_link() {
		echo '<input class="regular-text" id="print_app_cust_download_link" name="print_app_cust_download_link" type="checkbox" '. ( get_option('print_app_cust_download_link') == 'on' ? 'checked' : '' ) . ' />';
	}

	// creates the PrintApp settings link in admin
	function add_settings_link($links) {
		$settings_link = array(
			'<a href="/wp-admin/admin.php?page=printapp" rel="noopener">Settings</a>',
		);
		$actions = array_merge( $links, $settings_link );
		return $actions;
	}