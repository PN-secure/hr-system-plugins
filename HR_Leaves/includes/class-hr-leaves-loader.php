<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Leaves_Loader {

    /**
     * Konstruktor: Od razu zaciąga wszystkie pliki modułu urlopowego.
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Wczytuje pliki zachowując wzorzec MVC.
     */
    private function load_dependencies() {
        // WARSTWA MODELI (Logika biznesowa)
        // class-hr-leaves-schema.php jest ładowane tylko przy aktywacji, więc go tu pomijamy
        require_once HR_LEAVES_DIR . 'includes/models/class-hr-leave-type.php';
        require_once HR_LEAVES_DIR . 'includes/models/class-hr-leave-request.php';
        require_once HR_LEAVES_DIR . 'includes/models/class-hr-calculator.php';
        require_once HR_LEAVES_DIR . 'includes/models/class-hr-time-entry.php';
        require_once HR_LEAVES_DIR . 'includes/models/class-hr-delegations.php';

        // WARSTWA KONTROLERÓW (REST API)
        require_once HR_LEAVES_DIR . 'includes/api/class-hr-api-leave-types.php';
        require_once HR_LEAVES_DIR . 'includes/api/class-hr-api-requests.php';
        require_once HR_LEAVES_DIR . 'includes/api/class-hr-api-time-tracking.php';
        require_once HR_LEAVES_DIR . 'includes/api/class-hr-api-delegations.php';
    }

    /**
     * Inicjalizuje kontrolery API, podpinając je pod system routing'u WordPressa.
     * Uruchamiane z poziomu hr-leaves.php po pozytywnej weryfikacji zależności.
     */
    public function init() {
        // Zauważ: nie ładujemy tu autoryzacji (JWT ani Permissions), 
        // ponieważ system HR Core robi to za nas globalnie dla całego API (hr/v1/...)!

        // Uruchomienie Endpointów dla słownika typów urlopów (L4, Wypoczynkowy)
        if ( class_exists( 'HR_API_Leave_Types' ) ) {
            ( new HR_API_Leave_Types() )->init();
        }

        // Uruchomienie Endpointów dla składania i akceptacji wniosków
        if ( class_exists( 'HR_API_Requests' ) ) {
            ( new HR_API_Requests() )->init();
        }

        if ( class_exists( 'HR_API_Time_Tracking' ) ) {
            ( new HR_API_Time_Tracking() )->init();
        }

        if ( class_exists( 'HR_API_Delegations' ) ) {
            ( new HR_API_Delegations() )->init();
        }
    }
}
