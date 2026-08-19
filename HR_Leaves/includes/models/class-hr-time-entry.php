<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Time_Entry {

    private static $table_suffix = 'hr_time_entries';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Rozpoczęcie pracy (Clock In).
     */
    public static function clock_in( $employee_id, $ip_address ) {
        global $wpdb;
        $table = self::get_table_name();

        // Sprawdzamy, czy pracownik nie jest już "w pracy" (brak zamkniętego statusu clock_out)
        $active_session = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM {$table} WHERE employee_id = %d AND clock_out IS NULL", 
            absint( $employee_id ) 
        ) );

        if ( $active_session ) {
            return new WP_Error( 'already_clocked_in', 'Masz już otwartą sesję pracy.' );
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id' => absint( $employee_id ),
                'clock_in'    => current_time( 'mysql', 1 ), // Czas UTC na serwerze
                'ip_address'  => sanitize_text_field( $ip_address )
            ),
            array( '%d', '%s', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Zakończenie pracy (Clock Out).
     */
    public static function clock_out( $employee_id ) {
        global $wpdb;
        $table = self::get_table_name();

        // Szukamy otwartej sesji dla tego pracownika
        $active_session_id = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM {$table} WHERE employee_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1", 
            absint( $employee_id ) 
        ) );

        if ( ! $active_session_id ) {
            return new WP_Error( 'not_clocked_in', 'Nie masz otwartej sesji pracy, którą mógłbyś zakończyć.' );
        }

        $updated = $wpdb->update(
            $table,
            array( 'clock_out' => current_time( 'mysql', 1 ) ),
            array( 'id' => $active_session_id ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Pobiera ewidencję pracownika z konkretnego miesiąca.
     */
    public static function get_monthly_timesheet( $employee_id, $year, $month ) {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT id, clock_in, clock_out, ip_address 
                FROM {$table} 
                WHERE employee_id = %d 
                AND YEAR(clock_in) = %d 
                AND MONTH(clock_in) = %d
                ORDER BY clock_in ASC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ), absint( $year ), absint( $month ) ), ARRAY_A );
    }
}