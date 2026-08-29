<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single source of truth for the [all_posts_ajax] list query.
 * Built either from shortcode attributes (from_atts) or from the AJAX
 * request (from_request, added in Task 1.2). Produces the data-* set,
 * the WP_Query args, and the pagination args.
 */
class WRALM_Query_Config {

    public $post_type = 'post';
    public $posts_per_page = 10;
    public $paged = 1;
    public $pagination_type = 'default';
    public $row_classes = 'posts_row';
    public $load_more_label = '';
    public $load_more_classes = 'load_more_button';
    public $prev_text = '';
    public $next_text = '';
    public $filter_id = '';
    public $update_url = true;
    public $archive_context = false;
    public $tax_filters = array();
    public $archive_term_id = 0;
    public $archive_taxonomy = '';
    public $search = '';
    public $orderby = 'date';
    public $order = 'DESC';
    public $base_url = '';

    const ORDERBY_WHITELIST = array(
        'date', 'title', 'menu_order', 'rand', 'modified', 'comment_count',
        'price', 'popularity', 'rating',
    );

    public static function shortcode_defaults() {
        return array(
            'post_type'         => 'post',
            'posts_per_page'    => '10',
            'type_pagination'   => 'default',
            'row_classes'       => 'posts_row',
            'load_more_label'   => __( 'Show more', 'wr-ajax-load-more-and-filters' ),
            'load_more_classes' => 'load_more_button',
            'prev_text'         => __( 'Previous', 'wr-ajax-load-more-and-filters' ),
            'next_text'         => __( 'Next', 'wr-ajax-load-more-and-filters' ),
            'filter_id'         => '',
            'update_url'        => 'true',
            'orderby'           => 'date',
        );
    }

    public static function from_atts( $atts ) {
        $a = shortcode_atts( self::shortcode_defaults(), $atts, 'all_posts_ajax' );
        $c = new self();

        $c->post_type         = sanitize_key( $a['post_type'] );
        $c->posts_per_page    = (int) $a['posts_per_page'];
        $c->pagination_type   = sanitize_key( $a['type_pagination'] );
        $c->row_classes       = sanitize_text_field( $a['row_classes'] );
        $c->load_more_label   = sanitize_text_field( $a['load_more_label'] );
        $c->load_more_classes = sanitize_text_field( $a['load_more_classes'] );
        $c->prev_text         = sanitize_text_field( $a['prev_text'] );
        $c->next_text         = sanitize_text_field( $a['next_text'] );
        $c->update_url        = ( 'false' !== strtolower( (string) $a['update_url'] ) );
        $c->orderby           = self::sanitize_orderby( $a['orderby'] );

        $c->filter_id = $a['filter_id'] !== ''
            ? sanitize_key( $a['filter_id'] )
            : $c->post_type . '_filter';

        // Page context: real /page/N/ URLs only make sense on archive-ish pages.
        $c->archive_context = ( is_archive() || is_home() || is_front_page()
            || is_post_type_archive() || is_category() || is_tag() || is_tax() );

        $c->paged = self::current_paged();

        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            $c->archive_term_id  = (int) $term->term_id;
            $c->archive_taxonomy = (string) $term->taxonomy;
        }

