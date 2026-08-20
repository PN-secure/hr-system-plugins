<?php
/**
 * Plugin Name: Headless HR - Employee Development
 * Description: Moduł zarządzania rozwojem: Oceny okresowe, Instant Feedback, Spotkania 1on1. (Wymaga wtyczki HR Core).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-development
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HR_DEVELOPMENT_VERSION', '1.0.0' );
define( 'HR_DEVELOPMENT_DIR', plugin_dir_path( __FILE__ ) );

// 1. Inicjalizacja bazy danych przy aktywacji
register_activation_hook( __FILE__, 'hr_development_activate_plugin' );

function hr_development_activate_plugin() {
    require_once HR_DEVELOPMENT_DIR . 'includes/db/class-hr-development-schema.php';
    if ( class_exists( 'HR_Development_Schema' ) ) {
        HR_Development_Schema::create_tables();
    }
}

// 2. Weryfikacja zależności przed załadowaniem kodu
add_action( 'plugins_loaded', 'hr_development_check_dependencies' );

function hr_development_check_dependencies() {
    if ( ! class_exists( 'HR_Core_Loader' ) ) {
        add_action( 'admin_notices', 'hr_development_missing_core_notice' );
        return; 
    }

    require_once HR_DEVELOPMENT_DIR . 'includes/class-hr-development-loader.php';
    $hr_development = new HR_Development_Loader();
    $hr_development->init();
}

function hr_development_missing_core_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd wtyczki HR Employee Development:</strong> 
            Moduł rozwoju pracowników został zatrzymany. Do poprawnego działania wymagana jest włączona wtyczka <strong>HR Core</strong>.
        </p>
    </div>
    <?php
}
