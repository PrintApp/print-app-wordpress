<?php

    namespace printapp\functions\general;

    function order_email($order, $sent_to_admin, $plain_text, $email) {

        $items = $order->get_items();
        $include_download_link = get_option('print_app_cust_download_link') == 'on';

        foreach ($items as $item_id => $item) {
            $print_app_customization = $item->get_meta(PRINT_APP_CUSTOMIZATION_KEY, true);
            if (empty($print_app_customization)) continue;

            $project_id = isset($print_app_customization['projectId']) ? $print_app_customization['projectId'] : '';
            $previews = isset($print_app_customization['previews']) && is_array($print_app_customization['previews'])
                ? array_slice($print_app_customization['previews'], 0, 3)
                : [];
            $show_download_link = ($sent_to_admin || $include_download_link) && !empty($project_id);
            $download_url = 'https://pdf.print.app/' . $project_id;

            if ($plain_text) {
                foreach ($previews as $preview) {
                    if (!empty($preview['url'])) echo esc_url($preview['url']) . "\n";
                }
                if ($show_download_link) {
                    echo __('Download Customization PDF', 'printapp') . ': ' . esc_url($download_url) . "\n";
                }
                echo "\n";
                continue;
            }

            // The woocommerce_email_order_details hook fires outside of any <table>, so the
            // output must be a self-contained block. Bare <tr>/<td> tags break the email wrapper.
            echo '<table cellspacing="0" cellpadding="0" border="0" style="width:100%; border-collapse:collapse; margin:0 0 10px 0;">';

            if (!empty($previews)) {
                echo '<tr><td style="text-align:left; padding:10px 0;">';
                foreach ($previews as $preview) {
                    if (empty($preview['url'])) continue;
                    echo '<img src="' . esc_url($preview['url']) . '" width="180" style="width:180px; max-width:100%; height:auto; margin-right:10px; vertical-align:top;" alt="" />';
                }
                echo '</td></tr>';
            }

            if ($show_download_link) {
                echo '<tr><td style="text-align:left; padding:10px 0;"><a href="' . esc_url($download_url) . '">' . __('Download Customization PDF', 'printapp') . '</a></td></tr>';
            }

            echo '</table>';
        }
    }
