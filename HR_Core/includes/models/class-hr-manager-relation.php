<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Manager_Relation {

    private static $table_suffix = 'hr_manager_relations';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Przypisuje przełożonego do pracownika.
     * Najpierw usuwa stare powiązanie, aby pracownik miał tylko jednego głównego szefa.
     */
    public static function assign_manager( $employee_id, $manager_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $emp_id = absint( $employee_id );
        $mgr_id = absint( $manager_id );

        if ( $emp_id === 0 || $mgr_id === 0 || $emp_id === $mgr_id ) {
            return false; // Nie można być swoim własnym szefem
        }

        // Krok 1: Usunięcie ewentualnego poprzedniego przełożonego
        $wpdb->delete( $table, array( 'employee_id' => $emp_id ), array( '%d' ) );

        // Krok 2: Zapisanie nowej relacji
        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id' => $emp_id,
                'manager_id'  => $mgr_id
            ),
            array( '%d', '%d' )
        );

        return $inserted !== false;
    }

    /**
     * Pobiera listę bezpośrednich podwładnych danego menedżera.
     * Przydatne, gdy menedżer loguje się do systemu i chce zobaczyć swój zespół.
     */
    public static function get_subordinates( $manager_id ) {
        global $wpdb;
        $table_rel = self::get_table_name();
        $table_emp = $wpdb->prefix . 'hr_employees';
        
        $mgr_id = absint( $manager_id );

        $sql = "SELECT e.id, e.first_name, e.last_name, e.email 
                FROM {$table_emp} e
                INNER JOIN {$table_rel} r ON e.id = r.employee_id
                WHERE r.manager_id = %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $mgr_id ), ARRAY_A );
    }
}