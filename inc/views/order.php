<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var WRALM_Filter_Config $config — passed in scope by WRALM_Shortcode::render_filters(). */
if ( ! ( isset( $config ) && $config instanceof WRALM_Filter_Config ) ) {
    return;
}

$options = WRALM_Filter_Config::parse_sort_options( $config->sort_options );
if ( ! $options ) {
    return;
}
?>
<div class="wr-filters__sort">
    <select class="wr-filters__sort-control"
            data-role="sort"
            data-filter-id="<?= esc_attr( $config->filter_id ) ?>">
        <?php foreach ( $options as $opt ) : ?>
            <option value="<?= esc_attr( $opt['value'] ) ?>"><?= esc_html( $opt['label'] ) ?></option>
        <?php endforeach; ?>
    </select>
</div>
