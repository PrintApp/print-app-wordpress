<?php

namespace printapp\functions\general;

function fetch_credentials() {
	
	$printapp_domain_key = get_option('print_app_domain_key');
	$printapp_secret_key = get_option('print_app_secret_key');
	
	if (empty($printapp_domain_key) || empty($printapp_secret_key)) return;
	
	$timestamp = time();
	
	return array( 'signature' => md5($printapp_domain_key . $printapp_secret_key . $timestamp), 'timestamp' => $timestamp);
}
