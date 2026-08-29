<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var WRALM_Filter_Config $config — passed in scope by WRALM_Shortcode::render_filters(). */
if ( ! ( isset( $config ) && $config instanceof WRALM_Filter_Config ) ) {
    return;
}

if ( ! function_exists( 'wralm_term_tree' ) ) {
    /**
     * Every term of $taxonomy in ONE get_terms() call, grouped as
     * [ parent_term_id => WP_Term[] ] (root terms under key 0). Replaces the
     * old per-term get_terms( parent => X ) calls inside the render recursion.
     *
     * @return array<int,array>
     */
    function wralm_term_tree( $taxonomy ) {
        $terms     = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        $by_parent = array();
        if ( is_array( $terms ) ) {
            foreach ( $terms as $t ) {
                $by_parent[ (int) $t->parent ][] = $t;
            }
        }
        return $by_parent;
    }
}

if ( ! function_exists( 'wralm_render_filter_terms' ) ) {
    /**
     * Recursively render filter buttons for a term tree (button mode).
     * Increments $printed by the number of buttons emitted (for the item-limit toggle).
     *
     * @param array               $terms     List of WP_Term objects (callers must pass a real array).
     * @param string              $taxonomy  Taxonomy slug.
     * @param array               $by_parent [ parent_term_id => WP_Term[] ] — whole tree, fetched once.
     * @param WRALM_Filter_Config  $config    Panel config.
     * @param int                 $depth     Current nesting depth (0 = root).
     * @param int                 $printed   Running count of buttons emitted, passed by reference.
     * @param int                 $limit     filter_item_limit (0 = unlimited).
     * @param array               $counts    [ term_id => visible post count ] from
     *                                       WRALM_Filter_Config::term_visible_counts(), or an
     *                                       empty array when counts are disabled. When non-empty
     *                                       a 0-count term is skipped and a count <span> is
     *                                       emitted; when empty neither happens.
     */
    function wralm_render_filter_terms( array $terms, $taxonomy, array $by_parent, WRALM_Filter_Config $config, $depth, &$printed, $limit, array $counts = array() ) {
        $show_count = ! empty( $counts );
        $extra      = $config->filter_item_classes !== '' ? ' ' . $config->filter_item_classes : '';

        foreach ( $terms as $term ) {
            $children = isset( $by_parent[ $term->term_id ] ) ? $by_parent[ $term->term_id ] : array();

            $count = array_key_exists( $term->term_id, $counts )
                ? (int) $counts[ $term->term_id ]
                : (int) $term->count;

            // Skip a term that would lead to an empty result set: with counts on
            // that is any 0-count term; with counts off, mirror the old
            // hide_empty=true roots — no posts of its own and no children.
            if ( 0 === $count && ( $show_count || ! $children ) ) {
                continue;
            }

            $hidden      = ( $limit > 0 && $printed >= $limit ) ? ' is-hidden' : '';
            $depth_class = $depth === 0
                ? ( $children ? ' wr-filters__item--parent' : '' )
                : ' wr-filters__item--child';

            printf(
                '<button type="submit" class="wr-filters__item%s%s%s" data-multiply="%s" data-taxonomy="%s" data-slug="%s"><span class="wr-filters__item-label">%s</span>%s</button>',
                esc_attr( $extra ),
                esc_attr( $depth_class ),
                esc_attr( $hidden ),
                esc_attr( $config->multiply_filter ),
                esc_attr( $taxonomy ),
                esc_attr( $term->slug ),
                esc_html( $term->name ),
                $show_count ? '<span class="wr-filters__item-count">' . (int) $count . '</span>' : ''
            );
            $printed++;

            if ( $children ) {
                wralm_render_filter_terms( $children, $taxonomy, $by_parent, $config, $depth + 1, $printed, $limit, $counts );
            }
        }
    }
}

if ( ! function_exists( 'wralm_render_filter_options' ) ) {
    /**
     * Recursively render <option> elements for a term tree (select mode).
     *
     * @param array  $terms     List of WP_Term objects (callers must pass a real array).
     * @param string $taxonomy  Taxonomy slug.
     * @param array  $by_parent [ parent_term_id => WP_Term[] ] — term tree fetched once.
     * @param int    $depth     Current nesting depth (0 = root).
     */
    function wralm_render_filter_options( array $terms, $taxonomy, array $by_parent, $depth ) {
        foreach ( $terms as $term ) {
            $children = isset( $by_parent[ $term->term_id ] ) ? $by_parent[ $term->term_id ] : array();

            // Old code fetched roots with hide_empty=true; keep an empty,
            // childless term out of the dropdown.
            if ( 0 === (int) $term->count && ! $children ) {
                continue;
            }

            $indent = str_repeat( '&nbsp;&nbsp;', (int) $depth );
            printf(
                '<option value="%s">%s%s</option>',
                esc_attr( $term->slug ),
                $indent,
                esc_html( $term->name )
            );

            if ( $children ) {
                wralm_render_filter_options( $children, $taxonomy, $by_parent, $depth + 1 );
            }
        }
    }
}

