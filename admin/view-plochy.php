<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$plochy      = KASS_Vylep_Plochy::get_all();
$sloty_podla = KASS_Vylep_Plochy::get_sloty_vsetky();
$formaty     = array( 'A4', 'A3', 'A2', 'A1' );
?>
<div class="wrap kass-wrap">
    <h1>Plochy</h1>
    <p class="kass-lead">26 výlepných plôch. Pre každú nastav názov, zaradenie do kategórií TOP 10/15/20 (používa sa pri priraďovaní plagátov v hlavnej tabuľke) a rozloženie — zoznam slotov podľa formátu, ktoré určuje kapacitu aj vizuálnu schému plochy v záložke „Plochy".</p>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Plochy uložené.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kass-plochy-form">
        <?php wp_nonce_field( 'kass_save_plochy' ); ?>
        <input type="hidden" name="action" value="kass_save_plochy">

        <table class="widefat striped kass-table kass-plochy-table">
            <thead>
                <tr>
                    <th style="width:50px;">Č.</th>
                    <th style="width:200px;">Názov</th>
                    <th style="width:70px;">TOP 10</th>
                    <th style="width:70px;">TOP 15</th>
                    <th style="width:70px;">TOP 20</th>
                    <th>Rozloženie (sloty)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $plochy as $p ) :
                $pid  = (int) $p->id;
                $sloty = $sloty_podla[ $pid ] ?? array();
            ?>
                <tr>
                    <td><strong><?php echo (int) $p->cislo; ?></strong></td>
                    <td><input type="text" name="nazov[<?php echo $pid; ?>]" value="<?php echo esc_attr( $p->nazov ); ?>" style="width:100%;"></td>
                    <td style="text-align:center;"><input type="checkbox" name="top10[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top10 ) ); ?>></td>
                    <td style="text-align:center;"><input type="checkbox" name="top15[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top15 ) ); ?>></td>
                    <td style="text-align:center;"><input type="checkbox" name="top20[<?php echo $pid; ?>]" value="1" <?php checked( ! empty( $p->top20 ) ); ?>></td>
                    <td>
                        <div class="kass-plocha-sloty" data-plocha="<?php echo $pid; ?>">
                            <div class="kass-plocha-chips">
                                <?php if ( empty( $sloty ) ) : ?>
                                    <span class="kass-plocha-empty-hint">Žiadne sloty — plocha sa v záložke „Plochy" zobrazí ako nenastavená.</span>
                                <?php else : foreach ( $sloty as $f ) : ?>
                                    <span class="kass-plocha-chip" data-format="<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $f ); ?> <button type="button" class="kass-plocha-chip-del">✕</button></span>
                                <?php endforeach; endif; ?>
                            </div>
                            <select class="kass-plocha-add-sel">
                                <?php foreach ( $formaty as $f ) : ?>
                                    <option value="<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $f ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button kass-plocha-add-btn">+ Pridať slot</button>
                            <input type="hidden" name="sloty[<?php echo $pid; ?>]" class="kass-plocha-sloty-input" value="<?php echo esc_attr( implode( ',', $sloty ) ); ?>">
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit"><button type="submit" class="button button-primary">Uložiť plochy</button></p>
    </form>
</div>

<script>
(function () {
    function syncInput(wrap) {
        var chips = wrap.querySelectorAll('.kass-plocha-chip');
        var formaty = [];
        chips.forEach(function (c) { formaty.push(c.getAttribute('data-format')); });
        wrap.querySelector('.kass-plocha-sloty-input').value = formaty.join(',');
        var hint = wrap.querySelector('.kass-plocha-empty-hint');
        if (hint) { hint.style.display = formaty.length ? 'none' : ''; }
    }
    function addChip(wrap, format) {
        var chip = document.createElement('span');
        chip.className = 'kass-plocha-chip';
        chip.setAttribute('data-format', format);
        chip.innerHTML = format + ' <button type="button" class="kass-plocha-chip-del">✕</button>';
        wrap.querySelector('.kass-plocha-chips').appendChild(chip);
        syncInput(wrap);
    }
    document.querySelectorAll('.kass-plocha-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.kass-plocha-sloty');
            var sel  = wrap.querySelector('.kass-plocha-add-sel');
            addChip(wrap, sel.value);
        });
    });
    document.querySelectorAll('.kass-plocha-sloty').forEach(function (wrap) {
        wrap.addEventListener('click', function (e) {
            if (e.target.classList.contains('kass-plocha-chip-del')) {
                e.target.closest('.kass-plocha-chip').remove();
                syncInput(wrap);
            }
        });
    });
})();
</script>
