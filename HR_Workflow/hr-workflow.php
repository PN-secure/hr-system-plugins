<?php
/**
 * Plugin Name: Headless HR - Workflow & Admin
 * Description: Moduł administracyjny: Obieg wniosków wewnętrznych, rejestr faktur kosztowych oraz umów. (Wymaga wtyczki HR Core).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-workflow
 */

// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HR_WORKFLOW_VERSION', '1.0.0' );
define( 'HR_WORKFLOW_DIR', plugin_dir_path( __FILE__ ) );

// 1. Uruchomienie budowniczego bazy danych przy aktywacji
register_activation_hook( __FILE__, 'hr_workflow_activate_plugin' );

function hr_workflow_activate_plugin() {
    require_once HR_WORKFLOW_DIR . 'includes/db/class-hr-workflow-schema.php';
    if ( class_exists( 'HR_Workflow_Schema' ) ) {
        HR_Workflow_Schema::create_tables();
    }
}

// 2. Weryfikacja zależności przed załadowaniem jakiejkolwiek logiki biznesowej
add_action( 'plugins_loaded', 'hr_workflow_check_dependencies' );

function hr_workflow_check_dependencies() {
    // Sprawdzenie, czy fundament systemu istnieje w pamięci
    if ( ! class_exists( 'HR_Core_Loader' ) ) {
        add_action( 'admin_notices', 'hr_workflow_missing_core_notice' );
        return; 
    }

    // Bezpieczne wczytanie i uruchomienie orkiestratora
    require_once HR_WORKFLOW_DIR . 'includes/class-hr-workflow-loader.php';
    $hr_workflow = new HR_Workflow_Loader();
    $hr_workflow->init();
}

/**
 * Wyświetlenie ostrzeżenia w panelu administracyjnym.
 */
function hr_workflow_missing_core_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd wtyczki HR Workflow & Admin:</strong> 
            Moduł obiegu dokumentów został zablokowany. Wymaga on włączonej i skonfigurowanej wtyczki <strong>HR Core</strong>.
        </p>
    </div>
    <?php
}
