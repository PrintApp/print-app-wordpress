<?php

    namespace printapp\functions\general;

    function order_email($order, $sent_to_admin, $plain_text, $email) {

        $items = $order->get_items();

        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            $print_app_customization = $item->get_meta(PRINT_APP_CUSTOMIZATION_KEY, true);
            if (empty($print_app_customization)) continue;

            $count = 0;

            foreach ($print_app_customization['previews'] as $preview) {
                if ($count >= 3) break;
                echo '<tr><td colspan="2" style="text-align:left; padding: 10px 0;"><img src="' . $preview['url'] . '" width="180px; margin-right:10px;"/></td></tr>';
                $count++;
            }

            $include_download_link = get_option('print_app_cust_download_link') == 'on';

            if ($sent_to_admin || $include_download_link) {
                $post_fix = $print_app_customization['projectId'];
                echo '<tr><td colspan="2" style="text-align:left; padding: 10px 0;"><a href="https://pdf.print.app/' . $post_fix . '">Download Customization PDF</a></td></tr>';
            }

        }
    }