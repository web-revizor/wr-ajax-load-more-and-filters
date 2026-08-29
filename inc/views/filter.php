<?php
if (!defined('ABSPATH')) {
    exit;
}

global $load_more_variables;
$load_more_variables = isset( $config ) && $config instanceof WRALM_Filter_Config
    ? $config->to_legacy_array()
    : $load_more_variables;
?>
<form id="all_posts_filter"
      class="all_posts_form"
      role="search">
    <?php if ($load_more_variables['enable_search'] === 'true'): ?>
        <div class="all-post-search-holder">
            <input class="all-post-search"
                   type="search"
                   id="all-post-search"
                   placeholder="<?= esc_attr($load_more_variables['search_placeholder']); ?>">
            <button type="submit"
                    class="all-post-submit">
                <?= esc_html($load_more_variables['label_search_button']); ?>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($load_more_variables['filter_by_category'] === 'true' || $load_more_variables['enable_clear_button'] === 'true'): ?>
        <?php
        $filter_item_limit = $load_more_variables['filter_item_limit'];
        $filter_expand_label = $load_more_variables['filter_expand_label'];
        $filter_expand_class = $load_more_variables['filter_expand_class'];
        $categoriesCount = 0;
        $index = 0;
        $filter_item_limit = (int)($load_more_variables['filter_item_limit'] ?? 0);
        $term = get_queried_object();
        ?>
        <div class="<?= esc_attr($load_more_variables['filter_row_classes']) ?>">
            <?php $categoriesArray = explode(',', $load_more_variables['filter_taxonomy']) ?>
            <?php if ($load_more_variables['filter_type'] === 'button'): ?>
                <button type="submit"
                        class="js-category-filter allCategories active multiply-<?= esc_attr($load_more_variables['multiply_filter']) ?> <?= esc_attr($load_more_variables['filter_item_classes']); ?>">
                    <span class="text"><?= esc_html($load_more_variables['all_category_button']); ?></span>
                    <span class="postCount"><?= (int)wp_count_posts($load_more_variables['post_type'])->publish; ?></span>
                </button>
                <?php foreach ($categoriesArray as $categories) : ?>
                    <?php
                    $taxonomy = $categories;
                    $name = get_taxonomy($taxonomy);
                    $categories = get_terms($taxonomy, array(
                        'parent' => 0,
                        'exclude' => $term->term_id,
                    ));

                    if ($filter_item_limit > 0) {
                        $categoriesCount = $categoriesCount + count($categories);
                    }
                    ?>
                    <?php if ($categories && $load_more_variables['filter_titles'] === 'true'): ?>
                        <p class="filterHeading"><?= esc_html($name->label) ?></p>
                    <?php endif; ?>
                    <?php foreach ($categories as $category) : ?>

                        <?php
                        $childrensCat = get_terms($category->taxonomy, array(
                            'parent' => $category->term_id,
                            'hide_empty' => false,
                        ));
                        if ($filter_item_limit > 0) {
                            $categoriesCount = $categoriesCount + count($childrensCat);
                        }
                        ?>
                        <button type="submit"
                                class="js-category-filter multiply-<?= esc_attr($load_more_variables['multiply_filter']) ?> <?= esc_attr($load_more_variables['filter_item_classes']); ?><?= ($childrensCat) ? ' parentCategory' : '' ?><?= $filter_item_limit <= 0 || $index < $filter_item_limit ? '' : ' hidden' ?>"
                                data-taxonomy="<?= esc_attr($taxonomy) ?>"
                                data-slug="<?= esc_attr($category->slug); ?>">
                            <span class="text"><?= esc_html($category->name); ?></span>
                            <span class="postCount"><?= (int)$category->count; ?></span>
                        </button>
                        <?php $index++; ?>
                        <?php foreach ($childrensCat as $childrenCat) : ?>
                            <button type="submit"
                                    class="js-category-filter multiply-<?= esc_attr($load_more_variables['multiply_filter']) ?> childCategory <?= esc_attr($load_more_variables['filter_item_classes']); ?>"
                                    data-slug="<?= esc_attr($childrenCat->slug); ?>">
                                <span class="text"><?= esc_html($childrenCat->name); ?></span>
                                <span class="postCount"><?= (int)$childrenCat->count; ?></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php elseif ($load_more_variables['filter_type'] === 'select'): ?>
                <?php foreach ($categoriesArray as $categories) : ?>
                    <?php
                    $taxonomy = $categories;
                    $name = get_taxonomy($taxonomy);
                    $label = $name->label;
                    $categories = get_terms($taxonomy, array(
                        'parent' => 0,
                    )); ?>

                    <div class="category-filter-select-holder">
                        <select <?= $load_more_variables['multiply_filter'] == 'true' ? 'multiple' : '' ?>
                                class="js-category-filter-select <?= esc_attr($load_more_variables['filter_item_classes']); ?>"
                                data-taxonomy="<?= esc_attr($taxonomy) ?>">
                            <option <?= $load_more_variables['filter_item_classes'] == 'true' ? 'disabled' : 'selected' ?>
                                    value="">
                                <?= esc_html($label) ?>
                            </option>

                            <?php foreach ($categories as $category) : ?>
                                <?php
                                $childrensCat = get_terms($category->taxonomy, array(
                                    'parent' => $category->term_id,
                                    'hide_empty' => false,
                                ));
                                ?>
                                <option value="<?= esc_attr($category->slug); ?>">
                                    <?= esc_html($category->name); ?>
                                </option>
                                <?php foreach ($childrensCat as $childrenCat) : ?>
                                    <option value="<?= esc_attr($childrenCat->slug); ?>">
                                        <?= esc_html($childrenCat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($load_more_variables['enable_clear_button'] === 'true'): ?>
                <button type="submit"
                        class="js-clear-filter">
                    <?php esc_html_e('Clear Filters', 'wr-ajax-load-more-and-filters'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if ($filter_item_limit > 0 && $categoriesCount > $filter_item_limit) : ?>
            <div class="<?= esc_attr($filter_expand_class) ?>">
                <span><?= esc_html($filter_expand_label) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</form>
