<?php

	namespace printapp\functions\admin;

	function format_print_app_order_key($display_value, $meta, $order_item) {
		
		if ($meta->key === 'preview' || $meta->key === '_pda_w2p_set_option') return 'PrintApp Customization';
		return $display_value;
		
	}

	function format_print_app_order_value($formatted_meta, $order_item) {
		// Check if the current request is for an email
		if (did_action('woocommerce_email_order_details')) {
			return $formatted_meta;
		}

		foreach ($formatted_meta as $meta) {
			if ($meta->key === PRINT_APP_CUSTOMIZATION_PREVIEWS_KEY) {

				$item_meta_data = $order_item->get_meta_data();
				if (empty($item_meta_data)) return $formatted_meta;

				foreach ($item_meta_data as $item_meta) {
					if ($item_meta->key === PRINT_APP_CUSTOMIZATION_KEY) {
						$print_app_customization = $item_meta->value;

						$post_fix = $print_app_customization['projectId'];
						$previews = '';
						foreach ($print_app_customization['previews'] as $preview) {
							$previews .= '<img src="' . $preview['url'] . '" width="180px; margin-right:10px;"/>';
						}

						$display = '
							<div class="print_app_order_meta" style="display: flex;">
								<div data-project-id="' . $print_app_customization["projectId"] . '" class="pda_show_preview" style="display:flex; flex-wrap: wrap;">
									' . $previews . '
								</div>
								<div>
									<a target="_blank" href="https://pdf.print.app/'. $post_fix .'">• Download PDF</a><br/>
									<a target="_blank" href="https://png.print.app/'. $post_fix .'">• Download PNG</a><br/>
									<a target="_blank" href="https://jpg.print.app/'. $post_fix .'">• Download JPEG</a><br/>
									<a target="_blank" href="https://tiff.print.app/'. $post_fix .'">• Download TIFF</a><br/>
									<a target="_blank" href="https://admin.print.app/projects/'. $post_fix .'">• Modify Project</a>
								</div>
							</div>';

						$meta->display_value = $display;
						break;
					}
				}

				break;
			}
		}
		
		return $formatted_meta;
	}

	function handle_new_order( $order_id, $order ) {
		$items = $order->get_items();
		$project_ids = [];
		
		$user_data = [
            'id' => $order->get_user_id(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
			'info' => 'Order Id: ' . $order_id,
        ];

		foreach ( $items as $item ) {
			foreach ( $item->get_meta_data() as $item_meta ) {
				if ( $item_meta->key === PRINT_APP_CUSTOMIZATION_KEY ) {
					$print_app_customization = $item_meta->value;

					if ( !empty($print_app_customization['projectId']) ) {
						$project_ids[] = $print_app_customization['projectId'];
					}
				}
			}
		}

		if ( count($project_ids) ) {
			$auth_key = 'Bearer ' . get_option('print_app_secret_key');

			$response = wp_remote_post(PRINT_APP_UPDATE_USER_ENDPOINT, [
				'headers' => [ 'Authorization' => $auth_key ],
				'body'    => json_encode([
					'projectIds' => $project_ids,
					'user'     => $user_data,
				]),
				'method'  => 'POST',
			]);

			if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ) {
				error_log( '[PRINTAPP] Failed to append user ID to projects: ' . print_r($response, true) );
			}
		}
	}

	// this is for backward compatibility v1, to display old orders pre-installing this version
	function print_app_filter_wc_order_item_display_meta_value( $display_value, $meta ) {
		if ( $meta->key === '_pda_w2p_set_option' ) {
			$pda_data = json_decode($display_value, true);
			
			$auth_key = get_option('print_app_secret_key');

			if (!empty($pda_data['projectId'])) {
				$hash = md5( $pda_data['projectId'] . $auth_key );
				$post_fix = $pda_data['projectId'] . '!' . $hash;

				return '
					<div class="print_app_order_meta" style="display: flex;">
						<div onclick="pda_show_preview(this)" data-project-id="' . $pda_data["projectId"] . '" class="pda_show_preview" style="margin-right: 10px;">
							<img src="' . $pda_data['previews'][0]['url'] . '" width="180px"/>
							<div>
								<svg xmlns="http://www.w3.org/2000/svg" class="icon-tabler icon-tabler-search" width="22px" height="22px" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" data-v-09078359="">   <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>   <circle cx="10" cy="10" r="7"></circle>   <line x1="21" y1="21" x2="15" y2="15"></line> </svg>
							</div>
						</div>
						<div>
							<a target="_blank" href="https://pdf.print.app/'. $post_fix .'">Download PDF</a><br/>
							<a target="_blank" href="https://png.print.app/'. $post_fix .'">Download PNG</a><br/>
							<a target="_blank" href="https://jpg.print.app/'. $post_fix .'">Download JPEG</a><br/>
							<a target="_blank" href="https://tiff.print.app/'. $post_fix .'">Download TIFF</a><br/>
							<a target="_blank" href="https://admin.print.app/projects/'. $pda_data['projectId'] .'">Modify Project</a>
						</div>
					</div>';
			} else if (!empty($pda_data['form'])) {
				print_r($pda_data);
			}
		}
		return $display_value;  
	}