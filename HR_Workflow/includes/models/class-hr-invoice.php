<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Invoice {

    private static $table_suffix = 'hr_invoices';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Tworzy i zabezpiecza folder dla skanów faktur.
     */
    private static function get_secure_invoices_dir() {
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/hr_invoices';

        if ( ! is_dir( $invoices_dir ) ) {
            wp_mkdir_p( $invoices_dir );
            
            // Blokada dostępu z poziomu przeglądarki
            $htaccess_path = $invoices_dir . '/.htaccess';
            if ( ! file_exists( $htaccess_path ) ) {
                file_put_contents( $htaccess_path, "Order Allow,Deny\nDeny from all" );
            }
            
            $index_path = $invoices_dir . '/index.php';
            if ( ! file_exists( $index_path ) ) {
                file_put_contents( $index_path, "<?php\n// Silence is golden." );
            }
        }

        return $invoices_dir;
    }

    /**
     * Wgrywanie skanu faktury na serwer z pełną weryfikacją bezpieczeństwa.
     */
    public static function upload_invoice_file( $file_array ) {
        if ( empty( $file_array ) || $file_array['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Wystąpił błąd przesyłania pliku.' );
        }

        // Walidacja rozszerzenia
        $file_name = sanitize_file_name( $file_array['name'] );
        $ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
        $allowed_exts = array( 'pdf', 'jpg', 'jpeg', 'png' );
        
        if ( ! in_array( $ext, $allowed_exts ) ) {
            return new WP_Error( 'invalid_extension', 'Dozwolone są tylko pliki PDF, JPG i PNG.' );
        }

        // Weryfikacja rzeczywistego typu MIME pliku
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file_array['tmp_name'] );
        finfo_close( $finfo );

        $allowed_mimes = array( 'application/pdf', 'image/jpeg', 'image/png' );
        if ( ! in_array( $mime_type, $allowed_mimes ) ) {
            return new WP_Error( 'invalid_mime', 'Zawartość pliku jest niedozwolona lub uszkodzona.' );
        }

        // Limit wielkości pliku (Max 5MB)
        if ( $file_array['size'] > 5 * 1024 * 1024 ) {
            return new WP_Error( 'file_too_large', 'Skan faktury nie może ważyć więcej niż 5 MB.' );
        }

        // Zapis do ukrytego katalogu
        $secure_dir = self::get_secure_invoices_dir();
        $unique_filename = wp_generate_uuid4() . '_' . time() . '.' . $ext;
        $destination = $secure_dir . '/' . $unique_filename;

        if ( move_uploaded_file( $file_array['tmp_name'], $destination ) ) {
            return $destination;
        }

        return new WP_Error( 'move_error', 'Nie udało się zapisać skanu faktury na serwerze.' );
    }

    /**
     * Wprowadzenie nowej faktury kosztowej do obiegu.
     */
    public static function submit( $data, $file_path ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'employee_id'    => absint( $data['employee_id'] ),
                'invoice_number' => sanitize_text_field( $data['invoice_number'] ),
                'amount'         => floatval( $data['amount'] ),
                'currency'       => sanitize_text_field( $data['currency'] ),
                'description'    => sanitize_textarea_field( $data['description'] ),
                'file_path'      => sanitize_text_field( $file_path ),
                'status'         => 'pending' // Początkowy status czekający na przełożonego
            ),
            array( '%d', '%s', '%f', '%s', '%s', '%s', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Aktualizacja statusu przez menedżera lub księgowość.
     */
    public static function change_status( $invoice_id, $new_status ) {
        global $wpdb;
        $table = self::get_table_name();

        $allowed_statuses = array( 'manager_approved', 'paid', 'rejected' );
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            array( 'status' => $new_status ),
            array( 'id' => absint( $invoice_id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }
}
