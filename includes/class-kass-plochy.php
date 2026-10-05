<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Výlepné plochy — zoznam, rozloženie (sloty podľa formátu) a obsadenosť.
 */
class KASS_Vylep_Plochy {

    const POCET = 26;
    const KATEGORIE = array( 'top10', 'top15', 'top20' );

    /** Zaseeduje 26 plôch, ak tabuľka ešte neexistuje/je prázdna. Bez slotov — rozloženie si nastaví admin. */
    public static function seed_defaults() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy();
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        if ( $count > 0 ) { return; }
        for ( $i = 1; $i <= self::POCET; $i++ ) {
            $wpdb->insert( $t, array(
                'cislo'   => $i,
                'nazov'   => 'Plocha ' . $i,
                'poradie' => $i,
            ) );
        }
    }

    public static function get_all() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy();
        return $wpdb->get_results( "SELECT * FROM $t ORDER BY poradie ASC, cislo ASC" );
    }

    public static function get( $id ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
    }

    public static function get_sloty( $plocha_id ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy_sloty();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE plocha_id = %d ORDER BY poradie ASC", $plocha_id
        ) );
    }

    /** [plocha_id => sloty[]] pre všetky plochy naraz (bez N+1 dopytov). */
    public static function get_sloty_vsetky() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy_sloty();
        $rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY plocha_id ASC, poradie ASC" );
        $m = array();
        foreach ( $rows as $r ) {
            $m[ (int) $r->plocha_id ][] = $r->format;
        }
        return $m;
    }

    public static function save_plocha( $id, $data ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy();
        $wpdb->update( $t, array(
            'nazov' => sanitize_text_field( $data['nazov'] ?? '' ),
            'top10' => ! empty( $data['top10'] ) ? 1 : 0,
            'top15' => ! empty( $data['top15'] ) ? 1 : 0,
            'top20' => ! empty( $data['top20'] ) ? 1 : 0,
        ), array( 'id' => (int) $id ) );
    }

    /** Nahradí sloty danej plochy zoznamom formátov (poradie = poradie v poli). */
    public static function save_sloty( $plocha_id, array $formaty ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy_sloty();
        $plocha_id = (int) $plocha_id;
        $wpdb->delete( $t, array( 'plocha_id' => $plocha_id ) );
        $povolene = array( 'A4', 'A3', 'A2', 'A1' );
        $poradie = 0;
        foreach ( $formaty as $f ) {
            $f = strtoupper( trim( $f ) );
            if ( ! in_array( $f, $povolene, true ) ) { continue; }
            $wpdb->insert( $t, array(
                'plocha_id' => $plocha_id,
                'poradie'   => $poradie++,
                'format'    => $f,
            ) );
        }
    }

    /**
     * ID plôch, ktoré pokrýva daný spôsob priradenia výlepu.
     * $vsetky_plochy — výsledok get_all(), aby sa pri hromadnom spracovaní nevolalo opakovane.
     */
    public static function resolve_ids( $sposob, $vyber_csv, $vsetky_plochy ) {
        if ( $sposob === 'vyber' ) {
            $ids = array_filter( array_map( 'intval', explode( ',', (string) $vyber_csv ) ) );
            return array_values( $ids );
        }
        if ( in_array( $sposob, self::KATEGORIE, true ) ) {
            $out = array();
            foreach ( $vsetky_plochy as $p ) {
                if ( ! empty( $p->{$sposob} ) ) { $out[] = (int) $p->id; }
            }
            return $out;
        }
        // 'vsetky' (default aj fallback pre neznámu hodnotu)
        return wp_list_pluck( $vsetky_plochy, 'id' );
    }

    /**
     * Obsadenosť všetkých plôch podľa formátu, pre aktuálne prebiehajúce výlepy
     * (dátum $datum v rozsahu Od–Do). Jeden prechod cez aktívne výlepy.
     * Vráti [plocha_id => ['A4'=>n,'A3'=>n,'A2'=>n,'A1'=>n]] pre všetkých 26 plôch.
     */
    public static function obsadenost_vsetky( $datum = null ) {
        $datum = $datum ?: current_time( 'Y-m-d' );
        $plochy = self::get_all();

        $obsadenost = array();
        foreach ( $plochy as $p ) {
            $obsadenost[ (int) $p->id ] = array( 'A4' => 0, 'A3' => 0, 'A2' => 0, 'A1' => 0 );
        }

        $aktivne = KASS_Vylep_DB::get_vylepy( array( 'active_on' => $datum ) );
        foreach ( $aktivne as $v ) {
            $format = $v->format ?? 'A3';
            $ids = self::resolve_ids( $v->plochy_sposob ?? 'vsetky', $v->plochy_vyber ?? '', $plochy );
            foreach ( $ids as $pid ) {
                if ( isset( $obsadenost[ $pid ][ $format ] ) ) {
                    $obsadenost[ $pid ][ $format ]++;
                }
            }
        }
        return $obsadenost;
    }
}
