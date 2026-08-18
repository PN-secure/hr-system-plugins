<?php
// Zabezpieczenie przed bezpośrednim wywołaniem pliku
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Core_Loader {

    /**
     * Konstruktor uruchamiany w momencie powołania obiektu w hr-core.php.
     * Od razu ładuje wszystkie wymagane pliki do pamięci serwera.
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Wczytuje wszystkie niezbędne pliki z zachowaniem odpowiedniej kolejności.
     */
    private function load_dependencies() {
        // 1. WARSTWA BAZY DANYCH (Nie ładujemy schema-builder, bo on działa tylko przy aktywacji)
        // require_once HR_CORE_DIR . 'includes/db/class-hr-migrations.php'; 

        // 2. WARSTWA BEZPIECZEŃSTWA (Kluczowa dla API)
        require_once HR_CORE_DIR . 'includes/auth/class-hr-jwt-handler.php';
        require_once HR_CORE_DIR . 'includes/auth/class-hr-permissions.php';

        // 3. WARSTWA MODELI (Logika biznesowa i komunikacja z tabelami SQL)
        require_once HR_CORE_DIR . 'includes/models/class-hr-employee.php';
        require_once HR_CORE_DIR . 'includes/models/class-hr-department.php';
        require_once HR_CORE_DIR . 'includes/models/class-hr-role.php';

        // 4. WARSTWA KONTROLERÓW API (Endpointy wystawiane na świat)
        require_once HR_CORE_DIR . 'includes/api/class-hr-api-auth.php';
        require_once HR_CORE_DIR . 'includes/api/class-hr-api-employees.php';
        require_once HR_CORE_DIR . 'includes/api/class-hr-api-departments.php';
        require_once HR_CORE_DIR . 'includes/api/class-hr-api-org-chart.php';
    }

    /**
     * Inicjalizuje klasy i podpina je pod odpowiednie "hooki" w WordPressie.
     * Ta metoda jest wywoływana ręcznie w pliku głównym.
     */
    public function init() {
        // Uruchomienie mechanizmów autoryzacji (Globalne filtry sprawdzające nagłówki HTTP)
        if ( class_exists( 'HR_JWT_Handler' ) ) {
            ( new HR_JWT_Handler() )->init();
        }
        
        if ( class_exists( 'HR_Permissions' ) ) {
            ( new HR_Permissions() )->init();
        }

        // Rejestracja punktów końcowych (Endpointów) REST API
        // Każda z tych klas podepnie się pod akcję 'rest_api_init'
        if ( class_exists( 'HR_API_Auth' ) ) {
            ( new HR_API_Auth() )->init();
        }
        
        if ( class_exists( 'HR_API_Employees' ) ) {
            ( new HR_API_Employees() )->init();
        }
        
        if ( class_exists( 'HR_API_Departments' ) ) {
            ( new HR_API_Departments() )->init();
        }
        
        if ( class_exists( 'HR_API_Org_Chart' ) ) {
            ( new HR_API_Org_Chart() )->init();
        }
    }
}