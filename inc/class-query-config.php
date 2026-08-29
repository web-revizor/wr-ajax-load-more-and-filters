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
}
