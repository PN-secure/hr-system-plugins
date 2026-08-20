<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Contract {

    private static $table_suffix = 'hr_contracts';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Dodanie nowej umowy dla pracownika.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id'   => absint( $data['employee_id'] ),
                'contract_type' => sanitize_text_field( $data['contract_type'] ),
                'start_date'    => sanitize_text_field( $data['start_date'] ),
                'end_date'      => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
                'salary'        => floatval( $data['salary'] ),
                'is_active'     => 1
            ),
            array( '%d', '%s', '%s', '%s', '%f', '%d' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Pobiera aktywne umowy dla podanego pracownika.
     */
    public static function get_by_employee( $employee_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT * FROM {$table} WHERE employee_id = %d AND is_active = 1 ORDER BY start_date DESC";
        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }

    /**
     * Funkcjonalność powiadomień: Pobiera umowy, które kończą się w ciągu najbliższych X dni.
     * Używane przez dashboard HR, aby zaplanować rozmowy o przedłużeniu współpracy.
     *
     * @param int $days Liczba dni do wygaśnięcia (domyślnie 30)
     */
    public static function get_expiring_contracts( $days = 30 ) {
        global $wpdb;
        $table_contracts = self::get_table_name();
        $table_emp = $wpdb->prefix . 'hr_employees';

        // Obliczenie granicznej daty wyszukiwania
        $threshold_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );
        $today = date( 'Y-m-d' );

        $sql = "SELECT c.*, e.first_name, e.last_name 
                FROM {$table_contracts} c
                JOIN {$table_emp} e ON c.employee_id = e.id
                WHERE c.is_active = 1 
                  AND c.end_date IS NOT NULL 
                  AND c.end_date BETWEEN %s AND %s
                ORDER BY c.end_date ASC";

        return $wpdb->get_results( $wpdb->prepare( $sql, $today, $threshold_date ), ARRAY_A );
    }

    /**
     * Zakończenie umowy (np. po zwolnieniu lub przejściu na nową umowę).
     */
    public static function terminate( $contract_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $updated = $wpdb->update(
            $table,
            array( 'is_active' => 0 ),
            array( 'id' => absint( $contract_id ) ),
            array( '%d' ),
            array( '%d' )
        );

        return $updated !== false;
    }
}

