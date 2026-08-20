<?php
/**
 * Skrypt odinstalowujący wtyczkę HR Workflow & Admin.
 * Usuwa tabele administracyjne oraz fizyczne skany dokumentów finansowych.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Usunięcie tabel z bazy danych
$workflow_tables = array(
    $wpdb->prefix . 'hr_contracts',
    $wpdb->prefix . 'hr_invoices',
    $wpdb->prefix . 'hr_requests'
);

foreach ( $workflow_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'hr_workflow_db_version' );

// 2. Fizyczne usunięcie folderu ze skanami faktur
$upload_dir = wp_upload_dir();
$invoices_folder_path = $upload_dir['basedir'] . '/hr_invoices';

if ( is_dir( $invoices_folder_path ) ) {
    // Pobranie listy plików wewnątrz katalogu
    $files = array_diff( scandir( $invoices_folder_path ), array( '.', '..' ) );
    
    // Usunięcie każdego pliku z osobna
    foreach ( $files as $file ) {
        $file_path = $invoices_folder_path . '/' . $file;
        if ( is_file( $file_path ) ) {
            unlink( $file_path );
        }
    }
    
    // Usunięcie pustego już katalogu
    rmdir( $invoices_folder_path );
}