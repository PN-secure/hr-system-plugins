<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Schema_Builder {

    /**
     * Główna metoda budująca strukturę tabel w bazie danych MySQL.
     */
    public static function create_tables() {
        global $wpdb;

        // Pobranie domyślnego kodowania znaków dla WordPressa (zwykle utf8mb4)
        $charset_collate = $wpdb->get_charset_collate();

        // Wymagane do użycia funkcji dbDelta()
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. TABELA: Działy w firmie (np. Marketing, IT, Zarząd)
        $table_departments = $wpdb->prefix . 'hr_departments';
        $sql_departments = "CREATE TABLE $table_departments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_departments );

        // 2. TABELA: Stanowiska (np. Junior Developer, Księgowa)
        $table_roles = $wpdb->prefix . 'hr_roles';
        $sql_roles = "CREATE TABLE $table_roles (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_roles );

        // 3. TABELA: Pracownicy (Główna tabela systemu HR)
        // Zauważ, że zamiast wpisywać nazwę działu tekstem, używamy ID z poprzednich tabel (relacje)
        $table_employees = $wpdb->prefix . 'hr_employees';
        $sql_employees = "CREATE TABLE $table_employees (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            department_id mediumint(9) DEFAULT NULL,
            role_id mediumint(9) DEFAULT NULL,
            employment_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY department_id (department_id),
            KEY role_id (role_id)
        ) $charset_collate;";
        dbDelta( $sql_employees );

        // 4. TABELA: Drzewo organizacyjne (Relacje Przełożony - Podwładny)
        // Kluczowe do generowania ścieżek akceptacji wniosków urlopowych
        $table_managers = $wpdb->prefix . 'hr_manager_relations';
        $sql_managers = "CREATE TABLE $table_managers (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            manager_id mediumint(9) NOT NULL,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY manager_id (manager_id)
        ) $charset_collate;";
        dbDelta( $sql_managers );

        // Zapisanie informacji o wersji bazy danych
        // Przyda się w przyszłości, gdy wypuścicie aktualizację dodającą nowe kolumny
        update_option( 'hr_core_db_version', HR_CORE_VERSION );
    }
}