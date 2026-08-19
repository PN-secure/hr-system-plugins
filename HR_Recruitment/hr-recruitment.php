<?php
/**
 * Plugin Name: Headless HR - Recruitment & Onboarding
 * Description: Moduł ATS (System Rekrutacyjny) oraz Onboarding. Wystawia publiczne API dla kandydatów. (Wymaga wtyczki HR Core).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-recruitment
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HR_RECRUITMENT_VERSION', '1.0.0' );
define( 'HR_RECRUITMENT_DIR', plugin_dir_path( __FILE__ ) );

// 1. Inicjalizacja schematu bazy danych przy aktywacji
register_activation_hook( __FILE__, 'hr_recruitment_activate_plugin' );

function hr_recruitment_activate_plugin() {
    require_once HR_RECRUITMENT_DIR . 'includes/db/class-hr-recruitment-schema.php';
    if ( class_exists( 'HR_Recruitment_Schema' ) ) {
        HR_Recruitment_Schema::create_tables();
    }
}

// 2. Weryfikacja zależności (Dependency Check) na hooku plugins_loaded
add_action( 'plugins_loaded', 'hr_recruitment_check_dependencies' );

function hr_recruitment_check_dependencies() {
    // Weryfikacja, czy główny orkiestrator z wtyczki Core znajduje się w pamięci
    if ( ! class_exists( 'HR_Core_Loader' ) ) {
        // Blokada działania modułu i wyświetlenie komunikatu w panelu administracyjnym
        add_action( 'admin_notices', 'hr_recruitment_missing_core_notice' );
        return; 
    }

    // Bezpieczne ładowanie modułu ATS
    require_once HR_RECRUITMENT_DIR . 'includes/class-hr-recruitment-loader.php';
    $hr_recruitment = new HR_Recruitment_Loader();
    $hr_recruitment->init();
}

/**
 * Renderuje komunikat o błędzie braku zależności krytycznych.
 */
function hr_recruitment_missing_core_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd krytyczny:</strong> 
            Wtyczka <em>HR Recruitment & Onboarding</em> została zatrzymana. System rekrutacyjny wymaga do działania aktywnej wtyczki <strong>HR Core</strong>.
        </p>
    </div>
    <?php
}