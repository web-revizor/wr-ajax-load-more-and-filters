<?php
if (!defined('ABSPATH')) {
    exit;
}

global $load_more_variables;
$load_more_variables = isset( $config ) && $config instanceof WRALM_Filter_Config
    ? $config->to_legacy_array()
    : $load_more_variables;

if ( ! function_exists( 'wralm_render_filter_terms' ) ) {
    /**
     * Recursively render filter buttons for a term tree (button mode).
     * Increments $printed by the number of buttons emitted (for the item-limit toggle).
     *
     * @param array  $terms    List of WP_Term objects (callers must pass a real array).
     * @param string $taxonomy Taxonomy slug.
     * @param array  $v        Legacy $load_more_variables array.
     * @param int    $depth    Current nesting depth (0 = root).
     * @param int    $printed  Running count of buttons emitted, passed by reference.
     * @param int    $limit    filter_item_limit (0 = unlimited).
     */
    function wralm_render_filter_terms( array $terms, $taxonomy, array $v, $depth, &$printed, $limit ) {
        foreach ( $terms as $term ) {
            $children = get_terms( array(
                'taxonomy'   => $taxonomy,
                'parent'     => $term->term_id,
                'hide_empty' => false,
            ) );
            if ( ! is_array( $children ) ) {
                $children = array();
            }

            $hidden      = ( $limit > 0 && $printed >= $limit ) ? ' hidden' : '';
            $depth_class = $depth === 0
                ? ( $children ? ' parentCategory' : '' )
                : ' childCategory';

            printf(
                '<button type="submit" class="js-category-filter multiply-%s %s%s%s" data-taxonomy="%s" data-slug="%s"><span class="text">%s</span><span class="postCount">%d</span></button>',
                esc_attr( $v['multiply_filter'] ),
                esc_attr( $v['filter_item_classes'] ),
                esc_attr( $depth_class ),
                esc_attr( $hidden ),
                esc_attr( $taxonomy ),
                esc_attr( $term->slug ),
                esc_html( $term->name ),
                (int) $term->count
            );
            $printed++;

            if ( $children ) {
                wralm_render_filter_terms( $children, $taxonomy, $v, $depth + 1, $printed, $limit );
            }
        }
    }
}

if ( ! function_exists( 'wralm_render_filter_options' ) ) {
    /**
     * Recursively render <option> elements for a term tree (select mode).
     *
     * @param array  $terms    List of WP_Term objects (callers must pass a real array).
     * @param string $taxonomy Taxonomy slug.
     * @param int    $depth    Current nesting depth (0 = root).
     */
    function wralm_render_filter_options( array $terms, $taxonomy, $depth ) {
        foreach ( $terms as $term ) {
            $children = get_terms( array(
                'taxonomy'   => $taxonomy,
                'parent'     => $term->term_id,
                'hide_empty' => false,
            ) );
            if ( ! is_array( $children ) ) {
                $children = array();
            }

            $indent = str_repeat( '&nbsp;&nbsp;', (int) $depth );
            printf(
                '<option value="%s">%s%s</option>',
                esc_attr( $term->slug ),
                $indent,
                esc_html( $term->name )
            );

            if ( $children ) {
                wralm_render_filter_options( $children, $taxonomy, $depth + 1 );
            }
        }
    }
}
?>
<form id="all_posts_filter_<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>"
      class="all_posts_form"
      role="search"
      data-filter-id="<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>">
    <?php if ($load_more_variables['enable_search'] === 'true'): ?>
        <div class="all-post-search-holder">
            <input class="all-post-search"
                   type="search"
                   id="all-post-search-<?= esc_attr( $load_more_variables['filter_id'] ?? '' ) ?>"
                   placeholder="<?= esc_attr($load_more_variables['search_placeholder']); ?>"
                   data-role="search">
            <button type="submit"
                    class="all-post-submit">
                <?= esc_html($load_more_variables['label_search_button']); ?>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($load_more_variables['filter_by_category'] === 'true' || $load_more_variables['enable_clear_button'] === 'true'): ?>
        <?php
        $filter_expand_label = $load_more_variables['filter_expand_label'];
        $filter_expand_class = $load_more_variables['filter_expand_class'];
        $limit               = (int) ( $load_more_variables['filter_item_limit'] ?? 0 );
        $printed             = 0;
        $queried             = get_queried_object();
        $exclude_term_id     = ( $queried instanceof WP_Term ) ? (int) $queried->term_id : 0;
        ?>
        <div class="<?= esc_attr($load_more_variables['filter_row_classes']) ?>">
            <?php $categoriesArray = explode(',', $load_more_variables['filter_taxonomy']) ?>
            <?php if ($load_more_variables['filter_by_category'] === 'true'): ?>
            <?php if ($load_more_variables['filter_type'] === 'button'): ?>
                <button type="submit"
                        class="js-category-filter allCategories active multiply-<?= esc_attr($load_more_variables['multiply_filter']) ?> <?= esc_attr($load_more_variables['filter_item_classes']); ?>">
                    <span class="text"><?= esc_html($load_more_variables['all_category_button']); ?></span>
                    <span class="postCount"><?= (int) ( isset( $config ) && $config instanceof WRALM_Filter_Config ? WRALM_Filter_Config::visible_count( $load_more_variables['post_type'] ) : wp_count_posts( $load_more_variables['post_type'] )->publish ) ?></span>
                </button>
                <?php foreach ($categoriesArray as $taxonomy) : ?>
                    <?php
                    $taxonomy = trim( $taxonomy );
                    $name     = get_taxonomy( $taxonomy );
                    $roots    = get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'parent'     => 0,
                        'exclude'    => $exclude_term_id,
                        'hide_empty' => true,
                    ) );
                    $roots = is_array( $roots ) ? $roots : array();
                    ?>
                    <?php if ($roots && $load_more_variables['filter_titles'] === 'true' && $name): ?>
                        <p class="filterHeading"><?= esc_html($name->label) ?></p>
                    <?php endif; ?>
                    <?php wralm_render_filter_terms( $roots, $taxonomy, $load_more_variables, 0, $printed, $limit ); ?>
                <?php endforeach; ?>
            <?php elseif ($load_more_variables['filter_type'] === 'select'): ?>
                <?php foreach ($categoriesArray as $taxonomy) : ?>
                    <?php
                    $taxonomy = trim( $taxonomy );
                    $name     = get_taxonomy( $taxonomy );
                    $label    = $name ? $name->label : '';
                    $roots    = get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'parent'     => 0,
                        'hide_empty' => true,
                    ) );
                    $roots = is_array( $roots ) ? $roots : array();
                    ?>

                    <div class="category-filter-select-holder">
                        <select <?= $load_more_variables['multiply_filter'] == 'true' ? 'multiple' : '' ?>
                                class="js-category-filter-select <?= esc_attr($load_more_variables['filter_item_classes']); ?>"
                                data-taxonomy="<?= esc_attr($taxonomy) ?>">
                            <option value=""><?= esc_html( $label ) ?></option>
                            <?php wralm_render_filter_options( $roots, $taxonomy, 0 ); ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; /* filter_by_category */ ?>
            <?php if ($load_more_variables['enable_clear_button'] === 'true'): ?>
                <button type="submit"
                        class="js-clear-filter">
                    <?php esc_html_e('Clear Filters', 'wr-ajax-load-more-and-filters'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if ( $limit > 0 && $printed > $limit ) : ?>
            <div class="<?= esc_attr($filter_expand_class) ?>">
                <span><?= esc_html($filter_expand_label) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</form>
