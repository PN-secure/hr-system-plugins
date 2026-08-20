<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Employees {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/employees - Pobieranie listy pracowników
        register_rest_route( $this->namespace, '/employees', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_employees' ),
            'permission_callback' => '__return_true', // Autoryzacja globalna w HR_Permissions
        ) );

        // POST /wp-json/hr/v1/employees - Dodawanie nowego pracownika
        register_rest_route( $this->namespace, '/employees', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_employee' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Zwraca listę wszystkich pracowników (wymaga tylko bycia zalogowanym - sprawdza to HR_Permissions).
     */
    public function get_employees( WP_REST_Request $request ) {
        // Wywołujemy Model, aby nie brudzić kontrolera zapytaniami SQL
        $employees = HR_Employee::get_all();

        if ( empty( $employees ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        return new WP_REST_Response( $employees, 200 );
    }

    /**
     * Odbiera dane z formularza w React i tworzy pracownika w bazie.
     */
    public function create_employee( WP_REST_Request $request ) {
        // ZABEZPIECZENIE (RBAC - Role Based Access Control)
        // Tylko administrator HR może dodawać nowych pracowników do systemu!
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 
                'rest_forbidden', 
                __( 'Nie masz uprawnień, aby dodać pracownika.', 'hr-core' ), 
                array( 'status' => 403 ) 
            );
        }

        // Pobranie danych z żądania JSON
        $data = array(
            'first_name'      => $request->get_param( 'first_name' ),
            'last_name'       => $request->get_param( 'last_name' ),
            'email'           => $request->get_param( 'email' ),
            'password'        => $request->get_param( 'password' ),
            'department_id'   => $request->get_param( 'department_id' ),
            'role_id'         => $request->get_param( 'role_id' ),
            'employment_date' => $request->get_param( 'employment_date' ),
        );

        // Walidacja podstawowa na poziomie kontrolera
        if ( empty( $data['first_name'] ) || empty( $data['last_name'] ) || empty( $data['email'] ) || empty( $data['password'] ) ) {
            return new WP_Error( 
                'missing_fields', 
                __( 'Imię, nazwisko, adres email i hasło są obowiązkowe.', 'hr-core' ),
                array( 'status' => 400 ) 
            );
        }

        if ( ! is_email( $data['email'] ) ) {
            return new WP_Error(
                'invalid_email',
                __( 'Podany adres email jest nieprawidłowy.', 'hr-core' ),
                array( 'status' => 400 )
            );
        }

        $new_employee_id = HR_Employee::create( $data );

        if ( is_wp_error( $new_employee_id ) ) {
            return $new_employee_id;
        }

        if ( ! $new_employee_id ) {
            return new WP_Error( 
                'db_error', 
                __( 'Błąd bazy danych. Pracownik z takim adresem email może już istnieć.', 'hr-core' ),
                array( 'status' => 500 ) 
            );
        }

        // Sukces - Zwracamy kod 201 (Created)
        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Pracownik został dodany.', 'hr-core' ),
            'id'      => $new_employee_id
        ), 201 );
    }
}
