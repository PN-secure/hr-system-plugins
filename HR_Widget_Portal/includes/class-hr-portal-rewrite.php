<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Portal_Rewrite {

    public function init() {
        add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_widget_script' ) );
    }

    public static function add_rewrite_rules() {
        // Czysty link pod którym klient znajdzie skrypt portalu
        add_rewrite_rule(
            '^widgets/portal\.js$',
            'index.php?hr_widget=portal',
            'top'
        );
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'hr_widget';
        return $vars;
    }

    public function serve_widget_script() {
        $widget_type = get_query_var( 'hr_widget' );

        if ( $widget_type === 'portal' ) {
            
            $file_path = HR_WIDGET_PORTAL_DIR . 'public/js/widget-portal.js';

            if ( ! file_exists( $file_path ) ) {
                status_header( 404 );
                echo '/* Błąd 404: Nie znaleziono skryptu portalu pracowniczego. */';
                exit;
            }

            // CORS - w systemie prywatnym możesz tu wstawić weryfikację domeny 
            // sprawdzającą $_SERVER['HTTP_ORIGIN'] w Twojej bazie klientów.
            header( 'Access-Control-Allow-Origin: *' ); 
            header( 'Content-Type: application/javascript; charset=UTF-8' );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'Cache-Control: public, max-age=3600' );

            $script_content = file_get_contents( $file_path );

            // Wstrzyknięcie głównego adresu API do skryptu (np. /wp-json/hr/v1)
            $api_url = get_rest_url( null, 'hr/v1' );
            $script_content = str_replace( '{{HR_API_BASE_URL}}', $api_url, $script_content );

            echo $script_content;
            exit;
        }
    }
}
