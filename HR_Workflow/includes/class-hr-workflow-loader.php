<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Workflow_Loader {

    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Wczytywanie plików z warstw modeli i kontrolerów.
     */
    private function load_dependencies() {
        // WARSTWA MODELI (Logika biznesowa bazy danych)
        require_once HR_WORKFLOW_DIR . 'includes/models/class-hr-request.php';
        require_once HR_WORKFLOW_DIR . 'includes/models/class-hr-invoice.php';
        require_once HR_WORKFLOW_DIR . 'includes/models/class-hr-contract.php';

        // WARSTWA KONTROLERÓW (REST API)
        require_once HR_WORKFLOW_DIR . 'includes/api/class-hr-api-requests.php';
        require_once HR_WORKFLOW_DIR . 'includes/api/class-hr-api-invoices.php';
        require_once HR_WORKFLOW_DIR . 'includes/api/class-hr-api-contracts.php';
    }

    /**
     * Inicjalizacja endpointów i rejestracja ich w systemie WordPress REST API.
     * Uwaga: Autoryzacja i weryfikacja tokenów JWT odbywa się globalnie przez wtyczkę Core.
     */
    public function init() {
        // Endpointy wniosków pracowniczych (np. zapotrzebowanie na sprzęt)
        if ( class_exists( 'HR_Workflow_API_Requests' ) ) {
            ( new HR_Workflow_API_Requests() )->init();
        }

        // Endpointy obiegu faktur kosztowych
        if ( class_exists( 'HR_API_Invoices' ) ) {
            ( new HR_API_Invoices() )->init();
        }

        // Endpointy elektronicznego rejestru umów
        if ( class_exists( 'HR_API_Contracts' ) ) {
            ( new HR_API_Contracts() )->init();
        }
    }
}