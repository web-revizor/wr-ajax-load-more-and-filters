<?php
if (!defined('ABSPATH')) {
    exit;
}

global $load_more_variables;
$load_more_variables = isset( $config ) && $config instanceof WRALM_Filter_Config
    ? $config->to_legacy_array()
    : $load_more_variables;
?>
<div>
    <select id="js-post-order-<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>"
            class="js-post-order"
            data-role="order"
            data-filter-id="<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>">
        <option value="DESC">
            <?= esc_html($load_more_variables['label_newest_order']); ?>
        </option>
        <option value="ASC">
            <?= esc_html($load_more_variables['label_old_order']) ?>
        </option>
    </select>
</div>

<?php
$orderby_opts   = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $load_more_variables['order_by_options'] ?? '' ) ) ) ) );
$orderby_labels = array_values( array_map( 'trim', explode( ',', (string) ( $load_more_variables['order_by_labels'] ?? '' ) ) ) );
?>
<?php if ( $orderby_opts ) : ?>
    <div>
        <select class="js-post-orderby"
                data-role="orderby"
                data-filter-id="<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>">
            <?php foreach ( $orderby_opts as $i => $key ) : ?>
                <option value="<?= esc_attr( $key ) ?>"><?= esc_html( isset( $orderby_labels[ $i ] ) && $orderby_labels[ $i ] !== '' ? $orderby_labels[ $i ] : ucfirst( str_replace( '_', ' ', $key ) ) ) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
