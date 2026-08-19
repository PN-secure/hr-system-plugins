<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_File_Manager {

    /**
     * Zwraca i w razie potrzeby tworzy bezpieczną ścieżkę do folderu CV.
     */
    private static function get_secure_upload_dir() {
        $upload_dir = wp_upload_dir();
        $cv_dir = $upload_dir['basedir'] . '/hr_cv';

        if ( ! is_dir( $cv_dir ) ) {
            wp_mkdir_p( $cv_dir );
            
            // Generowanie .htaccess blokującego bezpośredni dostęp z przeglądarki
            $htaccess_path = $cv_dir . '/.htaccess';
            if ( ! file_exists( $htaccess_path ) ) {
                $rules = "Order Allow,Deny\nDeny from all";
                file_put_contents( $htaccess_path, $rules );
            }
            
            // Pusty plik index.php ukrywający strukturę katalogu
            $index_path = $cv_dir . '/index.php';
            if ( ! file_exists( $index_path ) ) {
                file_put_contents( $index_path, "<?php\n// Silence is golden." );
            }
        }

        return $cv_dir;
    }

    /**
     * Bezpiecznie przetwarza i zapisuje przesłane CV.
     * * @param array $file_array Tablica z danymi pliku (odpowiednik $_FILES['cv'])
     * @return string|WP_Error Ścieżka do pliku lub obiekt błędu.
     */
    public static function upload_cv( $file_array ) {
        if ( empty( $file_array ) || $file_array['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Wystąpił błąd podczas przesyłania pliku.' );
        }

        // 1. Walidacja rozszerzenia (Tylko PDF)
        $file_name = sanitize_file_name( $file_array['name'] );
        $ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
        if ( $ext !== 'pdf' ) {
            return new WP_Error( 'invalid_extension', 'Dozwolone są wyłącznie pliki PDF.' );
        }

        // 2. Walidacja rzeczywistego typu MIME (zabezpieczenie przed zmianą rozszerzenia pliku wykonywalnego)
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file_array['tmp_name'] );
        finfo_close( $finfo );

        if ( $mime_type !== 'application/pdf' ) {
            return new WP_Error( 'invalid_mime', 'Plik jest uszkodzony lub zawiera niedozwoloną zawartość.' );
        }

        // 3. Walidacja rozmiaru (Max 5 MB)
        $max_size = 5 * 1024 * 1024;
        if ( $file_array['size'] > $max_size ) {
            return new WP_Error( 'file_too_large', 'Plik CV nie może przekraczać 5 MB.' );
        }

        // 4. Generowanie unikalnej nazwy i przeniesienie pliku do bezpiecznego folderu
        $secure_dir = self::get_secure_upload_dir();
        $unique_filename = wp_generate_uuid4() . '_' . time() . '.pdf';
        $destination = $secure_dir . '/' . $unique_filename;

        if ( move_uploaded_file( $file_array['tmp_name'], $destination ) ) {
            return $destination;
        }

        return new WP_Error( 'move_error', 'Nie udało się zapisać pliku na serwerze.' );
    }
}