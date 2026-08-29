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

    public function add_meta_box()
    {
        $args = array(
            'public' => true,
            '_builtin' => false,
        );
        $screens = get_post_types($args, 'names', 'and');
        $screens[] = 'post';

        add_meta_box(
            'myplugin_sectionid',
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
        <label for="all_posts_ajax_hide">
            <input type="checkbox" id="all_posts_ajax_hide"
                   name="all_posts_ajax_hide" <?= $value ? 'checked' : '' ?>/>
            <?php esc_html_e('Hide from list', 'wr-ajax-load-more-and-filters'); ?>
        </label>
        <?php
    }

    public function save_meta_box($post_id)
    {
        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
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
