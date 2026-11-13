<?php

	namespace printapp\functions\lulu;

	// The "Lulu" WooCommerce shipping method: Lulu's own shipping options and prices at checkout,
	// and the one the customer picks is the level Lulu ships the order with. Loaded on
	// woocommerce_shipping_init (see shipping_init), not with the plugin's other files: those
	// load before WooCommerce, and this class extends one of its classes.
	class Shipping_Method extends \WC_Shipping_Method {

		public function __construct($instance_id = 0) {
			$this->id                 = SHIPPING_METHOD;
			$this->instance_id        = absint($instance_id);
			$this->method_title       = __('Lulu', 'printapp');
			$this->method_description = __('Lulu\'s shipping options and prices at checkout, for carts where every item is a Lulu product. The option the customer picks is the one Lulu ships with. Prices come from Lulu in your store\'s currency, which must be USD, EUR, GBP, CAD or AUD.', 'printapp');
			$this->supports           = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
			$this->init();

			add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
		}

		public function init() {
			$this->instance_form_fields = [
				'levels' => [
					'title'       => __('Shipping options', 'printapp'),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'options'     => array_combine(SHIPPING_LEVELS, array_map(__NAMESPACE__ . '\\level_name', SHIPPING_LEVELS)),
					'default'     => ['MAIL', 'EXPEDITED', 'EXPRESS'],
					'description' => __('The Lulu shipping levels to offer, where Lulu has them for the address.', 'printapp'),
					'desc_tip'    => true,
				],
				'markup_percent' => [
					'title'             => __('Markup (%)', 'printapp'),
					'type'              => 'number',
					'default'           => '0',
					'custom_attributes' => ['min' => '0', 'step' => 'any'],
					'description'       => __('Added to Lulu\'s price, e.g. 10 for 10%.', 'printapp'),
					'desc_tip'          => true,
				],
				'markup_fixed' => [
					'title'       => __('Markup (amount)', 'printapp'),
					'type'        => 'price',
					'default'     => '0',
					'description' => __('A fixed amount added to each option, after the percentage.', 'printapp'),
					'desc_tip'    => true,
				],
				'show_dates' => [
					'title'   => __('Delivery estimate', 'printapp'),
					'type'    => 'checkbox',
					'label'   => __('Show Lulu\'s estimated delivery dates with each option', 'printapp'),
					'default' => 'yes',
				],
				'tax_status' => [
					'title'   => __('Tax status', 'woocommerce'),
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'taxable',
					'options' => ['taxable' => __('Taxable', 'woocommerce'), 'none' => _x('None', 'Tax status', 'woocommerce')],
				],
			];
			$this->title      = __('Lulu', 'printapp');
			$this->tax_status = $this->get_option('tax_status');
		}

		public function calculate_shipping($package = []) {
			$rates = shipping_rates($package['contents'] ?? [], $package['destination'] ?? [], [
				'levels'         => (array) $this->get_option('levels', []),
				'markup_percent' => $this->get_option('markup_percent', 0),
				'markup_fixed'   => $this->get_option('markup_fixed', 0),
				'show_dates'     => $this->get_option('show_dates', 'yes') === 'yes',
			]);
			foreach ($rates as $rate) {
				$this->add_rate([
					'id'        => $this->get_rate_id($rate['level']),
					'label'     => $rate['label'],
					'cost'      => $rate['cost'],
					'package'   => $package,
					'meta_data' => [META_RATE_LEVEL => $rate['level']],
				]);
			}
		}
	}
