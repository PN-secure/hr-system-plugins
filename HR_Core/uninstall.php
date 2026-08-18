<?php
/**
 * Skrypt odinstalowujący wtyczkę HR Core.
 * Uruchamiany automatycznie przy całkowitym usunięciu wtyczki.
 */

// Potrójne zabezpieczenie: kod wykona się tylko jeśli został wywołany przez rdzeń WP
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Tablica z nazwami naszych autorskich tabel
$hr_tables = array(
    $wpdb->prefix . 'hr_manager_relations',
    $wpdb->prefix . 'hr_employees',
    $wpdb->prefix . 'hr_roles',
    $wpdb->prefix . 'hr_departments'
);

// Pętla usuwająca fizycznie tabele z bazy danych (Kolejność nie ma tu większego znaczenia w MySQL bez wymuszonych kluczy obcych)
foreach ( $hr_tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Usunięcie flagi konfiguracyjnej z tabeli wp_options
delete_option( 'hr_core_db_version' );