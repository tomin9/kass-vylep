<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$organizacie = KASS_Vylep_DB::get_organizacie();
$predvybrany_org = 0;
$polozky_init = array();
if ( isset( $_GET['vylep'] ) ) {
    $vy = KASS_Vylep_DB::get_vylep( (int) $_GET['vylep'] );
    if ( $vy ) {
        $predvybrany_org = (int) $vy->organizacia_id;
        // Ak nie je ID, skús nájsť org podľa mena
        if ( ! $predvybrany_org && ! empty( $vy->organizacia_nazov ) ) {
            foreach ( $organizacie as $o ) {
                if ( $o->nazov === $vy->organizacia_nazov || $o->odberatel === $vy->organizacia_nazov ) {
                    $predvybrany_org = (int) $o->id;
                    break;
                }
            }
        }
        $polozky_init[] = $vy;
    }
}
$org_js = array();
foreach ( $organizacie as $o ) {
    $org_js[ $o->id ] = array(
        'nazov' => $o->nazov, 'ulica' => $o->ulica, 'mesto' => $o->mesto,
        'ico' => $o->ico, 'dic' => $o->dic, 'icdph' => $o->ic_dph,
        'penaz' => $o->penaz_ustav, 'iban' => $o->iban,
    );
}
$cennik_js = KASS_Vylep_Cennik::get_js_matrix();
?>
<div class="wrap kass-wrap kass-faktura-wrap">
<h1 class="kass-noprint" style="<?php echo ! empty( $_GET['embed'] ) ? 'display:none;' : ''; ?>">Podklad k fakturácii</h1>

