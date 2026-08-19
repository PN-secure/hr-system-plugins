<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Job {

    private static $table_suffix = 'hr_jobs';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Pobiera tylko aktywne oferty pracy.
     * Metoda dedykowana dla publicznego widgetu (Zakładka Kariera).
     */
    public static function get_active_jobs() {
        global $wpdb;
        $table_jobs = self::get_table_name();
        $table_dept = $wpdb->prefix . 'hr_departments';

        // Dołączenie nazwy działu z wtyczki HR Core
        $sql = "SELECT j.id, j.title, j.description, j.created_at, d.name AS department_name 
                FROM {$table_jobs} j
                LEFT JOIN {$table_dept} d ON j.department_id = d.id
                WHERE j.status = 'open' 
                ORDER BY j.created_at DESC";

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Pobiera wszystkie oferty (aktywne i zamknięte).
     * Metoda dla panelu administracyjnego HR.
     */
    public static function get_all_jobs() {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT * FROM {$table} ORDER BY created_at DESC";
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Tworzy nowe ogłoszenie o pracę.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'title'         => sanitize_text_field( $data['title'] ),
                'description'   => wp_kses_post( $data['description'] ), // Zezwala na bezpieczny HTML (np. pogrubienia)
                'department_id' => isset( $data['department_id'] ) ? absint( $data['department_id'] ) : null,
                'status'        => 'open'
            ),
            array( '%s', '%s', '%d', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Zmienia status ogłoszenia (np. zamyka rekrutację).
     */
    public static function change_status( $job_id, $new_status ) {
        global $wpdb;
        $table = self::get_table_name();

        $allowed_statuses = array( 'open', 'closed' );
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            array( 'status' => $new_status ),
            array( 'id' => absint( $job_id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }
}
