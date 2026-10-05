<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$plochy = KASS_Vylep_Plochy::get_all();
?>
<div class="wrap kass-wrap">
    <h1>Plochy</h1>
    <p class="kass-lead">26 výlepných plôch. Pre každú nastav názov, zaradenie do kategórií TOP 10/15/20 a rozloženie — počet modulov v hornom a spodnom rade. Plagáty sa do schémy v záložke „Plochy" skladajú automaticky podľa formátu a orientácie.</p>
    <ul class="kass-lead" style="list-style:disc;margin-left:20px;">
        <li><strong>Horný rad</strong> (modul = A1 na výšku): zmestí sa tam A1 na výšku, 2× A2 na šírku alebo 4× A3 na výšku. Počet zadávaj aj po polovici (napr. 9,5 — posledný polovičný modul pojme 2× A3 na výšku).</li>
        <li><strong>Spodný rad</strong> (modul = A2 na výšku): zmestí sa tam A2 na výšku, 2× A3 na šírku, alebo A1 na šírku (zaberie 2 moduly).</li>
    </ul>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Plochy uložené.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kass-plochy-form">
        <?php wp_nonce_field( 'kass_save_plochy' ); ?>
        <input type="hidden" name="action" value="kass_save_plochy">

        <table class="widefat striped kass-table kass-plochy-table" style="max-width:900px;">
            <thead>
                <tr>
                    <th style="width:50px;">Č.</th>
                    <th style="width:220px;">Názov</th>
                    <th style="width:60px;">TOP 10</th>
                    <th style="width:60px;">TOP 15</th>
                    <th style="width:60px;">TOP 20</th>
                    <th style="width:150px;">Horný rad<br><span style="font-weight:400;">(moduly A1 výška)</span></th>
                    <th style="width:150px;">Spodný rad<br><span style="font-weight:400;">(moduly A2 výška)</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $plochy as $p ) : $pid = (int) $p->id; ?>
                <tr>
                    <td><strong><?php echo (int) $p->cislo; ?></strong></td>
                    <td><input type="text" name="nazov[<?php echo $pid; ?>]" value="<?php echo esc_attr( $p->nazov ); ?>" style="width:100%;"></td>
                    <td style="text-align:center;"><input type="checkbox" name="top10[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top10 ) ); ?>></td>
                    <td style="text-align:center;"><input type="checkbox" name="top15[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top15 ) ); ?>></td>
                    <td style="text-align:center;"><input type="checkbox" name="top20[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top20 ) ); ?>></td>
                    <td><input type="number" name="top_units[<?php echo $pid; ?>]" value="<?php echo esc_attr( (float) $p->top_units ); ?>" min="0" max="40" step="0.5" style="width:80px;"></td>
                    <td><input type="number" name="bottom_units[<?php echo $pid; ?>]" value="<?php echo esc_attr( (float) $p->bottom_units ); ?>" min="0" max="40" step="1" style="width:80px;"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit"><button type="submit" class="button button-primary">Uložiť plochy</button></p>
    </form>
</div>
