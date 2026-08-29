<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extends the default WordPress search of WRALM list queries to also match
 * ACF field values, comment text and taxonomy term names.
 *
 * Which ACF fields are searched, whether comments / term names are included,
 * and the per-query word limits all come from the "Search" settings screen
 * (WRALM_Search_Settings). With no ACF fields picked the search never touches
 * wp_postmeta.
 */
class WRALM_Search_ACF
{
    public function __construct()
    {
        add_filter('posts_search', [$this, 'extend_search_query'], 500, 2);
    }

    /**
     * Rebuilds the `WHERE` clause of a front-end search dynamically.
     */
    public function extend_search_query($where, $wp_query)
    {
        global $wpdb;

        // Never touch the WP admin post-list search (nor any other back-end query).
        if (is_admin() && !wp_doing_ajax()) {
            return $where;
        }

        // Only rewrite queries that opted in via WRALM_Query_Config (our shortcode
        // search sets query_var `wralm_search`). Global opt-in stays available.
        $is_ours      = !empty($wp_query->query_vars['wralm_search']);
        $allow_global = apply_filters('wralm_extend_all_search', false);
        if (!$is_ours && !$allow_global) {
            return $where;
        }

        if (empty($where) || empty($wp_query->query_vars['s'])) {
            return $where;
        }

        $settings = WRALM_Search_Settings::get();

        $terms = array_filter(explode(' ', $wp_query->query_vars['s']));

        // Drop words shorter than the configured minimum, then cap the count so
        // a pathological many-word string can't expand into one AND-ed block of
        // EXISTS subqueries per word.
        $min = (int) $settings['min_word_length'];
        $terms = array_filter($terms, function ($t) use ($min) {
            return (function_exists('mb_strlen') ? mb_strlen($t) : strlen($t)) >= $min;
        });
        $terms = array_slice(array_values($terms), 0, (int) $settings['max_words']);
        if (empty($terms)) {
            return $where;
        }

        $acf_fields = $settings['acf_fields'];
        $has_acf    = !empty($acf_fields);

        // Per-term OR block. Title + content always; the rest is opt-in.
        $parts = [
            "({$wpdb->posts}.post_title LIKE %s)",
            "({$wpdb->posts}.post_content LIKE %s)",
        ];
        $shape = ['like', 'like'];

        if ($has_acf) {
            $acf_ph  = implode(',', array_fill(0, count($acf_fields), '%s'));
            $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key IN ({$acf_ph}) AND meta_value LIKE %s )";
            $shape[] = 'acf_keys';
            $shape[] = 'like';
        }

        if ($settings['search_comments']) {
            $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->comments} WHERE comment_post_ID = {$wpdb->posts}.ID AND comment_content LIKE %s )";
            $shape[] = 'like';
        }

        $taxonomies = [];
        if ($settings['search_terms']) {
            $taxonomies = array_values(get_taxonomies(['public' => true], 'names'));
            if ($taxonomies) {
                $tax_ph  = implode(',', array_fill(0, count($taxonomies), '%s'));
                $parts[] = "EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr"
                    . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
                    . " INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
                    . " WHERE tr.object_id = {$wpdb->posts}.ID"
                    . " AND tt.taxonomy IN ({$tax_ph})"
                    . " AND t.name LIKE %s )";
                $shape[] = 'taxonomies';
                $shape[] = 'like';
            }
        }

        $base_sql  = implode(' OR ', $parts);
        $new_where = '';

        foreach ($terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';

            $params = [];
            foreach ($shape as $kind) {
                if ('acf_keys' === $kind) {
                    array_push($params, ...$acf_fields);
                } elseif ('taxonomies' === $kind) {
                    array_push($params, ...$taxonomies);
                } else {
                    $params[] = $like;
                }
            }

            $new_where .= $wpdb->prepare(" AND ({$base_sql})", $params);
        }

        return $new_where;
    }
}
