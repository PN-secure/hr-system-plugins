<?php
/**
 * Plugin Name: Headless HR - Widget Portal Pracowniczy
 * Description: Prywatny widget SPA dla pracowników (Logowanie, Czas Pracy, Urlopy, Oceny). (Wymaga HR Core, HR Leaves, HR Development).
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-widget-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HR_WIDGET_PORTAL_VERSION', '1.0.0' );
define( 'HR_WIDGET_PORTAL_DIR', plugin_dir_path( __FILE__ ) );

// 1. Aktywacja i Deaktywacja (Wymuszenie odświeżenia linków na Multisite)
register_activation_hook( __FILE__, 'hr_widget_portal_activate' );
register_deactivation_hook( __FILE__, 'hr_widget_portal_deactivate' );

function hr_widget_portal_activate( $network_wide ) {
    require_once HR_WIDGET_PORTAL_DIR . 'includes/class-hr-portal-rewrite.php';
    if ( ! class_exists( 'HR_Portal_Rewrite' ) ) return;

    if ( is_multisite() && $network_wide ) {
        global $wpdb;
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            HR_Portal_Rewrite::add_rewrite_rules();
            flush_rewrite_rules();
            restore_current_blog();
        }
    } else {
        HR_Portal_Rewrite::add_rewrite_rules();
        flush_rewrite_rules();
    }
}

function hr_widget_portal_deactivate() {
    flush_rewrite_rules(); 
}

// 2. Dependency Check
add_action( 'plugins_loaded', 'hr_widget_portal_check_dependencies' );

function hr_widget_portal_check_dependencies() {
    // Portal wymaga 3 silników API do prawidłowego działania kokpitu
    $core_active   = class_exists( 'HR_Core_Loader' );
    $leaves_active = class_exists( 'HR_Leaves_Loader' );
    $dev_active    = class_exists( 'HR_Development_Loader' );

    if ( ! $core_active || ! $leaves_active || ! $dev_active ) {
        add_action( 'admin_notices', 'hr_widget_portal_missing_dependencies_notice' );
        return; 
    }

    require_once HR_WIDGET_PORTAL_DIR . 'includes/class-hr-portal-rewrite.php';
    $rewrite_module = new HR_Portal_Rewrite();
    $rewrite_module->init();
}

function hr_widget_portal_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Błąd wtyczki HR Widget Portal:</strong> 
            Moduł wymaga aktywnych wtyczek: <strong>HR Core, HR Leaves, HR Development</strong>.
        </p>
    </div>
    <?php
}