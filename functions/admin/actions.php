<?php

	namespace printapp\functions\admin;

	function actions() {
		global $print_app;
		
		add_menu_page(
			'PrintApp Settings', 
			'PrintApp', 
			'manage_options', 
			'printapp', 
			'printapp\\functions\\admin\\admin_page', 
			$print_app->plugin_url() . '/assets/images/icon.svg'
		);
	}

	function admin_page() {
		if (!class_exists('WooCommerce')) {
			echo ('<h3>This plugin requires the WooCommerce plugin. Kindly install <a target="_blank" href="https://wordpress.org/plugins/woocommerce/">WooCommerce here!</a></h3>');
			exit();
		}
		settings_errors();

		echo '<form method="post" action="options.php"><div class="wrap">';
		
			settings_fields('print_app');
			do_settings_sections('print_app');
			submit_button();

		echo '</div></form>';
	}