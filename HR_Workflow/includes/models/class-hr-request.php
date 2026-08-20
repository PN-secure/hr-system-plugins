<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Request {

    private static $table_suffix = 'hr_requests';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Złożenie nowego wniosku przez pracownika.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id'  => absint( $data['employee_id'] ),
                'request_type' => sanitize_text_field( $data['request_type'] ),
                'description'  => wp_kses_post( $data['description'] ), // Dopuszcza formatowanie tekstu
                'status'       => 'pending'
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Pobiera historię wniosków dla konkretnego pracownika.
     */
    public static function get_by_employee( $employee_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT * FROM {$table} WHERE employee_id = %d ORDER BY created_at DESC";
        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }

    /**
     * Pobiera wnioski oczekujące na decyzję dla całego zespołu danego menedżera.
     * Wymaga integracji z modelem z wtyczki HR Core.
     */
    public static function get_pending_for_manager( $manager_id ) {
        global $wpdb;
        $table_requests = self::get_table_name();
        
        // Zabezpieczenie na wypadek braku modułu Core
        if ( ! class_exists( 'HR_Manager_Relation' ) ) {
            return array();
        }

        $team = HR_Manager_Relation::get_subordinates( $manager_id );
        if ( empty( $team ) ) {
            return array();
        }

        $team_ids = array_column( $team, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );

        // Pobieramy tylko wnioski oczekujące ('pending') dla osób z zespołu
        $sql = "SELECT r.*, e.first_name, e.last_name 
                FROM {$table_requests} r
                JOIN {$wpdb->prefix}hr_employees e ON r.employee_id = e.id
                WHERE r.status = 'pending' AND r.employee_id IN ($placeholders)
                ORDER BY r.created_at ASC";

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$team_ids ), ARRAY_A );
    }

    /**
     * Zmiana statusu wniosku (Akceptacja / Odrzucenie / Zakończenie).
     */
    public static function change_status( $request_id, $new_status, $manager_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $allowed_statuses = array( 'approved', 'rejected', 'completed' );
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            array( 
                'status'     => $new_status,
                'manager_id' => absint( $manager_id )
            ),
            array( 'id' => absint( $request_id ) ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        return $updated !== false;
    }
}