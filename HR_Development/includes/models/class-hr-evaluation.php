<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Evaluation {

    private static $table_suffix = 'hr_evaluations';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Zapisuje nową ocenę okresową dla pracownika.
     * * @param array $data Tablica z danymi (employee_id, reviewer_id, period, score, comments)
     * @return int|false ID nowo dodanej oceny lub false w przypadku błędu
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        // Rygorystyczna walidacja i sanityzacja danych wejściowych
        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id' => absint( $data['employee_id'] ),
                'reviewer_id' => absint( $data['reviewer_id'] ),
                'period'      => sanitize_text_field( $data['period'] ), // np. "Q3 2026"
                'score'       => floatval( $data['score'] ),             // Ułamki, np. 4.5
                'comments'    => wp_kses_post( $data['comments'] )       // Dopuszczamy bezpieczny HTML dla notatek
            ),
            array( '%d', '%d', '%s', '%f', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Pobiera historię ocen danego pracownika.
     * Dołącza dane z tabeli hr_employees, aby API wiedziało, kim jest "reviewer".
     *
     * @param int $employee_id ID pracownika
     * @return array Tablica z wynikami
     */
    public static function get_by_employee( $employee_id ) {
        global $wpdb;
        $table_evals = self::get_table_name();
        $table_emp = $wpdb->prefix . 'hr_employees';

        $sql = "SELECT ev.id, ev.period, ev.score, ev.comments, ev.created_at, 
                       rev.first_name AS reviewer_first_name, 
                       rev.last_name AS reviewer_last_name
                FROM {$table_evals} ev
                LEFT JOIN {$table_emp} rev ON ev.reviewer_id = rev.id
                WHERE ev.employee_id = %d
                ORDER BY ev.created_at DESC";

        return $wpdb->get_results( $wpdb->prepare( $sql, absint( $employee_id ) ), ARRAY_A );
    }
}