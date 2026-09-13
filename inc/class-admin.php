<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu page. Renders a single mount point and hands the React
 * console (frontend/, built into dist/app.js + dist/style.css) the
 * post types and taxonomies it needs to build the shortcode UI.
 */
class WRALM_Admin
{
    const PAGE_HOOK = 'toplevel_page_wr-ajax-load-more';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Ensures the theme has a place to put post-card templates.
     * Runs once, on activation, instead of on every page load.
     */
    public static function create_card_template()
    {
        $theme_dir = get_stylesheet_directory() . '/all_posts_ajax';

        if (!is_dir($theme_dir)) {
            wp_mkdir_p($theme_dir);
        }

        $card_file = $theme_dir . '/post-card.php';

        if (!is_file($card_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
            $handle = @fopen($card_file, 'w');
            if ($handle) {
                fclose($handle);
            }
        }
    }

    public function add_menu_page()
    {
        add_menu_page(
            __('Web Revizor: Ajax Load More', 'wr-ajax-load-more-and-filters'),
            __('WR Ajax Load More', 'wr-ajax-load-more-and-filters'),
            'manage_options',
            'wr-ajax-load-more',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook_suffix)
    {
        if ($hook_suffix !== self::PAGE_HOOK) {
            return;
        }

        wp_enqueue_style('wralm-admin', WRALM_URL . 'dist/style.css', [], WRALM_VERSION);

        wp_enqueue_script('wralm-admin', WRALM_URL . 'dist/app.js', [], WRALM_VERSION, true);
    }

    public function render_page()
    {
        $args = array(
            'public' => true,
            '_builtin' => false,
        );
        $post_types = array_values(get_post_types($args, 'names', 'and'));
        $taxonomies = array_values(get_taxonomies($args, 'names', 'and'));

        if (!in_array('post', $post_types, true)) {
            array_unshift($post_types, 'post');
        }

        $settings = array(
            'postTypes' => $post_types,
            'taxonomies' => $taxonomies,
        );
        ?>
        <script>
            window.wralmSettings = <?php echo wp_json_encode($settings); ?>;
        </script>
        <div class="wrap">
            <div class="web-revizor-container"
                 id="wralm-console"></div>
        </div>
        <?php
        require_once WRALM_PATH . 'template-parts/sprite.php';
    }
}
