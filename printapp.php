<?php
 /**
 * 	Plugin Name: 		Print.App
 * 	Plugin URI: 		https://print.app
 * 	Description: 		Empower your customers to personalize products like Business Cards, Photo Prints, T-Shirts, Mugs, Banners, Canvases, etc. on your store before purchase
 * 	Version: 			2.0.3
 * 	Requires at least: 	3.8
 * 	Requires PHP:      	5.2.4
 * 	Author:            	36 Studios, Inc.
 * 	Author URI:        	https://print.app
 * 	Tested up to: 		6.6
 * 	WC requires at least: 	4.0
 * 	WC tested up to: 		9.4
 * 	
 * 	License: GPL2+
 *	
 * 	@package PrintApp
 * 	@category Core
 * 	@author PrintApp
 */


	if (!defined('ABSPATH')) exit;	// Should not be accessed directly

	if (!class_exists('PrintApp')):

		// Include all general function files
		foreach (glob(__DIR__ . "/functions/general/*.php") as $filename) {
			include $filename;
		}
		
		// Include all front function files
		foreach (glob(__DIR__ . "/functions/front/*.php") as $filename) {
			include $filename;
		}
		
		// Include all admin function files
		foreach (glob(__DIR__ . "/functions/admin/*.php") as $filename) {
			include $filename;
		}

		class PrintApp {

			/**
			 * 	PrintApp version.
			 * 	@var string
			*/
			public $version = '2.0.3';

			/**
			 * 	The single instance of the class.
			 * 	@var PrintApp
			*/
			protected static $_instance = null;
			
			// main constructor
			public function __construct() {
				if (self::woocommerce_did_load())
					$this->construct();
				else
					add_action('plugins_loaded' , array($this, 'construct'));
			}

			public function construct() {
				$this->define_constants();

				$this->load_plugin_textdomain();

				\printapp\functions\general\init_hooks();
				
				register_activation_hook(__FILE__, 'printapp\\functions\\general\\install');

				add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
			}

			// singleton instance
			public static function instance() {
				if (self::$_instance === null)
					self::$_instance = new PrintApp();

				return self::$_instance;
			}

			// load the plugin text domain for translation
			private function load_plugin_textdomain() {
				load_plugin_textdomain('print_app', false, plugin_basename(dirname(__FILE__)) . '/i18n/languages');
			}

			// check if WooCommerce is loaded and has the correct version
			private static function woocommerce_did_load() {
				return 	defined('WC_VERSION') &&
						version_compare(WC_VERSION, '4.0', '>=');
			}

			// get the plugin url
			public function plugin_url() {
				return untrailingslashit(plugins_url('/', __FILE__ ));
			}

			// add links to the plugin display
			public static function plugin_row_meta($links, $file) {
				if (plugin_basename( __FILE__ ) == $file) {
					$row_meta = array(
						'docs' => '<a href="https://docs.print.app" target="_blank" aria-label="Print.App Documentation">' . esc_html__('Documentation', 'printapp') . '</a>',
						'dashboard' => '<a href="https://admin.print.app/register?cart=wordpress" target="_blank" aria-label="Print.App Dashboard">' . esc_html__('Dashboard', 'printapp') . '</a>'
					);
					return array_merge($links, $row_meta);
				}
				return (array)$links;
			}

			public function define_constants() {
				define('PRINT_APP_PLUGIN_BASENAME', plugin_basename(__FILE__));
				define('PRINT_APP_RUN_BASE_URL', 'https://run.print.app/');
				define('PRINT_APP_DESIGN_SELECT_JS', plugin_dir_url( __FILE__ ) . 'js/design-select.js');
				define('PRINT_APP_SESSION_PREFIX', 'print_app_sess_');
				define('PRINT_APP_CUSTOMIZATION_KEY', 'print_app_customization');
				define('PRINT_APP_CUSTOMIZATION_PREVIEWS_KEY', 'preview');
			}
			
		}

	endif;
	
	global $print_app;
	$print_app = PrintApp::instance();

	// Added support for WooCommerce High-Performance order storage feature
	add_action( 'before_woocommerce_init', function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true );
		}
	});
