<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Development_Loader {

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        // WARSTWA MODELI
        require_once HR_DEVELOPMENT_DIR . 'includes/models/class-hr-evaluation.php';
        require_once HR_DEVELOPMENT_DIR . 'includes/models/class-hr-feedback.php';

        // WARSTWA KONTROLERÓW (REST API)
        require_once HR_DEVELOPMENT_DIR . 'includes/api/class-hr-api-evaluations.php';
        require_once HR_DEVELOPMENT_DIR . 'includes/api/class-hr-api-feedback.php';
    }

    public function init() {
        if ( class_exists( 'HR_API_Evaluations' ) ) {
            ( new HR_API_Evaluations() )->init();
        }

        if ( class_exists( 'HR_API_Feedback' ) ) {
            ( new HR_API_Feedback() )->init();
        }
    }
}