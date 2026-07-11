<?php
// Spustí sa pri zmazaní pluginu z WordPressu.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$tabulky = array(
    $wpdb->prefix . 'kass_vylepy',
    $wpdb->prefix . 'kass_organizacie',
    $wpdb->prefix . 'kass_cennik',
);
// Pozn.: zmazanie tabuliek je zakomentované, aby sa nedopatrením nestratili dáta.
// Ak chceš úplné odstránenie vrátane dát, odkomentuj nasledujúce riadky:
// foreach ( $tabulky as $t ) {
//     $wpdb->query( "DROP TABLE IF EXISTS $t" );
// }
