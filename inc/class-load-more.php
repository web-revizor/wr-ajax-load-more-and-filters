<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end script enqueue + the list/filter endpoint.
 *
 * The endpoint is a REST GET route (wralm/v1/list) so a reverse proxy / CDN can
 * cache it; the legacy admin-ajax `loadmore` action stays as a thin shim over
 * the same handler for themes that call it directly. Both delegate to
 * build_list_response().
 */
class WRALM_Load_More
{
    const REST_NAMESPACE = 'wralm/v1';
    const REST_ROUTE     = '/list';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest']);
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
            'resturl'      => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE)),
            'ajaxurl'      => admin_url('admin-ajax.php'), // legacy consumers
            'current_page' => get_query_var('paged') ? get_query_var('paged') : 1,
            'max_page'     => $wp_query->max_num_pages,
            'nonce'        => wp_create_nonce('wp_rest'),
        ));

        wp_enqueue_script('my_loadmore');
    }

    public function register_rest()
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_rest'],
            'permission_callback' => [$this, 'rest_permission'],
        ));
    }

    /**
     * Public read endpoint — no nonce gate (an anonymous nonce is not a secret
     * and a list of published posts is not a CSRF target). Abuse is bounded by
     * a light per-IP rate limit instead.
     */
    public function rest_permission()
    {
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $max = (int) apply_filters('wralm_rate_limit', 60);
        if ($max < 1 || '' === $ip) {
            return true;
        }

        $key  = 'wralm_rl_' . md5($ip);
        $hits = (int) get_transient($key);
        if ($hits >= $max) {
            return new WP_Error('wralm_rate_limited', __('Too many requests', 'wr-ajax-load-more-and-filters'), array('status' => 429));
        }
        set_transient($key, $hits + 1, MINUTE_IN_SECONDS);

        return true;
    }

    public function handle_rest(WP_REST_Request $request)
    {
        $response = rest_ensure_response($this->build_list_response($request->get_params()));
        // Safe to cache: output depends only on the query args, and the list
        // changes slowly. Short TTL keeps staleness bounded.
        $response->header('Cache-Control', 'public, max-age=30');
        return $response;
    }

    public function handle_ajax()
    {
        wp_send_json($this->build_list_response(wp_unslash($_POST)));
    }

    /**
     * The shared handler: build the query config from request params, render
     * the cards + pagination, return the JSON-shaped array.
     *
     * @param array $params Raw request params (unslashed).
     * @return array{html:string,pagination:string,max_page:int,canonical_url:string}
     */
    public function build_list_response(array $params)
    {
        $config = WRALM_Query_Config::from_request($params);

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
            $config->sync_pagination_url,
            $config->archive_context,
            $config->base_url
        );

        $add_args = $config->sync_filters_url ? $config->filter_query_args() : array();

        $pagination = WRALM_Pagination::links(
            $config->pagination_args($base, $format, $add_args) + array('total' => $query->max_num_pages)
        );

        return array(
            'html'          => $html,
            'pagination'    => $pagination,
            'max_page'      => $query->max_num_pages,
            'canonical_url' => WRALM_Pagination::page_url($base, $format, $config->paged, $add_args),
        );
    }
}
