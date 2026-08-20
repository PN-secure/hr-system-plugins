<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$development_tables = array(
    $wpdb->prefix . 'hr_one_on_ones',
    $wpdb->prefix . 'hr_feedback',
    $wpdb->prefix . 'hr_evaluations'
);

foreach ( $development_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'hr_development_db_version' );
