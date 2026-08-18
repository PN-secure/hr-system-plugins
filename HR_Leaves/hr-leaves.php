<?php
/**
 * Plugin Name: Headless HR - Leaves Module
 * Description: Moduł zarządzania nieobecnościami i urlopami. (Wymaga wtyczki HR Core).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-leaves
 */

// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HR_LEAVES_VERSION', '1.0.0' );
define( 'HR_LEAVES_DIR', plugin_dir_path( __FILE__ ) );

// 1. Mechanizm Aktywacji Wtyczki (Tworzenie tabel)
register_activation_hook( __FILE__, 'hr_leaves_activate_plugin' );

function hr_leaves_activate_plugin() {
    require_once HR_LEAVES_DIR . 'includes/db/class-hr-leaves-schema.php';
    if ( class_exists( 'HR_Leaves_Schema' ) ) {
        HR_Leaves_Schema::create_tables();
    }
}

// 2. Weryfikacja Zależności i Uruchomienie (Dependency Check)
add_action( 'plugins_loaded', 'hr_leaves_check_dependencies' );

function hr_leaves_check_dependencies() {
    // Sprawdzamy, czy główna klasa z modułu Core została wczytana do pamięci
    if ( ! class_exists( 'HR_Core_Loader' ) ) {
        // Jeśli nie ma Core, wyświetlamy błąd w panelu WP i PRZERYWAMY działanie wtyczki
        add_action( 'admin_notices', 'hr_leaves_missing_core_notice' );
        return; 
    }

    // Jeśli Core jest aktywny, bezpiecznie ładujemy nasz moduł urlopowy
    require_once HR_LEAVES_DIR . 'includes/class-hr-leaves-loader.php';
    $hr_leaves = new HR_Leaves_Loader();
    $hr_leaves->init();
}

/**
 * Wyświetla czerwony komunikat błędu w panelu administratora WordPressa.
 */
function hr_leaves_missing_core_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd krytyczny systemu HR:</strong> 
            Wtyczka <em>HR Leaves (Urlopy)</em> została wstrzymana. Do jej działania absolutnie wymagana jest aktywna wtyczka <strong>HR Core</strong>.
        </p>
    </div>
    <?php
}