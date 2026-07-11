<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Zvolený utorok (predvolene najbližší)
$zvoleny = isset( $_GET['tyzden'] ) ? sanitize_text_field( $_GET['tyzden'] ) : date( 'Y-m-d' );
$utorok  = KASS_Vylep_DB::najblizsi_utorok( $zvoleny );

// Aktívne plagáty v daný utorok
$vylepy = KASS_Vylep_DB::get_vylepy( array( 'active_on' => $utorok ) );

// Navigácia po týždňoch
$prev = ( new DateTime( $utorok ) )->modify( '-7 days' )->format( 'Y-m-d' );
$next = ( new DateTime( $utorok ) )->modify( '+7 days' )->format( 'Y-m-d' );

$dt_utorok = new DateTime( $utorok );
?>
<div class="wrap kass-wrap">
    <h1>Čo neprelepiť — týždeň od utorka <?php echo esc_html( $dt_utorok->format( 'j.n.Y' ) ); ?></h1>
    <p class="kass-lead">Plagáty, ktoré sú v tomto týždni ešte aktívne a <strong>nesmú sa prelepiť</strong>. Výlep prebieha v utorok.</p>

    <div class="kass-week-nav">
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kass-vylep&tyzden=' . $prev ) ); ?>">← Predošlý utorok</a>
        <form method="get" style="display:inline-block;margin:0 10px;">
            <input type="hidden" name="page" value="kass-vylep">
            <input type="date" name="tyzden" value="<?php echo esc_attr( $utorok ); ?>" onchange="this.form.submit()">
        </form>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kass-vylep&tyzden=' . $next ) ); ?>">Ďalší utorok →</a>
        <a class="button button-primary" style="margin-left:14px;" href="#" onclick="window.print();return false;">🖨 Tlačiť zoznam</a>
    </div>

    <?php if ( empty( $vylepy ) ) : ?>
        <div class="notice notice-info"><p>V tomto týždni nie sú žiadne aktívne plagáty.</p></div>
    <?php else : ?>
    <table class="widefat striped kass-table kass-print">
        <thead>
            <tr>
                <th>Formát</th>
                <th>Organizácia</th>
                <th>Názov akcie</th>
                <th>Od (utorok)</th>
                <th>Do (utorok)</th>
                <th>Zostáva týždňov</th>
                <th>Kusov</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $vylepy as $v ) :
            $do = new DateTime( $v->datum_do );
            $zostava = (int) ceil( ( $do->getTimestamp() - $dt_utorok->getTimestamp() ) / ( 7 * 86400 ) );
        ?>
            <tr>
                <td><strong><?php echo esc_html( $v->format ); ?></strong></td>
                <td><?php echo esc_html( $v->organizacia_nazov ); ?></td>
                <td><?php echo esc_html( $v->nazov_akcie ); ?></td>
                <td><?php echo esc_html( KASS_Vylep_Admin::datum_sk( $v->datum_od ) ); ?></td>
                <td><?php echo esc_html( KASS_Vylep_Admin::datum_sk( $v->datum_do ) ); ?></td>
                <td><?php echo esc_html( $zostava ); ?> T</td>
                <td><?php echo esc_html( $v->kusy ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="kass-sumary">Spolu aktívnych plagátov: <strong><?php echo count( $vylepy ); ?></strong></p>
    <?php endif; ?>
</div>
