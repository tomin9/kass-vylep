<?php
/**
 * Plugin Name: Výlep plagátov
 * Description: Evidencia výlepu plagátov, databáza odberateľov, cenník, generátor podkladu k fakturácii a týždenný zoznam plagátov, ktoré sa nesmú prelepiť.
 * Version: 1.4.0
 * Author: OZ Ars Preuge
 * Text Domain: kass-vylep
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // priamy prístup zakázaný
}

define( 'KASS_VYLEP_VERSION', '1.4.0' );
define( 'KASS_VYLEP_PATH', plugin_dir_path( __FILE__ ) );
define( 'KASS_VYLEP_URL', plugin_dir_url( __FILE__ ) );

require_once KASS_VYLEP_PATH . 'includes/class-kass-db.php';
require_once KASS_VYLEP_PATH . 'includes/class-kass-cennik.php';
require_once KASS_VYLEP_PATH . 'includes/class-kass-admin.php';
require_once KASS_VYLEP_PATH . 'public/class-kass-public.php';

/**
 * Aktivácia – vytvorí tabuľky a naplní východiskový cenník.
 */
function kass_vylep_activate() {
    KASS_Vylep_DB::create_tables();
    KASS_Vylep_Cennik::migrate();
    KASS_Vylep_Cennik::seed_defaults();
    update_option( 'kass_vylep_version', KASS_VYLEP_VERSION );
}
register_activation_hook( __FILE__, 'kass_vylep_activate' );

/**
 * Spustí upgrade/migráciu pri zmene verzie (napr. po nahratí nového zipu).
 */
function kass_vylep_maybe_upgrade() {
    $ulozena = get_option( 'kass_vylep_version' );
    if ( $ulozena !== KASS_VYLEP_VERSION ) {
        KASS_Vylep_DB::create_tables();
        KASS_Vylep_Cennik::migrate();
        KASS_Vylep_Cennik::seed_defaults();
        update_option( 'kass_vylep_version', KASS_VYLEP_VERSION );
    }
}
add_action( 'admin_init', 'kass_vylep_maybe_upgrade' );

/**
 * Štart pluginu.
 */
function kass_vylep_init() {
    if ( is_admin() ) {
        $admin = new KASS_Vylep_Admin();
        $admin->hooks();
    }
    // Shortcode a frontend fungujú vždy (aj pre neprihlásených)
    $public = new KASS_Vylep_Public();
    $public->hooks();
}
add_action( 'plugins_loaded', 'kass_vylep_init' );