<div class="kass-fakt-toolbar kass-noprint">
    <label>Odberateľ:
        <select id="fakt-org">
            <option value="0">— vyber —</option>
            <?php foreach ( $organizacie as $o ) : ?>
                <option value="<?php echo (int) $o->id; ?>" <?php selected( $predvybrany_org === (int) $o->id ); ?>><?php echo esc_html( $o->nazov ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="button" class="button" id="fakt-pridat">+ Pridať položku</button>
    <button type="button" class="button button-primary" onclick="window.print()">🖨 Tlačiť / uložiť PDF</button>
    <span class="description">Vyber odberateľa — údaje sa načítajú. Potom doplň položky a vytlač.</span>
</div>

<div class="kass-a4 fa4" id="fakt-doc">

    <div class="fa4-title">PODKLAD K FAKTURÁCII</div>

    <!-- DODÁVATEĽ + ODBERATEĽ -->
    <table class="fa4-hlavicka">
        <!-- Dodávateľ -->
        <tr>
            <td class="fa4-col-l fa4-sect-lbl">Dodávateľ:</td>
            <td class="fa4-col-r fa4-ico-blok" rowspan="2" style="vertical-align:top; padding-top:3px;">
                <table><tbody>
                    <tr><td class="ico-lbl"><b>IČO:</b></td><td>516988</td></tr>
                    <tr><td class="ico-lbl"><b>DIČ:</b></td><td>2021160317</td></tr>
                    <tr><td class="ico-lbl"><b>IČ DPH:</b></td><td>SK 2021160317</td></tr>
                    <tr><td class="ico-lbl"><b>Peňaž. úst:</b></td><td>VÚB Prievidza</td></tr>
                    <tr><td class="ico-lbl"><b>Č. účtu:</b></td><td>4430-382/0200</td></tr>
                    <tr><td class="ico-lbl"><b>IBAN:</b></td><td>SK13 0200 0000 0000 0443 0382</td></tr>
                </tbody></table>
            </td>
        </tr>
        <tr>
            <td class="fa4-col-l fa4-addr">
                Kultúrne a spoločenské stredisko v Prievidzi<br>
                príspevková organizácia mesta<br>
                Ul. F. Madvu 11<br>
                971 01&nbsp; Prievidza
            </td>
        </tr>
        <!-- Odberateľ -->
        <tr class="fa4-divider">
            <td class="fa4-col-l fa4-sect-lbl">Odberateľ:</td>
            <td class="fa4-col-r fa4-ico-blok" rowspan="2" style="vertical-align:top; padding-top:3px;">
                <table><tbody>
                    <tr><td class="ico-lbl"><b>IČO:</b></td><td><input class="fai" id="f-ico"></td></tr>
                    <tr><td class="ico-lbl"><b>DIČ:</b></td><td><input class="fai" id="f-dic"></td></tr>
                    <tr><td class="ico-lbl"><b>IČ DPH:</b></td><td><input class="fai" id="f-icdph"></td></tr>
                    <tr><td class="ico-lbl"><b>Peňaž. úst:</b></td><td><input class="fai" id="f-penaz"></td></tr>
                    <tr><td class="ico-lbl"><b>IBAN:</b></td><td><input class="fai fai-last" id="f-iban"></td></tr>
                </tbody></table>
            </td>
        </tr>
        <tr>
            <td class="fa4-col-l fa4-addr">
                <div><input class="fai fai-bold" id="f-nazov" placeholder="Názov organizácie"></div>
                <div><input class="fai" id="f-ulica" placeholder="Ulica a číslo"></div>
                <div><input class="fai fai-last" id="f-mesto" placeholder="PSČ a mesto"></div>
            </td>
        </tr>
    </table>

    <!-- OBJEDNÁVKA / ZMLUVA -->
    <table class="fa4-obj">
        <tr>
            <td class="fa4-obj-l">
                <div style="line-height:1.35;"><b>Objednávka č.:</b> <span class="fa4-line" contenteditable="true" id="f-obj" style="min-width:60px;"></span></div>
                <div style="line-height:1.35;"><b>zo dňa:</b> <span class="fa4-line" contenteditable="true" id="f-obj-d" style="min-width:60px;"></span></div>
            </td>
            <td class="fa4-obj-m">
                <div style="line-height:1.35;"><b>Zmluva:</b> <span class="fa4-line" contenteditable="true" id="f-zml" style="min-width:60px;"></span></div>
                <div style="line-height:1.35;"><b>zo dňa:</b> <span class="fa4-line" contenteditable="true" id="f-zml-d" style="min-width:60px;"></span></div>
            </td>
            <td class="fa4-obj-r"></td>
        </tr>
    </table>

    <!-- POLOŽKY -->
    <table class="fa4-polozky-wrap">
        <tbody id="fakt-polozky"></tbody>
        <tr class="kass-noprint">
            <td colspan="2">
                <button type="button" class="fakt-additem" id="fakt-additem">+ Pridať ďalší výlep</button>
            </td>
        </tr>
    </table>

    <!-- SUMA -->
    <table class="fa4-suma">
        <tr>
            <td class="fa4-suma-lbl">Suma celkom bez DPH:</td>
            <td class="fa4-suma-val" id="fakt-suma">0,00 €</td>
        </tr>
    </table>

    <!-- FINANČNÁ KONTROLA -->
    <div class="fa4-fk">
        <div>Podklad k fakturácii vystavil <b contenteditable="true" id="f-vystavil" style="outline:none;cursor:text;">Tomáš Pekár</b></div>
        <hr style="border:none;border-top:1px solid #000;margin:4px 0;">
        <div style="font-size:11pt;line-height:1.2;">Finančná kontrola vykonaná podľa zákona č. 357/2015 Z.z. o finančnej kontrole a audite</div>
        <div style="font-size:11pt;line-height:1.2;margin-bottom:2px;">a o zmene a doplnení niektorých zákonov:</div>
        <div>a) Finančná operácia alebo jej časť <b>je</b> / <s>nie je</s> * v súlade s rozpočtom, s právnymi predpismi.</div>
        <div>b) Finančná operácia alebo jej časť <b>spĺňa</b> / <s>nespĺňa</s> * podmienky hospodárnosti, efektívnosti, účinnosti a účelovosti.</div>
        <div>S finančnou operáciou alebo jej časťou <b>súhlasím</b> / <s>nesúhlasím</s> *.</div>
        <div class="fa4-fk-pod">
            <span>Dátum:</span>
            <span contenteditable="true" class="fa4-fk-date" id="f-d1">02.06.2026</span>
            <span class="fa4-fk-grow" style="display:flex;align-items:baseline;gap:3px;">Meno, priezvisko, podpis zam. za príslušnú FO: Tomáš Pekár<span style="flex:1;border-bottom:1px dotted #000;margin-bottom:2px;margin-left:4px;min-width:20px;"></span></span>
        </div>
        <div>S finančnou operáciou alebo jej časťou <b>súhlasím</b> / <s>nesúhlasím</s> *.</div>
        <div class="fa4-fk-pod">
            <span>Dátum:</span>
            <span contenteditable="true" class="fa4-fk-date" id="f-d2">02.06.2026</span>
            <span class="fa4-fk-grow" style="display:flex;align-items:baseline;gap:3px;">Meno, priezvisko, podpis štatutára: Mgr. Dana Horná<span style="flex:1;border-bottom:1px dotted #000;margin-bottom:2px;margin-left:4px;min-width:20px;"></span></span>
        </div>
        <div style="margin-top:3px;color:#888;font-size:7.5pt;">/* nehodiace sa prečiarknuť</div>
    </div>

</div>
</div>

<script>
window.KASSFakt = {
    org: <?php echo wp_json_encode( $org_js ); ?>,
    cennik: <?php echo wp_json_encode( $cennik_js ); ?>,
    obdobia: ["1 týždeň","2 týždne","3 týždne","4 týždne","5 týždňov"],
    formaty: ["A4","A3","A2","A1"],
    init: <?php echo wp_json_encode( array_map( function( $v ) {
        return array(
            'popis'     => $v->nazov_akcie,
            'fmt'       => $v->format,
            'oi'        => max( 0, (int) $v->tyzdne - 1 ),
            'kusy'      => (int) $v->kusy,
            'cena'      => (float) $v->cennik_cena,
            'od'        => $v->datum_od,
            'do'        => $v->datum_do,
            'org_nazov' => $v->organizacia_nazov,
        );
    }, $polozky_init ) ); ?>,
    predvybrany: <?php echo (int) $predvybrany_org; ?>
};
</script>
