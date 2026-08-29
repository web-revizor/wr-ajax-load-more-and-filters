<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom pagination markup (list / button / both / none), shared by the
 * shortcode's initial render and the load-more AJAX handler.
 */
class WRALM_Pagination
{
    /**
     * Compute the paginate_links() base + format for a given context.
     * Pretty /page/N/ URLs are only emitted on archive-ish pages AND when
     * pagination URL sync is on; everywhere else fall back to ?paged=N so the
     * plugin never produces a broken /page/N/ link on a static page.
     *
     * @return array [ $base, $format ]
     */
    public static function resolve_base($sync_pagination_url, $archive_context, $url)
    {
        global $wp_rewrite;

        $url = html_entity_decode($url);

        $pag_base = isset($wp_rewrite->pagination_base) ? $wp_rewrite->pagination_base : 'page';

        // Strip a trailing /page/N/ or /{pagination_base}/N/ that a deep server-rendered
        // URL carries in, so AJAX pagination links don't double (/blog/page/2/page/3/).
        // The (?=$|\?) anchor only matches when the segment ends the path.
        $url = preg_replace(
            '#/(?:page|' . preg_quote($pag_base, '#') . ')/\d+/?(?=$|\?)#',
            '/',
            $url
        );

        // Strip ?paged=N / ?page=N (and &-joined variants) from the query string.
        $url = preg_replace('#([?&])(?:paged|page)=\d+#', '$1', $url);
        $url = preg_replace('#[?&]+$#', '', $url);
        $url = str_replace('?&', '?', $url);

        $path = strtok($url, '?');
        $base = trailingslashit($path) . '%_%';

        $pretty = $sync_pagination_url && $archive_context && $wp_rewrite->using_permalinks();

        if ($pretty) {
            $format = $wp_rewrite->using_index_permalinks() && false === strpos($base, 'index.php') ? 'index.php/' : '';
            $format .= user_trailingslashit($wp_rewrite->pagination_base . '/%#%', 'paged');
        } else {
            $format = '?paged=%#%';
        }

        return array($base, $format);
    }

    private static function href($link, $sync_pagination_url)
    {
        return $sync_pagination_url ? esc_url(apply_filters('paginate_links', $link)) : '#';
    }

    /**
     * The single "URL for page N" builder: substitutes the paginate_links
     * %_% / %#% placeholders, folds in $add_args, appends the fragment.
     * Page 1 drops the format segment (no /page/1/, no ?paged=1). Shared by
     * links() and by WRALM_Load_More for the canonical address-bar URL, so the
     * two never drift.
     */
    public static function page_url($base, $format, $page, $add_args = array(), $fragment = '')
    {
        $page = max(1, (int) $page);
        $link = str_replace('%_%', 1 === $page ? '' : $format, $base);
        $link = str_replace('%#%', $page, $link);
        if (!empty($add_args)) {
            $link = add_query_arg($add_args, $link);
            // add_query_arg urlencodes commas in multi-term values; keep them
            // literal so the address bar matches what the public script writes.
            $link = str_replace(array('%2C', '%2c'), ',', $link);
        }
        return $link . $fragment;
    }

