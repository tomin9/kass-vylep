<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin rozhranie: menu, spracovanie formulárov, vykreslenie stránok.
 */
class KASS_Vylep_Admin {

    const CAP = 'manage_options'; // kto má prístup

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_init', array( $this, 'handle_embed' ) );
        add_action( 'admin_post_kass_save_org',    array( $this, 'handle_save_org' ) );
        add_action( 'admin_post_kass_delete_org',  array( $this, 'handle_delete_org' ) );
        add_action( 'admin_post_kass_save_cennik', array( $this, 'handle_save_cennik' ) );
        add_action( 'wp_ajax_kass_cena',           array( $this, 'ajax_cena' ) );
    }

    /** Embed mode — vykreslí faktúru bez WP adminu. */
    public function handle_embed() {
        if ( empty( $_GET['embed'] ) || empty( $_GET['page'] ) || $_GET['page'] !== 'kass-faktura' ) {
            return;
        }
        if ( ! current_user_can( self::CAP ) ) { wp_die( 'Neoprávnený prístup.' ); }

        // Načítaj CSS + JS závislosti
        $css_url = KASS_VYLEP_URL . 'admin/css/admin.css?v=' . KASS_VYLEP_VERSION;
        $js_url  = KASS_VYLEP_URL . 'admin/js/admin.js?v=' . KASS_VYLEP_VERSION;
        ?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Podklad k fakturácii</title>
<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
<style>
body { margin:0; padding:0; background:#2a2a2a; font-family:Arial,Helvetica,sans-serif; }
h1.wp-heading-inline { display:none !important; }
.kass-faktura-wrap { background:#2a2a2a !important; padding: 12px 16px !important; margin: 0 !important; }

/* Toolbar — tmavý štýl */
.kass-fakt-toolbar {
    background: #1a1a1a !important;
    border: 1px solid rgba(255,255,255,.1) !important;
    border-radius: 8px !important;
    color: #e5e7eb !important;
    box-shadow: none !important;
}
.kass-fakt-toolbar label { color: #d1d5db !important; font-size: 13px; }
.kass-fakt-toolbar select {
    background: rgba(255,255,255,.08) !important;
    color: #e5e7eb !important;
    border: 1px solid rgba(255,255,255,.2) !important;
    border-radius: 6px; font-size: 13px;
}
.kass-fakt-toolbar select option { background: #1a1a1a; color: #e5e7eb; }
.kass-fakt-toolbar .button {
    background: rgba(255,255,255,.1) !important;
    border: 1px solid rgba(255,255,255,.2) !important;
    color: #e5e7eb !important;
    border-radius: 6px !important;
    font-size: 13px !important;
    text-shadow: none !important;
    box-shadow: none !important;
}
.kass-fakt-toolbar .button:hover { background: rgba(255,255,255,.18) !important; }
.kass-fakt-toolbar .button-primary {
    background: #2563eb !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
}
.kass-fakt-toolbar .button-primary:hover { background: #1d4ed8 !important; }
.kass-fakt-toolbar .description { color: rgba(255,255,255,.45) !important; font-size: 12px; }
</style>
</head>
<body>
<?php
        require KASS_VYLEP_PATH . 'admin/view-faktura.php';
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
window.ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
window.KASSVylep = { ajax: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', nonce: '<?php echo wp_create_nonce( 'kass_cena' ); ?>' };
</script>
<script src="<?php echo esc_url( $js_url ); ?>"></script>
</body>
</html>
<?php
        exit;
    }

    public function menu() {
        add_menu_page(
            'Výlep plagátov', 'Výlep plagátov', self::CAP, 'kass-vylep',
            array( $this, 'page_organizacie' ), 'dashicons-megaphone', 30
        );
        add_submenu_page( 'kass-vylep', 'Odberatelia', 'Odberatelia', self::CAP, 'kass-vylep', array( $this, 'page_organizacie' ) );
        add_submenu_page( 'kass-vylep', 'Cenník', 'Cenník', self::CAP, 'kass-cennik', array( $this, 'page_cennik' ) );
        // Skryté stránky — zaregistrované ale nezobrazené v menu
        add_submenu_page( null, 'Odberatelia', '', self::CAP, 'kass-organizacie', array( $this, 'page_organizacie' ) );
        add_submenu_page( null, 'Podklad k faktúre', '', self::CAP, 'kass-faktura', array( $this, 'page_faktura' ) );
    }

    public function assets( $hook ) {
        if ( strpos( $hook, 'kass' ) === false ) {
            return;
        }
        wp_enqueue_style( 'kass-vylep-admin', KASS_VYLEP_URL . 'admin/css/admin.css', array(), KASS_VYLEP_VERSION );
        wp_enqueue_script( 'kass-vylep-admin', KASS_VYLEP_URL . 'admin/js/admin.js', array( 'jquery' ), KASS_VYLEP_VERSION, true );
        wp_localize_script( 'kass-vylep-admin', 'KASSVylep', array(
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'kass_cena' ),
        ) );
    }

    /* ===================== SPRACOVANIE FORMULÁROV ===================== */

    public function handle_save_vylep() {
        if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'kass_save_vylep' ) ) {
            wp_die( 'Neoprávnený prístup.' );
        }
        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $data = array(
            'organizacia_id'    => isset( $_POST['organizacia_id'] ) ? (int) $_POST['organizacia_id'] : 0,
            'organizacia_nazov' => $_POST['organizacia_nazov'] ?? '',
            'nazov_akcie'       => $_POST['nazov_akcie'] ?? '',
            'platba'            => $_POST['platba'] ?? 'Zadarmo',
            'cennik_typ'        => $_POST['cennik_typ'] ?? 'vylep',
            'format'            => $_POST['format'] ?? 'A3',
            'datum_od'          => KASS_Vylep_DB::najblizsi_utorok( $_POST['datum_od'] ?? date( 'Y-m-d' ) ),
            'tyzdne'            => $_POST['tyzdne'] ?? 1,
            'kusy'              => $_POST['kusy'] ?? 0,
            'cennik_cena'       => $_POST['cennik_cena'] ?? 0,
            'tlac'              => $_POST['tlac'] ?? 0,
            'ine'               => $_POST['ine'] ?? 0,
            'poznamka'          => $_POST['poznamka'] ?? '',
        );
        KASS_Vylep_DB::save_vylep( $data, $id );
        wp_safe_redirect( admin_url( 'admin.php?page=kass-vylepy&msg=saved' ) );
        exit;
    }

    public function handle_delete_vylep() {
        if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'kass_delete_vylep' ) ) {
            wp_die( 'Neoprávnený prístup.' );
        }
        KASS_Vylep_DB::delete_vylep( (int) $_GET['id'] );
        wp_safe_redirect( admin_url( 'admin.php?page=kass-vylepy&msg=deleted' ) );
        exit;
    }

    public function handle_save_org() {
        if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'kass_save_org' ) ) {
            wp_die( 'Neoprávnený prístup.' );
        }
        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $data = array(
            'odberatel'   => $_POST['odberatel'] ?? '',
            'nazov'       => $_POST['nazov'] ?? '',
            'ulica'       => $_POST['ulica'] ?? '',
            'mesto'       => $_POST['mesto'] ?? '',
            'ico'         => $_POST['ico'] ?? '',
            'dic'         => $_POST['dic'] ?? '',
            'ic_dph'      => $_POST['ic_dph'] ?? '',
            'penaz_ustav' => $_POST['penaz_ustav'] ?? '',
            'iban'        => $_POST['iban'] ?? '',
        );
        KASS_Vylep_DB::save_organizacia( $data, $id );
        wp_safe_redirect( admin_url( 'admin.php?page=kass-vylep&msg=saved' ) );
        exit;
    }

    public function handle_delete_org() {
        if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'kass_delete_org' ) ) {
            wp_die( 'Neoprávnený prístup.' );
        }
        KASS_Vylep_DB::delete_organizacia( (int) $_GET['id'] );
        wp_safe_redirect( admin_url( 'admin.php?page=kass-vylep&msg=deleted' ) );
        exit;
    }

    public function handle_save_cennik() {
        if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'kass_save_cennik' ) ) {
            wp_die( 'Neoprávnený prístup.' );
        }
        KASS_Vylep_Cennik::save_prices( $_POST['bez'] ?? array(), $_POST['sdph'] ?? array() );
        wp_safe_redirect( admin_url( 'admin.php?page=kass-cennik&msg=saved' ) );
        exit;
    }

    /** AJAX: vráti cenu za kus pre kategóriu + formát + týždne. */
    public function ajax_cena() {
        check_ajax_referer( 'kass_cena', 'nonce' );
        $kategoria = sanitize_text_field( $_GET['kategoria'] ?? 'vylep' );
        $format = sanitize_text_field( $_GET['format'] ?? 'A3' );
        $tyzdne = (int) ( $_GET['tyzdne'] ?? 1 );
        wp_send_json_success( array( 'cena' => KASS_Vylep_Cennik::cena( $kategoria, $format, $tyzdne ) ) );
    }

    /* ===================== STRÁNKY ===================== */

    public function page_organizacie() {
        require KASS_VYLEP_PATH . 'admin/view-organizacie.php';
    }
    public function page_cennik() {
        require KASS_VYLEP_PATH . 'admin/view-cennik.php';
    }
    public function page_faktura() {
        require KASS_VYLEP_PATH . 'admin/view-faktura.php';
    }

    /* ===================== POMOCNÉ ===================== */

    public static function eur( $n ) {
        return number_format( (float) $n, 2, ',', ' ' ) . ' €';
    }
    public static function datum_sk( $d ) {
        if ( ! $d || $d === '0000-00-00' ) return '';
        $dt = new DateTime( $d );
        return $dt->format( 'j.n.Y' );
    }
}
