<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Jobs {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST /wp-json/hr/v1/jobs - Tworzenie nowej oferty (Tylko HR)
        register_rest_route( $this->namespace, '/jobs', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_job' ),
            'permission_callback' => '__return_true', // Autoryzacja realizowana globalnie (JWT)
        ) );

        // PUT /wp-json/hr/v1/jobs/{id}/status - Zamknięcie/otwarcie oferty
        register_rest_route( $this->namespace, '/jobs/(?P<id>\d+)/status', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_job_status' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Endpoint publikujący nową ofertę pracy.
     */
    public function create_job( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Tylko dział HR może tworzyć oferty pracy.', array( 'status' => 403 ) );
        }

        $data = array(
            'title'         => $request->get_param( 'title' ),
            'description'   => $request->get_param( 'description' ),
            'department_id' => $request->get_param( 'department_id' )
        );

        if ( empty( $data['title'] ) || empty( $data['description'] ) ) {
            return new WP_Error( 'missing_data', 'Tytuł i opis są wymagane.', array( 'status' => 400 ) );
        }

        $job_id = HR_Job::create( $data );

        if ( ! $job_id ) {
            return new WP_Error( 'db_error', 'Błąd tworzenia oferty.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'id' => $job_id, 'message' => 'Oferta została opublikowana.' ), 201 );
    }

    /**
     * Zmiana statusu oferty (otwarta/zamknięta).
     */
    public function update_job_status( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień.', array( 'status' => 403 ) );
        }

        $job_id = $request->get_param( 'id' );
        $status = $request->get_param( 'status' );

        $updated = HR_Job::change_status( $job_id, $status );

        if ( ! $updated ) {
            return new WP_Error( 'update_failed', 'Nie udało się zaktualizować statusu.', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Status ogłoszenia został zaktualizowany.' ), 200 );
    }
}