        return $c;
    }

    public static function sanitize_orderby( $value ) {
        $value = sanitize_key( $value );
        return in_array( $value, self::ORDERBY_WHITELIST, true ) ? $value : 'date';
    }

    /** Front page uses ?page, everything else ?paged. */
    public static function current_paged() {
        $paged = (int) get_query_var( 'paged' );
        if ( ! $paged ) {
            $paged = (int) get_query_var( 'page' );
        }
        return max( 1, $paged );
    }

    public function data_attrs() {
        return array(
            'data-filter-id'        => $this->filter_id,
            'data-pagination-type'  => $this->pagination_type,
            'data-posts-per-page'   => (string) $this->posts_per_page,
            'data-posts-type'       => $this->post_type,
            'data-more-classes'     => $this->load_more_classes,
            'data-more-label'       => $this->load_more_label,
            'data-prev-text'        => $this->prev_text,
            'data-next-text'        => $this->next_text,
            'data-cat-id'           => (string) $this->archive_term_id,
            'data-cat-taxonomy'     => $this->archive_taxonomy,
            'data-orderby'          => $this->orderby,
            'data-update-url'       => $this->update_url ? 'true' : 'false',
            'data-archive-context'  => $this->archive_context ? 'true' : 'false',
        );
    }

    public function render_data_attr_string() {
        $out = '';
        foreach ( $this->data_attrs() as $k => $v ) {
            $out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
        }
        return $out;
    }

    public static function from_request( array $req ) {
        $c = new self();

        $pt = isset( $req['post_type'] ) && is_string( $req['post_type'] ) ? sanitize_key( $req['post_type'] ) : 'post';
        if ( ! $pt || ! post_type_exists( $pt ) || ! is_post_type_viewable( $pt ) ) {
            $pt = 'post';
        }
        $c->post_type = $pt;

        $c->posts_per_page  = isset( $req['posts_per_page'] ) && is_scalar( $req['posts_per_page'] ) ? (int) $req['posts_per_page'] : 10;
        $c->pagination_type = isset( $req['pagination_type'] ) && is_string( $req['pagination_type'] ) ? sanitize_key( $req['pagination_type'] ) : 'default';
        $c->load_more_classes = isset( $req['more_classes'] ) && is_string( $req['more_classes'] ) ? sanitize_text_field( $req['more_classes'] ) : '';
        $c->load_more_label   = isset( $req['more_label'] ) && is_string( $req['more_label'] ) ? sanitize_text_field( $req['more_label'] ) : '';
        $c->prev_text = isset( $req['prev_text'] ) && is_string( $req['prev_text'] ) ? sanitize_text_field( $req['prev_text'] ) : '';
        $c->next_text = isset( $req['next_text'] ) && is_string( $req['next_text'] ) ? sanitize_text_field( $req['next_text'] ) : '';
        $c->filter_id = isset( $req['filter_id'] ) && is_string( $req['filter_id'] ) ? sanitize_key( $req['filter_id'] ) : '';
        $c->update_url = ! ( isset( $req['update_url'] ) && is_string( $req['update_url'] ) && 'false' === strtolower( $req['update_url'] ) );
        $c->archive_context = isset( $req['archive_context'] ) && is_string( $req['archive_context'] ) && 'true' === strtolower( $req['archive_context'] );

        $c->paged = isset( $req['page'] ) && is_scalar( $req['page'] ) ? max( 1, absint( $req['page'] ) ) : 1;

        $c->base_url = isset( $req['base_url'] ) && is_string( $req['base_url'] ) ? esc_url_raw( $req['base_url'] ) : home_url( '/' );

        $c->archive_term_id  = isset( $req['category_id'] ) && is_scalar( $req['category_id'] ) ? absint( $req['category_id'] ) : 0;
        $c->archive_taxonomy = isset( $req['category_taxonomy'] ) && is_string( $req['category_taxonomy'] ) ? sanitize_key( $req['category_taxonomy'] ) : '';

        if ( ! empty( $req['search'] ) && is_string( $req['search'] ) ) {
            $c->search = sanitize_text_field( $req['search'] );
        }

        $c->order   = ( isset( $req['order'] ) && is_string( $req['order'] ) && 'ASC' === strtoupper( $req['order'] ) ) ? 'ASC' : 'DESC';
        $c->orderby = isset( $req['orderby'] ) && is_string( $req['orderby'] ) ? self::sanitize_orderby( $req['orderby'] ) : 'date';

        if ( ! empty( $req['category'] ) && is_array( $req['category'] ) ) {
            foreach ( $req['category'] as $taxonomy => $slugs ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                    continue;
                }
                $slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $slugs ) ) );
                if ( $slugs ) {
                    $c->tax_filters[ $taxonomy ] = $slugs;
                }
            }
        }

        return $c;
    }

    private function hide_meta_query() {
        return array(
            'relation' => 'OR',
            array( 'key' => 'all_posts_ajax_hide', 'value' => '1', 'compare' => '!=' ),
            array( 'key' => 'all_posts_ajax_hide', 'compare' => 'NOT EXISTS' ),
        );
    }

    /**
     * Merge the archive term scope with the user's taxonomy filters.
     * A user filter on the SAME taxonomy as the archive term replaces the
     * archive scope (the user explicitly chose terms there); filters on other
     * taxonomies are AND-ed on top. Fixes the "archive + same-taxonomy filter
     * = zero results" bug.
     */
    private function build_tax_query() {
        $clauses = array( 'relation' => 'AND' );

        foreach ( $this->tax_filters as $taxonomy => $slugs ) {
            $clauses[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $slugs,
                'operator' => 'IN',
            );
        }

        if ( $this->archive_term_id && $this->archive_taxonomy
            && ! isset( $this->tax_filters[ $this->archive_taxonomy ] ) ) {
            $clauses[] = array(
                'taxonomy' => $this->archive_taxonomy,
                'field'    => 'term_id',
                'terms'    => array( $this->archive_term_id ),
            );
        }

        if ( class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $this->post_type ) ) {
            $vis = WRALM_Woo::visibility_tax_query();
            if ( $vis ) {
                $clauses[] = $vis;
            }
        }

        return count( $clauses ) > 1 ? $clauses : array();
    }

    public function wp_query_args() {
        $args = array(
            'post_type'      => $this->post_type,
            'posts_per_page' => $this->posts_per_page,
            'post_status'    => 'publish',
            'paged'          => $this->paged,
            'meta_query'     => $this->hide_meta_query(),
            'order'          => $this->order,
        );

        $tax_query = $this->build_tax_query();
        if ( $tax_query ) {
            $args['tax_query'] = $tax_query;
        }

        if ( '' !== $this->search ) {
            $args['s'] = $this->search;
            $args['wralm_search'] = 1; // scope flag for WRALM_Search_ACF
        }

        // orderby mapping (WooCommerce-aware keys handled in Phase 9 via WRALM_Woo)
        if ( class_exists( 'WRALM_Woo' ) && WRALM_Woo::is_product_query( $this->post_type ) ) {
            $args = array_merge( $args, WRALM_Woo::orderby_args( $this->orderby, $this->order ) );
        } else {
            $map = array( 'price' => 'date', 'popularity' => 'comment_count', 'rating' => 'date' );
            $orderby = isset( $map[ $this->orderby ] ) ? $map[ $this->orderby ] : $this->orderby;
            $args['orderby'] = $orderby;
        }

        return apply_filters( 'wralm_query_args', $args, $this );
    }

    public function pagination_args( $base, $format, $add_args = array() ) {
        return array(
            'base'              => $base,
            'format'            => $format,
            'current'           => $this->paged,
            'type'              => $this->pagination_type,
            'update_url'        => $this->update_url,
            'add_args'          => $add_args,
            'load_more_classes' => $this->load_more_classes,
            'load_more_label'   => $this->load_more_label !== '' ? $this->load_more_label : __( 'Show more', 'wr-ajax-load-more-and-filters' ),
            'prev_text'         => $this->prev_text !== '' ? $this->prev_text : __( 'Previous', 'wr-ajax-load-more-and-filters' ),
            'next_text'         => $this->next_text !== '' ? $this->next_text : __( 'Next', 'wr-ajax-load-more-and-filters' ),
        );
    }
}
