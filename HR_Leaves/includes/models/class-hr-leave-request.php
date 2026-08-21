<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Leave_Request {

    private static $table_suffix = 'hr_leave_requests';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
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

    /**
     * Główny proces składania wniosku urlopowego.
     */
    public static function apply( $employee_id, $leave_type_id, $start_date, $end_date, $reason = '' ) {
        global $wpdb;
        $table_requests = self::get_table_name();
        $table_types = $wpdb->prefix . 'hr_leave_types';

        if ( 0 === $employee_id ) {
            return new WP_Error( 'employee_not_found', 'Nie znaleziono pracownika w systemie HR.', array( 'status' => 404 ) );
        }

        // 1. Sprawdzenie, czy daty mają logiczny sens
        if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
            return new WP_Error( 'invalid_dates', 'Data początkowa musi być wcześniejsza niż końcowa.' );
        }

        // 2. Wyliczenie fizycznych dni roboczych (używamy silnika z poprzedniego pliku)
        $working_days_requested = HR_Calculator::get_working_days( $employee_id, $start_date, $end_date );

        if ( $working_days_requested === 0 ) {
            return new WP_Error( 'zero_days', 'Wybrany okres to same weekendy lub święta. Wniosek odrzucony.' );
        }

        // 3. Sprawdzenie limitu urlopowego pracownika
        $type_info = $wpdb->get_row( $wpdb->prepare( "SELECT default_limit_days FROM {$table_types} WHERE id = %d", $leave_type_id ) );
        
        if ( ! $type_info ) {
            return new WP_Error( 'invalid_type', 'Taki typ urlopu nie istnieje w systemie.' );
        }

        // Jeśli typ urlopu ma zdefiniowany limit > 0 (czyli np. 26 dni Wypoczynkowego)
        if ( $type_info->default_limit_days > 0 ) {
            $current_year = date( 'Y', strtotime( $start_date ) );
            $used_days = self::get_used_days_in_year( $employee_id, $leave_type_id, $current_year );
            
            $days_left = $type_info->default_limit_days - $used_days;

            if ( $working_days_requested > $days_left ) {
                return new WP_Error( 
                    'limit_exceeded', 
                    sprintf( 'Przekroczono limit roczny. Zostało Ci %d dni, a wnioskujesz o %d.', $days_left, $working_days_requested )
                );
            }
        }

        // 4. Jeśli wszystko gra, zapisujemy wniosek ze statusem oczekującym (pending)
        $inserted = $wpdb->insert(
            $table_requests,
            array(
                'employee_id'        => absint( $employee_id ),
                'leave_type_id'      => absint( $leave_type_id ),
                'start_date'         => sanitize_text_field( $start_date ),
                'end_date'           => sanitize_text_field( $end_date ),
                'working_days_used'  => $working_days_requested,
                'status'             => 'pending',
                'reason'             => sanitize_textarea_field( $reason )
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Wystąpił błąd zapisu wniosku w bazie danych.' );
        }

        return $wpdb->insert_id;
    }

    /**
     * Zmiana statusu wniosku przez menedżera (Akceptacja / Odrzucenie).
     */
    public static function change_status( $request_id, $new_status, $manager_id, $reject_reason = '' ) {
        global $wpdb;
        $table_requests = self::get_table_name();

        $allowed_statuses = array( 'approved', 'rejected' );
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            return false;
        }

        // Aktualizacja rekordu w bazie
        $updated = $wpdb->update(
            $table_requests,
            array( 
                'status' => $new_status,
                'reason' => sanitize_textarea_field( $reject_reason ) // Ewentualny powód odrzucenia
            ),
            array( 'id' => absint( $request_id ) ), // WHERE id = X
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Funkcja pomocnicza: Oblicza ile dni ZATWIERDZONEGO urlopu pracownik już wykorzystał w danym roku.
     */
    private static function get_used_days_in_year( $employee_id, $leave_type_id, $year ) {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT SUM(working_days_used) 
                FROM {$table} 
                WHERE employee_id = %d 
                AND leave_type_id = %d 
                AND status = 'approved' 
                AND YEAR(start_date) = %d";

        $used = $wpdb->get_var( $wpdb->prepare( $sql, $employee_id, $leave_type_id, $year ) );

        return (int) $used;
    }

    // Sprawdzenie, czy menedżer może zmienić status wniosku pracownika
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
}