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
    <select id="js-post-order"
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
