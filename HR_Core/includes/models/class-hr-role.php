<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Role {

    private static $table_suffix = 'hr_roles';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Pobiera listę wszystkich zdefiniowanych stanowisk w firmie.
     */
    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();

        $sql = "SELECT id, name, created_at FROM {$table} ORDER BY name ASC";
        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Tworzy nowe stanowisko robocze.
     */
    public static function create( $name ) {
        global $wpdb;
        $table = self::get_table_name();

        $clean_name = sanitize_text_field( $name );

        if ( empty( $clean_name ) ) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            array( 'name' => $clean_name ),
            array( '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        return $wpdb->insert_id;
    }
}