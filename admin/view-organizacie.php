<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$edit_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

if ( $action === 'edit' || $action === 'new' ) :
    $o = $edit_id ? KASS_Vylep_DB::get_organizacia( $edit_id ) : null;
    $val = function( $f ) use ( $o ) { return $o && isset( $o->$f ) ? esc_attr( $o->$f ) : ''; };
?>
<div class="wrap kass-wrap">
    <h1><?php echo $edit_id ? 'Upraviť odberateľa' : 'Nový odberateľ'; ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kass-form">
        <?php wp_nonce_field( 'kass_save_org' ); ?>
        <input type="hidden" name="action" value="kass_save_org">
        <input type="hidden" name="id" value="<?php echo (int) $edit_id; ?>">
        <table class="form-table">
            <tr><th><label>Odberateľ <small>(skratka)</small></label></th><td><input type="text" name="odberatel" value="<?php echo $val( 'odberatel' ); ?>" style="width:200px;" placeholder="napr. KaSS, RKC..."></td></tr>
            <tr><th><label>Názov <small>(oficiálny)</small></label></th><td><input type="text" name="nazov" value="<?php echo $val( 'nazov' ); ?>" required style="width:360px;"></td></tr>
            <tr><th><label>Ulica a číslo</label></th><td><input type="text" name="ulica" value="<?php echo $val( 'ulica' ); ?>" style="width:360px;"></td></tr>
            <tr><th><label>PSČ a mesto</label></th><td><input type="text" name="mesto" value="<?php echo $val( 'mesto' ); ?>" style="width:360px;"></td></tr>
            <tr><th><label>IČO</label></th><td><input type="text" name="ico" value="<?php echo $val( 'ico' ); ?>"></td></tr>
            <tr><th><label>DIČ</label></th><td><input type="text" name="dic" value="<?php echo $val( 'dic' ); ?>"></td></tr>
            <tr><th><label>IČ DPH</label></th><td><input type="text" name="ic_dph" value="<?php echo $val( 'ic_dph' ); ?>"></td></tr>
            <tr><th><label>Peňažný ústav</label></th><td><input type="text" name="penaz_ustav" value="<?php echo $val( 'penaz_ustav' ); ?>" style="width:260px;"></td></tr>
            <tr><th><label>IBAN</label></th><td><input type="text" name="iban" value="<?php echo $val( 'iban' ); ?>" style="width:300px;"></td></tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary">Uložiť</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-organizacie' ) ); ?>" class="button">Zrušiť</a>
        </p>
    </form>
</div>

<?php else :
$organizacie = KASS_Vylep_DB::get_organizacie();
?>
<div class="wrap kass-wrap">
    <h1>Odberatelia
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-organizacie&action=new' ) ); ?>" class="page-title-action">Pridať odberateľa</a>
    </h1>
    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo $_GET['msg'] === 'deleted' ? 'Odberateľ zmazaný.' : 'Odberateľ uložený.'; ?></p></div>
    <?php endif; ?>
    <table class="widefat striped kass-table">
        <thead><tr><th>Odberateľ</th><th>Názov</th><th>Adresa</th><th>IČO</th><th>IBAN</th><th></th></tr></thead>
        <tbody>
        <?php if ( empty( $organizacie ) ) : ?>
            <tr><td colspan="6">Zatiaľ žiadni odberatelia.</td></tr>
        <?php else : foreach ( $organizacie as $o ) : ?>
            <tr>
                <td><strong><?php echo esc_html( $o->odberatel ?: $o->nazov ); ?></strong></td>
                <td><?php echo esc_html( $o->nazov ); ?></td>
                <td><?php echo esc_html( trim( $o->ulica . ', ' . $o->mesto, ', ' ) ); ?></td>
                <td><?php echo esc_html( $o->ico ); ?></td>
                <td><?php echo esc_html( $o->iban ); ?></td>
                <td>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kass-organizacie&action=edit&id=' . $o->id ) ); ?>">Upraviť</a> |
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kass_delete_org&id=' . $o->id ), 'kass_delete_org' ) ); ?>" onclick="return confirm('Naozaj zmazať?')" style="color:#b32d2e;">Zmazať</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
