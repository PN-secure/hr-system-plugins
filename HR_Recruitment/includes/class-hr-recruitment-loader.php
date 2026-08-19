<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Recruitment_Loader {

    /**
     * Konstruktor wywoływany po pomyślnej walidacji zależności.
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Importuje pliki z warstwy logiki biznesowej i kontrolerów.
     */
    private function load_dependencies() {
        // WARSTWA MODELI (Logika biznesowa i bezpieczne zarządzanie plikami)
        require_once HR_RECRUITMENT_DIR . 'includes/models/class-hr-job.php';
        require_once HR_RECRUITMENT_DIR . 'includes/models/class-hr-candidate.php';
        require_once HR_RECRUITMENT_DIR . 'includes/models/class-hr-file-manager.php';

        // WARSTWA KONTROLERÓW (REST API)
        require_once HR_RECRUITMENT_DIR . 'includes/api/class-hr-api-public.php';
        require_once HR_RECRUITMENT_DIR . 'includes/api/class-hr-api-jobs.php';
        require_once HR_RECRUITMENT_DIR . 'includes/api/class-hr-api-candidates.php';
    }

    /**
     * Inicjalizuje endpointy i podpina je pod routing WordPressa.
     */
    public function init() {
        // Endpointy publiczne - np. dla Zakładki Kariera na stronie www
        // UWAGA: Te endpointy muszą same zarządzać weryfikacją bezpieczeństwa 
        // i ochroną przed spamem (brak blokady na poziomie globalnego filtra JWT).
        if ( class_exists( 'HR_API_Public' ) ) {
            ( new HR_API_Public() )->init();
        }

        // Endpointy wewnętrzne - Zarządzanie ofertami pracy (Tylko HR)
        if ( class_exists( 'HR_API_Jobs' ) ) {
            ( new HR_API_Jobs() )->init();
        }

        // Endpointy wewnętrzne - Lejek rekrutacyjny i Kanban (Tylko HR)
        if ( class_exists( 'HR_API_Candidates' ) ) {
            ( new HR_API_Candidates() )->init();
        }
    }
}
