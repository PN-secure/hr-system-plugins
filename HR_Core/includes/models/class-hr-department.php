<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Department {

    private static $table_suffix = 'hr_departments';

    /**
     * Konstruuje i zwraca pełną nazwę tabeli
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Pobiera wszystkie działy utworzone w firmie
     */
    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();

        // Proste pobranie wszystkiego ze słownika, posortowane alfabetycznie
        $sql = "SELECT id, name, created_at FROM {$table} ORDER BY name ASC";

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Dodaje nowy dział do słownika
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
            array(
                'name' => $clean_name
            ),
            array( '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Usuwa dział na podstawie ID
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = self::get_table_name();
        
        // Zabezpieczenie przed atakiem SQL Injection przez rzutowanie na liczbę (absint)
        $clean_id = absint( $id );

        if ( $clean_id === 0 ) {
            return false;
        }

        $deleted = $wpdb->delete(
            $table,
            array( 'id' => $clean_id ),
            array( '%d' )
        );

        return $deleted !== false;
    }
}