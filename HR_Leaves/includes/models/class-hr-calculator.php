<?php
// Zabezpieczenie przed bezpośrednim dostępem
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HR_Calculator {

    /**
     * Pobiera zdefiniowane dni robocze dla danego pracownika.
     * W przyszłości można to połączyć z tabelą grafików z modułu Time Management.
     * 
     * @param int $employee_id
     * @return array Tablica dni tygodnia (1 = Poniedziałek, 7 = Niedziela)
     */
    private static function get_employee_work_days( $employee_id ) {
        // TUTAJ W PRODUKCJI BYŁOBY ZAPYTANIE DO BAZY DANYCH (np. tabeli hr_employees)
        // symulujemy pobranie danych z bazy:
        
        // Przykład: Pracownik ID 5 pracuje w weekendy (od środy do niedzieli)
        if ( $employee_id == 5 ) {
            return array( 3, 4, 5, 6, 7 ); 
        }

        // Domyślny tryb dla reszty firmy (Poniedziałek - Piątek)
        return array( 1, 2, 3, 4, 5 );
    }

    /**
     * Oblicza rzeczywistą liczbę dni roboczych pomiędzy dwiema datami DLA KONKRETNEGO PRACOWNIKA.
     * Uwzględnia jego indywidualny harmonogram oraz ustawowe święta.
     * 
     * @param int $employee_id ID pracownika (wymagane, by sprawdzić jego tryb pracy)
     * @param string $start_date Data początkowa (format YYYY-MM-DD)
     * @param string $end_date Data końcowa (format YYYY-MM-DD)
     * @return int Liczba dni roboczych
     */
    public static function get_working_days( $employee_id, $start_date, $end_date ) {
        try {
            $start = new DateTime( $start_date );
            $end   = new DateTime( $end_date );
            
            // Dodajemy jeden dzień, aby DatePeriod uwzględniła ostatni dzień przedziału
            $end->modify( '+1 day' ); 

            $interval = new DateInterval( 'P1D' );
            $period   = new DatePeriod( $start, $interval, $end );
            
            $working_days = 0;
            
            // Pobranie świąt (dla uproszczenia z roku początkowego)
            $holidays = self::get_polish_holidays( $start->format( 'Y' ) );
            
            // 1. Zmiana: Pobieramy indywidualny harmonogram pracownika!
            $custom_work_days = self::get_employee_work_days( $employee_id );

            foreach ( $period as $date ) {
                $day_of_week = (int) $date->format( 'N' ); // 1 do 7
                $date_string = $date->format( 'Y-m-d' );

                // 2. Zmiana: Sprawdzamy, czy dzień tygodnia znajduje się w harmonogramie TEGO pracownika
                // oraz czy nie wypada w święto ustawowe
                if ( in_array( $day_of_week, $custom_work_days ) && ! in_array( $date_string, $holidays ) ) {
                    $working_days++;
                }
            }

            return $working_days;
            
        } catch ( Exception $e ) {
            return 0; // Blokada w razie błędu parsowania daty
        }
    }

    /**
     * Zwraca tablicę ze stałymi i ruchomymi polskimi świętami w danym roku.
     */
    private static function get_polish_holidays( $year ) {
        $holidays = array(
            $year . '-01-01', // Nowy Rok
            $year . '-01-06', // Trzech Króli
            $year . '-05-01', // Święto Pracy
            $year . '-05-03', // Święto Konstytucji
            $year . '-08-15', // Wniebowzięcie NMP
            $year . '-11-01', // Wszystkich Świętych
            $year . '-11-11', // Święto Niepodległości
            $year . '-12-25', // Boże Narodzenie 1
            $year . '-12-26', // Boże Narodzenie 2
        );

        // Wielkanoc i Boże Ciało (Święta ruchome)
        $easter_days = easter_days( $year );
        $easter_date = new DateTime( "$year-03-21" );
        $easter_date->modify( "+$easter_days days" );
        
        $easter_monday = clone $easter_date;
        $easter_monday->modify( '+1 day' );
        
        $corpus_christi = clone $easter_date;
        $corpus_christi->modify( '+60 days' );

        $holidays[] = $easter_monday->format( 'Y-m-d' );
        $holidays[] = $corpus_christi->format( 'Y-m-d' );

        return $holidays;
    }
}
