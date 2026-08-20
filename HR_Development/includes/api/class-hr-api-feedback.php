<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Feedback {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // GET /wp-json/hr/v1/feedback - Pobranie feedbacku otrzymanego przez zalogowanego pracownika
        register_rest_route( $this->namespace, '/feedback', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_feedback' ),
            'permission_callback' => '__return_true',
        ) );

        // POST /wp-json/hr/v1/feedback - Wysłanie pochwały/feedbacku do innej osoby
        register_rest_route( $this->namespace, '/feedback', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'send_feedback' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_feedback( WP_REST_Request $request ) {
        global $hr_current_user;
        
        $employee_id = $hr_current_user->user_id;
        $feedback = HR_Feedback::get_received( $employee_id );

        return new WP_REST_Response( $feedback ? $feedback : array(), 200 );
    }

    public function send_feedback( WP_REST_Request $request ) {
        global $hr_current_user;

        $data = array(
            'sender_id'    => $hr_current_user->user_id,
            'receiver_id'  => $request->get_param( 'receiver_id' ),
            'message'      => $request->get_param( 'message' ),
            'is_anonymous' => $request->get_param( 'is_anonymous' ) ? 1 : 0 // Konwersja boolean do Integera dla MySQL
        );

        if ( empty( $data['receiver_id'] ) || empty( $data['message'] ) ) {
            return new WP_Error( 'missing_data', 'Musisz wskazać odbiorcę i wpisać treść wiadomości.', array( 'status' => 400 ) );
        }

        // Zabezpieczenie przed wysłaniem feedbacku samemu sobie
        if ( $data['sender_id'] == $data['receiver_id'] ) {
            return new WP_Error( 'invalid_action', 'Nie możesz wysłać feedbacku do samego siebie.', array( 'status' => 400 ) );
        }

        $feedback_id = HR_Feedback::send( $data );

        if ( ! $feedback_id ) {
            return new WP_Error( 'db_error', 'Błąd podczas wysyłania feedbacku.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Feedback został wysłany.' ), 201 );
    }
}