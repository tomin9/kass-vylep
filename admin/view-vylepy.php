<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$edit_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$organizacie = KASS_Vylep_DB::get_organizacie();
$formaty = KASS_Vylep_Cennik::formaty();

if ( $action === 'edit' || $action === 'new' ) :
    $v = $edit_id ? KASS_Vylep_DB::get_vylep( $edit_id ) : null;
    $val = function( $field, $default = '' ) use ( $v ) {
        return $v && isset( $v->$field ) ? esc_attr( $v->$field ) : $default;
    };
?>
<div class="wrap kass-wrap">
    <h1><?php echo $edit_id ? 'Upraviť výlep' : 'Nový výlep'; ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kass-form">
        <?php wp_nonce_field( 'kass_save_vylep' ); ?>
        <input type="hidden" name="action" value="kass_save_vylep">
        <input type="hidden" name="id" value="<?php echo (int) $edit_id; ?>">

        <table class="form-table">
            <tr>
                <th><label>Organizácia / odberateľ</label></th>
                <td>
                    <select name="organizacia_id" id="ars-org-select">
                        <option value="0">— vyber z databázy —</option>
                        <?php foreach ( $organizacie as $o ) : ?>
                            <option value="<?php echo (int) $o->id; ?>" data-nazov="<?php echo esc_attr( $o->nazov ); ?>"
                                <?php selected( $v && $v->organizacia_id == $o->id ); ?>>
                                <?php echo esc_html( $o->nazov ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="organizacia_nazov" id="ars-org-nazov" value="<?php echo $val( 'organizacia_nazov' ); ?>" placeholder="alebo napíš názov" style="width:260px;">
                    <p class="description">Vyber z databázy alebo napíš názov. Pre úplné fakturačné údaje pridaj odberateľa v sekcii Odberatelia.</p>
                </td>
            </tr>
            <tr>
                <th><label>Názov akcie</label></th>
                <td><input type="text" name="nazov_akcie" value="<?php echo $val( 'nazov_akcie' ); ?>" required style="width:360px;"></td>
            </tr>
            <tr>
                <th><label>Platba</label></th>
                <td>
                    <select name="platba">
                        <?php foreach ( array( 'Zadarmo', 'Faktúra', 'Hotovosť', 'Karta' ) as $p ) : ?>
                            <option <?php selected( $v && $v->platba === $p ); ?>><?php echo esc_html( $p ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Typ cenníka</label></th>
                <td>
                    <select name="cennik_typ" id="kass-cennik-typ">
                        <option value="vylep" <?php selected( ! $v || $v->cennik_typ === 'vylep' ); ?>>Štandardný výlep</option>
                        <option value="predvolba" <?php selected( $v && $v->cennik_typ === 'predvolba' ); ?>>Predvolebná kampaň</option>
                    </select>
                    <p class="description">Predvolebná kampaň má vlastné ceny (A4/A3 spolu, A2 zvlášť, max 4 týždne).</p>
                </td>
            </tr>
            <tr>
                <th><label>Formát</label></th>
                <td>
                    <select name="format" id="kass-format">
                        <?php foreach ( $formaty as $f ) : ?>
                            <option <?php selected( $v && $v->format === $f ); ?>><?php echo esc_html( $f ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Dátum výlepu (utorok)</label></th>
                <td>
                    <input type="date" name="datum_od" id="ars-datum-od" value="<?php echo $val( 'datum_od', date( 'Y-m-d' ) ); ?>">
                    <p class="description">Ak zvolíš iný deň, automaticky sa zarovná na utorok.</p>
                </td>
            </tr>
            <tr>
                <th><label>Trvanie (týždne)</label></th>
                <td>
                    <input type="number" name="tyzdne" id="ars-tyzdne" min="1" max="20" value="<?php echo $val( 'tyzdne', '1' ); ?>" style="width:80px;"> T
                    <span class="description" id="ars-datum-do-info"></span>
                </td>
            </tr>
            <tr>
                <th><label>Počet kusov</label></th>
                <td><input type="number" name="kusy" id="ars-kusy" min="0" value="<?php echo $val( 'kusy', '26' ); ?>" style="width:80px;"></td>
            </tr>
            <tr>
                <th><label>Cena za kus (cenník)</label></th>
                <td>
                    <input type="text" name="cennik_cena" id="ars-cena" value="<?php echo $val( 'cennik_cena', '1.3008' ); ?>" style="width:100px;"> €
                    <button type="button" class="button" id="ars-doplnit-cenu">Doplniť z cenníka</button>
                    <span class="description">Výlep spolu: <strong id="kass-vylep-suma">—</strong></span>
                </td>
            </tr>
            <tr>
                <th><label>Tlač (€)</label></th>
                <td><input type="text" name="tlac" value="<?php echo $val( 'tlac', '0' ); ?>" style="width:100px;"></td>
            </tr>
            <tr>
                <th><label>Iné (€)</label></th>
                <td><input type="text" name="ine" value="<?php echo $val( 'ine', '0' ); ?>" style="width:100px;"></td>
            </tr>
            <tr>
                <th><label>Poznámka</label></th>
                <td><textarea name="poznamka" rows="2" style="width:360px;"><?php echo $v ? esc_textarea( $v->poznamka ) : ''; ?></textarea></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary">Uložiť výlep</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-vylepy' ) ); ?>" class="button">Zrušiť</a>
        </p>
    </form>
</div>

<?php else : // ---------- ZOZNAM ----------
$rok = isset( $_GET['rok'] ) ? (int) $_GET['rok'] : (int) date( 'Y' );
$vylepy = KASS_Vylep_DB::get_vylepy( array( 'rok' => $rok ) );
$dnes = new DateTime();
?>
<div class="wrap kass-wrap">
    <h1>
        Evidencia výlepov <?php echo esc_html( $rok ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-vylepy&action=new' ) ); ?>" class="page-title-action">Pridať výlep</a>
    </h1>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php echo $_GET['msg'] === 'deleted' ? 'Výlep zmazaný.' : 'Výlep uložený.'; ?>
        </p></div>
    <?php endif; ?>

    <form method="get" style="margin:10px 0;">
        <input type="hidden" name="page" value="kass-vylepy">
        <label>Rok: <input type="number" name="rok" value="<?php echo esc_attr( $rok ); ?>" style="width:90px;"></label>
        <button class="button">Zobraziť</button>
    </form>

    <div class="kass-table-scroll">
    <table class="widefat striped kass-table kass-evidencia">
        <thead>
            <tr>
                <th>Č.</th><th>Platba</th><th>Formát</th><th>Organizácia</th><th>Názov akcie</th>
                <th>Od</th><th>Do</th><th>Trvanie</th><th>Kusy</th><th>Cenník</th><th>Výlep</th>
                <th>Tlač</th><th>Iné</th><th>Hotovosť</th><th>Faktúra</th><th>Zadarmo</th><th>Kartou</th>
                <th>Do konca</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $vylepy ) ) : ?>
            <tr><td colspan="19">Žiadne výlepy pre rok <?php echo esc_html( $rok ); ?>.</td></tr>
        <?php else :
            $i = 1;
            $sum_vylep = $sum_tlac = $sum_ine = $sum_hot = $sum_fak = $sum_zad = $sum_kar = 0;
            foreach ( $vylepy as $v ) :
                $do = new DateTime( $v->datum_do );
                $do_konca = (int) floor( ( $do->getTimestamp() - $dnes->getTimestamp() ) / 86400 );

                // Suma, ktorá ide do stĺpca podľa typu platby = Výlep + Tlač + Iné
                $spolu = (float) $v->vylep_suma + (float) $v->tlac + (float) $v->ine;
                $hot = ( $v->platba === 'Hotovosť' ) ? $spolu : null;
                $fak = ( $v->platba === 'Faktúra' )  ? $spolu : null;
                $zad = ( $v->platba === 'Zadarmo' )  ? $spolu : null;
                $kar = ( $v->platba === 'Karta' )    ? $spolu : null;

                $sum_vylep += (float) $v->vylep_suma;
                $sum_tlac  += (float) $v->tlac;
                $sum_ine   += (float) $v->ine;
                $sum_hot   += $hot ?? 0;
                $sum_fak   += $fak ?? 0;
                $sum_zad   += $zad ?? 0;
                $sum_kar   += $kar ?? 0;
        ?>
            <tr>
                <td><?php echo $i++; ?>.</td>
                <td><?php echo esc_html( $v->platba ); ?></td>
                <td><?php echo esc_html( $v->format ); ?><?php echo ( isset( $v->cennik_typ ) && $v->cennik_typ === 'predvolba' ) ? ' <span class="kass-tag">PV</span>' : ''; ?></td>
                <td><?php echo esc_html( $v->organizacia_nazov ); ?></td>
                <td><?php echo esc_html( $v->nazov_akcie ); ?></td>
                <td><?php echo esc_html( KASS_Vylep_Admin::datum_sk( $v->datum_od ) ); ?></td>
                <td><?php echo esc_html( KASS_Vylep_Admin::datum_sk( $v->datum_do ) ); ?></td>
                <td><?php echo esc_html( $v->tyzdne ); ?>T</td>
                <td><?php echo esc_html( $v->kusy ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $v->cennik_cena ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $v->vylep_suma ) ); ?></td>
                <td class="num"><?php echo $v->tlac > 0 ? esc_html( KASS_Vylep_Admin::eur( $v->tlac ) ) : '—'; ?></td>
                <td class="num"><?php echo $v->ine > 0 ? esc_html( KASS_Vylep_Admin::eur( $v->ine ) ) : '—'; ?></td>
                <td class="num"><?php echo $hot !== null ? esc_html( KASS_Vylep_Admin::eur( $hot ) ) : '—'; ?></td>
                <td class="num"><?php echo $fak !== null ? esc_html( KASS_Vylep_Admin::eur( $fak ) ) : '—'; ?></td>
                <td class="num"><?php echo $zad !== null ? esc_html( KASS_Vylep_Admin::eur( $zad ) ) : '—'; ?></td>
                <td class="num"><?php echo $kar !== null ? esc_html( KASS_Vylep_Admin::eur( $kar ) ) : '—'; ?></td>
                <td class="num <?php echo $do_konca < 0 ? 'kass-past' : 'kass-future'; ?>"><?php echo esc_html( $do_konca ); ?></td>
                <td class="kass-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-vylepy&action=edit&id=' . $v->id ) ); ?>">Upraviť</a> |
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-faktura&vylep=' . $v->id ) ); ?>">Faktúra</a> |
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kass_delete_vylep&id=' . $v->id ), 'kass_delete_vylep' ) ); ?>" onclick="return confirm('Naozaj zmazať?')" style="color:#b32d2e;">Zmazať</a>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr class="kass-total-row">
                <td colspan="10" style="text-align:right;">Spolu:</td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_vylep ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_tlac ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_ine ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_hot ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_fak ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_zad ) ); ?></td>
                <td class="num"><?php echo esc_html( KASS_Vylep_Admin::eur( $sum_kar ) ); ?></td>
                <td colspan="2"></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
