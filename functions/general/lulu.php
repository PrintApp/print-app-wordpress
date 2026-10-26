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

	function enabled() {
		return get_option('print_app_lulu_enabled') === 'on'
			&& get_option('print_app_lulu_client_key')
			&& get_option('print_app_lulu_client_secret');
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

	// A JSON request to the Lulu API: ['status' => int, 'body' => array|null] or WP_Error.
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
		return ['status' => $status, 'body' => json_decode(wp_remote_retrieve_body($response), true)];
	}

	// The product's Lulu settings, or null when it isn't set up for Lulu.
	function product_settings($product_id) {
		$pod = trim((string) get_post_meta($product_id, '_print_app_lulu_pod_package_id', true));
		$interior = trim((string) get_post_meta($product_id, '_print_app_lulu_interior_url', true));
		return ($pod !== '' && $interior !== '') ? ['pod_package_id' => $pod, 'interior' => $interior] : null;
	}

	// The order lines that go to Lulu: a Print.App design on a product set up for Lulu.
	function order_lines($order) {
		$lines = [];
		foreach ($order->get_items() as $item_id => $item) {
			$customization = $item->get_meta(PRINT_APP_CUSTOMIZATION_KEY, true);
			$project_id = is_array($customization) ? ($customization['projectId'] ?? '') : '';
			if ($project_id === '') continue;

			$settings = product_settings($item->get_product_id());
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

	function shipping_level() {
		$level = get_option('print_app_lulu_shipping_level');
		return in_array($level, SHIPPING_LEVELS, true) ? $level : 'MAIL';
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
			'shipping_level'   => shipping_level(),
			'shipping_address' => shipping_address($order),
			'line_items'       => $items,
		];
	}

	// Lulu's error body, short enough for an order note.
	function describe_error($body) {
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
			return new \WP_Error('lulu_rejected', "Lulu didn't accept the print job (HTTP {$result['status']}): " . describe_error($result['body']), ['retry' => $retry]);
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
		$order->delete_meta_data(META_ATTEMPTS);
		$order->add_order_note(sprintf(
			'Lulu%s: sent as print job #%s (status: %s). Lulu holds a new job until it is paid for in your Lulu account, unless you keep a payment card on file there.',
			$environment, $job['id'], $status
		));
		$order->save();
	}

	// The latest status from Lulu, with tracking links once it has shipped.
	function refresh_status($order) {
		$job_id = $order->get_meta(META_JOB);
		if (!$job_id || !enabled()) return;

		$result = request('GET', '/print-jobs/' . rawurlencode($job_id) . '/');
		if (is_wp_error($result) || $result['status'] !== 200) {
			$order->add_order_note('Lulu: could not read print job #' . $job_id . '. ' . (is_wp_error($result) ? $result->get_error_message() : 'HTTP ' . $result['status']));
			return;
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

		$order->update_meta_data(META_STATUS, $status);
		$order->add_order_note('Lulu: print job #' . $job_id . ' is ' . $status . '.'
			. ($problems ? ' ' . implode(' ', array_unique($problems)) : '')
			. ($tracking ? ' Tracking: ' . implode(' ', array_unique($tracking)) : ''));
		$order->save();
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
			return new \WP_Error('lulu_cover', 'Lulu could not work out the cover size: ' . describe_error($result['body']));

		return sprintf('%s × %s mm', round((float) $result['body']['width'], 1), round((float) $result['body']['height'], 1));
	}
