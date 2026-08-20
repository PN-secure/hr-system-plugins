<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Time_Tracking {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST: Rozpoczęcie pracy
        register_rest_route( $this->namespace, '/time/clock-in', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_clock_in' ),
            'permission_callback' => '__return_true', // Wymaga JWT (Moduł Core)
        ) );

        // POST: Zakończenie pracy
        register_rest_route( $this->namespace, '/time/clock-out', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_clock_out' ),
            'permission_callback' => '__return_true',
        ) );

        // GET: Pobranie raportu z danego miesiąca (np. /time/timesheet?year=2026&month=8)
        register_rest_route( $this->namespace, '/time/timesheet', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_timesheet' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_clock_in( WP_REST_Request $request ) {
        global $hr_current_user;
        $employee_id = $hr_current_user->employee_id;
        
        // Pobranie IP użytkownika wykonującego żądanie HTTP
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $result = HR_Time_Entry::clock_in( $employee_id, $ip_address );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Rozpoczęto rejestrację czasu pracy.' ), 201 );
    }

    public function handle_clock_out( WP_REST_Request $request ) {
        global $hr_current_user;
        $employee_id = $hr_current_user->employee_id;

        $result = HR_Time_Entry::clock_out( $employee_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Zakończono rejestrację czasu pracy.' ), 200 );
    }

    public function get_timesheet( WP_REST_Request $request ) {
        global $hr_current_user;
        $employee_id = $hr_current_user->employee_id;

        $year = $request->get_param( 'year' );
        $month = $request->get_param( 'month' );

        if ( empty( $year ) || empty( $month ) ) {
            return new WP_Error( 'missing_params', 'Należy podać rok i miesiąc.', array( 'status' => 400 ) );
        }

        $records = HR_Time_Entry::get_monthly_timesheet( $employee_id, $year, $month );

        return new WP_REST_Response( $records, 200 );
    }
}