<?php

	namespace printapp\functions\admin;

	use printapp\functions\lulu;

	// Admin side of Send to Lulu: its settings (a panel under Fulfilment providers, see
	// fulfilment.php), the product's Lulu tab and the order actions. The sending itself
	// lives in functions/general/lulu.php.

	function lulu_settings_intro() {
		echo '<p>' . __('Send paid orders to <a target="_blank" href="https://www.lulu.com/sell/sell-on-your-site/print-api">Lulu</a> for printing and shipping. Each order line with a Print.App design, on a product that has a Lulu product and interior PDF set (product data &gt; Lulu tab), goes to Lulu with the customer\'s design as the cover. Lulu bills your Lulu account directly, and keeps a new print job unpaid until you pay for it there, unless you keep a payment card on file.', 'printapp') . '</p>'
			. '<p>' . __('Get an API key and secret from <a target="_blank" href="https://developers.lulu.com/user-profile/api-keys">developers.lulu.com</a>, or from <a target="_blank" href="https://developers.sandbox.lulu.com/user-profile/api-keys">the sandbox</a> to test first. Lulu fetches the cover PDFs from your site\'s uploads folder, so the site must be reachable from the internet.', 'printapp') . '</p>';
	}

	function lulu_settings_init() {
		add_settings_section('print_app_lulu_section', '', 'printapp\\functions\\admin\\lulu_settings_intro', 'print_app_lulu');

		$fields = [
			'print_app_lulu_enabled'        => __('Send paid orders to Lulu:', 'printapp'),
			'print_app_lulu_environment'    => __('Lulu environment:', 'printapp'),
			'print_app_lulu_client_key'     => __('Lulu API key:', 'printapp'),
			'print_app_lulu_client_secret'  => __('Lulu API secret:', 'printapp'),
			'print_app_lulu_contact_email'  => __('Contact email for Lulu:', 'printapp'),
			'print_app_lulu_shipping_level' => __('Lulu shipping level:', 'printapp'),
		];
		foreach ($fields as $id => $label) {
			add_settings_field($id, $label, 'printapp\\functions\\admin\\' . $id, 'print_app_lulu', 'print_app_lulu_section');
		}

		register_setting('print_app', 'print_app_lulu_enabled', ['sanitize_callback' => function ($value) { return $value === 'on' ? 'on' : ''; }]);
		register_setting('print_app', 'print_app_lulu_environment', ['sanitize_callback' => function ($value) { return $value === 'production' ? 'production' : 'sandbox'; }]);
		register_setting('print_app', 'print_app_lulu_client_key', ['sanitize_callback' => 'sanitize_text_field']);
		register_setting('print_app', 'print_app_lulu_client_secret', ['sanitize_callback' => 'sanitize_text_field']);
		register_setting('print_app', 'print_app_lulu_contact_email', ['sanitize_callback' => 'sanitize_email']);
		register_setting('print_app', 'print_app_lulu_shipping_level', ['sanitize_callback' => function ($value) {
			return in_array($value, lulu\SHIPPING_LEVELS, true) ? $value : 'MAIL';
		}]);
	}

	function print_app_lulu_enabled() {
		echo '<input id="print_app_lulu_enabled" name="print_app_lulu_enabled" type="checkbox" ' . (get_option('print_app_lulu_enabled') === 'on' ? 'checked' : '') . ' />';
	}

	function print_app_lulu_environment() {
		$current = lulu\environment();
		echo '<select id="print_app_lulu_environment" name="print_app_lulu_environment">';
		foreach (['sandbox' => __('Sandbox (test orders, nothing is printed)', 'printapp'), 'production' => __('Production (real print jobs)', 'printapp')] as $value => $label) {
			echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}

	function print_app_lulu_client_key() {
		echo '<input class="regular-text" id="print_app_lulu_client_key" name="print_app_lulu_client_key" type="text" value="' . esc_attr(get_option('print_app_lulu_client_key')) . '" autocomplete="off" />';
	}

	function print_app_lulu_client_secret() {
		echo '<input class="regular-text" id="print_app_lulu_client_secret" name="print_app_lulu_client_secret" type="password" value="' . esc_attr(get_option('print_app_lulu_client_secret')) . '" autocomplete="new-password" />';
	}

	function print_app_lulu_contact_email() {
		echo '<input class="regular-text" id="print_app_lulu_contact_email" name="print_app_lulu_contact_email" type="email" value="' . esc_attr(get_option('print_app_lulu_contact_email')) . '" placeholder="' . esc_attr(get_option('admin_email')) . '" />'
			. '<p class="description">' . __('Lulu writes here if it has questions about a print job. Defaults to the site admin email.', 'printapp') . '</p>';
	}

	function print_app_lulu_shipping_level() {
		$current = lulu\shipping_level();
		echo '<select id="print_app_lulu_shipping_level" name="print_app_lulu_shipping_level">';
		foreach (lulu\SHIPPING_LEVELS as $level) {
			echo '<option value="' . esc_attr($level) . '" ' . selected($current, $level, false) . '>' . esc_html($level) . '</option>';
		}
		echo '</select><p class="description">' . __('Lulu\'s shipping service for every order: MAIL is the cheapest and slowest, EXPRESS the fastest.', 'printapp') . '</p>';
	}

	// Product data: a Lulu tab, shown once Send to Lulu is switched on.
	function add_lulu_product_tab($tabs) {
		if (get_option('print_app_lulu_enabled') !== 'on') return $tabs;
		$tabs['print_app_lulu'] = [
			'label'    => __('Lulu', 'printapp'),
			'target'   => 'print_app_lulu_tab',
			'priority' => 61,
			'class'    => [],
		];
		return $tabs;
	}

	function lulu_product_panel() {
		global $post;
		if (get_option('print_app_lulu_enabled') !== 'on') return;

		echo '<div id="print_app_lulu_tab" class="panel woocommerce_options_panel hidden">';
		woocommerce_wp_text_input([
			'id'          => '_print_app_lulu_pod_package_id',
			'label'       => __('Lulu product (POD package ID)', 'printapp'),
			'placeholder' => '0600X0900.FC.STD.CO.080CW444.MXX',
			'desc_tip'    => true,
			'description' => __('The Lulu product to print, from Lulu\'s pricing calculator. Leave empty to keep this product out of Lulu.', 'printapp'),
		]);
		woocommerce_wp_text_input([
			'id'          => '_print_app_lulu_interior_url',
			'label'       => __('Interior PDF URL', 'printapp'),
			'type'        => 'url',
			'desc_tip'    => true,
			'description' => __('A link Lulu can download your fixed interior PDF from. The same file is used for every order.', 'printapp'),
		]);
		woocommerce_wp_text_input([
			'id'                => '_print_app_lulu_page_count',
			'label'             => __('Interior page count', 'printapp'),
			'type'              => 'number',
			'custom_attributes' => ['min' => 1, 'step' => 1],
			'desc_tip'          => true,
			'description'       => __('Used to work out the cover size your Print.App design needs.', 'printapp'),
		]);

		$size = get_post_meta($post->ID, '_print_app_lulu_cover_size', true);
		if ($size) {
			echo '<p class="form-field"><label>' . esc_html__('Cover size', 'printapp') . '</label>'
				. '<strong>' . esc_html($size) . '</strong><br/>'
				. esc_html__('Size your Print.App cover design to this full spread: back cover, spine and front cover, including bleed.', 'printapp') . '</p>';
		}
		echo '</div>';
	}

	function save_lulu_product_fields($product_id) {
		if (!isset($_POST['_print_app_lulu_pod_package_id'])) return;	// the tab wasn't on the form

		$pod = strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', wp_unslash($_POST['_print_app_lulu_pod_package_id'])));
		$interior = esc_url_raw(wp_unslash($_POST['_print_app_lulu_interior_url'] ?? ''));
		$pages = absint($_POST['_print_app_lulu_page_count'] ?? 0);

		$previous = [get_post_meta($product_id, '_print_app_lulu_pod_package_id', true), (int) get_post_meta($product_id, '_print_app_lulu_page_count', true)];

		update_post_meta($product_id, '_print_app_lulu_pod_package_id', $pod);
		update_post_meta($product_id, '_print_app_lulu_interior_url', $interior);
		update_post_meta($product_id, '_print_app_lulu_page_count', $pages ?: '');

		// the cover size follows the product and page count; ask Lulu only when they change
		if (!$pod || !$pages) {
			delete_post_meta($product_id, '_print_app_lulu_cover_size');
		} else if ($previous !== [$pod, $pages] || !get_post_meta($product_id, '_print_app_lulu_cover_size', true)) {
			$size = lulu\enabled() ? lulu\cover_size($pod, $pages) : null;
			if (is_string($size)) update_post_meta($product_id, '_print_app_lulu_cover_size', $size);
			else delete_post_meta($product_id, '_print_app_lulu_cover_size');
		}
	}

	// Order actions: send (or retry, or resend after Lulu rejected or canceled the job) and refresh the status.
	function lulu_order_actions($actions, $order = null) {
		if (!lulu\enabled()) return $actions;
		$order = $order ?: ($GLOBALS['theorder'] ?? null);	// WooCommerce before 6.x passes only $actions
		if ($order && !$order->get_meta(lulu\META_JOB)) {
			$actions['print_app_lulu_send'] = __('Send Print.App designs to Lulu', 'printapp');
		} else if ($order && lulu\can_resend($order)) {
			$actions['print_app_lulu_send'] = __('Send Print.App designs to Lulu again', 'printapp');
		}
		if ($order && $order->get_meta(lulu\META_JOB)) {
			$actions['print_app_lulu_refresh'] = __('Refresh Lulu print job status', 'printapp');
		}
		return $actions;
	}

	function lulu_order_action_send($order) {
		$previous = $order->get_meta(lulu\META_JOB);
		if ($previous && !lulu\can_resend($order)) {
			$order->add_order_note(sprintf(__('Lulu: already sent as print job #%s, so it was not sent again.', 'printapp'), $previous));
			return;
		}
		if (!lulu\order_lines($order)) {
			$order->add_order_note(__('Lulu: nothing to send. No line has a Print.App design on a product with a Lulu product and interior PDF set.', 'printapp'));
			return;
		}
		$previous_status = $order->get_meta(lulu\META_STATUS);
		$order->delete_meta_data(lulu\META_JOB);	// the old job stays in Lulu, marked rejected or canceled
		$order->delete_meta_data(lulu\META_STATUS);
		$order->delete_meta_data(lulu\META_ATTEMPTS);
		$order->save();
		lulu\schedule($order->get_id());
		$order->add_order_note($previous
			? sprintf(__('Lulu: print job #%1$s was %2$s, so the order is queued to be sent again as a new print job. The result appears in these notes within a few minutes.', 'printapp'), $previous, $previous_status)
			: __('Lulu: queued for sending. The result appears in these notes within a few minutes.', 'printapp'));
	}

	function lulu_order_action_refresh($order) {
		lulu\refresh_status($order);
	}
