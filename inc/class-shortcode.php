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
            $config->update_url,
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
        $results .= ' data-filter-id="' . esc_attr($config->filter_id) . '">';
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
        global $load_more_variables;

        $default = array(
            'post_type' => 'post',
            'enable_clear_button' => false,
            'filter_by_category' => false,
            'multiply_filter' => 'false',
            'filter_type' => 'button',
            'filter_titles' => false,
            'filter_row_classes' => 'filter_row',
            'filter_item_classes' => 'filter_item',
            'filter_item_limit' => 0,
            'filter_expand_label' => __('See all', 'wr-ajax-load-more-and-filters'),
            'filter_expand_class' => 'filter_expand_class',
            'filter_taxonomy' => 'category',
            'all_category_button' => __('All', 'wr-ajax-load-more-and-filters'),
            'enable_search' => false,
            'label_search_button' => __('Search', 'wr-ajax-load-more-and-filters'),
            'search_placeholder' => __('Search', 'wr-ajax-load-more-and-filters'),
            'enable_order' => false,
            'label_newest_order' => __('Newest First', 'wr-ajax-load-more-and-filters'),
            'label_old_order' => __('Old First', 'wr-ajax-load-more-and-filters'),
            'filter_id' => '',
        );

        $a = shortcode_atts($default, $atts);

        $load_more_variables = array(
            'enable_clear_button' => $a['enable_clear_button'],
            'filter_by_category' => $a['filter_by_category'],
            'filter_type' => $a['filter_type'],
            'filter_titles' => $a['filter_titles'],
            'multiply_filter' => $a['multiply_filter'],
            'filter_row_classes' => $a['filter_row_classes'],
            'filter_item_classes' => $a['filter_item_classes'],
            'filter_item_limit' => $a['filter_item_limit'],
            'filter_expand_label' => $a['filter_expand_label'],
            'filter_expand_class' => $a['filter_expand_class'],
            'filter_taxonomy' => $a['filter_taxonomy'],
            'enable_search' => $a['enable_search'],
            'all_category_button' => $a['all_category_button'],
            'label_search_button' => $a['label_search_button'],
            'search_placeholder' => $a['search_placeholder'],
            'label_newest_order' => $a['label_newest_order'],
            'label_old_order' => $a['label_old_order'],
            'post_type' => $a['post_type'],
        );

        $filter_id = $a['filter_id'];
        $filter_data_attr = $filter_id ? ' data-filter-id="' . esc_attr($filter_id) . '"' : '';

        $results = '<div class="ajax_filters_wrapper"' . $filter_data_attr . '>';

        if ($a['filter_by_category'] === 'true' || $a['enable_search'] === 'true') {
            ob_start();
            require WRALM_PATH . 'inc/views/filter.php';
            $results .= ob_get_clean();
        }

        if ($a['enable_order'] === 'true') {
            ob_start();
            require WRALM_PATH . 'inc/views/order.php';
            $results .= ob_get_clean();
        }

        $results .= '</div>';

        return $results;
    }
}
