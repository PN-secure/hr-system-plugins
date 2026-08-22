<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Leaves_Schema {

    /**
     * Buduje strukturę tabel dla modułu absencji.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. TABELA: Typy urlopów (Słownik)
        // Pozwala firmie zdefiniować, czy dany urlop jest płatny i ile dni bazowo przysługuje
        $table_leave_types = $wpdb->prefix . 'hr_leave_types';
        $sql_types = "CREATE TABLE $table_leave_types (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            is_paid boolean DEFAULT 1,
            default_limit_days int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_types );

        // Dodanie podstawowych typów urlopów, jeśli tabela jest pusta (tzw. Seeding)
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_leave_types" );
        if ( $count == 0 ) {
            $wpdb->insert( $table_leave_types, array( 'name' => 'Wypoczynkowy', 'is_paid' => 1, 'default_limit_days' => 26 ) );
            $wpdb->insert( $table_leave_types, array( 'name' => 'L4 (Chorobowe)', 'is_paid' => 1, 'default_limit_days' => 33 ) );
            $wpdb->insert( $table_leave_types, array( 'name' => 'Bezpłatny', 'is_paid' => 0, 'default_limit_days' => 0 ) );
            $wpdb->insert( $table_leave_types, array( 'name' => 'Opieka nad dzieckiem', 'is_paid' => 1, 'default_limit_days' => 2 ) );
        }

        // 2. TABELA: Wnioski urlopowe
        // Łączy ID pracownika (z modułu Core) z ID urlopu oraz przetrzymuje status akceptacji
        $table_requests = $wpdb->prefix . 'hr_leave_requests';
        $sql_requests = "CREATE TABLE $table_requests (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            leave_type_id mediumint(9) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            working_days_used int(3) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            reason text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql_requests );

        // 3. TABELA: Ewidencja Czasu Pracy (Odbicia wejścia i wyjścia)
        $table_time_entries = $wpdb->prefix . 'hr_time_entries';
        $sql_time_entries = "CREATE TABLE $table_time_entries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            clock_in datetime NOT NULL,
            clock_out datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY clock_in (clock_in)
        ) $charset_collate;";
        dbDelta( $sql_time_entries );

        // 4. TABELA: Delegacje (Podróże służbowe)
        $table_delegations = $wpdb->prefix . 'hr_delegations';
        $sql_delegations = "CREATE TABLE $table_delegations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            destination varchar(255) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            status varchar(20) DEFAULT 'pending',
            reason text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id)
        ) $charset_collate;";
        dbDelta( $sql_delegations );

        update_option( 'hr_leaves_db_version', HR_LEAVES_VERSION );
    }
}