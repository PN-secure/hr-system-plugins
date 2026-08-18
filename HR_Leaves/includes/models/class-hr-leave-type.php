<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Leave_Type {

    private static $table_suffix = 'hr_leave_types';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Pobiera wszystkie dostępne typy urlopów (np. Wypoczynkowy, L4).
     */
    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT id, name, is_paid, default_limit_days FROM {$table} ORDER BY name ASC";
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Zwraca szczegóły konkretnego typu urlopu (używane przez kalkulator wniosków do sprawdzania limitów).
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT * FROM {$table} WHERE id = %d";
        return $wpdb->get_row( $wpdb->prepare( $sql, absint( $id ) ), ARRAY_A );
    }

    /**
     * Pozwala Działowi HR dodać nowy typ urlopu.
     */
    public static function create( $name, $is_paid = 1, $default_limit_days = 0 ) {
        global $wpdb;
        $table = self::get_table_name();

        $clean_name = sanitize_text_field( $name );

        if ( empty( $clean_name ) ) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'name'               => $clean_name,
                'is_paid'            => absint( $is_paid ),
                'default_limit_days' => absint( $default_limit_days )
            ),
            array( '%s', '%d', '%d' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }
}
