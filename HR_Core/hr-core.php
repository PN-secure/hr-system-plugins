<?php
/**
 * Plugin Name: Headless HR - Core Module
 * Description: Główny silnik bazy danych i uwierzytelniania API (JWT) dla systemu HR. Wymagany do działania pozostałych modułów.
 * Version: 1.0.0
 * Author: PerfectSoft
 * Text Domain: hr-core
 */

// Zabezpieczenie przed bezpośrednim wywołaniem pliku przez przeglądarkę
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// Definicja stałych dla łatwiejszego odwoływania się do ścieżek w innych plikach
define( 'HR_CORE_VERSION', '1.0.0' );
define( 'HR_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'HR_CORE_URL', plugin_dir_url( __FILE__ ) );

// 1. Ładowanie zależności zewnętrznych (np. biblioteki Firebase PHP-JWT)
// Composer generuje folder vendor, z którego automatycznie zaczytywane są klasy.
if ( file_exists( HR_CORE_DIR . 'vendor/autoload.php' ) ) {
    require_once HR_CORE_DIR . 'vendor/autoload.php';
}

// 2. Mechanizm Aktywacji Wtyczki
// Hook uruchamiany tylko raz, gdy klient klika "Włącz" w panelu WordPressa.
register_activation_hook( __FILE__, 'hr_core_activate_plugin' );

function hr_core_activate_plugin() {
    // Wczytujemy plik budowniczego bazy danych
    require_once HR_CORE_DIR . 'includes/db/class-hr-schema-builder.php';
    
    // Odpalamy statyczną metodę budującą tabele (np. hr_employees, hr_departments)
    if ( class_exists( 'HR_Schema_Builder' ) ) {
        HR_Schema_Builder::create_tables();
    }
}

// 3. Uruchomienie głównego orkiestratora systemu
// Używamy akcji 'plugins_loaded', aby mieć pewność, że cały rdzeń WP jest już gotowy.
add_action( 'plugins_loaded', 'run_hr_core_plugin' );

function run_hr_core_plugin() {
    // Wczytanie klasy ładującej całą architekturę MVC
    require_once HR_CORE_DIR . 'includes/class-hr-core-loader.php';
    
    // Utworzenie instancji i inicjalizacja wtyczki
    $hr_core = new HR_Core_Loader();
    $hr_core->init();
}
