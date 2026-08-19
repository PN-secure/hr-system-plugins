<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Candidates {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/candidates?job_id=X - Pobiera kandydatów dla wybranej oferty (pod tablicę Kanban)
        register_rest_route( $this->namespace, '/candidates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_candidates' ),
            'permission_callback' => '__return_true', 
        ) );

        // PUT /wp-json/hr/v1/candidates/{id}/stage - Zmiana etapu kandydata
        register_rest_route( $this->namespace, '/candidates/(?P<id>\d+)/stage', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_candidate_stage' ),
            'permission_callback' => '__return_true',
        ) );

        // POST /wp-json/hr/v1/candidates/{id}/hire - Zatrudnienie i Onboarding
        register_rest_route( $this->namespace, '/candidates/(?P<id>\d+)/hire', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'hire_candidate' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Pobiera aplikacje. Oczekuje parametru job_id.
     */
    public function get_candidates( WP_REST_Request $request ) {
        global $wpdb;

        // Endpoint dedykowany tylko dla działu HR oraz dla menedżerów (aby oceniali kandydatów na swój zespół)
        if ( ! HR_Permissions::has_role( 'hr_admin' ) && ! HR_Permissions::has_role( 'manager' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień.', array( 'status' => 403 ) );
        }

        $job_id = absint( $request->get_param( 'job_id' ) );
        
        if ( ! $job_id ) {
            return new WP_Error( 'missing_job_id', 'Należy przekazać identyfikator oferty (job_id).', array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'hr_candidates';
        // CV nie jest tu zwracane pełną ścieżką z dysku. W pełnej wersji systemu 
        // tworzy się oddzielny chroniony endpoint do strumieniowania pliku PDF.
        $sql = "SELECT id, first_name, last_name, email, phone, stage, rating, created_at 
                FROM {$table} 
                WHERE job_id = %d 
                ORDER BY created_at DESC";

        $candidates = $wpdb->get_results( $wpdb->prepare( $sql, $job_id ), ARRAY_A );

        return new WP_REST_Response( $candidates ? $candidates : array(), 200 );
    }

    /**
     * Aktualizacja etapu w lejku rekrutacyjnym (drag & drop).
     */
    public function update_candidate_stage( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień do edycji statusu.', array( 'status' => 403 ) );
        }

        $candidate_id = $request->get_param( 'id' );
        $new_stage    = $request->get_param( 'stage' );

        $updated = HR_Candidate::change_stage( $candidate_id, $new_stage );

        if ( ! $updated ) {
            return new WP_Error( 'update_failed', 'Zapis statusu nie powiódł się (błędny etap lub brak rekordu).', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Zaktualizowano etap rekrutacji.' ), 200 );
    }

    /**
     * Konwersja Kandydata na Pracownika - trigger procesu Onboardingu.
     */
    public function hire_candidate( WP_REST_Request $request ) {
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Tylko dział HR może zatrudnić kandydata.', array( 'status' => 403 ) );
        }

        $candidate_id  = $request->get_param( 'id' );
        
        // Zależności do głównej bazy z modułu Core
        $department_id = $request->get_param( 'department_id' );
        $role_id       = $request->get_param( 'role_id' );

        if ( empty( $department_id ) || empty( $role_id ) ) {
            return new WP_Error( 'missing_data', 'Do zatrudnienia należy wskazać docelowy Dział oraz Stanowisko.', array( 'status' => 400 ) );
        }

        $result = HR_Candidate::hire( $candidate_id, $department_id, $role_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 
            'success'     => true, 
            'employee_id' => $result,
            'message'     => 'Kandydat został zatrudniony! Utworzono kartotekę i wygenerowano listę zadań onboardingowych.' 
        ), 201 );
    }
}