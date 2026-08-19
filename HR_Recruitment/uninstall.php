<?php
/**
 * Skrypt czyszczący środowisko przy całkowitym usunięciu modułu HR Recruitment.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Usunięcie tabel z bazy danych
$recruitment_tables = array(
    $wpdb->prefix . 'hr_onboarding_tasks',
    $wpdb->prefix . 'hr_candidates',
    $wpdb->prefix . 'hr_jobs'
);

foreach ( $recruitment_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'hr_recruitment_db_version' );

// 2. Fizyczne usunięcie folderu z plikami CV kandydatów
$upload_dir = wp_upload_dir();
$cv_folder_path = $upload_dir['basedir'] . '/hr_cv';

if ( is_dir( $cv_folder_path ) ) {
    // PHP nie posiada wbudowanej funkcji do usuwania pełnego folderu, wymagana iteracja
    $files = array_diff( scandir( $cv_folder_path ), array( '.', '..' ) );
    foreach ( $files as $file ) {
        $file_path = $cv_folder_path . '/' . $file;
        if ( is_file( $file_path ) ) {
            unlink( $file_path );
        }
    }
    rmdir( $cv_folder_path );
}
