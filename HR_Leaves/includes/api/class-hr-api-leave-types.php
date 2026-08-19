<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Leave_Types {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/leaves/types
        register_rest_route( $this->namespace, '/leaves/types', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_types' ),
            'permission_callback' => '__return_true', // Autoryzacja JWT przez HR Core
        ) );

        // (Opcjonalnie dla studentów można tu dodać POST do tworzenia nowych typów)
    }

    /**
     * Zwraca listę opcji absencji dla formularza składania wniosku.
     */
    public function get_types( WP_REST_Request $request ) {
        // TERAZ POPRAWNIE: Używamy Modelu zamiast bezpośrednich zapytań SQL!
        if ( ! class_exists( 'HR_Leave_Type' ) ) {
            return new WP_Error( 'internal_error', 'Brak modelu typów urlopów.', array( 'status' => 500 ) );
        }

        $types = HR_Leave_Type::get_all();

        if ( empty( $types ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        return new WP_REST_Response( $types, 200 );
    }
}
