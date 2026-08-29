<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extends the default WordPress search to also match ACF field values,
 * by caching the list of registered ACF field names in a WordPress option.
 */
class WRALM_Search_ACF
{
    const OPTION_NAME = 'wralm_searchable_acf_fields';

    public function __construct()
    {
        // Hook into ACF save post
        add_action('acf/save_post', [$this, 'refresh_searchable_fields'], 20);
        add_filter('posts_search', [$this, 'extend_search_query'], 500, 2);
        add_action('admin_init', [$this, 'maybe_bootstrap']);
    }

    /**
     * Wipes the cache on activation so the next admin_init rebuilds it fresh.
     */
    public static function on_activate()
    {
        delete_option(self::OPTION_NAME);
    }

    /**
     * Populates the ACF field-name cache the first time an admin screen loads
     * after activation (or any time the option is missing), so search works
     * without waiting for a field group to be re-saved.
     */
    public function maybe_bootstrap()
    {
        if (false !== get_option(self::OPTION_NAME, false)) {
            return;
        }
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return;
        }
        $this->rebuild_all();
    }

    /**
     * ACF field-group save hook: rebuilds the searchable-field cache, but only
     * when an `acf-field-group` post is saved (not on every post save).
     */
    public function refresh_searchable_fields($post_id)
    {
        // Prevent execution on regular posts/pages
        if (get_post_type($post_id) !== 'acf-field-group') {
            return;
        }

        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return;
        }

        $this->rebuild_all();
    }

    /**
     * Collects every ACF field name across all public post types and caches
     * it (autoloaded) in the plugin option.
     */
    public function rebuild_all()
    {
        $post_types = get_post_types(['public' => true], 'names');
        $field_names = [];

        foreach ($post_types as $post_type) {
            $groups = acf_get_field_groups(['post_type' => $post_type]);
            if (empty($groups)) continue;

            foreach ($groups as $group) {
                $fields = acf_get_fields($group['key']);
                if (empty($fields)) continue;

                foreach ($fields as $field) {
                    if (!empty($field['name'])) {
                        $field_names[] = $field['name'];
                    }
                }
            }
        }

        // FIX: Enable autoload (3rd param = true) to avoid extra DB queries on every search.
        update_option(self::OPTION_NAME, array_values(array_unique($field_names)), true);
    }

    /**
     * Rebuilds the `WHERE` clause of a front-end search dynamically.
     */
    public function extend_search_query($where, $wp_query)
    {
        global $wpdb;

        // FIX: Never touch the WP admin post-list search (nor any other back-end query).
        if (is_admin() && !wp_doing_ajax()) {
            return $where;
        }

        // FIX: Only rewrite queries that opted in via WRALM_Query_Config (our shortcode
        // search sets query_var `wralm_search`). Global opt-in stays available via filter.
        $is_ours      = !empty($wp_query->query_vars['wralm_search']);
        $allow_global = apply_filters('wralm_extend_all_search', false);
        if (!$is_ours && !$allow_global) {
            return $where;
        }

        if (empty($where) || empty($wp_query->query_vars['s'])) {
            return $where;
        }

        $search_term = $wp_query->query_vars['s'];
        // Filter out empty strings caused by multiple spaces
        $terms = array_filter(explode(' ', $search_term));
        if (empty($terms)) {
            return $where;
        }

        // FIX: Dynamically fetch all public taxonomies instead of hardcoding them
        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (empty($taxonomies)) {
            return $where;
        }
        $taxonomies = array_values($taxonomies);
        $tax_placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));

        $acf_fields = $this->get_searchable_fields();
        $has_acf = !empty($acf_fields);

        // Build the per-term OR block in parts. The postmeta EXISTS clause is
        // appended ONLY when we have a known ACF field list, and it is ALWAYS
        // bounded by `meta_key IN (...)` — we never full-scan wp_postmeta.
        $parts = [
            "({$wpdb->posts}.post_title LIKE %s)",
            "({$wpdb->posts}.post_content LIKE %s)",
        ];
        $per_term_params_shape = ['title', 'content'];

        if ($has_acf) {
            $acf_ph = implode(',', array_fill(0, count($acf_fields), '%s'));
            $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key IN ({$acf_ph}) AND meta_value LIKE %s )";
            $per_term_params_shape[] = 'acf_keys';
            $per_term_params_shape[] = 'meta_value';
        }

        $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->comments} WHERE comment_post_ID = {$wpdb->posts}.ID AND comment_content LIKE %s )";
        $per_term_params_shape[] = 'comment';

        $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . " INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
            . " WHERE tr.object_id = {$wpdb->posts}.ID"
            . " AND tt.taxonomy IN ({$tax_placeholders})"
            . " AND t.name LIKE %s )";
        $per_term_params_shape[] = 'taxonomies';
        $per_term_params_shape[] = 'term_name';

        $base_sql = implode(' OR ', $parts);

        $new_where = '';

        foreach ($terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';

            // Build parameters array sequentially to avoid memory overhead of array_merge in loops
            $params = [];
            foreach ($per_term_params_shape as $shape) {
                switch ($shape) {
                    case 'acf_keys':
                        array_push($params, ...$acf_fields);
                        break;
                    case 'taxonomies':
                        array_push($params, ...$taxonomies);
                        break;
                    default: // title, content, meta_value, comment, term_name
                        $params[] = $like;
                        break;
                }
            }

            $new_where .= $wpdb->prepare(" AND ({$base_sql})", $params);
        }

        return $new_where;
    }

    /**
     * @return string[]
     */
    private function get_searchable_fields()
    {
        $fields = get_option(self::OPTION_NAME, []);
        return is_array($fields) ? $fields : [];
    }
}
