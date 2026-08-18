<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_API_Org_Chart {

    private $namespace = 'hr/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // POST: Przypisanie pracownika do menedżera (Tylko HR Admin)
        register_rest_route( $this->namespace, '/org-chart/assign', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'assign_manager' ),
            'permission_callback' => '__return_true', // Autoryzacja leci przez klasę HR_Permissions
        ) );

        // GET: Pobranie zespołu (podwładnych) dla danego menedżera
        register_rest_route( $this->namespace, '/org-chart/team/(?P<manager_id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_team' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Endpoint dla HR do budowania struktury firmy.
     */
    public function assign_manager( WP_REST_Request $request ) {
        // Ochrona: Tylko dział kadr może zmieniać strukturę firmy
        if ( ! HR_Permissions::has_role( 'hr_admin' ) ) {
            return new WP_Error( 'rest_forbidden', 'Brak uprawnień do zmiany struktury.', array( 'status' => 403 ) );
        }

        $employee_id = $request->get_param( 'employee_id' );
        $manager_id  = $request->get_param( 'manager_id' );

        $success = HR_Manager_Relation::assign_manager( $employee_id, $manager_id );

        if ( ! $success ) {
            return new WP_Error( 'db_error', 'Nie udało się przypisać przełożonego (błędne dane lub pętla).', array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Struktura zaktualizowana.' ), 200 );
    }

    /**
     * Endpoint dla menedżera, by mógł wyświetlić swoich ludzi.
     */
    public function get_team( WP_REST_Request $request ) {
        global $hr_current_user; // Zmienna z tokena JWT

        $manager_id = $request->get_param( 'manager_id' );

        // Ochrona danych: Menedżer może pobrać tylko SWÓJ zespół, 
        // chyba że jest administratorem HR (wtedy może podejrzeć każdy zespół).
        if ( ! HR_Permissions::has_role( 'hr_admin' ) && $hr_current_user->user_id != $manager_id ) {
            return new WP_Error( 'rest_forbidden', 'Możesz przeglądać tylko własny zespół.', array( 'status' => 403 ) );
        }

        $team = HR_Manager_Relation::get_subordinates( $manager_id );

        return new WP_REST_Response( $team, 200 );
    }
}