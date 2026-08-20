<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Development_Schema {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. TABELA: Oceny okresowe (Performance Reviews)
        $table_evaluations = $wpdb->prefix . 'hr_evaluations';
        $sql_evaluations = "CREATE TABLE $table_evaluations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            reviewer_id mediumint(9) NOT NULL,
            period varchar(50) NOT NULL, -- np. 'Q3 2026'
            score DECIMAL(3,1) DEFAULT NULL, -- Ocena liczbowa (np. 4.5)
            comments longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id),
            KEY reviewer_id (reviewer_id)
        ) $charset_collate;";
        dbDelta( $sql_evaluations );

        // 2. TABELA: Instant Feedback (Pochwały / Konstruktywna krytyka)
        $table_feedback = $wpdb->prefix . 'hr_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id mediumint(9) NOT NULL,
            receiver_id mediumint(9) NOT NULL,
            message longtext NOT NULL,
            is_anonymous boolean DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY receiver_id (receiver_id)
        ) $charset_collate;";
        dbDelta( $sql_feedback );

        // 3. TABELA: Spotkania 1-on-1 (Notatki menedżera)
        $table_1on1 = $wpdb->prefix . 'hr_one_on_ones';
        $sql_1on1 = "CREATE TABLE $table_1on1 (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            manager_id mediumint(9) NOT NULL,
            meeting_date date NOT NULL,
            notes longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY employee_id (employee_id)
        ) $charset_collate;";
        dbDelta( $sql_1on1 );

        update_option( 'hr_development_db_version', HR_DEVELOPMENT_VERSION );
    }
}