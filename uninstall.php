<?php
// called when the plugin is uninstalled (removed) via the "Plugins" screen

defined ('WP_UNINSTALL_PLUGIN') or die('Nope!');

// clear plugin data from DB
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . 'ipv_logs' );

delete_option( 'ipv_whitelist' );
delete_option( 'ipv_request_includes' );
delete_option( 'ipv_request_excludes' );
delete_option( 'ipv_log_keep_days' );
delete_option( 'ipv_home_path' );
delete_option( 'ipv_gdpr_ips' );
delete_option( 'ipv_use_asn' );
delete_option( 'ipv_auth_slug' );
delete_option( 'ipv_modus' );
delete_option( 'ipv_count_blocked' );


?>
