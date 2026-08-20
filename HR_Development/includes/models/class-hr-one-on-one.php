<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_One_On_One {

    private static $table_suffix = 'hr_one_on_ones';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Zapisuje nową notatkę ze spotkania 1-na-1.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id'  => absint( $data['employee_id'] ),
                'manager_id'   => absint( $data['manager_id'] ),
                'meeting_date' => sanitize_text_field( $data['meeting_date'] ),
                'notes'        => wp_kses_post( $data['notes'] ) // Zezwala na bezpieczny HTML
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Pobiera historię spotkań dla danego pracownika.
     */
    public static function get_by_employee( $employee_id ) {
        global $wpdb;
        $table_1on1 = self::get_table_name();
        $table_emp = $wpdb->prefix . 'hr_employees';

        $sql = "SELECT o.id, o.meeting_date, o.notes, o.created_at, 
                       m.first_name AS manager_first_name, 
                       m.last_name AS manager_last_name
                FROM {$table_1on1} o
                LEFT JOIN {$table_emp} m ON o.manager_id = m.id
                WHERE o.employee_id = %d
                ORDER BY o.meeting_date DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }
}