<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$kategorie = KASS_Vylep_Cennik::kategorie();
?>
<div class="wrap kass-wrap">
    <h1>Cenník</h1>
    <p class="kass-lead">Kompletný cenník s kódmi. Ceny môžeš upraviť a uložiť. Pri zadávaní výlepu sa cena dopĺňa automaticky podľa formátu, dĺžky a typu (štandard / predvolebná kampaň).</p>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Cenník uložený.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'kass_save_cennik' ); ?>
        <input type="hidden" name="action" value="kass_save_cennik">

        <?php foreach ( $kategorie as $kat_key => $kat_nazov ) :
            $polozky = KASS_Vylep_Cennik::by_kategoria( $kat_key );
            if ( empty( $polozky ) ) { continue; }
        ?>
            <h2 class="kass-cennik-h2"><?php echo esc_html( $kat_nazov ); ?></h2>
            <table class="widefat striped kass-table kass-cennik-table" style="max-width:760px;">
                <thead>
                    <tr>
                        <th style="width:60px;">Kód</th>
                        <th>Formát</th>
                        <th>Popis / trvanie</th>
                        <th style="width:150px;">Cena bez DPH / ks</th>
                        <th style="width:150px;">Cena s DPH / ks</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $polozky as $p ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p->kod ); ?></strong></td>
                        <td><?php echo esc_html( $p->format ); ?></td>
                        <td><?php echo esc_html( $p->popis ); ?></td>
                        <td>
                            <input type="text" name="bez[<?php echo (int) $p->id; ?>]" value="<?php echo esc_attr( rtrim( rtrim( number_format( $p->cena_bez_dph, 4, '.', '' ), '0' ), '.' ) ); ?>" style="width:100px;text-align:right;"> €
                        </td>
                        <td>
                            <input type="text" name="sdph[<?php echo (int) $p->id; ?>]" value="<?php echo esc_attr( rtrim( rtrim( number_format( $p->cena_s_dph, 4, '.', '' ), '0' ), '.' ) ); ?>" style="width:100px;text-align:right;"> €
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <p class="submit"><button type="submit" class="button button-primary">Uložiť cenník</button></p>
    </form>
</div>