    public static function links($args = '')
    {
        global $wp_rewrite;

        // When the caller supplies base + format + total + current, work purely
        // from $args and never touch WP globals / the current query.
        $has_explicit = is_array($args)
            && isset($args['base'], $args['format'], $args['total'], $args['current']);

        if ($has_explicit) {
            $url_parts = array(strtok((string)$args['base'], '?'));
            $pagenum_link = $args['base'];
            $format = $args['format'];
            $total = (int)$args['total'];
            $current = (int)$args['current'];
        } else {
            // Setting up default values based on the current URL.
            $pagenum_link = html_entity_decode(get_pagenum_link());
            $url_parts = explode('?', $pagenum_link);

            // Get max pages and current page out of the current query, if available.
            $total = isset($GLOBALS['wp_query']->max_num_pages) ? $GLOBALS['wp_query']->max_num_pages : 1;
            $current = get_query_var('paged') ? (int)get_query_var('paged') : 1;

            // Append the format placeholder to the base URL.
            $pagenum_link = trailingslashit($url_parts[0]) . '%_%';

            // URL base depends on permalink settings.
            $format = $wp_rewrite->using_index_permalinks() && !strpos($pagenum_link, 'index.php') ? 'index.php/' : '';
            $format .= $wp_rewrite->using_permalinks() ? user_trailingslashit($wp_rewrite->pagination_base . '/%#%', 'paged') : '?paged=%#%';
        }

        $defaults = array(
            'base' => $pagenum_link,
            'format' => $format,
            'total' => $total,
            'current' => $current,
            'aria_current' => 'page',
            'show_all' => false,
            'prev_next' => true,
            'prev_text' => __('Previous', 'wr-ajax-load-more-and-filters'),
            'next_text' => __('Next', 'wr-ajax-load-more-and-filters'),
            'end_size' => 1,
            'mid_size' => 2,
            'type' => 'plain',
            'add_args' => array(),
            'add_fragment' => '',
            'before_page_number' => '',
            'after_page_number' => '',
            'load_more_classes' => '',
            'load_more_label' => __('Show more', 'wr-ajax-load-more-and-filters'),
            'sync_pagination_url' => true,
        );

        $args = wp_parse_args($args, $defaults);

        if (!is_array($args['add_args'])) {
            $args['add_args'] = array();
        }

        if (isset($url_parts[1])) {
            $format = explode('?', str_replace('%_%', $args['format'], $args['base']));
            $format_query = isset($format[1]) ? $format[1] : '';
            wp_parse_str($format_query, $format_args);
            wp_parse_str($url_parts[1], $url_query_args);
            foreach ($format_args as $format_arg => $format_arg_value) {
                unset($url_query_args[$format_arg]);
            }
            $args['add_args'] = array_merge($args['add_args'], urlencode_deep($url_query_args));
        }

        $total = (int)$args['total'];
        if ($total < 2) {
            return;
        }
        $current = (int)$args['current'];
        $end_size = (int)$args['end_size'];
        if ($end_size < 1) {
            $end_size = 1;
        }
        $mid_size = (int)$args['mid_size'];
        if ($mid_size < 0) {
            $mid_size = 2;
        }

        $add_args = $args['add_args'];
        $r = '';
        $page_links = array();
        $page_links_more = array();
        $dots = false;

        if ($args['prev_next'] && $current && 1 < $current) :
            $link = self::page_url($args['base'], $args['format'], $current - 1, $add_args, $args['add_fragment']);
            $page_links[] = sprintf(
                '<a class="prev load_page" href="%s" data-page="%s">%s</a>',
                self::href($link, $args['sync_pagination_url']),
                number_format_i18n($current - 1),
                $args['prev_text']
            );
        else :
            $page_links[] = sprintf('<span class="prev disabled">%s</span>', $args['prev_text']);
        endif;

        for ($n = 1; $n <= $total; $n++) :
            if ($n == $current) :
                $page_links[] = sprintf(
                    '<span aria-current="%s" class="page-numbers current">%s</span>',
                    esc_attr($args['aria_current']),
                    $args['before_page_number'] . number_format_i18n($n) . $args['after_page_number']
                );
                $dots = true;
            else :
                if ($args['show_all'] || ($n <= $end_size || ($current && $n >= $current - $mid_size && $n <= $current + $mid_size) || $n > $total - $end_size)) :
                    $link = self::page_url($args['base'], $args['format'], $n, $add_args, $args['add_fragment']);
                    $page_links[] = sprintf(
                        '<a class="page-numbers load_page" href="%s" data-page="%s">%s</a>',
                        self::href($link, $args['sync_pagination_url']),
                        number_format_i18n($n),
                        $args['before_page_number'] . number_format_i18n($n) . $args['after_page_number']
                    );
                    $dots = true;
                elseif ($dots && !$args['show_all']) :
                    $page_links[] = '<span class="page-numbers dots">' . __('&hellip;', 'wr-ajax-load-more-and-filters') . '</span>';
                    $dots = false;
                endif;
            endif;
        endfor;

        if ($args['prev_next'] && $current && $current < $total) :
            $link = self::page_url($args['base'], $args['format'], $current + 1, $add_args, $args['add_fragment']);
            $page_links[] = sprintf(
                '<a class="next load_page" href="%s" data-page="%s">%s</a>',
                self::href($link, $args['sync_pagination_url']),
                number_format_i18n($current + 1),
                $args['next_text']
            );
            $page_links_more[] = sprintf(
                '<a class="load_page load_more %s" href="%s" data-page="%s">%s</a>',
                esc_attr($args['load_more_classes']),
                self::href($link, $args['sync_pagination_url']),
                number_format_i18n($current + 1),
                $args['load_more_label']
            );
        else :
            $page_links_more[] = '';
            $page_links[] = sprintf('<span class="next disabled">%s</span>', $args['next_text']);
        endif;

        switch ($args['type']) {
            case 'list':
                $r .= "<div class='pagination_holder load_more_holder'>";
                $r .= implode("\n", $page_links);
                $r .= "</div>";
                break;
            case 'both':
                $r .= "<div class='pagination_holder load_more_holder'>";
                $r .= implode("\n", $page_links);
                $r .= '<div>' . implode("\n", $page_links_more) . '</div>';
                $r .= "</div>";
                break;
            case 'none':
                break;
            default:
                $r .= "<div class='pagination_holder load_more_holder'>";
                $r .= implode("\n", $page_links_more);
                $r .= "</div>";
                break;
        }

        return apply_filters('paginate_links_output', $r, $args);
    }
}
