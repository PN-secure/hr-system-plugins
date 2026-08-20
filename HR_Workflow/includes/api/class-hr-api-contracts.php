<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Contracts {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST: Zarejestrowanie nowej umowy (Tylko HR)
        register_rest_route( $this->namespace, '/contracts', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_contract' ),
            'permission_callback' => '__return_true',
        ) );

        // GET: Pobranie umów wygasających w najbliższym czasie (Dashboard HR)
        register_rest_route( $this->namespace, '/contracts/expiring', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_expiring_contracts' ),
            'permission_callback' => '__return_true',
        ) );

        // PUT: Zakończenie umowy (Tylko HR)
        register_rest_route( $this->namespace, '/contracts/(?P<id>\d+)/terminate', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'terminate_contract' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Wprowadzenie nowej umowy.
     */
    public function create_contract( WP_REST_Request $request ) {
        // Krytyczne zabezpieczenie: Umowy to dane najwyższej wagi (zarobki).
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Dostęp ograniczony wyłącznie dla działu kadr.', array( 'status' => 403 ) );
        }

        $data = array(
            'employee_id'   => $request->get_param( 'employee_id' ),
            'contract_type' => $request->get_param( 'contract_type' ),
            'start_date'    => $request->get_param( 'start_date' ),
            'end_date'      => $request->get_param( 'end_date' ),
            'salary'        => $request->get_param( 'salary' )
        );

        if ( empty( $data['employee_id'] ) || empty( $data['start_date'] ) || empty( $data['contract_type'] ) ) {
            return new WP_Error( 'missing_data', 'Brakujące dane do wygenerowania rekordu umowy.', array( 'status' => 400 ) );
        }

        $contract_id = HR_Contract::create( $data );

        if ( ! $contract_id ) {
            return new WP_Error( 'db_error', 'Wystąpił błąd zapisu w bazie danych.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Nowa umowa została pomyślnie zapisana.' ), 201 );
    }

    /**
     * Endpoint zasilający widżet powiadomień działu HR.
     */
    public function get_expiring_contracts( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Dostęp zabroniony.', array( 'status' => 403 ) );
        }

        $days = $request->get_param( 'days' ) ? absint( $request->get_param( 'days' ) ) : 30;
        $expiring = HR_Contract::get_expiring_contracts( $days );

        return new WP_REST_Response( $expiring ? $expiring : array(), 200 );
    }

    /**
     * Rozwiązanie umowy.
     */
    public function terminate_contract( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Dostęp zabroniony.', array( 'status' => 403 ) );
        }

        $contract_id = $request->get_param( 'id' );
        $terminated = HR_Contract::terminate( $contract_id );

        if ( ! $terminated ) {
            return new WP_Error( 'update_failed', 'Nie udało się zakończyć wybranej umowy.', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Umowa została oznaczona jako nieaktywna.' ), 200 );
    }
}