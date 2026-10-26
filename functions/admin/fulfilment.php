<?php

	namespace printapp\functions\admin;

	use printapp\functions\lulu;

	// Fulfilment providers: optional services that paid orders can be sent to for printing and
	// shipping. They sit at the bottom of the settings page in one collapsed section, each in
	// its own collapsed panel, so a shop that uses none never has to read about them.
	//
	// A provider registers its fields on its own settings page slug (in the print_app option
	// group, so the one settings form saves them) and adds an entry here.
	function fulfilment_providers() {
		$lulu = get_option('print_app_lulu_enabled') === 'on';
		return [
			'lulu' => [
				'title'   => __('Lulu', 'printapp'),
				'summary' => __('print-on-demand books, notebooks and journals', 'printapp'),
				'page'    => 'print_app_lulu',
				'status'  => $lulu ? (lulu\environment() === 'sandbox' ? __('On, sandbox', 'printapp') : __('On', 'printapp')) : __('Off', 'printapp'),
				'on'      => $lulu,
			],
		];
	}

	function fulfilment_section() {
		echo '<style>
			.print-app-fulfilment { margin-top: 2em; }
			.print-app-fulfilment > summary { cursor: pointer; }
			.print-app-fulfilment > summary h2 { display: inline; }
			.print-app-provider { margin: 1em 0; padding: .5em 1em; background: #fff; border: 1px solid #c3c4c7; }
			.print-app-provider > summary { cursor: pointer; padding: .25em 0; }
			.print-app-provider-status { margin-left: .5em; padding: 1px 8px; border-radius: 10px; background: #f0f0f1; font-size: 12px; }
			.print-app-provider-status.is-on { background: #d1e7dd; }
		</style>';

		echo '<details class="print-app-fulfilment"><summary><h2>' . esc_html__('Fulfilment providers', 'printapp') . '</h2> '
			. '<span class="description">' . esc_html__('(optional)', 'printapp') . '</span></summary>'
			. '<p>' . esc_html__('Send paid orders straight to a print and fulfilment service, with the customer\'s design. Each provider stays off until you set it up.', 'printapp') . '</p>';

		foreach (fulfilment_providers() as $provider) {
			echo '<details class="print-app-provider"><summary><strong>' . esc_html($provider['title']) . '</strong>: ' . esc_html($provider['summary'])
				. '<span class="print-app-provider-status' . ($provider['on'] ? ' is-on' : '') . '">' . esc_html($provider['status']) . '</span></summary>';
			do_settings_sections($provider['page']);
			echo '</details>';
		}

		echo '</details>';
	}
