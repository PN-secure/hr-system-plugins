<?php
// Zabezpieczenie przed bezpośrednim wywołaniem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Candidate {

    private static $table_suffix = 'hr_candidates';

    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_suffix;
    }

    /**
     * Zapisuje nową aplikację kandydata na wybrane stanowisko.
     */
    public static function apply( $data, $cv_path ) {
        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'job_id'     => absint( $data['job_id'] ),
                'first_name' => sanitize_text_field( $data['first_name'] ),
                'last_name'  => sanitize_text_field( $data['last_name'] ),
                'email'      => sanitize_email( $data['email'] ),
                'phone'      => sanitize_text_field( $data['phone'] ),
                'cv_path'    => sanitize_text_field( $cv_path ),
                'stage'      => 'new' // Domyślny etap na tablicy Kanban
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Przesuwa kandydata do kolejnego etapu rekrutacji.
     */
    public static function change_stage( $candidate_id, $new_stage ) {
        global $wpdb;
        $table = self::get_table_name();

        $allowed_stages = array( 'new', 'phone_screen', 'interview', 'task', 'offer', 'hired', 'rejected' );
        if ( ! in_array( $new_stage, $allowed_stages ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            array( 'stage' => $new_stage ),
            array( 'id' => absint( $candidate_id ) ),
            array( '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Najważniejsza operacja biznesowa: Zatrudnienie.
     * Wykorzystuje HR_Employee z modułu Core do założenia kartoteki,
     * a następnie generuje zadania wdrożeniowe.
     */
    public static function hire( $candidate_id, $department_id, $role_id ) {
        global $wpdb;
        $table_candidates = self::get_table_name();

        // 1. Pobranie danych kandydata
        $candidate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_candidates} WHERE id = %d", $candidate_id ), ARRAY_A );
        
        if ( ! $candidate ) {
            return new WP_Error( 'not_found', 'Kandydat nie istnieje.' );
        }

        // 2. Walidacja: Upewnienie się, że moduł Core jest aktywny i udostępnia model pracownika
        if ( ! class_exists( 'HR_Employee' ) ) {
            return new WP_Error( 'dependency_error', 'Wtyczka HR Core jest wymagana do utworzenia profilu pracownika.' );
        }

        // 3. Utworzenie pełnoprawnego pracownika w systemie HR Core!
        $employee_data = array(
            'first_name'      => $candidate['first_name'],
            'last_name'       => $candidate['last_name'],
            'email'           => $candidate['email'],
            'department_id'   => $department_id,
            'role_id'         => $role_id,
            'employment_date' => current_time( 'mysql', 1 )
        );

        $employee_id = HR_Employee::create( $employee_data );

        if ( ! $employee_id ) {
            return new WP_Error( 'creation_failed', 'Nie udało się założyć kartoteki pracownika.' );
        }

        // 4. Aktualizacja statusu kandydata na "Zatrudniony"
        self::change_stage( $candidate_id, 'hired' );

        // 5. Inicjalizacja Onboardingu (dodanie podstawowych zadań do tabeli zadań)
        self::generate_onboarding_tasks( $employee_id );

        return $employee_id;
    }

    /**
     * Generuje checklistę na start dla nowo zatrudnionego pracownika.
     */
    private static function generate_onboarding_tasks( $employee_id ) {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'hr_onboarding_tasks';

        $default_tasks = array(
            'Podpisanie umowy o pracę',
            'Szkolenie wstępne BHP',
            'Utworzenie konta e-mail i dostępu do komunikatora',
            'Odbiór sprzętu służbowego (laptop, telefon)'
        );

        foreach ( $default_tasks as $task ) {
            $wpdb->insert(
                $table_tasks,
                array(
                    'employee_id' => absint( $employee_id ),
                    'task_title'  => sanitize_text_field( $task ),
                    'is_completed'=> 0
                ),
                array( '%d', '%s', '%d' )
            );
        }
    }
}