<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Workflow_Schema {

    /**
     * Główna funkcja budująca strukturę tabel dla modułu administracyjnego.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. TABELA: Wnioski pracownicze (Zapotrzebowanie na sprzęt, zaświadczenia itp.)
        $table_requests = $wpdb->prefix . 'hr_requests';
        $sql_requests = "CREATE TABLE $table_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            request_type varchar(50) NOT NULL, -- np. 'equipment', 'certificate', 'remote_work'
            description longtext NOT NULL,
            status varchar(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'completed'
            manager_id mediumint(9) DEFAULT NULL, -- Kto podjął decyzję
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql_requests );

        // 2. TABELA: Faktury kosztowe (Rozliczanie wydatków, delegacji, zakupów)
        $table_invoices = $wpdb->prefix . 'hr_invoices';
        $sql_invoices = "CREATE TABLE $table_invoices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            invoice_number varchar(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'PLN',
            description text NOT NULL,
            file_path varchar(255) NOT NULL, -- Ścieżka do bezpiecznego folderu ze skanem
            status varchar(20) DEFAULT 'pending', -- 'pending', 'manager_approved', 'paid', 'rejected'
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id)
        ) $charset_collate;";
        dbDelta( $sql_invoices );

        // 3. TABELA: Rejestr umów (Kontrola terminów)
        $table_contracts = $wpdb->prefix . 'hr_contracts';
        $sql_contracts = "CREATE TABLE $table_contracts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            contract_type varchar(50) NOT NULL, -- np. 'UoP', 'B2B', 'UZ'
            start_date date NOT NULL,
            end_date date DEFAULT NULL, -- NULL oznacza umowę na czas nieokreślony
            salary DECIMAL(10,2) DEFAULT NULL,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY end_date (end_date)
        ) $charset_collate;";
        dbDelta( $sql_contracts );

        // Zapisanie wersji bazy danych
        update_option( 'hr_workflow_db_version', HR_WORKFLOW_VERSION );
    }
}