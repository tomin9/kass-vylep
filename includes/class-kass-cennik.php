<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Cenník – kompletný katalóg s kódmi, cenami bez/s DPH a kategóriami.
 */
class KASS_Vylep_Cennik {

    /**
     * Kompletný katalóg.
     * Pole položiek: kod, kategoria, format, popis, tyzdne, bez_dph, s_dph
     * kategoria: 'vylep' | 'predvolba' | 'kopirovanie' | 'ostatne'
     */
    public static function catalog() {
        return array(
            // ---- ŠTANDARDNÝ VÝLEP ----
            array( 'kod' => 33, 'kategoria' => 'vylep', 'format' => 'A4', 'popis' => '1 týždeň',  'tyzdne' => 1, 'bez_dph' => 1.1382, 's_dph' => 1.40 ),
            array( 'kod' => 34, 'kategoria' => 'vylep', 'format' => 'A4', 'popis' => '2 týždne',  'tyzdne' => 2, 'bez_dph' => 1.3821, 's_dph' => 1.70 ),
            array( 'kod' => 35, 'kategoria' => 'vylep', 'format' => 'A4', 'popis' => '3 týždne',  'tyzdne' => 3, 'bez_dph' => 1.7073, 's_dph' => 2.10 ),
            array( 'kod' => 36, 'kategoria' => 'vylep', 'format' => 'A4', 'popis' => '4 týždne',  'tyzdne' => 4, 'bez_dph' => 2.2764, 's_dph' => 2.80 ),
            array( 'kod' => 37, 'kategoria' => 'vylep', 'format' => 'A4', 'popis' => '5 týždňov', 'tyzdne' => 5, 'bez_dph' => 2.9268, 's_dph' => 3.60 ),

            array( 'kod' => 1,  'kategoria' => 'vylep', 'format' => 'A3', 'popis' => '1 týždeň',  'tyzdne' => 1, 'bez_dph' => 1.3008, 's_dph' => 1.60 ),
            array( 'kod' => 2,  'kategoria' => 'vylep', 'format' => 'A3', 'popis' => '2 týždne',  'tyzdne' => 2, 'bez_dph' => 1.7073, 's_dph' => 2.10 ),
            array( 'kod' => 3,  'kategoria' => 'vylep', 'format' => 'A3', 'popis' => '3 týždne',  'tyzdne' => 3, 'bez_dph' => 2.1138, 's_dph' => 2.60 ),
            array( 'kod' => 4,  'kategoria' => 'vylep', 'format' => 'A3', 'popis' => '4 týždne',  'tyzdne' => 4, 'bez_dph' => 2.5203, 's_dph' => 3.10 ),
            array( 'kod' => 5,  'kategoria' => 'vylep', 'format' => 'A3', 'popis' => '5 týždňov', 'tyzdne' => 5, 'bez_dph' => 3.3333, 's_dph' => 4.10 ),

            array( 'kod' => 6,  'kategoria' => 'vylep', 'format' => 'A2', 'popis' => '1 týždeň',  'tyzdne' => 1, 'bez_dph' => 1.9512, 's_dph' => 2.40 ),
            array( 'kod' => 7,  'kategoria' => 'vylep', 'format' => 'A2', 'popis' => '2 týždne',  'tyzdne' => 2, 'bez_dph' => 2.4390, 's_dph' => 3.00 ),
            array( 'kod' => 8,  'kategoria' => 'vylep', 'format' => 'A2', 'popis' => '3 týždne',  'tyzdne' => 3, 'bez_dph' => 3.1707, 's_dph' => 3.90 ),
            array( 'kod' => 9,  'kategoria' => 'vylep', 'format' => 'A2', 'popis' => '4 týždne',  'tyzdne' => 4, 'bez_dph' => 4.0650, 's_dph' => 5.00 ),
            array( 'kod' => 10, 'kategoria' => 'vylep', 'format' => 'A2', 'popis' => '5 týždňov', 'tyzdne' => 5, 'bez_dph' => 5.2846, 's_dph' => 6.50 ),

            array( 'kod' => 11, 'kategoria' => 'vylep', 'format' => 'A1', 'popis' => '1 týždeň',  'tyzdne' => 1, 'bez_dph' => 3.0081, 's_dph' => 3.70 ),
            array( 'kod' => 12, 'kategoria' => 'vylep', 'format' => 'A1', 'popis' => '2 týždne',  'tyzdne' => 2, 'bez_dph' => 4.2276, 's_dph' => 5.20 ),
            array( 'kod' => 13, 'kategoria' => 'vylep', 'format' => 'A1', 'popis' => '3 týždne',  'tyzdne' => 3, 'bez_dph' => 6.0976, 's_dph' => 7.50 ),
            array( 'kod' => 14, 'kategoria' => 'vylep', 'format' => 'A1', 'popis' => '4 týždne',  'tyzdne' => 4, 'bez_dph' => 7.5610, 's_dph' => 9.30 ),
            array( 'kod' => 15, 'kategoria' => 'vylep', 'format' => 'A1', 'popis' => '5 týždňov', 'tyzdne' => 5, 'bez_dph' => 9.3496, 's_dph' => 11.50 ),

            array( 'kod' => 16, 'kategoria' => 'ostatne', 'format' => '', 'popis' => 'Mimoriadny výlep', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 150.00 ),
            array( 'kod' => 17, 'kategoria' => 'ostatne', 'format' => '', 'popis' => 'Grafické práce (€/h)', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 25.00 ),

            // ---- KOPÍROVANIE ----
            array( 'kod' => 18, 'kategoria' => 'kopirovanie', 'format' => 'A4', 'popis' => 'ČB',             'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 0.15 ),
            array( 'kod' => 19, 'kategoria' => 'kopirovanie', 'format' => 'A4', 'popis' => 'ČB obojstranne', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 0.30 ),
            array( 'kod' => 20, 'kategoria' => 'kopirovanie', 'format' => 'A3', 'popis' => 'ČB',             'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 0.25 ),
            array( 'kod' => 21, 'kategoria' => 'kopirovanie', 'format' => 'A3', 'popis' => 'ČB obojstranne', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 0.50 ),
            array( 'kod' => 22, 'kategoria' => 'kopirovanie', 'format' => 'A4', 'popis' => 'Farebne',        'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 0.70 ),
            array( 'kod' => 23, 'kategoria' => 'kopirovanie', 'format' => 'A3', 'popis' => 'Farebne',        'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 1.40 ),
            array( 'kod' => 24, 'kategoria' => 'kopirovanie', 'format' => '',   'popis' => 'Laminovanie A4', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 1.20 ),
            array( 'kod' => 38, 'kategoria' => 'kopirovanie', 'format' => '',   'popis' => 'Prenájom plochy', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 17.00 ),
            array( 'kod' => 39, 'kategoria' => 'kopirovanie', 'format' => '',   'popis' => 'Prenájom plochy', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 68.00 ),

            // ---- PREDVOLEBNÁ KAMPAŇ ----
            array( 'kod' => 25, 'kategoria' => 'predvolba', 'format' => 'A4/A3', 'popis' => '1 týždeň', 'tyzdne' => 1, 'bez_dph' => 2.4390, 's_dph' => 3.00 ),
            array( 'kod' => 26, 'kategoria' => 'predvolba', 'format' => 'A4/A3', 'popis' => '2 týždne', 'tyzdne' => 2, 'bez_dph' => 3.0894, 's_dph' => 3.80 ),
            array( 'kod' => 27, 'kategoria' => 'predvolba', 'format' => 'A4/A3', 'popis' => '3 týždne', 'tyzdne' => 3, 'bez_dph' => 3.6585, 's_dph' => 4.50 ),
            array( 'kod' => 28, 'kategoria' => 'predvolba', 'format' => 'A4/A3', 'popis' => '4 týždne', 'tyzdne' => 4, 'bez_dph' => 4.8780, 's_dph' => 6.00 ),

            array( 'kod' => 29, 'kategoria' => 'predvolba', 'format' => 'A2', 'popis' => '1 týždeň', 'tyzdne' => 1, 'bez_dph' => 3.6585, 's_dph' => 4.50 ),
            array( 'kod' => 30, 'kategoria' => 'predvolba', 'format' => 'A2', 'popis' => '2 týždne', 'tyzdne' => 2, 'bez_dph' => 4.6341, 's_dph' => 5.70 ),
            array( 'kod' => 31, 'kategoria' => 'predvolba', 'format' => 'A2', 'popis' => '3 týždne', 'tyzdne' => 3, 'bez_dph' => 6.0976, 's_dph' => 7.50 ),
            array( 'kod' => 32, 'kategoria' => 'predvolba', 'format' => 'A2', 'popis' => '4 týždne', 'tyzdne' => 4, 'bez_dph' => 7.8862, 's_dph' => 9.70 ),

            array( 'kod' => 40, 'kategoria' => 'ostatne', 'format' => '', 'popis' => 'Mimoriadny výlep (predvoľby)', 'tyzdne' => 0, 'bez_dph' => 0, 's_dph' => 165.00 ),
        );
    }

    public static function formaty() {
        return array( 'A4', 'A3', 'A2', 'A1' );
    }

    public static function kategorie() {
        return array(
            'vylep'       => 'Výlep plagátov',
            'predvolba'   => 'Predvolebná kampaň',
            'kopirovanie' => 'Kopírovanie a ostatné služby',
            'ostatne'     => 'Mimoriadne položky',
        );
    }

    public static function seed_defaults() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        if ( $count > 0 ) { return; }
        self::insert_catalog();
    }

    public static function insert_catalog() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        foreach ( self::catalog() as $p ) {
            $wpdb->insert( $t, array(
                'kod'          => $p['kod'],
                'kategoria'    => $p['kategoria'],
                'format'       => $p['format'],
                'popis'        => $p['popis'],
                'tyzdne'       => $p['tyzdne'],
                'cena_bez_dph' => $p['bez_dph'],
                'cena_s_dph'   => $p['s_dph'],
            ) );
        }
    }

    /** Migrácia zo starej štruktúry (v1.0) – prebuduje cenník ak treba. */
    public static function migrate() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        $ma_kod = $wpdb->get_var( "SHOW COLUMNS FROM $t LIKE 'kod'" );
        if ( ! $ma_kod ) { return; }
        $count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        $bez_kodu = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE kod IS NULL OR kod = 0" );
        if ( $count === 0 ) {
            self::insert_catalog();
        } elseif ( $bez_kodu > 0 ) {
            $wpdb->query( "TRUNCATE TABLE $t" );
            self::insert_catalog();
        }
    }

    public static function all() {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        return $wpdb->get_results( "SELECT * FROM $t ORDER BY FIELD(kategoria,'vylep','predvolba','kopirovanie','ostatne'), kod ASC" );
    }

    public static function by_kategoria( $kat ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE kategoria = %s ORDER BY kod ASC", $kat
        ) );
    }

    /**
     * Cena za kus (bez DPH) pre výlep podľa kategórie, formátu a počtu týždňov.
     */
    public static function cena( $kategoria, $format, $tyzdne ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        if ( $kategoria === 'predvolba' && in_array( $format, array( 'A4', 'A3' ), true ) ) {
            $format = 'A4/A3';
        }
        $max = ( $kategoria === 'predvolba' ) ? 4 : 5;
        $ty  = min( $max, max( 1, (int) $tyzdne ) );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT cena_bez_dph FROM $t WHERE kategoria = %s AND format = %s AND tyzdne = %d",
            $kategoria, $format, $ty
        ) );
        return $row ? (float) $row->cena_bez_dph : 0;
    }

    public static function get_js_matrix() {
        $m = array();
        foreach ( self::all() as $r ) {
            if ( (int) $r->tyzdne < 1 ) { continue; }
            $m[ $r->kategoria ][ $r->format ][ (int) $r->tyzdne ] = array(
                'bez' => (float) $r->cena_bez_dph,
                's'   => (float) $r->cena_s_dph,
            );
        }
        return $m;
    }

    public static function save_prices( $bez, $sdph ) {
        global $wpdb;
        $t = KASS_Vylep_DB::t_cennik();
        if ( ! is_array( $bez ) ) { return; }
        foreach ( $bez as $id => $val ) {
            $id = (int) $id;
            $wpdb->update( $t, array(
                'cena_bez_dph' => (float) str_replace( ',', '.', $val ),
                'cena_s_dph'   => isset( $sdph[ $id ] ) ? (float) str_replace( ',', '.', $sdph[ $id ] ) : 0,
            ), array( 'id' => $id ) );
        }
    }
}
