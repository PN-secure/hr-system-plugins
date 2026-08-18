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
    }

    public function apply_delegation( WP_REST_Request $request ) {
        global $wpdb;
        global $hr_current_user;

        $employee_id = $hr_current_user->user_id;
        $destination = sanitize_text_field( $request->get_param( 'destination' ) );
        $start_date  = sanitize_text_field( $request->get_param( 'start_date' ) );
        $end_date    = sanitize_text_field( $request->get_param( 'end_date' ) );

        if ( empty( $destination ) || empty( $start_date ) || empty( $end_date ) ) {
            return new WP_Error( 'missing_data', 'Cel i daty delegacji są wymagane.', array( 'status' => 400 ) );
        }

        if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
            return new WP_Error( 'invalid_dates', 'Data końcowa nie może być wcześniejsza niż początkowa.', array( 'status' => 400 ) );
        }

        // Zapis delegacji (w celach skrócenia kodu pomijam tworzenie osobnego modelu, though studenci powinni go zrobić)
        $table = $wpdb->prefix . 'hr_delegations';
        $wpdb->insert(
            $table,
            array(
                'employee_id' => absint( $employee_id ),
                'destination' => $destination,
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'status'      => 'pending'
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Wniosek o delegację został wysłany do akceptacji.' ), 201 );
    }
}