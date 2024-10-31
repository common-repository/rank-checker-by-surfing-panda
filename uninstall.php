<?php
/**
 * @package Internals
 *
 * Code used when the plugin is removed (not just deactivated but actively deleted through the WordPress Admin).
 */

//Remove the options
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

foreach ( array('sp_rc_num_days') as $option) {
	delete_option( $option );
}

//Remove the cron job
wp_clear_scheduled_hook('sp_rc_daily_event_hook');

//Remove the database table
global $wpdb;
$sp_rc_db_tablename = $wpdb->prefix . "sp_rc_rankdata";
$sql = "DROP TABLE $sp_rc_db_tablename;";
$wpdb->query($sql);

?>