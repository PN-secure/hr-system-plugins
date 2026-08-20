<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Employee {

    private static $table_suffix = 'hr_employees';

    /**
     * Zwraca nazwę tabeli z uwzględnieniem przedrostka (np. wp_hr_employees)
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Pobiera wszystkich pracowników z uwzględnieniem nazw działów i ról (JOIN)
     */
    public static function get_all() {
        global $wpdb;
        $table_emp  = self::get_table_name();
        $table_dep  = $wpdb->prefix . 'hr_departments';
        $table_role = $wpdb->prefix . 'hr_roles';

        // Zapytanie łączące dane, by nie zwracać samych samych numerków ID
        $sql = "SELECT 
                    e.id, e.first_name, e.last_name, e.email, e.employment_date,
                    d.name as department_name,
                    r.name as role_name
                FROM {$table_emp} e
                LEFT JOIN {$table_dep} d ON e.department_id = d.id
                LEFT JOIN {$table_role} r ON e.role_id = r.id
                ORDER BY e.id DESC";

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Tworzy nowego pracownika w bazie danych
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'first_name'      => sanitize_text_field( $data['first_name'] ),
                'last_name'       => sanitize_text_field( $data['last_name'] ),
                'email'           => sanitize_email( $data['email'] ),
                'department_id'   => isset( $data['department_id'] ) ? absint( $data['department_id'] ) : null,
                'role_id'         => isset( $data['role_id'] ) ? absint( $data['role_id'] ) : null,
                'employment_date' => isset( $data['employment_date'] ) ? sanitize_text_field( $data['employment_date'] ) : current_time( 'mysql', 1 ),
            ),
            array( '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        return $wpdb->insert_id; // Zwraca ID nowo stworzonego pracownika
    }

    /**
     * Łączy pracownika HR z użytkownikiem WordPress.
     */
    public static function link_wp_user( $employee_id, $wp_user_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $employee_id = absint( $employee_id );
        $wp_user_id  = absint( $wp_user_id );

        if ( ! $employee_id || ! $wp_user_id || ! get_userdata( $wp_user_id ) ) {
            return false;
        }

        $employee_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                $employee_id
            )
        );

        if ( ! $employee_exists ) {
            return false;
        }

        $linked_employee = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE wp_user_id = %d AND id != %d",
                $wp_user_id,
                $employee_id
            )
        );

        if ( $linked_employee ) {
            return false;
        }

        return false !== $wpdb->update(
            $table,
            array( 'wp_user_id' => $wp_user_id ),
            array( 'id' => $employee_id ),
            array( '%d' ),
            array( '%d' )
        );
    }
}
