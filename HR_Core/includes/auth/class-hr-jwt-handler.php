<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Wykorzystanie zewnętrznej, bezpiecznej biblioteki (zainstalowanej przez Composer)
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class HR_JWT_Handler {

    private $secret_key;
    private $algorithm = 'HS256';

    public function __construct() {
        // W produkcji klucz szyfrujący MUST BYĆ przechowywany w wp-config.php (poza folderem wtyczki)
        // np. define('HR_JWT_SECRET', 'XyzBardzoTrudnyKlucz123');
        $this->secret_key = defined( 'HR_JWT_SECRET' ) ? HR_JWT_SECRET : 'niebezpieczny-domyslny-klucz-testowy';
    }

    public function init() {
    }

    /**
     * Generuje nowy token JWT dla zalogowanego użytkownika.
     */
    public function generate_token( $wp_user_id, $hr_role = 'employee' ) {
        $issued_at = time();
        // Token ważny tylko przez 1 godzinę (dla bezpieczeństwa w razie kradzieży)
        $expiration_time = $issued_at + HOUR_IN_SECONDS; 
        
        $payload = array(
            'iat'  => $issued_at,         // Kiedy wydano
            'exp'  => $expiration_time,   // Kiedy wygasa
            'iss'  => get_bloginfo('url'),// Wystawca (nasza domena)
            'data' => array(              // Paczka danych odczytywana przez API
                'user_id' => $wp_user_id,
                'role'    => $hr_role     // Np. 'hr_admin', 'manager', 'employee'
            )
        );

        // Podpisujemy paczkę naszym sekretem
        return JWT::encode( $payload, $this->secret_key, $this->algorithm );
    }

    /**
     * Dekoduje i weryfikuje token nadesłany z frontendu (React) lub Agenta (C#).
     */
    public function validate_token( $token ) {
        try {
            // Jeśli token wygasł lub ktoś próbował go zmodyfikować, JWT::decode wyrzuci wyjątek
            $decoded = JWT::decode( $token, new Key( $this->secret_key, $this->algorithm ) );
            return $decoded->data;
        } catch ( Exception $e ) {
            // Można tu dodać logowanie błędów do pliku (error_log)
            error_log($e->getMessage() );
            return false;
        }
    }
}