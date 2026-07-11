<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Práca s databázou: vytvorenie tabuliek + CRUD pre odberateľov a výlepy.
 */
class KASS_Vylep_DB {

    public static function t_org()   { global $wpdb; return $wpdb->prefix . 'kass_organizacie'; }
    public static function t_vylep() { global $wpdb; return $wpdb->prefix . 'kass_vylepy'; }
    public static function t_cennik(){ global $wpdb; return $wpdb->prefix . 'kass_cennik'; }

    /**
     * Vytvorenie tabuliek pri aktivácii.
     */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $org = self::t_org();
        $sql_org = "CREATE TABLE $org (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            odberatel VARCHAR(100) DEFAULT '',
            nazov VARCHAR(255) NOT NULL,
            ulica VARCHAR(255) DEFAULT '',
            mesto VARCHAR(255) DEFAULT '',
            ico VARCHAR(32) DEFAULT '',
            dic VARCHAR(32) DEFAULT '',
            ic_dph VARCHAR(32) DEFAULT '',
            penaz_ustav VARCHAR(128) DEFAULT '',
            iban VARCHAR(64) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY nazov (nazov)
        ) $charset;";
        dbDelta( $sql_org );
        // Migrácia — pridaj stĺpec ak neexistuje
        $wpdb->query( "ALTER TABLE $org ADD COLUMN IF NOT EXISTS odberatel VARCHAR(100) DEFAULT '' AFTER id" );

        $vylep = self::t_vylep();
        $sql_vylep = "CREATE TABLE $vylep (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organizacia_id BIGINT UNSIGNED DEFAULT NULL,
            organizacia_nazov VARCHAR(255) DEFAULT '',
            nazov_akcie VARCHAR(255) NOT NULL,
            platba VARCHAR(20) DEFAULT 'Zadarmo',
            cennik_typ VARCHAR(20) DEFAULT 'vylep',
            format VARCHAR(8) DEFAULT 'A3',
            datum_od DATE NOT NULL,
            tyzdne INT DEFAULT 1,
            datum_do DATE NOT NULL,
            kusy INT DEFAULT 26,
            cennik_cena DECIMAL(10,4) DEFAULT 0,
            vylep_suma DECIMAL(10,2) DEFAULT 0,
            tlac DECIMAL(10,2) DEFAULT 0,
            ine DECIMAL(10,2) DEFAULT 0,
            poznamka TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY datum_od (datum_od),
            KEY datum_do (datum_do),
            KEY organizacia_id (organizacia_id)
        ) $charset;";
        dbDelta( $sql_vylep );

        $cennik = self::t_cennik();
        $sql_cennik = "CREATE TABLE $cennik (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            kod INT DEFAULT 0,
            kategoria VARCHAR(20) DEFAULT 'vylep',
            format VARCHAR(10) DEFAULT '',
            popis VARCHAR(64) DEFAULT '',
            tyzdne INT NOT NULL DEFAULT 0,
            cena_bez_dph DECIMAL(10,4) NOT NULL DEFAULT 0,
            cena_s_dph DECIMAL(10,4) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY kod (kod),
            KEY lookup (kategoria, format, tyzdne)
        ) $charset;";
        dbDelta( $sql_cennik );
    }

    /* ---------------- ODBERATELIA ---------------- */

    public static function get_organizacie() {
        global $wpdb;
        $t = self::t_org();
        return $wpdb->get_results( "SELECT * FROM $t ORDER BY nazov ASC" );
    }

    public static function get_organizacia( $id ) {
        global $wpdb;
        $t = self::t_org();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
    }

    public static function save_organizacia( $data, $id = 0 ) {
        global $wpdb;
        $t = self::t_org();
        $fields = array(
            'odberatel'   => sanitize_text_field( $data['odberatel'] ?? '' ),
            'nazov'       => sanitize_text_field( $data['nazov'] ),
            'ulica'       => sanitize_text_field( $data['ulica'] ),
            'mesto'       => sanitize_text_field( $data['mesto'] ),
            'ico'         => sanitize_text_field( $data['ico'] ),
            'dic'         => sanitize_text_field( $data['dic'] ),
            'ic_dph'      => sanitize_text_field( $data['ic_dph'] ),
            'penaz_ustav' => sanitize_text_field( $data['penaz_ustav'] ),
            'iban'        => sanitize_text_field( $data['iban'] ),
        );
        if ( $id ) {
            $wpdb->update( $t, $fields, array( 'id' => $id ) );
            return $id;
        }
        $wpdb->insert( $t, $fields );
        return $wpdb->insert_id;
    }

    public static function delete_organizacia( $id ) {
        global $wpdb;
        $wpdb->delete( self::t_org(), array( 'id' => $id ) );
    }

    /* ---------------- VÝLEPY ---------------- */

    public static function get_vylepy( $args = array() ) {
        global $wpdb;
        $t = self::t_vylep();
        $where = '1=1';
        $params = array();

        if ( ! empty( $args['active_on'] ) ) {
            // plagáty aktívne v daný dátum: od <= dátum < do
            $where .= ' AND datum_od <= %s AND datum_do > %s';
            $params[] = $args['active_on'];
            $params[] = $args['active_on'];
        }
        if ( ! empty( $args['rok'] ) ) {
            $where .= ' AND YEAR(datum_od) = %d';
            $params[] = (int) $args['rok'];
        }

        $sql = "SELECT * FROM $t WHERE $where ORDER BY datum_od ASC, id ASC";
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }
        return $wpdb->get_results( $sql );
    }

    public static function get_vylep( $id ) {
        global $wpdb;
        $t = self::t_vylep();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
    }

    public static function save_vylep( $data, $id = 0 ) {
        global $wpdb;
        $t = self::t_vylep();

        $datum_od = $data['datum_od'];
        $tyzdne   = max( 1, (int) $data['tyzdne'] );
        $datum_do = self::vypocitaj_datum_do( $datum_od, $tyzdne );

        $kusy = max( 0, (int) $data['kusy'] );
        $cena = (float) str_replace( ',', '.', $data['cennik_cena'] );
        $vylep_suma = round( $cena * $kusy, 2 );

        $fields = array(
            'organizacia_id'    => ! empty( $data['organizacia_id'] ) ? (int) $data['organizacia_id'] : null,
            'organizacia_nazov' => sanitize_text_field( $data['organizacia_nazov'] ),
            'nazov_akcie'       => sanitize_text_field( $data['nazov_akcie'] ),
            'platba'            => sanitize_text_field( $data['platba'] ),
            'cennik_typ'        => sanitize_text_field( isset( $data['cennik_typ'] ) ? $data['cennik_typ'] : 'vylep' ),
            'format'            => sanitize_text_field( $data['format'] ),
            'datum_od'          => $datum_od,
            'tyzdne'            => $tyzdne,
            'datum_do'          => $datum_do,
            'kusy'              => $kusy,
            'cennik_cena'       => $cena,
            'vylep_suma'        => $vylep_suma,
            'tlac'              => (float) str_replace( ',', '.', $data['tlac'] ),
            'ine'               => (float) str_replace( ',', '.', $data['ine'] ),
            'poznamka'          => sanitize_textarea_field( $data['poznamka'] ),
        );

        if ( $id ) {
            $wpdb->update( $t, $fields, array( 'id' => $id ) );
            return $id;
        }
        $wpdb->insert( $t, $fields );
        return $wpdb->insert_id;
    }

    public static function delete_vylep( $id ) {
        global $wpdb;
        $wpdb->delete( self::t_vylep(), array( 'id' => $id ) );
    }

    /**
     * Výlep je vždy v utorok. Koniec = od + (tyzdne × 7 dní).
     */
    public static function vypocitaj_datum_do( $datum_od, $tyzdne ) {
        $d = new DateTime( $datum_od );
        $d->modify( '+' . ( $tyzdne * 7 ) . ' days' );
        return $d->format( 'Y-m-d' );
    }

    /**
     * Najbližší utorok k zadanému dátumu (na zarovnanie výlepu).
     */
    public static function najblizsi_utorok( $datum ) {
        $d = new DateTime( $datum );
        $dow = (int) $d->format( 'N' ); // 1=Po ... 7=Ne, utorok = 2
        $diff = 2 - $dow;
        if ( $diff !== 0 ) {
            $d->modify( ( $diff > 0 ? '+' : '' ) . $diff . ' days' );
        }
        return $d->format( 'Y-m-d' );
    }
}
