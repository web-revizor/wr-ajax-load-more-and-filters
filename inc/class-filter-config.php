<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single source of truth for the [all_posts_ajax_filters] panel config.
 * Built from shortcode attributes (from_atts). Replaces the hand-built
 * $load_more_variables global that render_filters() used to assemble.
 * to_legacy_array() reproduces (a superset of) that old shape so the
 * inc/views/filter.php and inc/views/order.php templates keep working.
 */
class WRALM_Filter_Config {

    public $post_type = 'post';
    public $enable_clear_button = false;
    public $filter_by_category = false;
    public $multiply_filter = 'false';
    public $filter_type = 'button';
    public $filter_titles = false;
    public $filter_row_classes = 'filter_row';
    public $filter_item_classes = 'filter_item';
    public $filter_item_limit = 0;
    public $filter_expand_label = '';
    public $filter_expand_class = 'filter_expand';
    public $filter_taxonomy = 'category';
    public $all_category_button = '';
    public $enable_search = false;
    public $label_search_button = '';
    public $search_placeholder = '';
    public $enable_order = false;
    public $label_newest_order = '';
    public $label_old_order = '';
    public $filter_id = '';

    public $orderby_options = '';
    public $orderby_labels = '';

    public static function shortcode_defaults() {
        return array(
            'post_type'           => 'post',
            'enable_clear_button' => false,
            'filter_by_category'  => false,
            'multiply_filter'     => 'false',
            'filter_type'         => 'button',
            'filter_titles'       => false,
            'filter_row_classes'  => 'filter_row',
            'filter_item_classes' => 'filter_item',
            'filter_item_limit'   => 0,
            'filter_expand_label' => __( 'See all', 'wr-ajax-load-more-and-filters' ),
            'filter_expand_class' => 'filter_expand',
            'filter_taxonomy'     => 'category',
            'all_category_button' => __( 'All', 'wr-ajax-load-more-and-filters' ),
            'enable_search'       => false,
            'label_search_button' => __( 'Search', 'wr-ajax-load-more-and-filters' ),
            'search_placeholder'  => __( 'Search', 'wr-ajax-load-more-and-filters' ),
            'enable_order'        => false,
            'label_newest_order'  => __( 'Newest First', 'wr-ajax-load-more-and-filters' ),
            'label_old_order'     => __( 'Old First', 'wr-ajax-load-more-and-filters' ),
            'filter_id'           => '',
            'order_by_options'    => '',
            'order_by_labels'     => '',
        );
    }

    public static function from_atts( $atts ) {
        $a = shortcode_atts( self::shortcode_defaults(), $atts, 'all_posts_ajax_filters' );
        $c = new self();

        $c->post_type           = $a['post_type'];
        $c->enable_clear_button = $a['enable_clear_button'];
        $c->filter_by_category  = $a['filter_by_category'];
        $c->multiply_filter     = $a['multiply_filter'];
        $c->filter_type         = $a['filter_type'];
        $c->filter_titles       = $a['filter_titles'];
        $c->filter_row_classes  = $a['filter_row_classes'];
        $c->filter_item_classes = $a['filter_item_classes'];
        $c->filter_item_limit   = $a['filter_item_limit'];
        $c->filter_expand_label = $a['filter_expand_label'];
        $c->filter_expand_class = $a['filter_expand_class'];
        $c->filter_taxonomy     = $a['filter_taxonomy'];
        $c->all_category_button = $a['all_category_button'];
        $c->enable_search       = $a['enable_search'];
        $c->label_search_button = $a['label_search_button'];
        $c->search_placeholder  = $a['search_placeholder'];
        $c->enable_order        = $a['enable_order'];
        $c->label_newest_order  = $a['label_newest_order'];
        $c->label_old_order     = $a['label_old_order'];
        $c->orderby_options     = $a['order_by_options'];
        $c->orderby_labels      = $a['order_by_labels'];

        $c->filter_id = $a['filter_id'] !== ''
            ? sanitize_key( $a['filter_id'] )
            : sanitize_key( $a['post_type'] ) . '_filter';

        return $c;
    }

    /**
     * The old $load_more_variables associative shape (18 keys) plus
     * filter_id / enable_order / order_by_options / order_by_labels for later
     * tasks. Harmless extras for the current views.
     */
    public function to_legacy_array() {
        return array(
            'enable_clear_button' => $this->enable_clear_button,
            'filter_by_category'  => $this->filter_by_category,
            'filter_type'         => $this->filter_type,
            'filter_titles'       => $this->filter_titles,
            'multiply_filter'     => $this->multiply_filter,
            'filter_row_classes'  => $this->filter_row_classes,
            'filter_item_classes' => $this->filter_item_classes,
            'filter_item_limit'   => $this->filter_item_limit,
            'filter_expand_label' => $this->filter_expand_label,
            'filter_expand_class' => $this->filter_expand_class,
            'filter_taxonomy'     => $this->filter_taxonomy,
            'enable_search'       => $this->enable_search,
            'all_category_button' => $this->all_category_button,
            'label_search_button' => $this->label_search_button,
            'search_placeholder'  => $this->search_placeholder,
            'label_newest_order'  => $this->label_newest_order,
            'label_old_order'     => $this->label_old_order,
            'post_type'           => $this->post_type,
            'filter_id'           => $this->filter_id,
            'enable_order'        => $this->enable_order,
            'order_by_options'    => $this->orderby_options,
            'order_by_labels'     => $this->orderby_labels,
        );
    }

    /**
     * Count of published posts of $post_type that the [all_posts_ajax] list
     * would actually show: excludes "Hide from list" meta and, for products,
     * WooCommerce catalog-invisible / (optionally) out-of-stock items.
     * Cached in a 5-minute transient; the TTL is the staleness bound (no
     * explicit invalidation).
     */
    public static function visible_count( $post_type ) {
        $post_type = sanitize_key( $post_type );
        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            return 0;
        }

        $cache_key = 'wralm_vcount_' . $post_type;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        $args = array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => array(
                'relation' => 'OR',
                array( 'key' => 'all_posts_ajax_hide', 'value' => '1', 'compare' => '!=' ),
                array( 'key' => 'all_posts_ajax_hide', 'compare' => 'NOT EXISTS' ),
            ),
        );

        if ( class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $post_type ) ) {
            $vis = WRALM_Woo::visibility_tax_query();
            if ( $vis ) {
                $args['tax_query'] = array( $vis );
            }
        }

        $q     = new WP_Query( $args );
        $count = (int) $q->found_posts;

        set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );

        return $count;
    }
}
