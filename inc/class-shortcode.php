<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers [all_posts_ajax] and [all_posts_ajax_filters].
 */
class WRALM_Shortcode
{
    public function __construct()
    {
        add_shortcode('all_posts_ajax', [$this, 'render_posts']);
        add_shortcode('all_posts_ajax_filters', [$this, 'render_filters']);
    }

    public function render_posts($atts)
    {
        $config = WRALM_Query_Config::from_atts($atts);

        $wp_query = new WP_Query($config->wp_query_args());

        list($base, $format) = WRALM_Pagination::resolve_base(
            $config->sync_pagination_url,
            $config->archive_context,
            get_pagenum_link()
        );

        $is_product = class_exists('WRALM_Woo') && WRALM_Woo::is_product_query($config->post_type);

        $posts_result = '';
        $pagination = '';

        if ($wp_query->have_posts()) {
            if ($is_product) {
                WRALM_Woo::setup_loop($wp_query);
            }
            ob_start();
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                get_template_part('all_posts_ajax/' . $config->post_type . '-card');
            }
            $posts_result = ob_get_clean();
            if ($is_product) {
                WRALM_Woo::reset_loop();
            }

            if ($wp_query->max_num_pages > 1) {
                $pagination = WRALM_Pagination::links(
                    $config->pagination_args($base, $format) + array('total' => $wp_query->max_num_pages)
                );
            }
            wp_reset_postdata();
        }

        $results = '<div class="ajax_row_holder" data-init-page="' . esc_attr($config->paged) . '"';
        $results .= ' data-filter-id="' . esc_attr($config->filter_id) . '"';
        // The filter state the server actually rendered (from the URL, via
        // WRALM_Query_Config::from_atts). The public script compares this with
        // the address bar and skips the initial re-fetch when they already match.
        $init_filters = $config->sync_filters_url ? $config->filter_query_args() : array();
        if ($init_filters) {
            $results .= ' data-init-filters="' . esc_attr(wp_json_encode($init_filters)) . '"';
        }
        $results .= '>';
        $results .= '<div class="ajax_row ' . esc_attr($config->row_classes) . '"'
            . $config->render_data_attr_string() . '>';
        $results .= $posts_result;
        $results .= '</div>';
        $results .= $pagination;
        $results .= '</div>';

        return $results;
    }

    public function render_filters($atts)
    {
        // $config is read directly by inc/views/filter.php and inc/views/order.php
        // (both required into this scope below).
        $config = WRALM_Filter_Config::from_atts($atts);

        $results = '<div class="ajax_filters_wrapper" data-filter-id="' . esc_attr($config->filter_id) . '">';

        $need_filter_view = in_array('true', array(
            (string) $config->filter_by_category,
            (string) $config->enable_search,
            (string) $config->enable_clear_button,
        ), true);

        if ($need_filter_view) {
            ob_start();
            require WRALM_PATH . 'inc/views/filter.php';
            $results .= ob_get_clean();
        }

        if ('true' === (string) $config->enable_order) {
            ob_start();
            require WRALM_PATH . 'inc/views/order.php';
            $results .= ob_get_clean();
        }

        $results .= '</div>';

        return $results;
    }
}
