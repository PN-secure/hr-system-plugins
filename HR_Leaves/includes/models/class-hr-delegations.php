<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Delegation {

    private static $table_suffix = 'hr_delegations';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    private static function get_employee_id_by_wp_user_id( $wp_user_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . $wpdb->prefix . 'hr_employees WHERE wp_user_id = %d LIMIT 1',
                absint( $wp_user_id )
            )
        );
    }

    public static function get_by_employee( $employee_id ) {
        global $wpdb;

        if ( 0 === $employee_id ) {
            return array();
        }

        $table_requests = self::get_table_name();
        $table_types    = $wpdb->prefix . 'hr_leave_types';

        $sql = "SELECT r.*, t.name AS leave_type_name
                FROM {$table_requests} r
                LEFT JOIN {$table_types} t ON r.leave_type_id = t.id
                WHERE r.employee_id = %d
                ORDER BY r.start_date DESC, r.id DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }

    public static function apply( $employee_id, $destination, $start_date, $end_date ) {
        global $wpdb;
        $table = self::get_table_name();

        if ( 0 === $employee_id ) {
            return new WP_Error( 'employee_not_found', 'Nie znaleziono pracownika w systemie HR.', array( 'status' => 404 ) );
        }

        if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
            return new WP_Error( 'invalid_dates', 'Data początkowa musi być wcześniejsza niż końcowa.' );
        }

        $table = $wpdb->prefix . 'hr_delegations';
        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id' => absint( $employee_id ),
                'destination' => $destination,
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'status'      => 'pending'
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Wystąpił błąd zapisu wniosku w bazie danych.' );
        }

        return $wpdb->insert_id;
    }

    public static function can_user_update_request( $request_id, $manager_employee_id ) {
        global $wpdb;

        $table_requests  = self::get_table_name();
        $table_relations = $wpdb->prefix . 'hr_manager_relations';

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1
                FROM {$table_requests} r
                INNER JOIN {$table_relations} mr
                    ON mr.employee_id = r.employee_id
                WHERE r.id = %d
                AND mr.manager_id = %d
                LIMIT 1",
                $request_id,
                $manager_employee_id
            )
        );

        return (bool) $result;
    }

    public static function change_status( $request_id, $new_status, $manager_id, $reject_reason = '' ) {
        global $wpdb;
        $table_requests = self::get_table_name();

        $allowed_statuses = array( 'approved', 'rejected' );
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table_requests,
            array( 
                'status' => $new_status,
                'reason' => sanitize_textarea_field( $reject_reason )
            ),
            array( 'id' => absint( $request_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    public static function get_requests_by_employee( $employee_id ) {
        global $wpdb;

        if ( 0 === $employee_id ) {
            return array();
        }

        $table_requests = self::get_table_name();

        $sql = "SELECT *
                FROM {$table_requests}
                WHERE employee_id = %d
                ORDER BY start_date DESC, id DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }
}