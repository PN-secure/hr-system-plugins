<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Recruitment_Schema {

    /**
     * Buduje strukturę tabel MySQL dla systemu ATS.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Wymagane do bezpiecznej aktualizacji schematu bazy danych
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. TABELA: Oferty pracy
        $table_jobs = $wpdb->prefix . 'hr_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description longtext NOT NULL,
            department_id mediumint(9) DEFAULT NULL,
            status varchar(20) DEFAULT 'open',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY department_id (department_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql_jobs );

        // 2. TABELA: Kandydaci (Aplikacje)
        // Pole 'stage' posłuży do wizualizacji na tablicy Kanban (np. 'new', 'phone_screen', 'interview', 'hired', 'rejected')
        $table_candidates = $wpdb->prefix . 'hr_candidates';
        $sql_candidates = "CREATE TABLE $table_candidates (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            job_id mediumint(9) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT '',
            cv_path varchar(255) NOT NULL,
            stage varchar(50) DEFAULT 'new',
            rating tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY stage (stage)
        ) $charset_collate;";
        dbDelta( $sql_candidates );

        // 3. TABELA: Zadania Onboardingowe
        // Kiedy kandydat przechodzi w status 'hired', system skopiuje mu tutaj szablony zadań wdrożeniowych
        $table_onboarding = $wpdb->prefix . 'hr_onboarding_tasks';
        $sql_onboarding = "CREATE TABLE $table_onboarding (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            task_title varchar(255) NOT NULL,
            is_completed boolean DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id)
        ) $charset_collate;";
        dbDelta( $sql_onboarding );

        // Zapisanie wersji bazy danych w opcjach WordPressa
        update_option( 'hr_recruitment_db_version', HR_RECRUITMENT_VERSION );
    }
}