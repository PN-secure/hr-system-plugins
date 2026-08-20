<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Feedback {

    private static $table_suffix = 'hr_feedback';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Zapisuje nową wiadomość feedbacku.
     */
    public static function send( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'sender_id'    => absint( $data['sender_id'] ),
                'receiver_id'  => absint( $data['receiver_id'] ),
                'message'      => sanitize_textarea_field( $data['message'] ),
                'is_anonymous' => absint( $data['is_anonymous'] )
            ),
            array( '%d', '%d', '%s', '%d' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Pobiera feedback otrzymany przez pracownika, uwzględniając anonimowość.
     */
    public static function get_received( $employee_id ) {
        global $wpdb;
        $table_feedback = self::get_table_name();
        $table_emp = $wpdb->prefix . 'hr_employees';

        // Logika SQL: Jeśli is_anonymous = 1, nadpisz dane nadawcy wartością NULL lub tekstem "Anonim"
        $sql = "SELECT f.id, f.message, f.created_at, f.is_anonymous,
                       IF(f.is_anonymous = 1, 'Ktoś', sender.first_name) AS sender_first_name,
                       IF(f.is_anonymous = 1, 'Anonimowy', sender.last_name) AS sender_last_name
                FROM {$table_feedback} f
                LEFT JOIN {$table_emp} sender ON f.sender_id = sender.id
                WHERE f.receiver_id = %d
                ORDER BY f.created_at DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }
}