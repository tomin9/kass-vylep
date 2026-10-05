<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class KASS_Vylep_Public {

    /** Koľko najnovších riadkov sa vykreslí okamžite (zvyšok sa dopĺňa na pozadí). */
    const RIADKOV_HNED = 60;

    public function hooks() {
        add_shortcode( 'kass_plagat', array( $this, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'init', array( $this, 'handle_login' ) );
        add_action( 'init', array( $this, 'handle_logout' ) );
        add_action( 'wp_ajax_kass_pub_save_vylep',   array( $this, 'ajax_save_vylep' ) );
        add_action( 'wp_ajax_kass_pub_delete_vylep', array( $this, 'ajax_delete_vylep' ) );
        add_action( 'wp_ajax_kass_pub_save_org',     array( $this, 'ajax_save_org' ) );
        add_action( 'wp_ajax_kass_pub_get_obsadenost', array( $this, 'ajax_get_obsadenost' ) );
    }

    private function is_plagat_page() {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'kass_plagat' );
    }

    /** Dáta pre JS (KASSPub) — plnia sa v assets(), vypíšu sa v pätičke. */
    private $js_data = null;

    public function assets() {
        if ( ! $this->is_plagat_page() ) { return; }
        wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css', array(), '4.6.13' );
        // CSS a JS pluginu sa vkladajú priamo do HTML stránky (nie ako samostatné
        // súbory) — cache pluginy ich tak nevedia zlúčiť/zdržať a po aktualizácii
        // pluginu je vždy aktuálna verzia.
        add_action( 'wp_head', array( $this, 'print_inline_css' ), 100 );
        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.13', true );
            wp_enqueue_script( 'jquery' );
            $orgs = KASS_Vylep_DB::get_organizacie();
            $org_list = array();
            foreach ( $orgs as $o ) {
                $org_list[] = array(
                    'id'        => (int) $o->id,
                    'odberatel' => $o->odberatel ?: $o->nazov,
                    'nazov'     => $o->nazov,
                );
            }
            $plochy_list = array();
            foreach ( KASS_Vylep_Plochy::get_all() as $p ) {
                $plochy_list[] = array(
                    'id'     => (int) $p->id,
                    'cislo'  => (int) $p->cislo,
                    'nazov'  => $p->nazov,
                    'top10'  => (bool) $p->top10,
                    'top15'  => (bool) $p->top15,
                    'top20'  => (bool) $p->top20,
                    'topUnits'    => (float) $p->top_units,
                    'bottomUnits' => (float) $p->bottom_units,
                );
            }
            $this->js_data = array(
                'ajax'      => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'kass_pub' ),
                'orgs'      => $org_list,
                'cennik'    => KASS_Vylep_Cennik::get_js_matrix(),
                'faktUrl'   => admin_url( 'admin.php?page=kass-faktura&vylep=' ),
                'tlacCennik' => KASS_Vylep_Cennik::get_tlac_matrix(),
                'plochy'    => $plochy_list,
            );
            // Po wp_print_footer_scripts (priorita 20), takže jQuery a flatpickr sú už načítané
            add_action( 'wp_footer', array( $this, 'print_inline_js' ), 100 );
        }
    }

    public function print_inline_css() {
        $css = file_get_contents( KASS_VYLEP_PATH . 'public/css/public.css' );
        echo '<style id="kass-plagat-public-css">' . $css . "</style>\n";
    }

    public function print_inline_js() {
        if ( $this->js_data === null ) { return; }
        $js   = file_get_contents( KASS_VYLEP_PATH . 'public/js/table.js' );
        $data = wp_json_encode( $this->js_data, JSON_HEX_TAG | JSON_HEX_AMP );
        echo '<script id="kass-plagat-js">window.KASSPub = ' . $data . ";\n" . $js . "\n</script>\n";
    }

    /** Kam presmerovať po prihlásení/odhlásení (pri hooku init ešte nie je známy get_permalink()). */
    private function redirect_back() {
        $back = isset( $_REQUEST['kass_back'] ) ? esc_url_raw( wp_unslash( $_REQUEST['kass_back'] ) ) : '';
        return $back ? $back : home_url( '/' );
    }

    public function handle_login() {
        if ( ! isset( $_POST['kass_login_nonce'] ) || ! wp_verify_nonce( $_POST['kass_login_nonce'], 'kass_login' ) ) { return; }
        $creds = array(
            'user_login'    => sanitize_text_field( $_POST['kass_user'] ?? '' ),
            'user_password' => $_POST['kass_pass'] ?? '',
            'remember'      => ! empty( $_POST['kass_remember'] ),
        );
        $user = wp_signon( $creds, is_ssl() );
        if ( is_wp_error( $user ) ) {
            set_transient( 'kass_login_err_' . md5( $_SERVER['REMOTE_ADDR'] ), 'Nesprávne meno alebo heslo.', 30 );
        }
        nocache_headers();
        wp_safe_redirect( $this->redirect_back() );
        exit;
    }

    public function handle_logout() {
        if ( isset( $_GET['kass_logout'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'kass_logout' ) ) {
            wp_logout();
            nocache_headers();
            wp_safe_redirect( $this->redirect_back() );
            exit;
        }
    }

    /* ===== AJAX ===== */

    public function ajax_save_vylep() {
        check_ajax_referer( 'kass_pub', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Nie ste prihlásený.' ); }
        $id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $data = array(
            'organizacia_id'    => (int) ( $_POST['organizacia_id'] ?? 0 ),
            'organizacia_nazov' => $_POST['organizacia_nazov'] ?? '',
            'nazov_akcie'       => $_POST['nazov_akcie'] ?? '',
            'platba'            => $_POST['platba'] ?? 'Zadarmo',
            'cennik_typ'        => 'vylep',
            'format'            => $_POST['format'] ?? 'A3',
            'datum_od'          => $_POST['datum_od'] ?? date( 'Y-m-d' ),
            'tyzdne'            => $_POST['tyzdne'] ?? 1,
            'kusy'              => $_POST['kusy'] ?? 0,
            'cennik_cena'       => $_POST['cennik_cena'] ?? 0,
            'tlac'              => $_POST['tlac'] ?? 0,
            'tlac_bez_dph'      => $_POST['tlac_bez_dph'] ?? 0,
            'ine'               => $_POST['ine'] ?? 0,
            'poznamka'          => $_POST['poznamka'] ?? '',
            'plochy_sposob'     => $_POST['plochy_sposob'] ?? 'vsetky',
            'plochy_vyber'      => $_POST['plochy_vyber'] ?? '',
            'orientacia'        => $_POST['orientacia'] ?? 'v',
        );
        $new_id = KASS_Vylep_DB::save_vylep( $data, $id );
        $row    = KASS_Vylep_DB::get_vylep( $new_id );
        wp_send_json_success( array( 'id' => $new_id, 'row' => $row ) );
    }

    public function ajax_get_obsadenost() {
        check_ajax_referer( 'kass_pub', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Nie ste prihlásený.' ); }
        wp_send_json_success( KASS_Vylep_Plochy::obsadenost_vsetky() );
    }

    public function ajax_delete_vylep() {
        check_ajax_referer( 'kass_pub', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Nie ste prihlásený.' ); }
        KASS_Vylep_DB::delete_vylep( (int) $_POST['id'] );
        wp_send_json_success();
    }

    public function ajax_save_org() {
        check_ajax_referer( 'kass_pub', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Nie ste prihlásený.' ); }
        $nazov = sanitize_text_field( $_POST['nazov'] ?? '' );
        if ( ! $nazov ) { wp_send_json_error( 'Chýba názov.' ); }
        $id = KASS_Vylep_DB::save_organizacia( array(
            'nazov' => $nazov, 'ulica' => '', 'mesto' => '',
            'ico' => '', 'dic' => '', 'ic_dph' => '', 'penaz_ustav' => '', 'iban' => '',
        ) );
        wp_send_json_success( array( 'id' => $id, 'nazov' => $nazov ) );
    }

    /* ===== SHORTCODE ===== */

    public function shortcode( $atts ) {
        ob_start();
        if ( is_user_logged_in() ) {
            $this->render_tabulka();
        } else {
            $this->render_login();
        }
        return ob_get_clean();
    }

    /* ===== LOGIN ===== */

    private function render_login() {
        $err_key = 'kass_login_err_' . md5( $_SERVER['REMOTE_ADDR'] );
        $chyba   = get_transient( $err_key );
        if ( $chyba ) { delete_transient( $err_key ); }
        ?>
        <div class="kp-login-wrap">
            <div class="kp-login-box">
                <div class="kp-login-logo">
                    <svg width="52" height="52" viewBox="0 0 52 52" fill="none"><rect width="52" height="52" rx="12" fill="#1e3a5f"/><rect x="11" y="14" width="30" height="5" rx="2.5" fill="#fff"/><rect x="11" y="24" width="30" height="5" rx="2.5" fill="#fff"/><rect x="11" y="34" width="20" height="5" rx="2.5" fill="#fff"/></svg>
                </div>
                <h1 class="kp-login-title">Výlep plagátov</h1>
                <p class="kp-login-sub">KaSS Prievidza — interný systém</p>
                <?php if ( $chyba ) : ?><div class="kp-error"><?php echo esc_html( $chyba ); ?></div><?php endif; ?>
                <form method="post" class="kp-login-form">
                    <?php wp_nonce_field( 'kass_login', 'kass_login_nonce' ); ?>
                    <input type="hidden" name="kass_back" value="<?php echo esc_attr( get_permalink() ); ?>">
                    <div class="kp-field"><label>Používateľské meno</label><input type="text" name="kass_user" autocomplete="username" required value="<?php echo esc_attr( $_POST['kass_user'] ?? '' ); ?>"></div>
                    <div class="kp-field"><label>Heslo</label><input type="password" name="kass_pass" autocomplete="current-password" required></div>
                    <div class="kp-field-check"><input type="checkbox" name="kass_remember" id="kp_rem" value="1"><label for="kp_rem">Zapamätať si ma</label></div>
                    <button type="submit" class="kp-btn-login">Prihlásiť sa</button>
                </form>
            </div>
        </div>
        <?php
    }

    /* ===== TABUĽKA ===== */

    private function render_tabulka() {
        $t_start = microtime( true );
        $user    = wp_get_current_user();
        $rok     = isset( $_GET['rok'] ) ? (int) $_GET['rok'] : (int) date( 'Y' );
        $vylepy  = KASS_Vylep_DB::get_vylepy( array( 'rok' => $rok ) );
        $logout_url = wp_nonce_url( add_query_arg( array( 'kass_logout' => '1', 'kass_back' => rawurlencode( get_permalink() ) ), get_permalink() ), 'kass_logout' );

        // Zoznam organizácií pre filter
        $orgs = KASS_Vylep_DB::get_organizacie();
        ?>
        <div class="kp-fullwidth">
        <div class="kp-app" id="kp-app">

            <!-- Sticky horný panel: hlavička + toolbar -->
            <div class="kp-sticky-top">

            <!-- Hlavička -->
            <div class="kp-header">
                <div class="kp-header-left">
                    <svg width="28" height="28" viewBox="0 0 52 52" fill="none"><rect width="52" height="52" rx="12" fill="rgba(255,255,255,.15)"/><rect x="11" y="14" width="30" height="5" rx="2.5" fill="#fff"/><rect x="11" y="24" width="30" height="5" rx="2.5" fill="#fff"/><rect x="11" y="34" width="20" height="5" rx="2.5" fill="#fff"/></svg>
                    <div>
                        <div class="kp-header-title">Výlep plagátov</div>
                        <div class="kp-header-sub">KaSS Prievidza</div>
                    </div>
                </div>
                <div class="kp-header-right">
                    <form method="get" style="display:inline-flex;align-items:center;gap:6px;">
                        <label style="color:rgba(255,255,255,.75);font-size:13px;">Rok:</label>
                        <select name="rok" onchange="this.form.submit()" class="kp-sel-rok">
                            <?php for ( $y = 2014; $y <= (int)date('Y')+1; $y++ ) : ?>
                                <option value="<?php echo $y; ?>" <?php selected( $rok, $y ); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                    <span class="kp-username"><?php echo esc_html( $user->display_name ); ?></span>
                    <a href="<?php echo esc_url( $logout_url ); ?>" class="kp-logout">Odhlásiť</a>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="kp-toolbar">
                <div class="kp-toolbar-left">
                    <div class="kp-tabs" id="kp-tabs">
                        <button type="button" class="kp-tab active" data-tab="tabulka">📋 Tabuľka</button>
                        <button type="button" class="kp-tab" data-tab="plochy">🧱 Plochy</button>
                    </div>
                    <button class="kp-btn-add" id="kp-add-row" type="button">+ Pridať riadok</button>
                    <!-- Filter organizácie -->
                    <div class="kp-filter-wrap">
                        <label class="kp-filter-label">Filtrovať:</label>
                        <select id="kp-filter-org" class="kp-filter-sel">
                            <option value="">— všetky organizácie —</option>
                            <?php foreach ( $orgs as $o ) : ?>
                                <option value="<?php echo esc_attr( $o->nazov ); ?>"><?php echo esc_html( $o->nazov ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="kp-filter-mes" class="kp-filter-sel" style="min-width:120px;">
                            <option value="">— všetky mesiace —</option>
                            <?php
                            $mesiace = array(
                                1=>'Január',2=>'Február',3=>'Marec',4=>'Apríl',
                                5=>'Máj',6=>'Jún',7=>'Júl',8=>'August',
                                9=>'September',10=>'Október',11=>'November',12=>'December'
                            );
                            foreach ( $mesiace as $mn => $mn_nazov ) :
                            ?>
                                <option value="<?php echo $mn; ?>"><?php echo esc_html( $mn_nazov ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="kp-filter-platba" class="kp-filter-sel" style="min-width:110px;">
                            <option value="">— všetky platby —</option>
                            <option value="FA">Faktúra</option>
                            <option value="Z">Zadarmo</option>
                            <option value="H">Hotovosť</option>
                            <option value="K">Karta</option>
                        </select>
                        <button type="button" id="kp-filter-clear" class="kp-btn-clear" style="display:none;">✕ Zrušiť</button>
                    </div>
                </div>
                <div class="kp-toolbar-right">
                    <span id="kp-status" class="kp-status"></span>
                    <span id="kp-count" class="kp-count"></span>
                    <button id="kp-btn-vylep-list" class="kp-btn-print" type="button" onclick="kassVylepList()">📋 Výlep</button>
                    <button class="kp-btn-print" onclick="window.print()" type="button">🖨 Tlačiť</button>
                </div>
            </div>

            <?php
            $thead_row = '<tr>
                <th class="kp-th-num">Č.</th>
                <th class="kp-th-platba">Platba</th>
                <th class="kp-th-fmt">Formát</th>
                <th class="kp-th-org">Organizácia</th>
                <th class="kp-th-akcia">Názov akcie</th>
                <th class="kp-th-date">Od</th>
                <th class="kp-th-date">Do</th>
                <th class="kp-th-sm">Týždne</th>
                <th class="kp-th-kusy">Kusy</th>
                <th class="kp-th-num2">Cenník</th>
                <th class="kp-th-num2">Výlep</th>
                <th class="kp-th-num2">Tlač</th>
                <th class="kp-th-num2">Iné</th>
                <th class="kp-th-num2">Hotovosť</th>
                <th class="kp-th-num2">Faktúra</th>
                <th class="kp-th-num2">Zadarmo</th>
                <th class="kp-th-num2">Kartou</th>
                <th class="kp-th-act">Akcie</th>
            </tr>';
            ?>
            <!-- Ukotvená hlavička stĺpcov (šírky synchronizuje JS s tabuľkou) -->
            <div class="kp-head-wrap" id="kp-head-wrap">
                <table class="kp-table kp-head-table" id="kp-head-table">
                    <thead><?php echo $thead_row; ?></thead>
                </table>
            </div>

            </div><!-- .kp-sticky-top -->

            <!-- Tabuľka -->
            <div class="kp-table-wrap">
                <table class="kp-table" id="kp-table">
                    <thead class="kp-thead-src"><?php echo $thead_row; ?></thead>
                    <tbody id="kp-tbody">
                        <?php
                        // Najnovších KP_RIADKOV_HNED riadkov sa vykreslí hneď, staršie ide do <template>
                        // a JS ich dopĺňa po dávkach (vykreslenie stoviek riadkov naraz je pomalé).
                        $celkom  = count( $vylepy );
                        $starsie = max( 0, $celkom - self::RIADKOV_HNED );
                        $i = 1;
                        $older_html = '';
                        foreach ( $vylepy as $idx => $v ) {
                            if ( $idx < $starsie ) { $older_html .= $this->row_html( $v, $i++ ); }
                            else                   { echo $this->row_html( $v, $i++ ); }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr id="kp-sum-row">
                            <td colspan="10" class="kp-sum-label">Spolu:</td>
                            <td class="kp-sum" id="kp-sum-vylep">—</td>
                            <td class="kp-sum" id="kp-sum-tlac">—</td>
                            <td class="kp-sum" id="kp-sum-ine">—</td>
                            <td class="kp-sum" id="kp-sum-hot">—</td>
                            <td class="kp-sum" id="kp-sum-fak">—</td>
                            <td class="kp-sum" id="kp-sum-zad">—</td>
                            <td class="kp-sum" id="kp-sum-kar">—</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Plochy (vizualizácia obsadenosti) -->
            <div class="kp-plochy-view" id="kp-plochy-view">
                <div class="kp-plochy-selector" id="kp-plochy-selector"></div>
                <div class="kp-plochy-detail" id="kp-plochy-detail"></div>
            </div>

            <?php printf( "<!-- kass-vylep: %d riadkov, generovanie tabuľky %d ms, %d kB HTML -->\n", $celkom, round( ( microtime( true ) - $t_start ) * 1000 ), round( strlen( $older_html ) / 1024 ) ); ?>
            <?php if ( $older_html !== '' ) : ?>
            <template id="kp-older-rows"><?php echo $older_html; ?></template>
            <?php endif; ?>

        </div><!-- .kp-app -->
        </div><!-- .kp-fullwidth -->

        <script>
        function kassVylepList() {
            var rows = document.querySelectorAll('#kp-tbody .kp-row');
            var today = new Date();
            var dow = today.getDay();
            var diffToTue = (dow >= 2) ? (dow - 2) : (dow + 5);
            var thisTue = new Date(today);
            thisTue.setDate(today.getDate() - diffToTue);
            var thisTueStr = thisTue.toISOString().slice(0,10);

            var neprelepit = [], prelepit = [];

            rows.forEach(function(tr) {
                if (tr.style.display === 'none') return;
                var doEl = tr.querySelector('.kp-do');
                var doVal = doEl ? doEl.value : '';
                var platbaEl = tr.querySelector('.kp-platba-hidden');
                var platba = platbaEl ? platbaEl.value : '';
                var fmtEl = tr.querySelector('.kp-format');
                var fmt = fmtEl ? fmtEl.value : '';
                var orgEl = tr.querySelector('.kp-ac-input');
                var org = orgEl ? orgEl.value : (tr.getAttribute('data-org') || '');
                var akciaEl = tr.querySelector('.kp-akcia');
                var akcia = akciaEl ? akciaEl.value : '';
                var odEl = tr.querySelector('.kp-od');
                var od = odEl ? odEl.value : '';
                if (!doVal) return;
                var platbaSkr = ({'Hotovosť':'H','Faktúra':'FA','Karta':'K','Zadarmo':'Z'})[platba] || platba;
                var isPaid = (platbaSkr === 'H' || platbaSkr === 'FA' || platbaSkr === 'K');
                var item = { fmt: fmt, org: org, akcia: akcia, od: od, do: doVal };
                if (doVal === thisTueStr && isPaid) prelepit.push(item);
                else if (doVal > thisTueStr && od !== thisTueStr) neprelepit.push(item);
            });

            function fd(iso) { if (!iso) return ''; var p=iso.split('-'); return p[2]+'.'+p[1]+'.'; }
            function tbl(items) {
                if (!items.length) return '<tr><td colspan="3" style="padding:8px 10px;color:#aaa;font-style:italic;font-size:9pt;">žiadne položky</td></tr>';
                var groups={}, order=[];
                items.forEach(function(r){ var k=r.org||'—'; if(!groups[k]){groups[k]=[];order.push(k);} groups[k].push(r); });
                var out='';
                order.forEach(function(org){
                    var gi=groups[org];
                    gi.forEach(function(r,i){
                        out+='<tr style="border-bottom:1px solid #e8e8e8;">'
                            +(i===0?'<td rowspan="'+gi.length+'" style="padding:6px 10px;font-weight:800;vertical-align:top;border-right:2px solid #ddd;min-width:110px;">'+org+'</td>':'')
                            +'<td style="padding:6px 10px;">'+(r.akcia||'—')+'</td>'
                            +'<td style="padding:6px 10px;font-weight:700;text-align:center;width:40px;">'+r.fmt+'</td></tr>';
                    });
                    out+='<tr><td colspan="3" style="height:4px;background:#f5f5f5;"></td></tr>';
                });
                return out;
            }
            var ds = today.toLocaleDateString('sk',{day:'numeric',month:'long',year:'numeric'});
            var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
                +'body{font-family:Arial,Helvetica,sans-serif;font-size:10pt;color:#111;margin:0;padding:16px;background:#2a2a2a;}'
                +'.a4{background:#fff;padding:12mm 14mm;max-width:210mm;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.4);border-radius:3px;}'
                +'h1{font-size:15pt;margin:0 0 3px;font-weight:900;}'
                +'.sub{font-size:9pt;color:#666;margin-bottom:14px;}'
                +'h2{font-size:10.5pt;margin:18px 0 6px;padding:5px 10px;border-radius:5px;font-weight:800;}'
                +'.nep{background:#fdecea;color:#b71c1c;}.pre{background:#e8f5e9;color:#1b5e20;}'
                +'table{width:100%;border-collapse:collapse;}th{text-align:left;padding:5px 10px;font-size:9pt;background:#f0f0f0;border-bottom:2px solid #ccc;}'
                +'@media print{body{padding:0;background:#fff;}.a4{box-shadow:none;padding:0;}@page{size:A4 portrait;margin:10mm;}}'
                +'</style></head><body><div class="a4">'
                +'<h1>Výlep plagátov</h1><div class="sub">KaSS Prievidza · '+ds+' · týždeň od '+fd(thisTueStr)+'</div>'
                +'<h2 class="nep">🔴 NEPRELEPIŤ <span style="font-size:9pt;font-weight:400;">('+neprelepit.length+' položiek)</span></h2>'
                +'<table><thead><tr><th>Organizácia</th><th>Názov akcie</th><th>Fmt</th></tr></thead><tbody>'+tbl(neprelepit)+'</tbody></table>'
                +'<h2 class="pre">🟢 PRELEPIŤ <span style="font-size:9pt;font-weight:400;">('+prelepit.length+' položiek)</span></h2>'
                +'<table><thead><tr><th>Organizácia</th><th>Názov akcie</th><th>Fmt</th></tr></thead><tbody>'+tbl(prelepit)+'</tbody></table>'
                +'</div></body></html>';

            var modal = document.getElementById('kp-vylep-list-modal');
            var ifr = document.getElementById('kp-vylep-list-iframe');
            ifr.src = 'about:blank';
            setTimeout(function(){
                ifr.contentDocument.open();
                ifr.contentDocument.write(html);
                ifr.contentDocument.close();
            }, 60);
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';

            document.getElementById('kp-vylep-list-close').onclick = function(){
                modal.classList.remove('open');
                document.body.style.overflow = '';
            };
            document.getElementById('kp-vylep-list-print').onclick = function(){
                ifr.contentWindow.focus();
                ifr.contentWindow.print();
            };
            modal.onclick = function(e){ if(e.target===modal){ modal.classList.remove('open'); document.body.style.overflow=''; } };
        }
        </script>

        <!-- Výlep zoznam modal -->
        <div class="kp-modal-overlay" id="kp-vylep-list-modal">
            <div class="kp-modal-box" style="max-width:760px;">
                <div class="kp-modal-bar" style="justify-content:space-between;padding:8px 14px;">
                    <span style="color:#fff;font-size:13px;font-weight:600;">📋 Výlep — zoznam</span>
                    <div style="display:flex;gap:8px;">
                        <button id="kp-vylep-list-print" style="background:#2563eb;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">🖨 Tlačiť</button>
                        <button id="kp-vylep-list-close" class="kp-modal-close">✕</button>
                    </div>
                </div>
                <iframe id="kp-vylep-list-iframe" style="flex:1;border:none;width:100%;background:#2a2a2a;" src="about:blank"></iframe>
            </div>
        </div>

        <!-- Tlač modal -->
        <div class="kp-tlac-modal" id="kp-tlac-modal">
            <div class="kp-tlac-box">
                <h3>🖨 Tlač plagátu</h3>
                <div id="kp-tlac-lines"></div>
                <button class="kp-tlac-add-line" id="kp-tlac-add-line">+ Pridať ďalší formát</button>
                <div class="kp-tlac-result">
                    <span class="kp-tlac-result-lbl">Cena spolu (s DPH):</span>
                    <span class="kp-tlac-result-val" id="kp-tlac-cena">—</span>
                </div>
                <div class="kp-tlac-btns">
                    <button class="kp-tlac-clear" id="kp-tlac-clear">🗑 Vymazať</button>
                    <button class="kp-tlac-cancel" id="kp-tlac-cancel">Zrušiť</button>
                    <button class="kp-tlac-confirm" id="kp-tlac-ok">Potvrdiť</button>
                </div>
            </div>
        </div>

        <!-- Faktura modal -->
        <div class="kp-modal-overlay" id="kp-fakt-modal">
            <div class="kp-modal-box">
                <div class="kp-modal-bar">
                    <button class="kp-modal-close" id="kp-modal-close-btn">✕</button>
                </div>
                <iframe class="kp-modal-iframe" id="kp-fakt-iframe" src="about:blank"></iframe>
            </div>
        </div>

        <!-- Priradenie plôch modal (rovnaký vzor ako Tlač) -->
        <div class="kp-tlac-modal" id="kp-plochy-modal">
            <div class="kp-tlac-box" style="width:440px;">
                <h3>🧱 Kusy a plochy</h3>
                <div class="kp-plochy-kusy-row">
                    <label for="kp-plochy-kusy">Počet kusov</label>
                    <input type="number" id="kp-plochy-kusy" min="0" max="999">
                </div>
                <div class="kp-plochy-modes" id="kp-plochy-modes">
                    <button type="button" class="kp-plochy-mode-btn" data-val="vsetky">26 (všetky)</button>
                    <button type="button" class="kp-plochy-mode-btn" data-val="top10">TOP 10</button>
                    <button type="button" class="kp-plochy-mode-btn" data-val="top15">TOP 15</button>
                    <button type="button" class="kp-plochy-mode-btn" data-val="top20">TOP 20</button>
                    <button type="button" class="kp-plochy-mode-btn" data-val="vyber">Vybrať konkrétne</button>
                </div>
                <div class="kp-plochy-picker-grid" id="kp-plochy-picker-grid" style="display:none;"></div>
                <div class="kp-plochy-count" id="kp-plochy-count"></div>
                <div class="kp-tlac-btns">
                    <button type="button" class="kp-tlac-cancel" id="kp-plochy-cancel">Zrušiť</button>
                    <button type="button" class="kp-tlac-confirm" id="kp-plochy-ok">Potvrdiť</button>
                </div>
            </div>
        </div>
        <?php
    }

    /** HTML jedného riadku. */
    public function row_html( $v, $i ) {
        $id        = (int) $v->id;
        $org_id    = (int) ( $v->organizacia_id ?? 0 );
        $org_nazov = esc_attr( $v->organizacia_nazov ?? '' );
        $akcia     = esc_attr( $v->nazov_akcie ?? '' );
        $datum_od  = esc_attr( $v->datum_od ?? '' );
        $datum_do  = esc_attr( $v->datum_do ?? '' );
        $tyzdne    = (int) ( $v->tyzdne ?? 1 );
        $kusy      = (int) ( $v->kusy ?? 0 );
        $cena      = (float) ( $v->cennik_cena ?? 0 );
        $vylep_s   = (float) ( $v->vylep_suma ?? 0 );
        $tlac      = (float) ( $v->tlac ?? 0 );
        $tlac_bez  = (float) ( $v->tlac_bez_dph ?? 0 );
        $ine       = (float) ( $v->ine ?? 0 );
        $poznamka  = esc_attr( $v->poznamka ?? '' );
        $platba    = $v->platba ?? 'Zadarmo';

        $spolu = $vylep_s + $tlac + $ine;
        $hot   = $platba === 'Hotovosť' ? $spolu : 0;
        $fak   = $platba === 'Faktúra'  ? $spolu : 0;
        $zad   = $platba === 'Zadarmo'  ? $spolu : 0;
        $kar   = $platba === 'Karta'    ? $spolu : 0;

        $platba_skr = array( 'Faktúra' => 'FA', 'Zadarmo' => 'Z', 'Hotovosť' => 'H', 'Karta' => 'K' );
        $platba_label = isset( $platba_skr[ $platba ] ) ? $platba_skr[ $platba ] : 'Z';
        $current_fmt = $v->format ?? 'A3';
        $orient = ( ( $v->orientacia ?? 'v' ) === 's' ) ? 's' : 'v';
        $orient_tit = $orient === 's' ? 'Na šírku' : 'Na výšku';

        $plochy_sposob = $v->plochy_sposob ?? 'vsetky';
        if ( ! in_array( $plochy_sposob, array( 'vsetky', 'top10', 'top15', 'top20', 'vyber' ), true ) ) {
            $plochy_sposob = 'vsetky';
        }
        $plochy_vyber = esc_attr( $v->plochy_vyber ?? '' );

        $f2 = function( $n ) { return $n > 0 ? number_format( $n, 2, ',', ' ' ) . ' €' : '—'; };
        $fakt_url = admin_url( 'admin.php?page=kass-faktura&vylep=' . $id );

        $mes_od = $datum_od ? (int) date('n', strtotime($datum_od)) : 0;
        $html = "
<tr data-id=\"$id\" data-org=\"$org_nazov\" data-mes=\"$mes_od\" class=\"kp-row\">
  <td class=\"kp-num\"><span class=\"kp-num-edit\" contenteditable=\"true\">$i</span>.</td>
  <td>
    <div class=\"kp-platba-wrap\">
      <div class=\"kp-platba-btn\" data-val=\"$platba_label\" data-full=\"$platba\">$platba_label</div>
      <input type=\"hidden\" class=\"kp-platba-hidden\" name=\"platba\" value=\"$platba\">
      <div class=\"kp-platba-drop\"></div>
    </div>
  </td>
  <td>
    <div class=\"kp-fmt-cell\">
      <div class=\"kp-fmt-wrap\">
        <div class=\"kp-fmt-btn\">$current_fmt</div>
        <input type=\"hidden\" class=\"kp-format\" name=\"format\" value=\"$current_fmt\">
        <div class=\"kp-fmt-drop\"></div>
      </div>
      <button type=\"button\" class=\"kp-orient-btn\" data-val=\"$orient\" title=\"$orient_tit — klik pre zmenu\"></button>
      <input type=\"hidden\" class=\"kp-orient\" name=\"orientacia\" value=\"$orient\">
    </div>
  </td>
  <td>
    <div class=\"kp-ac-wrap\">
      <input type=\"text\" class=\"kp-inp kp-ac-input\" placeholder=\"Začni písať…\" value=\"$org_nazov\" autocomplete=\"off\">
      <input type=\"hidden\" class=\"kp-ac-id\" name=\"organizacia_id\" value=\"$org_id\">
      <input type=\"hidden\" class=\"kp-ac-nazov\" name=\"organizacia_nazov\" value=\"$org_nazov\">
      <div class=\"kp-ac-drop\" style=\"display:none;\"></div>
    </div>
  </td>
  <td><input type=\"text\" class=\"kp-inp kp-akcia\" name=\"nazov_akcie\" value=\"$akcia\" placeholder=\"Názov akcie\"></td>
  <td class=\"kp-date-cell\"><span class=\"kp-date-txt kp-od-txt\"></span><input type=\"date\" class=\"kp-date-real kp-od\" name=\"datum_od\" value=\"$datum_od\"></td>
  <td class=\"kp-date-cell\"><span class=\"kp-date-txt kp-do-txt\"></span><input type=\"date\" class=\"kp-date-real kp-do\" name=\"datum_do\" value=\"$datum_do\"></td>
  <td><input type=\"number\" class=\"kp-inp kp-tyzdne\" name=\"tyzdne\" value=\"$tyzdne\" min=\"1\" max=\"20\"></td>
  <td>
    <div class=\"kp-kusy-wrap\">
      <button type=\"button\" class=\"kp-kusy-btn\" title=\"Kusy a plochy\">$kusy</button>
      <input type=\"hidden\" class=\"kp-kusy\" name=\"kusy\" value=\"$kusy\">
      <div class=\"kp-plochy-wrap\">
        <input type=\"hidden\" class=\"kp-plochy-sposob\" name=\"plochy_sposob\" value=\"$plochy_sposob\">
        <input type=\"hidden\" class=\"kp-plochy-vyber\" name=\"plochy_vyber\" value=\"$plochy_vyber\">
      </div>
    </div>
  </td>
  <td class=\"kp-calc\"><span class=\"kp-cena-val\">" . ( $cena > 0 ? number_format( $cena, 2, ',', ' ' ) . ' €' : '—' ) . "</span><input type=\"hidden\" class=\"kp-cena\" name=\"cennik_cena\" value=\"$cena\"></td>
  <td class=\"kp-calc kp-bold\"><span class=\"kp-vylep-val\">" . $f2( $vylep_s ) . "</span></td>
  <td><input type=\"text\" class=\"kp-inp kp-tlac\" name=\"tlac\" value=\"" . ( $tlac > 0 ? number_format( $tlac, 2, ',', ' ' ) . ' €' : '' ) . "\" placeholder=\"0\"><input type=\"hidden\" class=\"kp-tlac-bez\" value=\"$tlac_bez\"></td>
  <td><input type=\"text\" class=\"kp-inp kp-ine\" name=\"ine\" value=\"" . ( $ine > 0 ? number_format( $ine, 2, ',', ' ' ) . ' €' : '' ) . "\" placeholder=\"0\"></td>
  <td class=\"kp-calc\"><span class=\"kp-hot-val\">" . $f2( $hot ) . "</span></td>
  <td class=\"kp-calc\"><span class=\"kp-fak-val\">" . $f2( $fak ) . "</span></td>
  <td class=\"kp-calc\"><span class=\"kp-zad-val\">" . $f2( $zad ) . "</span></td>
  <td class=\"kp-calc\"><span class=\"kp-kar-val\">" . $f2( $kar ) . "</span></td>
  <td class=\"kp-actions\">
    <button type=\"button\" class=\"kp-btn-save\" title=\"Uložiť\">💾</button>
    <a href=\"" . esc_url( $fakt_url ) . "\" target=\"_blank\" class=\"kp-btn-fakt\" title=\"Podklad k faktúre\">📄</a>
    <button type=\"button\" class=\"kp-btn-del\" title=\"Zmazať\">✕</button>
  </td>
</tr>";
        // Kompaktné HTML: bez odsadenia a zalomení medzi značkami (stovky riadkov = menší prenos a rýchlejšie parsovanie)
        return preg_replace( '/>\s+</', '><', trim( $html ) );
    }
}
