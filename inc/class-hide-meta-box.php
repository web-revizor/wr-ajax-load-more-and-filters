<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Hide from list" checkbox meta box to every public post type,
 * used to exclude a post from the [all_posts_ajax] query.
 */
class WRALM_Hide_Meta_Box
{
    const META_KEY = 'all_posts_ajax_hide';
    const NONCE_ACTION = 'apa_hide_meta_box';
    const NONCE_NAME = 'apa_hide_meta_box_nonce';
    const CLEANUP_FLAG = 'wralm_hide_meta_cleaned';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_style']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('init', [$this, 'maybe_cleanup_legacy_meta']);
    }

    /**
     * One-time removal of legacy `all_posts_ajax_hide` rows that hold anything
     * other than '1'. Earlier versions wrote '0' on every post save, bloating
     * wp_postmeta and forcing the list query into a two-LEFT-JOIN meta_query.
     * Now only '1' rows exist (unchecked => delete_post_meta), so the query is a
     * single NOT EXISTS. Runs once per site, gated by an autoloaded flag.
     */
    public function maybe_cleanup_legacy_meta()
    {
        if (get_option(self::CLEANUP_FLAG)) {
            return;
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> %s",
            self::META_KEY,
            '1'
        ));

        update_option(self::CLEANUP_FLAG, 1);
    }

    /**
     * Post types that get the meta box: public custom types plus posts.
     *
     * @return string[]
     */
    private function screens()
    {
        $screens = array_values(get_post_types(['public' => true, '_builtin' => false], 'names', 'and'));
        $screens[] = 'post';

        return $screens;
    }

    /**
     * The admin stylesheet gives the meta box the plugin's dark look (it only
     * styles .web-revizor-container, form controls are not reset globally).
     */
    public function enqueue_style($hook_suffix)
    {
        $screen = get_current_screen();

        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true) || !$screen || !in_array($screen->post_type, $this->screens(), true)) {
            return;
        }

        wp_enqueue_style('wralm-admin', WRALM_URL . 'dist/style.css', [], WRALM_VERSION);
    }

    public function add_meta_box()
    {
        $screens = $this->screens();

        add_meta_box(
            'wralm_hide_from_list',
            __('All Posts Ajax', 'wr-ajax-load-more-and-filters'),
            [$this, 'render_meta_box'],
            $screens,
            'side',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $value = get_post_meta($post->ID, self::META_KEY, true);
        ?>
        <div class="web-revizor-container">
            <?php // A native checkbox drawn as the ui-kit small Toggle (no JS on the editor screen). ?>
            <label for="all_posts_ajax_hide" class="flex cursor-pointer select-none items-center justify-between gap-3 text-sm text-on-surface">
                <span><?php esc_html_e('Hide from list', 'wr-ajax-load-more-and-filters'); ?></span>
                <span class="relative inline-flex shrink-0">
                    <input type="checkbox" id="all_posts_ajax_hide" class="peer sr-only"
                           name="all_posts_ajax_hide" <?php checked((bool) $value); ?>/>
                    <span class="globalTransition block h-[26px] w-[46px] rounded-full border border-solid border-outline-variant/30 bg-surface-container peer-checked:bg-primary-container peer-hover:border-primary peer-focus-visible:border-primary peer-focus-visible:ring-1 peer-focus-visible:ring-primary"></span>
                    <span class="globalTransition magenta-glow pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-on-surface peer-checked:translate-x-5"></span>
                </span>
            </label>
        </div>
        <?php
    }

    public function save_meta_box($post_id)
    {
        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Store the meta ONLY when hidden. Writing '0' for every unchecked save
        // bloats wp_postmeta and defeats the NOT EXISTS lookup in the list query.
        if (isset($_POST['all_posts_ajax_hide'])) {
            update_post_meta($post_id, self::META_KEY, '1');
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }
}
