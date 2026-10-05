<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Výlepné plochy — zoznam, rozloženie (moduly v hornom/spodnom rade) a obsadenosť.
 *
 * Horný rad (modul = A1 na výšku):   A1 výška | 2× A2 šírka | 4× A3 výška
 * Spodný rad (modul = A2 na výšku):  A2 výška | A1 šírka (2 moduly) | 2× A3 šírka
 * Formát + orientácia plagátu teda jednoznačne určuje rad.
 */
class KASS_Vylep_Plochy {

    const POCET = 26;
    const KATEGORIE = array( 'top10', 'top15', 'top20' );

    /** Kľúče obsadenosti: formát + orientácia (v = na výšku, s = na šírku); A4 mimo schémy. */
    public static function kluce() {
        return array( 'A1v', 'A2s', 'A3v', 'A2v', 'A3s', 'A1s', 'A4' );
    }

    /** Zaseeduje 26 plôch, ak tabuľka ešte neexistuje/je prázdna. Rozloženie si nastaví admin. */
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

    public static function save_plocha( $id, $data ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_plochy();
        $wpdb->update( $t, array(
            'nazov'        => sanitize_text_field( $data['nazov'] ?? '' ),
            'top10'        => ! empty( $data['top10'] ) ? 1 : 0,
            'top15'        => ! empty( $data['top15'] ) ? 1 : 0,
            'top20'        => ! empty( $data['top20'] ) ? 1 : 0,
            'top_units'    => self::units( $data['top_units'] ?? 0, 0.5 ),
            'bottom_units' => self::units( $data['bottom_units'] ?? 0, 1 ),
        ), array( 'id' => (int) $id ) );
    }

    /** Počet modulov: nezáporné, zaokrúhlené na krok ($krok), max 40. */
    public static function units( $v, $krok ) {
        $v = (float) str_replace( ',', '.', (string) $v );
        $v = max( 0, min( 40, $v ) );
        return round( $v / $krok ) * $krok;
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

    /** Kľúč obsadenosti pre výlep (formát + orientácia). */
    public static function kluc_vylepu( $format, $orientacia ) {
        if ( $format === 'A4' ) { return 'A4'; }
        $o = ( $orientacia === 's' ) ? 's' : 'v';
        $k = $format . $o;
        return in_array( $k, self::kluce(), true ) ? $k : 'A4';
    }

    /**
     * Obsadenosť všetkých plôch (aktuálne prebiehajúce výlepy k dátumu $datum).
     * Vráti [plocha_id => ['A1v'=>[{o,a},...], 'A2s'=>[...], ...]] — pre každý kľúč zoznam
     * plagátov (o = organizácia, a = názov podujatia); počet = dĺžka zoznamu.
     */
    public static function obsadenost_vsetky( $datum = null ) {
        $datum = $datum ?: current_time( 'Y-m-d' );
        $plochy = self::get_all();

        $prazdne = array_fill_keys( self::kluce(), array() );
        $obsadenost = array();
        foreach ( $plochy as $p ) {
            $obsadenost[ (int) $p->id ] = $prazdne;
        }

        $aktivne = KASS_Vylep_DB::get_vylepy( array( 'active_on' => $datum ) );
        foreach ( $aktivne as $v ) {
            $kluc = self::kluc_vylepu( $v->format ?? 'A3', $v->orientacia ?? 'v' );
            $ids  = self::resolve_ids( $v->plochy_sposob ?? 'vsetky', $v->plochy_vyber ?? '', $plochy );
            foreach ( $ids as $pid ) {
                if ( isset( $obsadenost[ $pid ] ) ) {
                    $obsadenost[ $pid ][ $kluc ][] = array(
                        'o' => (string) ( $v->organizacia_nazov ?? '' ),
                        'a' => (string) ( $v->nazov_akcie ?? '' ),
                    );
                }
            }
        }
        return $obsadenost;
    }
}
