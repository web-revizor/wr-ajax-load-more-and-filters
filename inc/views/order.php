<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var WRALM_Filter_Config $config — passed in scope by WRALM_Shortcode::render_filters(). */
if ( ! ( isset( $config ) && $config instanceof WRALM_Filter_Config ) ) {
    return;
}
?>
<div>
    <select id="js-post-order-<?= esc_attr( $config->filter_id ) ?>"
            class="js-post-order"
            data-role="order"
            data-filter-id="<?= esc_attr( $config->filter_id ) ?>">
        <option value="DESC">
            <?= esc_html($config->label_newest_order); ?>
        </option>
        <option value="ASC">
            <?= esc_html($config->label_old_order) ?>
        </option>
    </select>
</div>

<?php
// Parse options and labels the SAME way so their indices stay parallel; a blank
// option slot is skipped in the loop (its label slot is skipped with it).
$orderby_opts   = array_values( array_map( 'trim', explode( ',', (string) $config->orderby_options ) ) );
$orderby_labels = array_values( array_map( 'trim', explode( ',', (string) $config->orderby_labels ) ) );
?>
<?php if ( array_filter( $orderby_opts ) ) : ?>
    <div>
        <select class="js-post-orderby"
                data-role="orderby"
                data-filter-id="<?= esc_attr( $config->filter_id ) ?>">
            <?php foreach ( $orderby_opts as $i => $key ) : ?>
                <?php if ( $key === '' ) { continue; } ?>
                <option value="<?= esc_attr( $key ) ?>"><?= esc_html( isset( $orderby_labels[ $i ] ) && $orderby_labels[ $i ] !== '' ? $orderby_labels[ $i ] : ucfirst( str_replace( '_', ' ', $key ) ) ) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
