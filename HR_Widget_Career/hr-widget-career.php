<?php
/**
 * Plugin Name: Headless HR - Widget Kariera
 * Description: Publiczny widget rekrutacyjny dla klientów. Serwuje bezpieczny kod JavaScript pod czystym adresem URL. (Wymaga HR Core oraz HR Recruitment).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-widget-career
 */

// Zabezpieczenie przed bezpośrednim uruchomieniem pliku przez przeglądarkę
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definicja stałych dla łatwiejszego zarządzania ścieżkami wewnątrz wtyczki
define( 'HR_WIDGET_CAREER_VERSION', '1.0.0' );
define( 'HR_WIDGET_CAREER_DIR', plugin_dir_path( __FILE__ ) );

// 1. Aktywacja i Deaktywacja wtyczki
// Kiedy klient włącza wtyczkę, musimy zresetować reguły linków (Rewrite Rules) w WordPressie,
// aby system zauważył nasz nowy adres api.twoj-system.pl/widgets/career.js
register_activation_hook( __FILE__, 'hr_widget_career_activate' );
register_deactivation_hook( __FILE__, 'hr_widget_career_deactivate' );

function hr_widget_career_activate() {
    require_once HR_WIDGET_CAREER_DIR . 'includes/class-hr-career-rewrite.php';
    if ( class_exists( 'HR_Career_Rewrite' ) ) {
        HR_Career_Rewrite::add_rewrite_rules();
        flush_rewrite_rules(); // Wymuszenie odświeżenia linków
    }
}

function hr_widget_career_deactivate() {
    flush_rewrite_rules(); // Sprzątanie po wyłączeniu wtyczki
}

// 2. Dependency Check - Weryfikacja wymaganych modułów
add_action( 'plugins_loaded', 'hr_widget_career_check_dependencies' );

function hr_widget_career_check_dependencies() {
    // Sprawdzamy, czy działa rdzeń (Baza i Autoryzacja) oraz moduł rekrutacji (API ofert)
    $is_core_active = class_exists( 'HR_Core_Loader' );
    $is_recruitment_active = class_exists( 'HR_Recruitment_Loader' );

    if ( ! $is_core_active || ! $is_recruitment_active ) {
        add_action( 'admin_notices', 'hr_widget_career_missing_dependencies_notice' );
        return; // Zatrzymujemy ładowanie tej wtyczki
    }

    // Jeśli zależności są spełnione, uruchamiamy orkiestrator reguł
    require_once HR_WIDGET_CAREER_DIR . 'includes/class-hr-career-rewrite.php';
    $rewrite_module = new HR_Career_Rewrite();
    $rewrite_module->init();
}

/**
 * Komunikat błędu wyświetlany w panelu administracyjnym, gdy brakuje fundamentów.
 */
function hr_widget_career_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd wtyczki HR Widget Kariera:</strong> 
            Moduł serwowania widgetów został wstrzymany. Wymaga on do działania aktywnych wtyczek <strong>HR Core</strong> oraz <strong>HR Recruitment</strong>.
        </p>
    </div>
    <?php
}