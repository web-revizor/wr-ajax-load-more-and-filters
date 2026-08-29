<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end script enqueue + the loadmore/filter AJAX endpoint.
 */
class WRALM_Load_More
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_loadmore', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_loadmore', [$this, 'handle_ajax']);
    }

    public function enqueue_scripts()
    {
        global $wp_query;

        wp_enqueue_script('jquery');

        wp_register_script(
            'my_loadmore',
            WRALM_URL . 'dist/js/load_more_and_filter.js',
            ['jquery'],
            WRALM_VERSION,
            true
        );

        wp_localize_script('my_loadmore', 'loadmore_params', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'current_page' => get_query_var('paged') ? get_query_var('paged') : 1,
            'max_page' => $wp_query->max_num_pages,
            'nonce' => wp_create_nonce('wralm_loadmore'),
        ));

        wp_enqueue_script('my_loadmore');
    }

    public function handle_ajax()
    {
        $this->maybe_check_nonce(); // soft nonce check (hard via wralm_require_nonce filter)

        $config = WRALM_Query_Config::from_request(wp_unslash($_POST));

        $query = new WP_Query($config->wp_query_args());

        $is_product = class_exists('WRALM_Woo') && WRALM_Woo::is_product_query($config->post_type);

        ob_start();
        if ($query->have_posts()) {
            if ($is_product) {
                WRALM_Woo::setup_loop($query);
            }
            while ($query->have_posts()) {
                $query->the_post();
                get_template_part('all_posts_ajax/' . $config->post_type . '-card');
            }
            if ($is_product) {
                WRALM_Woo::reset_loop();
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        list($base, $format) = WRALM_Pagination::resolve_base(
            $config->update_url,
            $config->archive_context,
            $config->base_url
        );

        // preserve non-pagination query args (?filter_search=, custom params) on links
        $add_args = array();
        $parsed = wp_parse_url($config->base_url);
        if (!empty($parsed['query'])) {
            wp_parse_str($parsed['query'], $add_args);
            unset($add_args['paged'], $add_args['page']);
            $add_args = urlencode_deep($add_args);
        }

        $pagination = WRALM_Pagination::links(
            $config->pagination_args($base, $format, $add_args) + array('total' => $query->max_num_pages)
        );

        $pag_base = isset($GLOBALS['wp_rewrite']->pagination_base) ? $GLOBALS['wp_rewrite']->pagination_base : 'page';

        wp_send_json(array(
            'html' => $html,
            'pagination' => $pagination,
            'max_page' => $query->max_num_pages,
            'base_url' => $config->update_url
                ? preg_replace('#/(?:page|' . preg_quote($pag_base, '#') . ')/\d+/?$#', '/', $config->base_url)
                : $config->base_url,
        ));
    }

    private function maybe_check_nonce()
    {
        $nonce = isset($_POST['nonce']) && is_string($_POST['nonce'])
            ? sanitize_text_field(wp_unslash($_POST['nonce']))
            : '';
        $valid = $nonce && wp_verify_nonce($nonce, 'wralm_loadmore');

        if (!$valid && apply_filters('wralm_require_nonce', false)) {
            wp_send_json_error(array('message' => 'bad nonce'), 403);
        }
    }
}
