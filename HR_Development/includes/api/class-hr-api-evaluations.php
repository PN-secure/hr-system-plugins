<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Evaluations {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/evaluations/me - Pobranie własnych ocen przez pracownika
        register_rest_route( $this->namespace, '/evaluations/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_evaluations' ),
            'permission_callback' => '__return_true', // Autoryzacja realizowana przez tokeny JWT w HR Core
        ) );

        // POST /wp-json/hr/v1/evaluations - Wystawienie nowej oceny podwładnemu (Tylko HR / Manager)
        register_rest_route( $this->namespace, '/evaluations', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_evaluation' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Endpoint dla pracownika - przegląd własnej historii ocen.
     */
    public function get_my_evaluations( WP_REST_Request $request ) {
        global $hr_current_user; // Zmienna wypełniana na etapie autoryzacji w wtyczce Core
        
        $employee_id = $hr_current_user->user_id;
        $evaluations = HR_Evaluation::get_by_employee( $employee_id );

        return new WP_REST_Response( $evaluations ? $evaluations : array(), 200 );
    }

    /**
     * Endpoint dla Menedżera/HR - wystawienie nowej oceny.
     */
    public function create_evaluation( WP_REST_Request $request ) {
        global $hr_current_user;

        // Ochrona endpointu: Tylko manager i hr_admin mogą wystawiać oceny okresowe
        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Nie posiadasz uprawnień do wystawiania ocen pracowniczych.', array( 'status' => 403 ) );
        }

        $data = array(
            'reviewer_id' => $hr_current_user->user_id, // System sam wie, kim jest oceniający z tokena JWT
            'employee_id' => $request->get_param( 'employee_id' ),
            'period'      => $request->get_param( 'period' ),
            'score'       => $request->get_param( 'score' ),
            'comments'    => $request->get_param( 'comments' )
        );

        // Walidacja pól obowiązkowych
        if ( empty( $data['employee_id'] ) || empty( $data['period'] ) || empty( $data['score'] ) ) {
            return new WP_Error( 'missing_data', 'Należy podać pracownika, okres oraz ocenę.', array( 'status' => 400 ) );
        }

        $evaluation_id = HR_Evaluation::create( $data );

        if ( ! $evaluation_id ) {
            return new WP_Error( 'db_error', 'Wystąpił błąd zapisu w bazie danych.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Ocena została pomyślnie zapisana.' ), 201 );
    }
}