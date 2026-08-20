<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Workflow_API_Requests {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET: Pobranie własnych wniosków pracownika
        register_rest_route( $this->namespace, '/requests/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_requests' ),
            'permission_callback' => '__return_true', // Wymaga JWT
        ) );

        // POST: Złożenie nowego wniosku
        register_rest_route( $this->namespace, '/requests', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_request' ),
            'permission_callback' => '__return_true',
        ) );

        // GET: Pobranie wniosków zespołu dla Menedżera
        register_rest_route( $this->namespace, '/requests/team', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_team_requests' ),
            'permission_callback' => '__return_true',
        ) );

        // PUT: Akceptacja / Odrzucenie wniosku (Tylko Manager)
        register_rest_route( $this->namespace, '/requests/(?P<id>\d+)/status', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_request_status' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_my_requests( WP_REST_Request $request ) {
        global $hr_current_user;
        $employee_id = $hr_current_user->user_id;

        $requests = HR_Request::get_by_employee( $employee_id );
        return new WP_REST_Response( $requests ? $requests : array(), 200 );
    }

    public function create_request( WP_REST_Request $request ) {
        global $hr_current_user;
        
        $data = array(
            'employee_id'  => $hr_current_user->user_id,
            'request_type' => $request->get_param( 'request_type' ),
            'description'  => $request->get_param( 'description' )
        );

        if ( empty( $data['request_type'] ) || empty( $data['description'] ) ) {
            return new WP_Error( 'missing_data', 'Typ wniosku oraz opis są wymagane.', array( 'status' => 400 ) );
        }

        $request_id = HR_Request::create( $data );

        if ( ! $request_id ) {
            return new WP_Error( 'db_error', 'Wystąpił błąd podczas składania wniosku.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Wniosek został przekazany do akceptacji.' ), 201 );
    }

    public function get_team_requests( WP_REST_Request $request ) {
        global $hr_current_user;

        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Tylko menedżer może przeglądać wnioski zespołu.', array( 'status' => 403 ) );
        }

        $pending_requests = HR_Request::get_pending_for_manager( $hr_current_user->user_id );
        return new WP_REST_Response( $pending_requests ? $pending_requests : array(), 200 );
    }

    public function update_request_status( WP_REST_Request $request ) {
        global $hr_current_user;

        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień do edycji wniosków.', array( 'status' => 403 ) );
        }

        $request_id = $request->get_param( 'id' );
        $new_status = $request->get_param( 'status' );

        $updated = HR_Request::change_status( $request_id, $new_status, $hr_current_user->user_id );

        if ( ! $updated ) {
            return new WP_Error( 'update_failed', 'Aktualizacja statusu nie powiodła się.', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Status wniosku został zmieniony.' ), 200 );
    }
}