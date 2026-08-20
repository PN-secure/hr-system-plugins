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
     * Pobiera nazwę roli HR przypisanej do konta WordPress.
     */
    public static function get_role( $wp_user_id ) {
        global $wpdb;

        $table_employees = self::get_table_name();
        $table_roles     = $wpdb->prefix . 'hr_roles';

        $role_name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT r.name
                FROM {$table_employees} e
                INNER JOIN {$table_roles} r ON e.role_id = r.id
                WHERE e.wp_user_id = %d
                LIMIT 1",
                absint( $wp_user_id )
            )
        );

        return $role_name ? $role_name : 'employee';
    }

    /**
     * Tworzy nowego pracownika w bazie danych
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        if ( get_user_by( 'email', sanitize_email( $data['email'] ) ) ) {
            return new WP_Error(
                'user_exists',
                __( 'Konto WordPress z takim adresem email już istnieje.', 'hr-core' ),
                array( 'status' => 409 )
            );
        }

        $first_name = sanitize_text_field( $data['first_name'] );
        $last_name  = sanitize_text_field( $data['last_name'] );
        $full_name   = trim( $first_name . ' ' . $last_name );

        $wp_user_id = wp_insert_user(
            array(
                'user_login'   => sanitize_email( $data['email'] ),
                'user_pass'    => $data['password'],
                'user_email'   => sanitize_email( $data['email'] ),
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'nickname'     => $full_name,
                'display_name' => $full_name,
                'role'         => 'subscriber',
            )
        );

        if ( is_wp_error( $wp_user_id ) ) {
            return new WP_Error(
                'user_creation_error',
                __( 'Nie udało się utworzyć konta WordPress.', 'hr-core' ),
                array( 'status' => 500 )
            );
        }

        $user_nicename = sanitize_title( $full_name . '-' . $wp_user_id );

        wp_update_user(
            array(
                'ID'           => $wp_user_id,
                'user_nicename' => $user_nicename,
            )
        );

        $inserted = $wpdb->insert(

            $table,
            array(
                'wp_user_id'      => $wp_user_id,
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'email'           => sanitize_email( $data['email'] ),
                'department_id'   => isset( $data['department_id'] ) ? absint( $data['department_id'] ) : null,
                'role_id'         => isset( $data['role_id'] ) ? absint( $data['role_id'] ) : null,
                'employment_date' => isset( $data['employment_date'] ) ? sanitize_text_field( $data['employment_date'] ) : current_time( 'mysql', 1 ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        if ( ! $inserted ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $wp_user_id );
            return false;
        }

        return $wpdb->insert_id; // Zwraca ID nowo stworzonego pracownika
    }
}
