<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Career_Rewrite {

    public function init() {
        // Rejestracja nowej ścieżki (ładnego linku)
        add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
        
        // Rejestracja zmiennej systemowej, która pozwoli nam rozpoznać to żądanie
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        
        // Przechwycenie żądania ZANIM WordPress zacznie generować HTML
        add_action( 'template_redirect', array( $this, 'serve_widget_script' ) );
    }

    /**
     * Definiuje nowy adres URL.
     * Kiedy ktoś wejdzie na "/widgets/career.js", WordPress potraktuje to wewnętrznie jako "?hr_widget=career".
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^widgets/career\.js$',
            'index.php?hr_widget=career',
            'top'
        );
    }

    /**
     * Mówimy WordPressowi, że "hr_widget" to bezpieczna i poprawna zmienna w adresie URL.
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'hr_widget';
        return $vars;
    }

    /**
     * Ta funkcja jest sercem serwowania plików bez zdradzania ścieżek WordPressa.
     */
    public function serve_widget_script() {
        // Sprawdzamy, czy obecne żądanie dotyczy naszego widgetu kariery
        $widget_type = get_query_var( 'hr_widget' );

        if ( $widget_type === 'career' ) {
            
            // Ścieżka do faktycznego pliku Vanilla JS na naszym serwerze
            $file_path = HR_WIDGET_CAREER_DIR . 'public/js/widget-career.js';

            // Upewniamy się, że plik istnieje
            if ( ! file_exists( $file_path ) ) {
                status_header( 404 );
                echo '/* Błąd 404: Nie znaleziono pliku widgetu rekrutacyjnego. */';
                exit;
            }

            // --- NAGŁÓWKI BEZPIECZEŃSTWA (CORS) ---
            // To jest moduł publiczny, więc zezwalamy na jego załadowanie z dowolnej domeny (*).
            // W przypadku prywatnego widgetu pracowniczego wstawilibyśmy tu walidację domeny klienta.
            header( 'Access-Control-Allow-Origin: *' );
            header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
            header( 'Content-Type: application/javascript; charset=UTF-8' );
            header( 'X-Content-Type-Options: nosniff' ); // Blokuje zgadywanie typu pliku przez przeglądarkę
            
            // Opcjonalnie: Krótki cache, żeby odciążyć serwer (np. 1 godzina)
            header( 'Cache-Control: public, max-age=3600' );

            // Pobieramy fizyczną zawartość pliku JS
            $script_content = file_get_contents( $file_path );

            // Wstrzykujemy dynamiczny adres naszego API do skryptu klienta!
            // Dzięki temu skrypt JS zawsze wie, gdzie wysyłać zapytania o oferty pracy,
            // nawet jeśli zmienisz domenę systemu SaaS.
            $api_url = get_rest_url( null, 'hr/v1/public' );
            $script_content = str_replace( '{{HR_API_BASE_URL}}', $api_url, $script_content );

            // Wypluwamy gotowy kod JavaScript
            echo $script_content;

            // Zatrzymujemy dalsze wykonywanie WordPressa - klient otrzymuje TYLKO czysty plik JS
            exit;
        }
    }
}