$list_extra   = $config->filter_row_classes !== '' ? ' ' . $config->filter_row_classes : '';
$item_extra   = $config->filter_item_classes !== '' ? ' ' . $config->filter_item_classes : '';
$expand_extra = $config->filter_expand_class !== '' ? ' ' . $config->filter_expand_class : '';
?>
<form class="wr-filters__form" role="search" data-filter-id="<?= esc_attr( $config->filter_id ) ?>">
    <?php if ($config->enable_search === 'true'): ?>
        <div class="wr-filters__search">
            <input class="wr-filters__search-input"
                   type="search"
                   placeholder="<?= esc_attr($config->search_placeholder); ?>"
                   data-role="search">
            <button type="submit" class="wr-filters__search-submit">
                <?= esc_html($config->label_search_button); ?>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($config->filter_by_category === 'true' || $config->enable_clear_button === 'true'): ?>
        <?php
        $limit             = (int) $config->filter_item_limit;
        $printed           = 0;
        $show_filter_count = ( $config->show_filter_count !== 'false' );
        ?>
        <div class="wr-filters__list<?= esc_attr( $list_extra ) ?>">
            <?php $categoriesArray = explode(',', $config->filter_taxonomy) ?>
            <?php if ($config->filter_by_category === 'true'): ?>
            <?php if ($config->filter_type === 'button'): ?>
                <button type="submit"
                        class="wr-filters__item wr-filters__item--all is-active<?= esc_attr( $item_extra ) ?>"
                        data-multiply="<?= esc_attr($config->multiply_filter) ?>">
                    <span class="wr-filters__item-label"><?= esc_html($config->all_category_button); ?></span>
                    <?php if ( $show_filter_count ): ?>
                    <span class="wr-filters__item-count"><?= (int) WRALM_Filter_Config::visible_count( $config->post_type ) ?></span>
                    <?php endif; ?>
                </button>
                <?php foreach ($categoriesArray as $taxonomy) : ?>
                    <?php
                    $taxonomy  = trim( $taxonomy );
                    $name      = get_taxonomy( $taxonomy );
                    $by_parent = wralm_term_tree( $taxonomy );
                    $roots     = isset( $by_parent[0] ) ? $by_parent[0] : array();
                    $term_counts = $show_filter_count
                        ? WRALM_Filter_Config::term_visible_counts( $config->post_type, $taxonomy )
                        : array();
                    ?>
                    <?php if ($roots && $config->filter_titles === 'true' && $name): ?>
                        <p class="wr-filters__heading"><?= esc_html($name->label) ?></p>
                    <?php endif; ?>
                    <?php wralm_render_filter_terms( $roots, $taxonomy, $by_parent, $config, 0, $printed, $limit, $term_counts ); ?>
                <?php endforeach; ?>
            <?php elseif ($config->filter_type === 'select'): ?>
                <?php foreach ($categoriesArray as $taxonomy) : ?>
                    <?php
                    $taxonomy  = trim( $taxonomy );
                    $name      = get_taxonomy( $taxonomy );
                    $label     = $name ? $name->label : '';
                    $by_parent = wralm_term_tree( $taxonomy );
                    $roots     = isset( $by_parent[0] ) ? $by_parent[0] : array();
                    ?>
                    <div class="wr-filters__select">
                        <select <?= $config->multiply_filter == 'true' ? 'multiple' : '' ?>
                                class="wr-filters__select-control"
                                data-taxonomy="<?= esc_attr($taxonomy) ?>">
                            <option value=""><?= esc_html( $label ) ?></option>
                            <?php wralm_render_filter_options( $roots, $taxonomy, $by_parent, 0 ); ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; /* filter_by_category */ ?>
            <?php if ($config->enable_clear_button === 'true'): ?>
                <button type="submit" class="wr-filters__clear">
                    <?php esc_html_e('Clear Filters', 'wr-ajax-load-more-and-filters'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if ( $limit > 0 && $printed > $limit ) : ?>
            <div class="wr-filters__expand<?= esc_attr( $expand_extra ) ?>">
                <span><?= esc_html($config->filter_expand_label) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</form>
