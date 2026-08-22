<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Requests {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // 1. Składanie wniosku (Pracownik)
        register_rest_route( $this->namespace, '/leaves/apply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'apply_for_leave' ),
            'permission_callback' => '__return_true',
        ) );

        // 2. Pobieranie wniosków oczekujących na akceptację (Menedżer)
        register_rest_route( $this->namespace, '/leaves/pending', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_pending_requests' ),
            'permission_callback' => '__return_true',
        ) );

        // 3. Akceptacja lub odrzucenie wniosku (Menedżer)
        register_rest_route( $this->namespace, '/leaves/(?P<id>\d+)/status', array(
            'methods'             => WP_REST_Server::EDITABLE, // Metoda PUT
            'callback'            => array( $this, 'update_status' ),
            'permission_callback' => '__return_true',
        ) );
        // 4. Pobieranie historycznych i aktualnych wniosków pracownika
        register_rest_route( $this->namespace, '/leaves/my-requests', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_requests' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Endpoint 1: Składanie wniosku
     */
    public function apply_for_leave( WP_REST_Request $request ) {
        // MAGIA CROSS-PLUGIN: Odczytujemy, kim jest wysyłający, z tokena JWT odkodowanego przez Core!
        global $hr_current_user; 
        
        $employee_id   = $hr_current_user->employee_id; // ID pracownika HR z bezpiecznego tokena.
        $leave_type_id = $request->get_param( 'leave_type_id' );
        $start_date    = $request->get_param( 'start_date' );
        $end_date      = $request->get_param( 'end_date' );
        $reason        = $request->get_param( 'reason' );

        // Zlecamy całą trudną matematykę (weekendy, limity) do modelu HR_Leave_Request
        $result = HR_Leave_Request::apply( $employee_id, $leave_type_id, $start_date, $end_date, $reason );

        if ( is_wp_error( $result ) ) {
            return $result; // Jeśli to błąd limitu lub dat, po prostu zwracamy JSON z błędem do Reacta
        }

        return new WP_REST_Response( array( 
            'success' => true, 
            'message' => 'Wniosek został złożony i czeka na akceptację.',
            'request_id' => $result
        ), 201 );
    }

    /**
     * Endpoint 2: Menedżer loguje się do systemu i widzi wnioski swojego zespołu
     */
    public function get_pending_requests( WP_REST_Request $request ) {
        global $wpdb;
        global $hr_current_user;

        $manager_id = $hr_current_user->employee_id;

        // Krok 1: Korzystamy z modelu wtyczki HR Core, żeby pobrać listę podwładnych tego menedżera
        if ( ! class_exists( 'HR_Manager_Relation' ) ) {
            return new WP_Error( 'dependency_error', 'Brak modułu drzewa organizacyjnego.', array( 'status' => 500 ) );
        }
        
        $team = HR_Manager_Relation::get_subordinates( $manager_id );
        
        if ( empty( $team ) ) {
            return new WP_REST_Response( array(), 200 ); // Menedżer nie ma podwładnych (lub to zwykły pracownik)
        }

        // Krok 2: Wyciągnięcie samych ID podwładnych
        $team_ids = array_column( $team, 'id' );
        $ids_placeholder = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );

        // Krok 3: Szukamy wniosków tylko dla tych konkretnych pracowników ze statusem "pending"
        $table_requests = $wpdb->prefix . 'hr_leave_requests';
        $sql = "SELECT r.*, e.first_name, e.last_name 
                FROM {$table_requests} r
                JOIN {$wpdb->prefix}hr_employees e ON r.employee_id = e.id
                WHERE r.status = 'pending' AND r.employee_id IN ($ids_placeholder)";
        
        $pending_requests = $wpdb->get_results( $wpdb->prepare( $sql, ...$team_ids ), ARRAY_A );

        return new WP_REST_Response( $pending_requests, 200 );
    }

    /**
     * Endpoint 3: Zmiana statusu przez Menedżera (Akceptacja)
     */
    public function update_status( WP_REST_Request $request ) {
        global $hr_current_user;

        $request_id    = $request->get_param( 'id' );
        $new_status    = $request->get_param( 'status' ); // 'approved' lub 'rejected'
        $reject_reason = $request->get_param( 'reason' );

        // Dodatkowe zabezpieczenie: Czy osoba próbująca zmienić status faktycznie jest menedżerem lub HR-em?
        // (W produkcji musielibyśmy sprawdzić w drzewie, czy to na pewno menedżer tego konkretnego pracownika)
        $is_admin = HR_Permissions::has_role( 'hr_admin' );

        $is_manager = HR_Leave_Request::can_user_update_request(
            $request_id,
            $hr_current_user->employee_id
        );

        // Sprawdzenie, czy przełożony może zmienić status tego konkretnego wniosku.
        if ( ! $is_admin && ! $is_manager ) {
            return new WP_Error(
                'rest_forbidden', 'Nie masz uprawnień do zmiany statusu tego wniosku.',
                array( 'status' => 403 )
            );
        }

        $success = HR_Leave_Request::change_status( $request_id, $new_status, $hr_current_user->employee_id, $reject_reason );

        if ( ! $success ) {
            return new WP_Error( 'update_failed', 'Nie udało się zmienić statusu wniosku.', array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Status wniosku został zaktualizowany.' ), 200 );
    }

    public function get_my_requests( WP_REST_Request $request ) {
        global $hr_current_user;

        $requests = HR_Leave_Request::get_by_employee( $hr_current_user->employee_id );

        return new WP_REST_Response( $requests, 200 );
    }
}