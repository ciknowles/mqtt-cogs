<?php
    // if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {die;}
 
delete_option('MqttCogs_Plugin__installed');
delete_option('MqttCogs_Plugin__version');
 
// for site options in Multisite
//delete_site_option($option_name);
 
// drop a custom database table
global $wpdb;
//error_log("DROP TABLE IF EXISTS {$wpdb->prefix}data");
//error_log("DROP TABLE IF EXISTS {$wpdb->prefix}buffer");

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mqttcogs_plugin_buffer");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mqttcogs_plugin_data");
?>