<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Auth {

    private $namespace = 'hr/v1';

    public function init() {
        // Rejestracja trasy w REST API WordPressa przy inicjalizacji API
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Endpoint: Logowanie (POST /wp-json/hr/v1/auth/login)
        register_rest_route( $this->namespace, '/auth/login', array(
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => array( $this, 'login' ),
            'permission_callback' => '__return_true', // Dostęp publiczny (sprawdzanie hasła wewnątrz)
        ) );

        // Endpoint: Pobranie danych o zalogowanym użytkowniku (GET /wp-json/hr/v1/auth/me)
        register_rest_route( $this->namespace, '/auth/me', array(
            'methods'             => WP_REST_Server::READABLE, // GET
            'callback'            => array( $this, 'get_current_user_info' ),
            'permission_callback' => '__return_true', // Przepuszczane przez filtr HR_Permissions (wymaga JWT)
        ) );
    }

    /**
     * Główna funkcja logująca. Odbiera login/email oraz hasło z frontendu (React).
     */
    public function login( WP_REST_Request $request ) {
        // Pobranie i oczyszczenie parametrów z zapytania JSON
        $username = sanitize_text_field( $request->get_param( 'username' ) );
        $password = $request->get_param( 'password' ); // Hasła nie sanitujemy, by nie zmienić znaku specjalnego!

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 
                'missing_credentials', 
                __( 'Podaj login/email oraz hasło.', 'hr-core' ), 
                array( 'status' => 400 ) 
            );
        }

        // Standardowa wbudowana funkcja WordPressa do weryfikacji haseł
        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 
                'invalid_credentials', 
                __( 'Nieprawidłowy login lub hasło.', 'hr-core' ), 
                array( 'status' => 401 ) 
            );
        }

        // Pobieramy lub przypisujemy rolę w systemie HR dla tego użytkownika
        // W produkcyjnym systemie rola wyciągana jest z naszej tabeli hr_employees
        $hr_role = get_user_meta( $user->ID, 'hr_role', true );
        if ( user_can( $user, 'manage_options' ) ) {
            $hr_role = 'hr_admin';
        } elseif ( empty( $hr_role ) ) {
            $hr_role = 'employee';
        }

        // Generujemy bezpieczny token JWT
        $jwt_handler = new HR_JWT_Handler();
        $token = $jwt_handler->generate_token( $user->ID, $hr_role );

        // Odpowiedź zwracana do Reacta / Vue / Agenta w C#
        return new WP_REST_Response( array(
            'success' => true,
            'token'   => $token,
            'user'    => array(
                'id'       => $user->ID,
                'email'    => $user->user_email,
                'nicename' => $user->display_name,
                'role'     => $hr_role
            )
        ), 200 );
    }

    /**
     * Zwraca dane zalogowanej osoby na podstawie przesłanego tokena.
     */
    public function get_current_user_info( WP_REST_Request $request ) {
        // Przechwytujemy tożsamość weryfikowaną wcześniej przez plik HR_Permissions
        global $hr_current_user;

        if ( empty( $hr_current_user ) ) {
            return new WP_Error( 'unauthorized', 'Brak autoryzacji', array( 'status' => 401 ) );
        }

        $user_id = $hr_current_user->user_id;
        $user_info = get_userdata( $user_id );

        return new WP_REST_Response( array(
            'id'       => $user_id,
            'email'    => $user_info->user_email,
            'name'     => $user_info->display_name,
            'role'     => $hr_current_user->role
        ), 200 );
    }
}