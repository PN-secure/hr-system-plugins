<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Departments {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET: Pobieranie słownika działów (Dostępne dla każdego z JWT)
        register_rest_route( $this->namespace, '/departments', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_departments' ),
            'permission_callback' => '__return_true', // Przepuszczane przez HR_Permissions
        ) );

        // POST: Dodawanie nowego działu (Tylko HR Admin)
        register_rest_route( $this->namespace, '/departments', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_department' ),
            'permission_callback' => '__return_true',
        ) );

        // DELETE: Usuwanie działu (Tylko HR Admin, wymaga podania ID w adresie URL)
        register_rest_route( $this->namespace, '/departments/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_department' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Zwraca listę działów firmy w formacie JSON.
     */
    public function get_departments( WP_REST_Request $request ) {
        $departments = HR_Department::get_all();
        return new WP_REST_Response( $departments, 200 );
    }

    /**
     * Dodaje nowy dział. Wymaga podwyższonych uprawnień.
     */
    public function create_department( WP_REST_Request $request ) {
        // Weryfikacja uprawnień (Tylko administrator HR)
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 
                'rest_forbidden', 
                __( 'Tylko administrator HR może dodawać nowe działy.', 'hr-core' ), 
                array( 'status' => 403 ) 
            );
        }

        $name = $request->get_param( 'name' );

        if ( empty( $name ) ) {
            return new WP_Error( 'missing_param', 'Nazwa działu jest wymagana.', array( 'status' => 400 ) );
        }

        $new_id = HR_Department::create( $name );

        if ( ! $new_id ) {
            return new WP_Error( 'db_error', 'Nie udało się zapisać działu w bazie.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'id' => $new_id,
            'message' => 'Dział został pomyślnie utworzony.'
        ), 201 );
    }

    /**
     * Usuwa dział z bazy danych na podstawie ID z adresu URL (np. /departments/5).
     */
    public function delete_department( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień.', array( 'status' => 403 ) );
        }

        // Pobranie ID wprost ze ścieżki URL (regex z register_routes)
        $id = $request->get_param( 'id' );

        $deleted = HR_Department::delete( $id );

        if ( ! $deleted ) {
            return new WP_Error( 'not_found', 'Dział o podanym ID nie istnieje lub wystąpił błąd.', array( 'status' => 404 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Dział usunięty.' ), 200 );
    }
}