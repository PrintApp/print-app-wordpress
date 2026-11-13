<?php

	namespace printapp\functions\lulu;

	// Send to Lulu: when an order is paid, every line that carries a Print.App design, on a
	// product with a Lulu product (POD package ID) and interior PDF set, goes to Lulu's
	// Print API as one print job, with the customer's design as the cover. Lulu bills the
	// merchant's own Lulu account. Runs in the background (Action Scheduler) so checkout
	// isn't held up while the cover PDFs render.

	const HOSTS = [
		'sandbox'    => 'https://api.sandbox.lulu.com',
		'production' => 'https://api.lulu.com',
	];
	const SHIPPING_LEVELS = ['MAIL', 'PRIORITY_MAIL', 'GROUND_HD', 'GROUND_BUS', 'GROUND', 'EXPEDITED', 'EXPRESS'];
	const SEND_HOOK = 'print_app_lulu_send_order';
	const META_JOB = '_print_app_lulu_job_id';
	const META_STATUS = '_print_app_lulu_status';
	const META_ATTEMPTS = '_print_app_lulu_attempts';
	const RETRY_DELAYS = [15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS];	// after attempts 1 and 2
	const RESENDABLE = ['REJECTED', 'CANCELED'];	// Lulu's end states in which nothing is printed
	const META_POD = '_print_app_lulu_pod_package_id';	// on a product, or on a variation to override it
	const META_TRACKING = '_print_app_lulu_tracking';
	const META_SENT_AT = '_print_app_lulu_sent_at';
	const STATUS_HOOK = 'print_app_lulu_check_status';
	const CHECK_FIRST = 30 * MINUTE_IN_SECONDS;	// Lulu checks the files within minutes
	const CHECK_EVERY = 6 * HOUR_IN_SECONDS;
	const CHECK_FOR_DAYS = 45;	// an unpaid job isn't checked forever
	const SETTLED = ['SHIPPED', 'DELIVERED', 'REJECTED', 'CANCELED'];	// nothing more to check
	const SHIPPING_METHOD = 'print_app_lulu';	// the WooCommerce shipping method, see functions/shipping/lulu.php
	const META_RATE_LEVEL = '_print_app_lulu_level';	// on the order's shipping line: the level the customer chose
	const SHIPPING_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];	// the currencies Lulu prices shipping in

	// Orders are sent: switched on, with an API key and secret.
	function enabled() {
		return get_option('print_app_lulu_enabled') === 'on' && connected();
	}

	// An API key and secret are saved: the product's Lulu tab, cover sizes and status checks
	// work, even while sending is still switched off.
	function connected() {
		return get_option('print_app_lulu_client_key') && get_option('print_app_lulu_client_secret');
	}

	function environment() {
		return get_option('print_app_lulu_environment') === 'production' ? 'production' : 'sandbox';
	}

	function host() {
		return HOSTS[environment()];
	}

	// OAuth client-credentials token, cached until shortly before it expires.
	function token($refresh = false) {
		$cache_key = 'print_app_lulu_token_' . md5(host() . get_option('print_app_lulu_client_key'));
		if (!$refresh && ($cached = get_transient($cache_key))) return $cached;

		$response = wp_remote_post(host() . '/auth/realms/glasstree/protocol/openid-connect/token', [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode(get_option('print_app_lulu_client_key') . ':' . get_option('print_app_lulu_client_secret')),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body' => ['grant_type' => 'client_credentials'],
		]);
		if (is_wp_error($response)) return new \WP_Error('lulu_network', $response->get_error_message(), ['retry' => true]);

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);
		if ($code !== 200 || empty($body['access_token']))
			return new \WP_Error('lulu_auth', "Lulu refused the API key and secret (HTTP $code). Check them in the Print.App settings.", ['retry' => $code >= 500]);

		set_transient($cache_key, $body['access_token'], max(60, (int) ($body['expires_in'] ?? 300) - 60));
		return $body['access_token'];
	}

	// A JSON request to the Lulu API: ['status' => int, 'body' => array|null, 'raw' => string]
	// or WP_Error. 'raw' keeps a reply that isn't JSON, such as a firewall's error page.
	function request($method, $path, $data = null, $retried = false) {
		$token = token();
		if (is_wp_error($token)) return $token;

		$args = [
			'method'  => $method,
			'timeout' => 60,
			'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
		];
		if ($data !== null) $args['body'] = wp_json_encode($data);

		$response = wp_remote_request(host() . $path, $args);
		if (is_wp_error($response)) return new \WP_Error('lulu_network', $response->get_error_message(), ['retry' => true]);

		$status = wp_remote_retrieve_response_code($response);
		if ($status === 401 && !$retried) {		// the cached token expired early
			token(true);
			return request($method, $path, $data, true);
		}
		$raw = (string) wp_remote_retrieve_body($response);
		return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
	}

	// The product's Lulu settings, or null when it isn't set up for Lulu. A variation can be its
	// own Lulu product (e.g. glossy and matte covers are different Lulu products); otherwise it
	// uses the product's.
	function product_settings($product_id, $variation_id = 0) {
		$pod = $variation_id ? trim((string) get_post_meta($variation_id, META_POD, true)) : '';
		if ($pod === '') $pod = trim((string) get_post_meta($product_id, META_POD, true));
		$interior = trim((string) get_post_meta($product_id, '_print_app_lulu_interior_url', true));
		return ($pod !== '' && $interior !== '') ? [
			'pod_package_id' => $pod,
			'interior'       => $interior,
			'page_count'     => (int) get_post_meta($product_id, '_print_app_lulu_page_count', true),
		] : null;
	}

	// The order lines that go to Lulu: a Print.App design on a product set up for Lulu.
	function order_lines($order) {
		$lines = [];
		foreach ($order->get_items() as $item_id => $item) {
			$customization = $item->get_meta(PRINT_APP_CUSTOMIZATION_KEY, true);
			$project_id = is_array($customization) ? ($customization['projectId'] ?? '') : '';
			if ($project_id === '') continue;

			$settings = product_settings($item->get_product_id(), $item->get_variation_id());
			if ($settings) $lines[] = ['item_id' => $item_id, 'item' => $item, 'project_id' => $project_id, 'settings' => $settings];
		}
		return $lines;
	}

	// Downloads the design's print-ready PDF and keeps a copy in the uploads folder, under an
	// unguessable name, for Lulu to fetch. The URL of the copy, or WP_Error.
	function store_cover($order, $project_id) {
		$response = wp_remote_get('https://pdf.print.app/' . rawurlencode($project_id), ['timeout' => 120]);
		if (is_wp_error($response)) return new \WP_Error('cover', $response->get_error_message(), ['retry' => true]);

		$code = wp_remote_retrieve_response_code($response);
		$pdf = wp_remote_retrieve_body($response);
		if ($code !== 200 || strncmp($pdf, '%PDF', 4) !== 0)
			return new \WP_Error('cover', "The cover PDF for design $project_id could not be generated (HTTP $code).", ['retry' => true]);

		$name = sanitize_file_name(sprintf('printapp-cover-%s-%s.pdf', $order->get_order_number(), wp_generate_password(24, false)));
		$upload = wp_upload_bits($name, null, $pdf);
		if (!empty($upload['error'])) return new \WP_Error('cover', 'Saving the cover PDF failed: ' . $upload['error'], ['retry' => true]);

		return $upload['url'];
	}

	// Lulu's shipping address from the order: the shipping address, or the billing one when
	// the order has none. Phone and email fall back to the billing details.
	function shipping_address($order) {
		$type = $order->has_shipping_address() ? 'shipping' : 'billing';
		$field = function ($name) use ($order, $type) {
			$getter = "get_{$type}_{$name}";
			return trim((string) $order->$getter());
		};
		$phone = ($type === 'shipping' && method_exists($order, 'get_shipping_phone')) ? trim((string) $order->get_shipping_phone()) : '';

		return array_filter([
			'name'         => trim($field('first_name') . ' ' . $field('last_name')),
			'organization' => $field('company'),
			'street1'      => $field('address_1'),
			'street2'      => $field('address_2'),
			'city'         => $field('city'),
			'state_code'   => $field('state'),
			'postcode'     => $field('postcode'),
			'country_code' => $field('country'),
			'phone_number' => $phone !== '' ? $phone : trim((string) $order->get_billing_phone()),
			'email'        => trim((string) $order->get_billing_email()),
		], function ($value) { return $value !== ''; });
	}

	// The Lulu shipping level for an order: the one the customer chose at checkout with the
	// Lulu shipping method, otherwise the store's setting.
	function shipping_level($order = null) {
		if ($order) {
			foreach ($order->get_shipping_methods() as $method) {
				$level = $method->get_method_id() === SHIPPING_METHOD ? $method->get_meta(META_RATE_LEVEL) : '';
				if (in_array($level, SHIPPING_LEVELS, true)) return $level;
			}
		}
		$level = get_option('print_app_lulu_shipping_level');
		return in_array($level, SHIPPING_LEVELS, true) ? $level : 'MAIL';
	}

	function level_name($level) {
		switch ($level) {
			case 'MAIL':          return __('Standard mail', 'printapp');
			case 'PRIORITY_MAIL': return __('Priority mail', 'printapp');
			case 'GROUND_HD':     return __('Ground, home delivery', 'printapp');
			case 'GROUND_BUS':    return __('Ground, business delivery', 'printapp');
			case 'GROUND':        return __('Ground', 'printapp');
			case 'EXPEDITED':     return __('Expedited', 'printapp');
			case 'EXPRESS':       return __('Express', 'printapp');
		}
		return $level;
	}

	// Lulu's shipping levels with their cost for these items to this address, cached for half an
	// hour: checkout recalculates often. [] when Lulu can't be asked (logged under printapp-lulu).
	function shipping_options($items, $destination, $currency) {
		$address = array_filter([
			'country'  => (string) ($destination['country'] ?? ''),
			'state'    => (string) ($destination['state'] ?? ''),
			'postcode' => (string) ($destination['postcode'] ?? ''),
			'city'     => (string) ($destination['city'] ?? ''),
			'street1'  => (string) ($destination['address'] ?? ($destination['address_1'] ?? '')),
		], function ($value) { return $value !== ''; });
		$body = ['currency' => $currency, 'line_items' => $items, 'shipping_address' => $address];

		$cache_key = 'print_app_lulu_ship_' . md5(environment() . wp_json_encode($body));
		$cached = get_transient($cache_key);
		if (is_array($cached)) return $cached;

		$result = request('POST', '/shipping-options/', $body);
		if (is_wp_error($result) || $result['status'] !== 200 || !is_array($result['body'])) {
			if (function_exists('wc_get_logger')) wc_get_logger()->warning('Lulu shipping options: ' . (is_wp_error($result)
				? $result->get_error_message() : 'HTTP ' . $result['status'] . ' ' . describe_error($result)), ['source' => 'printapp-lulu']);
			return [];
		}
		set_transient($cache_key, $result['body'], 30 * MINUTE_IN_SECONDS);
		return $result['body'];
	}

	// The checkout rates for a cart (package) whose every item is a Lulu product: Lulu's options
	// for the address, limited to the levels the shop offers, with its markup, cheapest first.
	// Nothing when anything else is in the package (the shop's other methods ship that), or
	// the store's currency isn't one Lulu prices in.
	function shipping_rates($contents, $destination, $settings) {
		$currency = get_woocommerce_currency();
		if (!in_array($currency, SHIPPING_CURRENCIES, true) || empty($destination['country'])) return [];

		$items = [];
		foreach ($contents as $line) {
			$lulu = product_settings($line['product_id'], $line['variation_id'] ?? 0);
			if (!$lulu || !$lulu['page_count']) return [];
			$items[] = ['pod_package_id' => $lulu['pod_package_id'], 'page_count' => $lulu['page_count'], 'quantity' => (int) $line['quantity']];
		}
		if (!$items) return [];

		$levels = array_filter((array) ($settings['levels'] ?? []));
		$rates = [];
		foreach (shipping_options($items, $destination, $currency) as $option) {
			$level = $option['level'] ?? '';
			if (!isset($option['cost_excl_tax']) || !in_array($level, SHIPPING_LEVELS, true) || ($levels && !in_array($level, $levels, true))) continue;

			$label = level_name($level);
			if (!empty($settings['show_dates']) && !empty($option['min_delivery_date']) && !empty($option['max_delivery_date'])) {
				$label .= ' (' . sprintf(__('arrives %1$s – %2$s', 'printapp'),
					date_i18n('M j', strtotime($option['min_delivery_date'])), date_i18n('M j', strtotime($option['max_delivery_date']))) . ')';
			}
			$cost = (float) $option['cost_excl_tax'] * (1 + (float) ($settings['markup_percent'] ?? 0) / 100) + (float) ($settings['markup_fixed'] ?? 0);
			$rates[] = ['level' => $level, 'label' => $label, 'cost' => round($cost, 2)];
		}
		usort($rates, function ($a, $b) { return $a['cost'] <=> $b['cost']; });
		return $rates;
	}

	// woocommerce_shipping_init: loads the Lulu shipping method once WooCommerce's base class exists.
	function shipping_init() {
		if (connected() && !class_exists(__NAMESPACE__ . '\\Shipping_Method')) require_once dirname(__DIR__) . '/shipping/lulu.php';
	}

	// woocommerce_shipping_methods: the Lulu method, offered once a Lulu API key and secret are saved.
	function add_shipping_method($methods) {
		if (class_exists(__NAMESPACE__ . '\\Shipping_Method')) $methods[SHIPPING_METHOD] = __NAMESPACE__ . '\\Shipping_Method';
		return $methods;
	}

	// The print-job request for Lulu's POST /print-jobs/.
	function print_job($order, $lines, $covers) {
		$items = [];
		foreach ($lines as $i => $line) {
			$items[] = [
				'external_id' => 'wc-item-' . $line['item_id'],
				'title'       => $line['item']->get_name(),
				'quantity'    => (int) $line['item']->get_quantity(),
				'printable_normalization' => [
					'pod_package_id' => $line['settings']['pod_package_id'],
					'cover'          => ['source_url' => $covers[$i]],
					'interior'       => ['source_url' => $line['settings']['interior']],
				],
			];
		}
		return [
			'external_id'      => 'wc-' . $order->get_order_number(),
			'contact_email'    => get_option('print_app_lulu_contact_email') ?: get_option('admin_email'),
			'shipping_level'   => shipping_level($order),
			'shipping_address' => shipping_address($order),
			'line_items'       => $items,
		];
	}

	// Lulu's error body, short enough for an order note. Pass the whole request() result to
	// keep a reply that isn't JSON, which would otherwise read as an empty error.
	function describe_error($body) {
		if (is_array($body) && array_key_exists('raw', $body)) {
			$result = $body;
			$body = $result['body'] ?? trim(wp_strip_all_tags($result['raw']));
			if ($body === '' || $body === []) $body = 'HTTP ' . $result['status'];
		}
		$text = is_array($body) ? wp_json_encode($body) : (string) $body;
		return strlen($text) > 600 ? substr($text, 0, 600) . '…' : $text;
	}

	// Renders and stores the covers, then creates the print job. The job, or WP_Error with
	// data ['retry' => bool]: only network trouble, rendering and 5xx/429 are worth retrying.
	function create_print_job($order) {
		$lines = order_lines($order);
		if (!$lines) return new \WP_Error('lulu_nothing', 'No order lines are set up for Lulu.', ['retry' => false]);

		// keys first: no point rendering and storing covers Lulu won't accept
		$token = token();
		if (is_wp_error($token)) return $token;

		$covers = [];
		foreach ($lines as $line) {
			$cover = store_cover($order, $line['project_id']);
			if (is_wp_error($cover)) return $cover;
			$covers[] = $cover;
		}

		$result = request('POST', '/print-jobs/', print_job($order, $lines, $covers));
		if (is_wp_error($result)) return $result;

		if ($result['status'] !== 201 || empty($result['body']['id'])) {
			$retry = $result['status'] >= 500 || $result['status'] === 429;
			return new \WP_Error('lulu_rejected', "Lulu didn't accept the print job (HTTP {$result['status']}): " . describe_error($result), ['retry' => $retry]);
		}
		return $result['body'];
	}

	function schedule($order_id, $delay = 0) {
		if (function_exists('as_schedule_single_action'))
			as_schedule_single_action(time() + $delay, SEND_HOOK, [$order_id], 'printapp');
		else
			wp_schedule_single_event(time() + $delay, SEND_HOOK, [$order_id]);
	}

	// woocommerce_order_status_processing: the order is paid.
	function queue_order($order_id) {
		if (!enabled()) return;
		$order = wc_get_order($order_id);
		if (!$order || $order->get_meta(META_JOB) || !order_lines($order)) return;
		schedule($order_id);
	}

	// An order whose print job Lulu rejected or canceled may be sent again, by hand only.
	function can_resend($order) {
		return $order->get_meta(META_JOB) && in_array($order->get_meta(META_STATUS), RESENDABLE, true);
	}

	// The scheduled send. Never sends an order twice: an order with a print job is skipped.
	function send_order($order_id) {
		$order = wc_get_order($order_id);
		if (!$order || !enabled() || $order->get_meta(META_JOB)) return;

		$lock = 'print_app_lulu_lock_' . $order_id;	// a retry and a manual send could overlap
		if (get_transient($lock)) return;
		set_transient($lock, 1, 10 * MINUTE_IN_SECONDS);

		try {
			$job = create_print_job($order);
		} finally {
			delete_transient($lock);
		}

		$environment = environment() === 'sandbox' ? ' (sandbox)' : '';

		if (is_wp_error($job)) {
			$attempts = (int) $order->get_meta(META_ATTEMPTS) + 1;
			$data = $job->get_error_data();
			$retry = !empty($data['retry']) && $attempts <= count(RETRY_DELAYS);

			$order->update_meta_data(META_ATTEMPTS, $attempts);
			$order->update_meta_data(META_STATUS, 'NOT_SENT');
			$order->add_order_note('Lulu' . $environment . ': not sent. ' . $job->get_error_message()
				. ($retry ? ' Trying again automatically.' : ' Fix the problem, then use the order action "Send Print.App designs to Lulu".'));
			$order->save();

			if ($retry) schedule($order_id, RETRY_DELAYS[$attempts - 1]);
			return;
		}

		$status = $job['status']['name'] ?? 'CREATED';
		$order->update_meta_data(META_JOB, (string) $job['id']);
		$order->update_meta_data(META_STATUS, $status);
		$order->update_meta_data(META_SENT_AT, time());
		$order->delete_meta_data(META_ATTEMPTS);
		$order->delete_meta_data(META_TRACKING);
		$order->add_order_note(sprintf(
			'Lulu%s: sent as print job #%s (status: %s). Lulu holds a new job until it is paid for in your Lulu account, unless you keep a payment card on file there.',
			$environment, $job['id'], $status
		));
		$order->save();

		schedule_check($order_id, CHECK_FIRST);
	}

	// The next automatic status check for an order; never more than one queued.
	function schedule_check($order_id, $delay) {
		if (function_exists('as_schedule_single_action')) {
			as_unschedule_all_actions(STATUS_HOOK, [$order_id], 'printapp');
			as_schedule_single_action(time() + $delay, STATUS_HOOK, [$order_id], 'printapp');
		} else {
			wp_clear_scheduled_hook(STATUS_HOOK, [$order_id]);
			wp_schedule_single_event(time() + $delay, STATUS_HOOK, [$order_id]);
		}
	}

	// The scheduled status check: a note only when something changed, then the next check,
	// until the job has shipped, been rejected or canceled, or CHECK_FOR_DAYS have passed.
	function check_status($order_id) {
		$order = wc_get_order($order_id);
		if (!$order || !$order->get_meta(META_JOB) || !connected()) return;

		$status = refresh_status($order, true);
		if (in_array($status, SETTLED, true)) return;

		$sent_at = (int) $order->get_meta(META_SENT_AT);
		if ($sent_at && time() - $sent_at > CHECK_FOR_DAYS * DAY_IN_SECONDS) return;
		schedule_check($order_id, CHECK_EVERY);
	}

	// The latest status from Lulu, with tracking links once it has shipped. Returns the status,
	// or null when Lulu couldn't be asked. Quiet (the automatic checks) adds a note only when
	// the status or tracking changed; a refresh by hand always adds one.
	function refresh_status($order, $quiet = false) {
		$job_id = $order->get_meta(META_JOB);
		if (!$job_id || !connected()) return null;

		$result = request('GET', '/print-jobs/' . rawurlencode($job_id) . '/');
		if (is_wp_error($result) || $result['status'] !== 200) {
			if (!$quiet) $order->add_order_note('Lulu: could not read print job #' . $job_id . '. ' . (is_wp_error($result) ? $result->get_error_message() : 'HTTP ' . $result['status']));
			return null;
		}

		$status = $result['body']['status']['name'] ?? 'UNKNOWN';
		$tracking = [];
		$problems = [];	// why Lulu rejected a file, e.g. a cover of the wrong size
		foreach (($result['body']['line_items'] ?? []) as $line) {
			foreach (($line['tracking_urls'] ?? []) as $url) $tracking[] = $url;
			foreach (['cover', 'interior'] as $file) {
				foreach (($line['status']['messages']['printable_normalization'][$file] ?? []) as $message) {
					$problems[] = ucfirst($file) . ' (' . ($line['title'] ?? 'item') . '): ' . $message;
				}
			}
			if (!empty($line['status']['messages']['error'])) $problems[] = $line['status']['messages']['error'];
		}

		$tracking = array_values(array_unique($tracking));
		$changed = $status !== $order->get_meta(META_STATUS)
			|| array_diff($tracking, (array) ($order->get_meta(META_TRACKING) ?: []));

		$order->update_meta_data(META_STATUS, $status);
		if ($tracking) $order->update_meta_data(META_TRACKING, $tracking);
		if (!$quiet || $changed) {
			$order->add_order_note('Lulu: print job #' . $job_id . ' is ' . $status . '.'
				. ($status === 'UNPAID' ? ' Lulu accepted the files; pay for the job in your Lulu account to start printing.' : '')
				. ($problems ? ' ' . implode(' ', array_unique($problems)) : '')
				. ($tracking ? ' Tracking: ' . implode(' ', $tracking) : '')
				. (in_array($status, RESENDABLE, true) ? ' Fix the problem, then use the order action "Send Print.App designs to Lulu again".' : ''));
		}
		$order->save();

		if ($status === 'SHIPPED' || $status === 'DELIVERED') complete_if_shipped($order);
		return $status;
	}

	// With "Complete shipped orders" on, a shipped order is marked Completed, so WooCommerce
	// emails the customer (with the tracking links, see email_tracking). Only when every item
	// went to Lulu: anything else in the order may still need sending.
	function complete_if_shipped($order) {
		if (get_option('print_app_lulu_complete_on_ship') !== 'on') return;
		if (!in_array($order->get_status(), ['processing', 'on-hold'], true)) return;
		if (count(order_lines($order)) !== count($order->get_items())) return;
		$order->update_status('completed', 'Lulu: shipped, so the order is complete.');
	}

	// woocommerce_email_order_details: the tracking links in the customer's "order complete" email.
	function email_tracking($order, $sent_to_admin, $plain_text, $email) {
		if ($sent_to_admin || !is_object($email) || ($email->id ?? '') !== 'customer_completed_order') return;
		$tracking = (array) ($order->get_meta(META_TRACKING) ?: []);
		if (!$tracking) return;

		if ($plain_text) {
			echo __('Track your order', 'printapp') . ': ' . implode(' ', array_map('esc_url', $tracking)) . "\n\n";
			return;
		}
		$links = array_map(function ($url) { return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>'; }, $tracking);
		echo '<p><strong>' . esc_html__('Track your order', 'printapp') . ':</strong> ' . implode(' ', $links) . '</p>';
	}

	// The cover spread size for a product, from Lulu: "W × H mm", or WP_Error.
	function cover_size($pod_package_id, $page_count) {
		$result = request('POST', '/cover-dimensions/', [
			'pod_package_id'      => $pod_package_id,
			'interior_page_count' => (int) $page_count,
			'unit'                => 'mm',
		]);
		if (is_wp_error($result)) return $result;
		if ($result['status'] !== 201 || !isset($result['body']['width'], $result['body']['height']))
			return new \WP_Error('lulu_cover', "Lulu could not work out the cover size (HTTP {$result['status']}): " . describe_error($result));

		return sprintf('%s × %s mm', round((float) $result['body']['width'], 1), round((float) $result['body']['height'], 1));
	}
