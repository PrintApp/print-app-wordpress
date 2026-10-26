<?php
// wp eval-file /sandbox/shop.php <command>
//   configure  plugin settings from the environment, the Lulu test product, the interior PDF
//   order      a paid test order for the product, carrying the cover design
//   status     the last test order's Lulu meta and notes
// Environment (from .env.local via setup.sh): LULU_CLIENT_KEY, LULU_CLIENT_SECRET,
// LULU_POD_PACKAGE_ID, LULU_PAGE_COUNT, COVER_PROJECT_ID.

$command = $args[0] ?? 'status';
$env = function ($name, $default = '') { $v = getenv($name); return ($v === false || $v === '') ? $default : $v; };

// A blank lined interior at the product's trim size, e.g. 0600X0900 = 6 x 9 in.
function sandbox_interior_pdf($pod, $pages) {
	if (!preg_match('/^(\d{2})(\d{2})X(\d{2})(\d{2})\./', $pod, $m)) WP_CLI::error("Can't read the trim size from $pod");
	$w = ($m[1] + $m[2] / 100) * 72;
	$h = ($m[3] + $m[4] / 100) * 72;

	$lines = '';
	for ($y = $h - 72; $y > 54; $y -= 21.6) $lines .= sprintf("54 %.2f m %.2f %.2f l ", $y, $w - 54, $y);
	$stream = "0.75 G 0.5 w {$lines}S";

	$objects = [];
	$kids = [];
	$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objects[3] = '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream";
	for ($i = 0; $i < $pages; $i++) {
		$n = 4 + $i;
		$kids[] = "$n 0 R";
		$objects[$n] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /TrimBox [0 0 %.2f %.2f] /Contents 3 0 R /Resources << >> >>', $w, $h, $w, $h);
	}
	$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pages . ' >>';
	ksort($objects);

	$pdf = "%PDF-1.4\n";
	$offsets = [];
	foreach ($objects as $n => $body) {
		$offsets[$n] = strlen($pdf);
		$pdf .= "$n 0 obj\n$body\nendobj\n";
	}
	$xref = strlen($pdf);
	$pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
	foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
	$pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
	return $pdf;
}

function sandbox_product_id() {
	$ids = wc_get_products(['sku' => 'lulu-sandbox-notebook', 'return' => 'ids', 'limit' => 1]);
	return $ids[0] ?? 0;
}

switch ($command) {

	case 'configure':
		$pod = strtoupper($env('LULU_POD_PACKAGE_ID', '0600X0900.BW.STD.CO.060UW444.MXX'));
		$pages = (int) $env('LULU_PAGE_COUNT', 100);

		update_option('print_app_lulu_enabled', ($env('LULU_CLIENT_KEY') && $env('LULU_CLIENT_SECRET')) ? 'on' : '');
		update_option('print_app_lulu_environment', 'sandbox');
		update_option('print_app_lulu_client_key', $env('LULU_CLIENT_KEY'));
		update_option('print_app_lulu_client_secret', $env('LULU_CLIENT_SECRET'));
		update_option('print_app_lulu_shipping_level', 'MAIL');
		update_option('woocommerce_default_country', 'US:NY');
		update_option('woocommerce_currency', 'USD');

		$upload = wp_upload_bits("lulu-sandbox-interior-$pages.pdf", null, sandbox_interior_pdf($pod, $pages));
		if (!empty($upload['error'])) WP_CLI::error('Interior PDF: ' . $upload['error']);

		$product = ($id = sandbox_product_id()) ? wc_get_product($id) : new WC_Product_Simple();
		$product->set_name('Personalised Notebook (Lulu sandbox)');
		$product->set_sku('lulu-sandbox-notebook');
		$product->set_regular_price('24.00');
		$product->set_status('publish');
		$id = $product->save();

		update_post_meta($id, '_print_app_lulu_pod_package_id', $pod);
		update_post_meta($id, '_print_app_lulu_interior_url', $upload['url']);
		update_post_meta($id, '_print_app_lulu_page_count', $pages);
		delete_post_meta($id, '_print_app_lulu_cover_size');

		WP_CLI::log("Product #$id: $pod, $pages pages, interior " . $upload['url']);
		if (get_option('print_app_lulu_enabled') === 'on') {
			$size = \printapp\functions\lulu\cover_size($pod, $pages);
			if (is_wp_error($size)) WP_CLI::warning('Cover size: ' . $size->get_error_message());
			else {
				update_post_meta($id, '_print_app_lulu_cover_size', $size);
				WP_CLI::success("Lulu cover size for this product: $size (full spread: back, spine, front, with bleed)");
			}
		} else {
			WP_CLI::warning('No Lulu keys in .env.local yet: Send to Lulu stays off.');
		}
		break;

	case 'order':
		$project = $env('COVER_PROJECT_ID');
		if (!$project) WP_CLI::error('Set COVER_PROJECT_ID in .env.local to a Print.App design or project id.');
		$product = wc_get_product(sandbox_product_id());
		if (!$product) WP_CLI::error('Run configure first.');

		$order = wc_create_order();
		$item_id = $order->add_product($product, 1);
		$item = $order->get_item($item_id);
		$item->add_meta_data(PRINT_APP_CUSTOMIZATION_KEY, ['projectId' => $project, 'previews' => []], true);
		$item->save();

		$address = [
			'first_name' => 'Test', 'last_name' => 'Customer', 'company' => '',
			'address_1' => '101 Independence Ave SE', 'address_2' => '', 'city' => 'Washington',
			'state' => 'DC', 'postcode' => '20540', 'country' => 'US',
		];
		$order->set_address($address + ['email' => 'test-customer@example.com', 'phone' => '+1 202 555 0123'], 'billing');
		$order->set_address($address, 'shipping');
		$order->calculate_totals();
		$order->update_status('processing', 'Lulu sandbox test order.');	// fires Send to Lulu's queue_order

		update_option('lulu_sandbox_last_order', $order->get_id());
		WP_CLI::success('Order #' . $order->get_id() . " placed and marked paid, cover $project");
		break;

	case 'status':
		$order = wc_get_order((int) ($args[1] ?? get_option('lulu_sandbox_last_order')));
		if (!$order) WP_CLI::error('No test order yet.');
		WP_CLI::log('Order #' . $order->get_id() . ': Lulu job ' . ($order->get_meta('_print_app_lulu_job_id') ?: '-') . ', status ' . ($order->get_meta('_print_app_lulu_status') ?: '-'));
		foreach (array_reverse(wc_get_order_notes(['order_id' => $order->get_id()])) as $note) WP_CLI::log('  note: ' . $note->content);
		break;

	default:
		WP_CLI::error("Unknown command $command");
}
