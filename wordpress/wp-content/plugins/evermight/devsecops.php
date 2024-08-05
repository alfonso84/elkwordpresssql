<?php
/*
 * Plugin Name: Elasticsearch WordPress Plugin Monitor
 * Description: Send information about out of date WordPress plugins to Elasticsearch
 * Author: devsecops
 * Author URI: https://devsecops.com
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
add_action( 'rest_api_init', 'devsecops_api_plugin_check');

define('ES_URL', 'https://es01:9200');
define('ES_USER', 'myagent');
define('ES_PASS', 'changeme');
define('PING_INTERVAL', 60*60*12); // minimum number of seconds to wait before next ES submission
define('PING_FILE_NAME', realpath(dirname(__FILE__)).'/.last-ping');
define('CA_CRT_FILE', '/etc/certs/es/ca.crt');

function devsecops_api_plugin_check(){
    register_rest_route( 'devsecops/v1', '/plugin-check', array(
        'methods' => 'GET',
        'callback' => 'devsecops_plugin_check',
    ));
}

function devsecops_greater_than_ping_interval() {
	return !file_exists(PING_FILE_NAME) || ( time() - filectime(PING_FILE_NAME) > PING_INTERVAL );
}

function devsecops_send_to_es($data) {
	$url = ES_URL . '/wp-plugins/_doc';
	$ch = curl_init( $url );
	# Setup request to send json via POST.
	$payload = json_encode( $data );
	curl_setopt( $ch, CURLOPT_CAINFO, CA_CRT_FILE );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_USERPWD, ES_USER . ':' . ES_PASS );
	# Return response instead of printing.
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	# Send request.
	$result = curl_exec($ch);
	curl_close($ch);
	if(WP_DEBUG) {
		print_r($result);
	}
}

function devsecops_get_plugin_updates(){
	$all_plugins     = get_plugins();
	$upgrade_plugins = array();
	$current         = get_site_transient( 'update_plugins' );

	foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
		if ( isset( $current->response[ $plugin_file ] ) ) {
			$plugin = (object) $plugin_data;
			$plugin_update = $current->response[ $plugin_file ];
			$item = [
				'name' => $plugin->Name,
				'version_current' => $plugin->Version,
				'version_new' => $plugin_update->new_version,
				'tested_on' => $plugin_update->tested,
				'require_wp' => $plugin_update->requires,
				'require_php' => $plugin_update->requires_php,
			];
			$upgrade_plugins[] = $item;
			//$upgrade_plugins[ $plugin_file ]         = (object) $plugin_data;
			//$upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
		}
	}
	return [
		'timestamp' => gmdate('Y-m-d') . 'T' . gmdate('H:i:s') . '.000000Z',
		'site_url' => get_site_url(),
		'hostname' => gethostname(),
		'outdated_plugin_count' => count($upgrade_plugins),
		'plugins' => $upgrade_plugins
	];
}

function devsecops_plugin_check($data){
	$plugins = devsecops_get_plugin_updates();
	if(devsecops_greater_than_ping_interval()) {
		devsecops_send_to_es($plugins);
		file_put_contents(PING_FILE_NAME,time());
	}
	return array();
}
