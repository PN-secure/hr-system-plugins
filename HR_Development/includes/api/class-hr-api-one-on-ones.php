<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_One_On_Ones {

    private $namespace = 'hr/v1';

    public function init() {
        // Podpięcie tej klasy do głównego loadera, który pominąłem w pośpiechu wcześniej.
        // W pliku class-hr-development-loader.php należy dopisać inicjalizację tej klasy.
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/one-on-ones/me - Pobranie własnych spotkań (dla pracownika)
        register_rest_route( $this->namespace, '/one-on-ones/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_meetings' ),
            'permission_callback' => '__return_true', // Wymaga JWT
        ) );

        // POST /wp-json/hr/v1/one-on-ones - Zapisanie notatki (Tylko Manager)
        register_rest_route( $this->namespace, '/one-on-ones', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_meeting' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_my_meetings( WP_REST_Request $request ) {
        global $hr_current_user;
        
        $employee_id = $hr_current_user->user_id;
        $meetings = HR_One_On_One::get_by_employee( $employee_id );

        return new WP_REST_Response( $meetings ? $meetings : array(), 200 );
    }

    public function create_meeting( WP_REST_Request $request ) {
        global $hr_current_user;

        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Tylko przełożony może dodawać notatki ze spotkań.', array( 'status' => 403 ) );
        }

        $data = array(
            'manager_id'   => $hr_current_user->user_id,
            'employee_id'  => $request->get_param( 'employee_id' ),
            'meeting_date' => $request->get_param( 'meeting_date' ),
            'notes'        => $request->get_param( 'notes' )
        );

        if ( empty( $data['employee_id'] ) || empty( $data['meeting_date'] ) ) {
            return new WP_Error( 'missing_data', 'Musisz podać pracownika i datę spotkania.', array( 'status' => 400 ) );
        }

        $meeting_id = HR_One_On_One::create( $data );

        if ( ! $meeting_id ) {
            return new WP_Error( 'db_error', 'Błąd zapisu w bazie danych.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Notatka ze spotkania została zapisana.' ), 201 );
    }
}