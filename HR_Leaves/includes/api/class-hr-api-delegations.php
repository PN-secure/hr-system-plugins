<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Delegations {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST: Zgłoszenie delegacji przez pracownika
        register_rest_route( $this->namespace, '/delegations/apply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'apply_delegation' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $this->namespace, '/delegations/pending', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_pending_delegations' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $this->namespace, '/delegations/(?P<id>\d+)/status', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_delegation_status' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( $this->namespace, '/delegations/my-requests', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_delegations_requests' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function apply_delegation( WP_REST_Request $request ) {
        global $wpdb;
        global $hr_current_user;

        $employee_id = $hr_current_user->employee_id;
        $destination = sanitize_text_field( $request->get_param( 'destination' ) );
        $start_date  = sanitize_text_field( $request->get_param( 'start_date' ) );
        $end_date    = sanitize_text_field( $request->get_param( 'end_date' ) );

        $result = HR_Delegation::apply( $employee_id, $destination, $start_date, $end_date );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'message' => 'Wniosek został złożony i czeka na akceptację.',
            'request_id' => $result
        ), 201 );
    }

    public function get_pending_delegations( WP_REST_Request $request ) {
        global $wpdb;
        global $hr_current_user;

        $manager_id = $hr_current_user->employee_id;

        $employee_id = $hr_current_user->user_id;

        if ( ! class_exists( 'HR_Manager_Relation' ) ) {
            return new WP_Error( 'dependency_error', 'Brak modułu drzewa organizacyjnego.', array( 'status' => 500 ) );
        }

        $team = HR_Manager_Relation::get_subordinates( $manager_id );

        if ( empty( $team ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        $team_ids = array_column( $team, 'id' );
        $ids_placeholder = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );

        $table = $wpdb->prefix . 'hr_delegations';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE employee_id IN ({$ids_placeholder}) AND status = 'pending' ORDER BY start_date DESC",
                ...array_map( 'absint', $team_ids )
            ),
            ARRAY_A
        );

        return new WP_REST_Response( $results, 200 );
    }

    public function update_delegation_status( WP_REST_Request $request ) {
        global $hr_current_user;

        $request_id    = $request->get_param( 'id' );
        $new_status    = $request->get_param( 'status' ); // 'approved' lub 'rejected'
        $reject_reason = $request->get_param( 'reason' );

        $is_admin = HR_Permissions::has_role( 'hr_admin' );

        $is_manager = HR_Delegation::can_user_update_request(
            $request_id,
            $hr_current_user->employee_id
        );

        if ( ! $is_admin && ! $is_manager ) {
            return new WP_Error(
                'rest_forbidden', 'Nie masz uprawnień do zmiany statusu tego wniosku.',
                array( 'status' => 403 )
            );
        }

        $success = HR_Delegation::change_status( $request_id, $new_status, $hr_current_user->employee_id, $reject_reason );

        if ( ! $success ) {
            return new WP_Error( 'update_failed', 'Nie udało się zmienić statusu wniosku.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Status wniosku został zaktualizowany.' ), 200 );
        
    }

    public function get_my_delegations_requests( WP_REST_Request $request ) {
        global $hr_current_user;

        $requests = HR_Delegation::get_requests_by_employee( $hr_current_user->employee_id  );

        return new WP_REST_Response( $requests, 200 );
    }
}