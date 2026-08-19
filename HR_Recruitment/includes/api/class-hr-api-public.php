<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Public {

    private $namespace = 'hr/v1/public';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/public/jobs - Zwraca aktywne ogłoszenia
        register_rest_route( $this->namespace, '/jobs', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_open_jobs' ),
            'permission_callback' => '__return_true', // Brak autoryzacji
        ) );

        // POST /wp-json/hr/v1/public/apply - Przyjmuje formularz aplikacyjny (wraz z plikiem)
        register_rest_route( $this->namespace, '/apply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'submit_application' ),
            'permission_callback' => '__return_true', // Brak autoryzacji
        ) );
    }

    /**
     * Endpoint zwracający wyłącznie otwarte oferty pracy do wyświetlenia na stronie WWW.
     */
    public function get_open_jobs( WP_REST_Request $request ) {
        $jobs = HR_Job::get_active_jobs();
        
        if ( empty( $jobs ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        return new WP_REST_Response( $jobs, 200 );
    }

    /**
     * Główny mechanizm przyjmowania aplikacji. 
     * Odbiera dane tekstowe oraz plik (CV), weryfikuje je i zapisuje w bazie.
     */
    public function submit_application( WP_REST_Request $request ) {
        // Odczyt danych z FormData (z uwagi na przesyłanie pliku)
        $data = array(
            'job_id'     => $request->get_param( 'job_id' ),
            'first_name' => $request->get_param( 'first_name' ),
            'last_name'  => $request->get_param( 'last_name' ),
            'email'      => $request->get_param( 'email' ),
            'phone'      => $request->get_param( 'phone' )
        );

        // Podstawowa walidacja pól tekstowych
        if ( empty( $data['job_id'] ) || empty( $data['first_name'] ) || empty( $data['last_name'] ) || empty( $data['email'] ) ) {
            return new WP_Error( 'missing_fields', 'Wypełnij wszystkie wymagane pola.', array( 'status' => 400 ) );
        }

        // 1. Walidacja i bezpieczny zapis pliku CV
        if ( empty( $_FILES['cv'] ) ) {
            return new WP_Error( 'missing_file', 'Dołączenie pliku CV jest obowiązkowe.', array( 'status' => 400 ) );
        }

        $upload_result = HR_File_Manager::upload_cv( $_FILES['cv'] );

        if ( is_wp_error( $upload_result ) ) {
            return $upload_result; // Zwraca konkretny błąd wygenerowany przez menedżera plików
        }

        $cv_path = $upload_result;

        // 2. Zapisanie kandydata w bazie
        $candidate_id = HR_Candidate::apply( $data, $cv_path );

        if ( ! $candidate_id ) {
            // W przypadku błędu bazy danych, należy usunąć wgrany przed chwilą plik
            unlink( $cv_path );
            return new WP_Error( 'db_error', 'Wystąpił błąd podczas zapisywania aplikacji.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'message' => 'Aplikacja została wysłana pomyślnie. Dziękujemy!' 
        ), 201 );
    }
}