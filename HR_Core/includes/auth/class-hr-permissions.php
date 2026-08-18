<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Permissions {

    public function init() {
        // Główny hook zabezpieczający cały system HR
        add_filter( 'rest_authentication_errors', array( $this, 'authenticate_api_requests' ) );
    }

    /**
     * Główny middleware (pośrednik) dla każdego zapytania API.
     */
    public function authenticate_api_requests( $result ) {
        // Jeśli inny plugin zdążył już zablokować to zapytanie, nic nie robimy
        if ( true === $result || is_wp_error( $result ) ) {
            return $result;
        }

        global $wp;
        // 1. Ograniczenie strefy wpływów
        // Interesują nas tylko zapytania do naszej wtyczki (namespace: hr/v1)
        if ( strpos( $wp->request, 'hr/v1' ) === false ) {
            return $result; 
        }

        // 2. Wyjątek dla Endpointu Logowania
        // Nie możemy żądać tokena przy logowaniu, bo użytkownik dopiero chce go dostać!
        if ( strpos( $wp->request, 'hr/v1/auth/login' ) !== false ) {
            return $result;
        }

        // 3. Pobranie nagłówka HTTP (Authorization)
        // Wiele tanich hostingów (Nginx) domyślnie obcina nagłówek Authorization. 
        // Ten kod to omija (to bardzo częsty błąd początkujących programistów API!)
        $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if ( empty( $auth_header ) && function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
            $auth_header = isset( $headers['Authorization'] ) ? $headers['Authorization'] : '';
        }

        // 4. Weryfikacja formatu nagłówka (musi być: "Bearer {TOKEN}")
        if ( empty( $auth_header ) || preg_match( '/Bearer\s(\S+)/', $auth_header, $matches ) !== 1 ) {
            return new WP_Error( 
                'rest_forbidden', 
                __( 'Brak tokena JWT. Odmowa dostępu.', 'hr-core' ), 
                array( 'status' => 401 ) 
            );
        }

        $token = $matches[1];

        // 5. Dekodowanie tokena
        $jwt_handler = new HR_JWT_Handler();
        $decoded_data = $jwt_handler->validate_token( $token );

        if ( ! $decoded_data ) {
            return new WP_Error( 
                'rest_unauthorized', 
                __( 'Twój token wygasł lub jest nieprawidłowy. Zaloguj się ponownie.', 'hr-core' ), 
                array( 'status' => 401 ) 
            );
        }

        // 6. SUKCES: Przekazanie tożsamości dalej
        // Zapisujemy dane o użytkowniku do zmiennej globalnej. 
        // Dzięki temu konkretne endpointy (np. pobierz moje urlopy) wiedzą, kim jest "ja".
        global $hr_current_user;
        $hr_current_user = $decoded_data;

        return true;
    }

    /**
     * Metoda pomocnicza dla endpointów API.
     * Pozwala szybko sprawdzić, czy zalogowany ma uprawnienia (np. HR_Permissions::has_role('hr_admin')).
     */
    public static function has_role( $required_role ) {
        global $hr_current_user;
        if ( empty( $hr_current_user ) || $hr_current_user->role !== $required_role ) {
            return false;
        }
        return true;
    }
}