<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Search" settings screen (submenu of the WR Ajax Load More menu).
 *
 * Replaces the old auto-collected ACF field cache in WRALM_Search_ACF: the
 * admin now picks explicitly which ACF fields the front-end search should look
 * inside, and can cap how expensive a search query gets. Everything is one
 * option; WRALM_Search_ACF reads it through WRALM_Search_Settings::get().
 */
class WRALM_Search_Settings
{
    const OPTION_NAME  = 'wralm_search_settings';
    const OPTION_GROUP = 'wralm_search';
    const PAGE_SLUG    = 'wralm-search';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'register']);
    }

    /**
     * Normalised settings with defaults applied. Safe to call anywhere.
     *
     * @return array{
     *     acf_fields: string[],
     *     search_comments: bool,
     *     search_terms: bool,
     *     max_words: int,
     *     min_word_length: int
     * }
     */
    public static function get()
    {
        $raw = get_option(self::OPTION_NAME, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $acf_fields = isset($raw['acf_fields']) && is_array($raw['acf_fields'])
            ? array_values(array_unique(array_filter(array_map('sanitize_key', $raw['acf_fields']))))
            : [];

        return [
            'acf_fields'      => $acf_fields,
            'search_comments' => !isset($raw['search_comments']) || (bool) $raw['search_comments'],
            'search_terms'    => !isset($raw['search_terms']) || (bool) $raw['search_terms'],
            'max_words'       => isset($raw['max_words']) ? max(1, min(20, (int) $raw['max_words'])) : 6,
            'min_word_length' => isset($raw['min_word_length']) ? max(1, min(10, (int) $raw['min_word_length'])) : 3,
        ];
    }

    /**
     * Every ACF field name available across public post types, as
     * `name => "Label (name)"`. Top-level fields only — nested repeater / group
     * sub-fields are stored under composite meta keys and are out of scope.
     *
     * @return array<string,string>
     */
    public static function available_acf_fields()
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $out = [];
        foreach (get_post_types(['public' => true], 'names') as $post_type) {
            $groups = acf_get_field_groups(['post_type' => $post_type]);
            if (empty($groups)) {
                continue;
            }
            foreach ($groups as $group) {
                $fields = acf_get_fields($group['key']);
                if (empty($fields)) {
                    continue;
                }
                foreach ($fields as $field) {
                    if (empty($field['name'])) {
                        continue;
                    }
                    $name = sanitize_key($field['name']);
                    $label = !empty($field['label']) ? $field['label'] : $name;
                    $out[$name] = $label . ' (' . $name . ')';
                }
            }
        }

        ksort($out);
        return $out;
    }

    public function add_page()
    {
        add_submenu_page(
            'wr-ajax-load-more',
            __('Search Settings', 'wr-ajax-load-more-and-filters'),
            __('Search', 'wr-ajax-load-more-and-filters'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function register()
    {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => [],
        ]);

        add_settings_section(
            'wralm_search_main',
            __('Front-end search', 'wr-ajax-load-more-and-filters'),
            function () {
                echo '<p>' . esc_html__(
                    'Controls what the [all_posts_ajax] search looks inside. Post title and content are always searched.',
                    'wr-ajax-load-more-and-filters'
                ) . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field('acf_fields', __('ACF fields to search', 'wr-ajax-load-more-and-filters'), [$this, 'field_acf_fields'], self::PAGE_SLUG, 'wralm_search_main');
        add_settings_field('sources', __('Also search', 'wr-ajax-load-more-and-filters'), [$this, 'field_sources'], self::PAGE_SLUG, 'wralm_search_main');
        add_settings_field('limits', __('Query limits', 'wr-ajax-load-more-and-filters'), [$this, 'field_limits'], self::PAGE_SLUG, 'wralm_search_main');
    }

    public function sanitize($input)
    {
        if (!is_array($input)) {
            $input = [];
        }

        $available = array_keys(self::available_acf_fields());
        $picked = isset($input['acf_fields']) && is_array($input['acf_fields'])
            ? array_map('sanitize_key', $input['acf_fields'])
            : [];

        return [
            'acf_fields'      => array_values(array_intersect($picked, $available)),
            'search_comments' => !empty($input['search_comments']),
            'search_terms'    => !empty($input['search_terms']),
            'max_words'       => isset($input['max_words']) ? max(1, min(20, (int) $input['max_words'])) : 6,
            'min_word_length' => isset($input['min_word_length']) ? max(1, min(10, (int) $input['min_word_length'])) : 3,
        ];
    }

    public function field_acf_fields()
    {
        $available = self::available_acf_fields();
        $current   = self::get()['acf_fields'];

        if (empty($available)) {
            echo '<p class="description">' . esc_html__('No ACF fields detected (Advanced Custom Fields not active, or no field groups on public post types).', 'wr-ajax-load-more-and-filters') . '</p>';
            return;
        }

        echo '<select name="' . esc_attr(self::OPTION_NAME) . '[acf_fields][]" multiple size="8" style="min-width:320px">';
        foreach ($available as $name => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($name),
                in_array($name, $current, true) ? ' selected' : '',
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Nothing selected = the search does not touch ACF values at all (fastest). Ctrl/Cmd-click to multi-select.', 'wr-ajax-load-more-and-filters') . '</p>';
    }

    public function field_sources()
    {
        $s = self::get();
        printf(
            '<label><input type="checkbox" name="%1$s[search_comments]" value="1"%2$s> %3$s</label><br>',
            esc_attr(self::OPTION_NAME),
            checked($s['search_comments'], true, false),
            esc_html__('Comment text', 'wr-ajax-load-more-and-filters')
        );
        printf(
            '<label><input type="checkbox" name="%1$s[search_terms]" value="1"%2$s> %3$s</label>',
            esc_attr(self::OPTION_NAME),
            checked($s['search_terms'], true, false),
            esc_html__('Taxonomy term names', 'wr-ajax-load-more-and-filters')
        );
    }

    public function field_limits()
    {
        $s = self::get();
        printf(
            '<label>%1$s <input type="number" min="1" max="20" name="%2$s[max_words]" value="%3$d" class="small-text"></label><br>',
            esc_html__('Max words per search', 'wr-ajax-load-more-and-filters'),
            esc_attr(self::OPTION_NAME),
            (int) $s['max_words']
        );
        printf(
            '<label>%1$s <input type="number" min="1" max="10" name="%2$s[min_word_length]" value="%3$d" class="small-text"></label>',
            esc_html__('Ignore words shorter than (characters)', 'wr-ajax-load-more-and-filters'),
            esc_attr(self::OPTION_NAME),
            (int) $s['min_word_length']
        );
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WR Ajax Load More — Search', 'wr-ajax-load-more-and-filters'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
