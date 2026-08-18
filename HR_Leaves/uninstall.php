<?php
/**
 * Skrypt odinstalowujący wtyczkę HR Leaves.
 * Usuwa tabele związane wyłącznie z wnioskami urlopowymi.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$leaves_tables = array(
    $wpdb->prefix . 'hr_leave_requests',
    $wpdb->prefix . 'hr_leave_types'
);

foreach ( $leaves_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'hr_leaves_db_version' );