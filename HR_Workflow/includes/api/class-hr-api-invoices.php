<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Invoices {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST: Dodanie nowej faktury wraz ze skanem pliku
        register_rest_route( $this->namespace, '/invoices', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'submit_invoice' ),
            'permission_callback' => '__return_true', // Token JWT weryfikowany globalnie
        ) );

        // PUT: Zmiana statusu faktury (Akceptacja przez Menedżera / Księgowość)
        register_rest_route( $this->namespace, '/invoices/(?P<id>\d+)/status', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_invoice_status' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Rejestracja nowej faktury w systemie.
     */
    public function submit_invoice( WP_REST_Request $request ) {
        global $hr_current_user;

        // Odczyt danych z FormData
        $data = array(
            'employee_id'    => $hr_current_user->user_id,
            'invoice_number' => $request->get_param( 'invoice_number' ),
            'amount'         => $request->get_param( 'amount' ),
            'currency'       => $request->get_param( 'currency' ) ? $request->get_param( 'currency' ) : 'PLN',
            'description'    => $request->get_param( 'description' )
        );

        if ( empty( $data['invoice_number'] ) || empty( $data['amount'] ) ) {
            return new WP_Error( 'missing_data', 'Numer faktury oraz kwota są wymagane.', array( 'status' => 400 ) );
        }

        if ( empty( $_FILES['invoice_file'] ) ) {
            return new WP_Error( 'missing_file', 'Musisz załączyć skan dokumentu (PDF/JPG/PNG).', array( 'status' => 400 ) );
        }

        // 1. Zabezpieczony upload pliku przez Model
        $upload_result = HR_Invoice::upload_invoice_file( $_FILES['invoice_file'] );

        if ( is_wp_error( $upload_result ) ) {
            return $upload_result;
        }

        $file_path = $upload_result;

        // 2. Zapis w bazie danych
        $invoice_id = HR_Invoice::submit( $data, $file_path );

        if ( ! $invoice_id ) {
            // Rollback: usunięcie wgranego pliku, jeśli baza odrzuciła zapis
            unlink( $file_path );
            return new WP_Error( 'db_error', 'Błąd podczas zapisywania faktury w bazie danych.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Faktura została przesłana do akceptacji.' ), 201 );
    }

    /**
     * Zmiana statusu faktury.
     */
    public function update_invoice_status( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Tylko przełożony lub księgowość mogą zmieniać status faktur.', array( 'status' => 403 ) );
        }

        $invoice_id = $request->get_param( 'id' );
        $new_status = $request->get_param( 'status' );

        $updated = HR_Invoice::change_status( $invoice_id, $new_status );

        if ( ! $updated ) {
            return new WP_Error( 'update_failed', 'Aktualizacja statusu faktury nie powiodła się.', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Status faktury został zaktualizowany.' ), 200 );
    }
}
