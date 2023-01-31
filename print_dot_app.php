<?php
 /**
 * Plugin Name: 		Print.App
 * Plugin URI: 			https://print.app
 * Description: 		An app that helps web2print shops, allow their customers to design, online.
 * Version: 			1.0
 * Requires at least: 	3.8
 * Requires PHP:      	7.0
 * Author:            	Print.App
 * Author URI:        	https://print.app
 * Tested up to: 5.7
 * WC requires at least: 3.0.0
 * WC tested up to: 5.6.0
 *
 * @package PitchPrint
 * @category Core
 * @author PitchPrint
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class PrintDotApp {
 	// IMPLEMENT CLASS CONSTRUCTOR
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}
	// DECLARE SOME CONSTANTS USED FOR THIS PLUGIN
	private function define_constants() {
		global $wpdb;
		define('PRINT_DOT_APP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define('PRINT_DOT_APP_TABLE_NAME', $wpdb->prefix . 'print_dot_app_projects' );
		define('PRINT_DOT_APP_CLIENT_JS', 'https://editor.print.app/js/client.js');
		define('PRINT_DOT_APP_WP_CLIENT_JS', plugin_dir_url( __FILE__ ) . 'wp-client.js');
		define('PRINT_DOT_APP_DESIGN_TREE_SELECT_JS', plugin_dir_url( __FILE__ ) . 'designTreeSelect.js');
		define('PRINT_DOT_APP_ADMIN_JS', 'https://pitchprint.io/rsc/js/a.wp.js');
		define('PRINT_DOT_APP_SESSION_ID', 'print_app_sessId');
		define('PRINT_DOT_APP_RUNTIME_API_URL', 'https://yhlk1004od.execute-api.eu-west-1.amazonaws.com/prod/runtime');
	}
	// INITIALIZE HOOKS FOR THIS PLUGIN
	public function init_hooks() {
		// INITIALIZE ADMIN HOOKS
		if ($this->request_type('frontend')) {
			add_action('init', array($this,'register_session'), 0 );
			add_action('woocommerce_before_add_to_cart_button', array($this, 'print_dot_app_add_edit_button'));
			add_filter('woocommerce_add_cart_item_data', array($this, 'print_dot_app_add_cart_item_data'), 10, 2);
			add_filter('woocommerce_cart_item_thumbnail', array($this, 'print_dot_app_cart_thumbnail'), 70, 2);
			add_filter('woocommerce_checkout_create_order_line_item', array($this, 'print_dot_app_add_order_item_meta'), 70, 2);
			add_action('wp_ajax_nopriv_print_dot_app_save_project', array($this, 'print_dot_app_save_project'));
			add_action('wp_ajax_print_dot_app_save_project', array($this, 'print_dot_app_save_project'));
			add_action('wp_ajax_nopriv_print_dot_app_reset_project', array($this, 'print_dot_app_reset_project'));
			add_action('wp_ajax_print_dot_app_reset_project', array($this, 'print_dot_app_reset_project'));
			add_action('wp_ajax_print_dot_app_fetch_designs', array($this, 'print_dot_app_fetch_designs'));
		}
		else if ($this->request_type('admin')) {
			add_action('wp_ajax_print_dot_app_fetch_designs', array($this, 'print_dot_app_fetch_designs'));
			add_action('admin_head', array( $this, 'custom_css_icon' ) );
			add_action('admin_menu', array($this, 'print_dot_app_actions'));
			add_action('admin_init', array($this, 'print_dot_app_settings_api_init'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'print_dot_app_add_settings_link'));
			add_filter('woocommerce_product_data_tabs', array($this, 'print_dot_app_add_design_selection_tab'), 10, 1 );
			add_action('woocommerce_product_data_panels', array($this,'print_dot_app_assign_design_form') );
			add_action('woocommerce_process_product_meta', array($this,'print_dot_app_save_post_meta') );
			add_filter('woocommerce_order_item_display_meta_key', array($this,'print_dot_app_filter_wc_order_item_display_meta_key'), 20, 3 );
			add_filter('woocommerce_order_item_display_meta_value', array($this,'print_dot_app_filter_wc_order_item_display_meta_value'), 20, 3 );
		}
		
	}
	// FORMAT THE MATA DATA ON ORDER TO DISPLAY DOWNLOAD LINKS
	public function print_dot_app_filter_wc_order_item_display_meta_value( $display_value, $meta ) {
		if( $meta->key === '_pda_w2p_set_option' ) {
			$pda_data = json_decode($display_value, true);
	    	$display_value = '<a href="https://pdf.print.app/'.$pda_data['projectId'].'">Download PDF</a>
	    					  <a href="https://png.print.app/'.$pda_data['projectId'].'">Download PNG</a>
	    					  <a href="https://jpg.print.app/'.$pda_data['projectId'].'">Download JPEG</a>
	    					  <a href="https://admin.print.app/projects#'. $pda_data['projectId'].'">Modify Project</a>';
		}
		return $display_value;  
	}
	// CHANGE THE META LABEL TO SOMETHING MORE HUMAN READABLE
	public function print_dot_app_filter_wc_order_item_display_meta_key( $display_key, $meta, $item ) {
	    if( $meta->key === '_pda_w2p_set_option' ) 
	    	$display_key = "Print.App";
	    return $display_key;    
	}
	// ADD PROJECT DATA AS META DATA ON ORDER ITEMS
	public function print_dot_app_add_order_item_meta($order_item, $cart_item_key) {
		// var_dump($order_item);die();
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if	( !empty($cart_item['_pda_w2p_set_option']) ) {
			$order_item->add_meta_data( '_pda_w2p_set_option', $cart_item['_pda_w2p_set_option'], true );
			$order_item->add_meta_data( 'Preview', '<img src="'.json_decode($cart_item['_pda_w2p_set_option'],true)['previews'][0]['url'].'">', true );
		}
		if	( gettype($cart_item) == 'object' && isset($cart_item->legacy_values) && isset($cart_item->legacy_values['_pda_w2p_set_option']) )
			$order_item->add_meta_data( '_pda_w2p_set_option', $cart_item->legacy_values['_pda_w2p_set_option'], true );
	}
	// SHOW PROJECT PREVIEW IN THUMBNAIL
	public function print_dot_app_cart_thumbnail($img, $val) {
		if (!empty($val['_pda_w2p_set_option'])) {
			$itm = $val['_pda_w2p_set_option'];
			$itm = json_decode($itm, true);
			$img = '<img src="' . $itm['previews'][0]['url'] . '" >';
		}
		return $img;
	}
	// ADD PROJECT DATA AS META TO CART ITEMS
	public function print_dot_app_add_cart_item_data($cart_item_meta, $product_id) {
		$_projects = $this->getProjectData($product_id);
		if (isset($_projects)) {
			if (isset($_projects[$product_id])) {
				$cart_item_meta['_pda_w2p_set_option'] = $_projects[$product_id];
				$this->clearProjects($product_id);
			}
		}
		return $cart_item_meta;
	}
	// REMOVE PROJECTS FROM SESSION
	private function clearProjects($productId) {
		global $wpdb;
		$sessId = isset($_COOKIE['print_app_sessId']) ? $_COOKIE['print_app_sessId'] : false;
		if (!$sessId) return false;
		$wpdb->delete(PRINT_DOT_APP_TABLE_NAME, array('id' => $sessId, 'product_id' => $productId) );
	}
	// CREATE UNIQUE SESSION ID FOR EACH CUSTOMER
	public function register_session() {
		// die('init');
		if(!isset($_COOKIE['print_app_sessId']))
			setcookie('print_app_sessId', uniqid('pda_w2p_', true), time()+60*60*24*30, '/');
	}
	// SAVE PROJECT DATA ON CLIENT SERVER FOR PAGE REFRESH AND NOT YET ADDED TO CART.
	public function print_dot_app_reset_project() {
		global $wpdb;
		$productId	= $_POST['product_id'];
		$sessId 	= $_COOKIE['print_app_sessId'];
		
		// Delete old
		$wpdb->delete(PRINT_DOT_APP_TABLE_NAME, array('id' => $sessId, 'product_id' => $productId) );
		wp_die(json_encode(array('success'=>true))); 
	}
	// FETCH DESIGNS FOR ASSIGNING IN BACKEND.
	public function print_dot_app_fetch_designs() {
		$authKey = get_option('print_dot_app_secret_key');
		$url = PRINT_DOT_APP_RUNTIME_API_URL.'/designs'.(isset($_POST['path']) ? '/'.$_POST['path'] : '');
		// die($url);
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
		    'Authorization: '.$authKey
		  ),
		));
		
		$response = curl_exec($curl);
		
		curl_close($curl);
		wp_die($response); 
	}
	// SAVE PROJECT DATA ON CLIENT SERVER FOR PAGE REFRESH AND NOT YET ADDED TO CART.
	public function print_dot_app_save_project() {
		global $wpdb;
		
		$value	= $_POST['value'];
		$productId	= $_POST['product_id'];
		$sessId 	= $_COOKIE['print_app_sessId'];
		
		// Delete old
		$wpdb->delete(PRINT_DOT_APP_TABLE_NAME, array('id' => $sessId, 'product_id' => $productId) );
		
		// Insert new
		$date = date('Y-m-d H:i:s', time()+60*60*24*30);
		$table_name = PRINT_DOT_APP_TABLE_NAME;
		$sql = "INSERT INTO `{$table_name}` VALUES ('$sessId', '$productId', '$value', '$date')";
		$exec = dbDelta($sql);
		wp_die(json_encode(array('success'=>true))); 
	}
	// DISTINGUISH WHERE THE REQUEST IS FROM, FRONT OR BACK
	private function request_type( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}
	// GET SAVED PROJECT DATA BY SESSION ID STORED IN COOKIE
	private function getProjectData($product_id) {
		global $wpdb;
		$_projects	= array();
		$sessId 	= $_COOKIE[PRINT_DOT_APP_SESSION_ID];
		$tableName	= PRINT_DOT_APP_TABLE_NAME;
		$sql		= "SELECT `value` FROM `$tableName` WHERE `product_id` = $product_id AND `id` = '$sessId';";
		
		$results = $wpdb->get_results($sql);
		if(count($results))
			$_projects[$product_id] = $results[0]->value;
		
		return $_projects;
	}
	// DISPLAY THE EDIT BUTTON
	public function print_dot_app_add_edit_button() {
		global $post;
		global $woocommerce;
		// CHECK IF DESIGN IS ASSIGNED TO THIS PRODUCT
		$designId = get_post_meta($post->ID, 'print_dot_app_design', true );
		if (!$designId) return;
		$designId = explode(':', $designId);
		$designId = $designId[0];
		// LOAD SCRIPTS
		wp_enqueue_script('print_dot_app_class', PRINT_DOT_APP_CLIENT_JS);
		wp_enqueue_script('print_dot_app_woo_class', PRINT_DOT_APP_WP_CLIENT_JS);
		// PREP VARS NEEDED FOR JAVASCRIPT
		$pda_display_mode 			= get_post_meta($post->ID, 'print_dot_app_display_mode', true );
		$pda_customization_required = get_post_meta($post->ID, 'print_dot_app_customization_required', true) == 'checked' ? 1 : 0;
		$pda_pdf_download			= get_post_meta($post->ID, 'print_dot_app_pdf_download', true) == 'checked' ? 1 : 0;
		$pda_use_design_preview		= get_post_meta($post->ID, 'print_dot_app_use_design_preview', true) == 'checked' ? 1 : 0;
		
		$userData = "user: 'data pending...'";
		$pda_now_value = 'now value pending...';
		
		$projects		= $this->getProjectData($post->ID);
		if (count($projects)) 
			$projectData = json_decode($projects[$post->ID], true);
			
		$pda_project_id = isset($projectData) ? $projectData['projectId'] : '';
		$pda_mode		= $pda_project_id ? 'edit-project':'new-project';
		$pda_previews	= isset($projectData) ? json_encode($projectData['previews']) : '';
		$pda_uid		= get_current_user_id() === 0 ? 'guest' : get_current_user_id();
		$pda_domain_key = 'dom_f80b84b4eb5cc81a140cb90f52e824f6';//get_option('print_dot_app_domain_key');
		
		wp_localize_script( 'print_dot_app_woo_class', 'wp_ajax_url', admin_url( 'admin-ajax.php' ) );
		wp_localize_script( 'print_dot_app_woo_class', 'wp_ajax_url', admin_url( 'admin-ajax.php' ) );
            
		wc_enqueue_js("
			window.paclient = new PrintAppWoo({
				displayMode: '{$pda_display_mode}',
				customizationRequired: ". $pda_customization_required.",
				pdfDownload: ". $pda_pdf_download .",
				useDesignPrevAsProdImage: " . $pda_use_design_preview. ",
				userId: '{$pda_uid}',
				langCode: '" . substr(get_bloginfo('language'), 0, 2) . "',
				designId: '{$designId}',
				previews: '{$pda_previews}',
				mode: '{$pda_mode}',
				createButtons: true,
				projectId: '{$pda_project_id}',
				pluginRoot: '" . site_url() . "/print_dot_app',
				domainKey: '" . $pda_domain_key . "',
				client: 'wp',
				product: {
					id: '" . $post->ID . "',
					name: '{$post->post_name}'
				},{$userData},
				ppValue: '{$pda_now_value}'
			});
		");
	}
	// SAVE PRODUCT POST META
	public function print_dot_app_save_post_meta ($post_id) {
		update_post_meta($post_id, 'print_dot_app_design', $_POST['print_dot_app_design']);
		update_post_meta($post_id, 'print_dot_app_display_mode', $_POST['print_dot_app_display_mode']);
		update_post_meta($post_id, 'print_dot_app_customization_required', $_POST['print_dot_app_customization_required']);
		update_post_meta($post_id, 'print_dot_app_pdf_download', $_POST['print_dot_app_pdf_download']);
		update_post_meta($post_id, 'print_dot_app_use_design_preview', $_POST['print_dot_app_use_design_preview']);
	}
	// SHOW DESIGN SELECTION FORM
	public function print_dot_app_assign_design_form() {
		echo '<div id="print_dot_app_tab" class="panel woocommerce_options_panel">';
		
		if (!class_exists('WooCommerce')) exit;
		global $post, $woocommerce;

		
		$pda_design 				= get_post_meta($post->ID, 'print_dot_app_design', true);
		$pda_display_mode			= get_post_meta($post->ID, 'print_dot_app_display_mode', true);
		$pda_customization_required = get_post_meta($post->ID, 'print_dot_app_customization_required', true);
		$pda_pdf_download			= get_post_meta($post->ID, 'print_dot_app_pdf_download', true);
		$pda_use_design_preview		= get_post_meta($post->ID, 'print_dot_app_use_design_preview', true);

		woocommerce_wp_select( array(
			'id'            => 'print_dot_app_design',
			'value'			=> $pda_design,
			'wrapper_class' => '',
			'options'       => array('' => 'None', 'design_a9ffabf7-cd2c-4214-b758-7f7e6925e8b7' => 'Test Design'),
			'label'         => 'Choose a Design',
			'desc_tip'    	=> true,
			'description' 	=> __("Visit the Print.App Admin Panel to create and edit designs", 'Print.App')
		) );

		woocommerce_wp_select( array(
			'id'            => 'print_dot_app_display_mode',
			'value'		    => $pda_display_mode,
			'label'         => 'Display Mode',
			'options'       => array(''=>'Default', 'modal'=>'Full Window', 'inline'=>'Inline', 'mini'=>'Mini'),
			'cbvalue'		=> 'unchecked',
			'desc_tip'		=> true,
			'description' 	=>  __("Define the way that Print.App designer should open for this product on the front.")
		) );
		
		woocommerce_wp_checkbox( array(
			'id'            => 'print_dot_app_customization_required',
			'value'		    => $pda_customization_required,
			'label'         => '',
			'cbvalue'		=> 'checked',
			'description' 	=> '&#8678; ' . __("Check this to make customization compulsory for this product", 'Print.App')
		) );
		
		woocommerce_wp_checkbox( array(
			'id'            => 'print_dot_app_pdf_download',
			'value'		    => $pda_pdf_download,
			'label'         => '',
			'cbvalue'		=> 'checked',
			'description' 	=> '&#8678; ' . __("Check this to allow PDF download for this product", 'Print.App')
		) );
		
		woocommerce_wp_checkbox( array(
			'id'            => 'print_dot_app_use_design_preview',
			'value'		    => $pda_use_design_preview,
			'label'         => '',
			'cbvalue'		=> 'checked',
			'description' 	=> '&#8678; ' . __("Check this to show the Print.App design preview if this product has no product image", 'Print.App')
		) );
		
		wp_enqueue_script('print_dot_app_design_tree', PRINT_DOT_APP_DESIGN_TREE_SELECT_JS);
		wp_localize_script( 'print_dot_app_design_tree', 'print_dot_app_current_design', $pda_design );
		wp_localize_script( 'print_dot_app_design_tree', 'wp_ajax_url', admin_url( 'admin-ajax.php' ) );
		
		echo '</div>';

	}
	// ADD DESIGN SELECTION TAB
	public function print_dot_app_add_design_selection_tab($default_tabs) {
		$default_tabs['print_dot_app_tab'] = array(
	        'label'   =>  __( 'Print.App', 'domain' ),
	        'target'  =>  'print_dot_app_tab',
	        'priority' => 60,
	        'class'   => array()
	    );
	    return $default_tabs;
	}
	// CSS FOR MENU ITEM ICON
	public function custom_css_icon() {
		echo '<style type="text/css">
			#toplevel_page_print_dot_app .wp-menu-image img {
				width: 22px;
    			padding-top: 5px;
			}
		</style>';
	}
	// PLUGIN LINKS AFTER DEACTIVATE/ACTIVATE
	public function print_dot_app_add_settings_link($links) {
		$settings_link = array(
			'<a href="/wp-admin/admin.php?page=print_dot_app" target="_blank" rel="noopener">Settings</a>',
		);
		$actions = array_merge( $links, $settings_link );
		return $actions;
	}
	// DISPLAY THE SETTINGS PAGE FOR THIS PLUGIN
	public function print_dot_app_admin_page() {
		if (!class_exists('WooCommerce')) {
			echo ('<h3>This plugin depends on WooCommerce plugin. Kindly install <a target="_blank" href="https://wordpress.org/plugins/woocommerce/">WooCommerce here!</a></h3>');
			exit();
		}
		settings_errors();

		echo '<form method="post" action="options.php"><div class="wrap">';
			settings_fields('print_dot_app');
			do_settings_sections('print_dot_app');
			submit_button();
		echo '</div></form>';
	}
	// STORE API KEY AND SECRET KEY
	public function print_dot_app_settings_api_init() {
		add_settings_section('print_dot_app_settings_section', 'Print.App Settings', array($this, 'print_dot_app_create_settings'), 'print_dot_app');
		add_settings_field('print_dot_app_domain_key', 'Domain Key', array($this, 'print_dot_app_domain_key'), 'print_dot_app', 'print_dot_app_settings_section', array());
		add_settings_field('print_dot_app_secret_key', 'Secret Key', array($this, 'print_dot_app_secret_key'), 'print_dot_app', 'print_dot_app_settings_section', array());
		add_settings_field('print_dot_app_cat_customize', 'Category Customization', array($this, 'print_dot_app_cat_customize'), 'print_dot_app', 'print_dot_app_settings_section', array());
		register_setting('print_dot_app', 'print_dot_app_domain_key');
		register_setting('print_dot_app', 'print_dot_app_secret_key');
		register_setting('print_dot_app', 'print_dot_app_cat_customize');
	}
	// DISPLAY DOMAIN KEY INPUT
	public function print_dot_app_domain_key() {
		echo '<input class="regular-text" id="print_dot_app_domain_key" name="print_dot_app_domain_key" type="text" value="' . get_option('print_dot_app_domain_key') . '" />';
	}
	// DISPLAY SECRET KEY INPUT
	public function print_dot_app_secret_key() {
		echo '<input class="regular-text" id="print_dot_app_secret_key" name="print_dot_app_secret_key" type="text" value="' . get_option('print_dot_app_secret_key') . '" />';
	}
	// DISPLAY SHOW ON CATEGORY SWITCH
	public function print_dot_app_cat_customize() {
		echo '<input class="regular-text" id="print_dot_app_cat_customize" name="print_dot_app_cat_customize" type="checkbox" '. ( get_option('print_dot_app_cat_customize') == 'on' ? 'checked' : '' ) . ' />';
	}
	// DISPLAY MESSAGE: HOW TO GET NEW DOMAIN KEY
	public function print_dot_app_create_settings() {
		echo '<p>' . __("You can generate your api and secret keys from the <a target=\"_blank\" href=\"https://admin.print.app/domains\">Print.App domains page</a>", "Print.App") . '</p>';
	}
	// ADD PLUGIN MENU TO ADMIN
	public function print_dot_app_actions() {
		$menu_icon = plugin_dir_url( __FILE__ ) .'assets/icon.svg';
		add_menu_page('Print.App Settings', 'Print.App', 'manage_options', 'print_dot_app', array($this, 'print_dot_app_admin_page'), $menu_icon);
	}
}
 
global $PrintDotApp;
$PrintDotApp = new PrintDotApp();
 

// PERFORM SOME ACTIONS UPON INSTALL
function print_dot_app_install() {
	// CREATE DATABASE TABLE TO STORE PROJECTS IN
	global $wpdb;
	
	$table_name 		= $wpdb->prefix . 'print_dot_app_projects';
	$charset_collate	= $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
	  id varchar(55) NOT NULL ,
	  product_id mediumint(9) NOT NULL,
	  value TEXT  NOT NULL,
	  expires TIMESTAMP
	) $charset_collate;";
	
	$exec = dbDelta( $sql );
}
register_activation_hook( __FILE__, 'print_dot_app_install');

 