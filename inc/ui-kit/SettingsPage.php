<?php

// Generated from @web-revizor/ui-kit/php — do not edit; rebuild instead.

/**
 * Settings API page whose fields are rendered by the ui-kit SettingsForm
 * component. Saving stays the standard options.php round trip.
 *
 * Copied into a plugin by build-tools/vite-php-modules-plugin.js, which
 * replaces the namespace below with the plugin's own.
 *
 *   $page = new SettingsPage([
 *       'page'       => 'my-plugin',            // menu slug
 *       'group'      => 'my_plugin_settings',   // option group
 *       'sections'   => ['main' => ['title' => __('Main', 'my-plugin')]],
 *       'fields'     => [['id' => 'my_plugin_url', 'type' => 'url', 'label' => …, 'section' => 'main']],
 *       'clear_name' => 'my_plugin_clear_secret',
 *       'strings'    => ['submit' => __('Save Settings', 'my-plugin'), …],
 *   ]);
 *
 * Optional: 'locked' => fn(string $id): ?string (constant name; default: the
 * upper-cased option id when defined), 'value' => fn(array $field) (default:
 * the constant when locked, else get_option()), 'show_errors' => true for
 * top-level menu pages. Array options ('key') and multi-selects need the
 * field's 'sanitize_callback' for the whole option.
 *   add_action('admin_init', [$page, 'register']);
 *   // in the add_options_page() callback: $page->render();
 *
 * The plugin bundle mounts SettingsForm on [data-wr-settings], whose
 * attribute holds schema() as JSON.
 */

namespace WRALM\UiKit;

defined('ABSPATH') || exit;

class SettingsPage
{
    /** @var array<string, mixed> */
    private $config;

    /** @var array<string, string> */
    private $strings;

    /**
     * @param array<string, mixed> $config See the class header.
     */
    public function __construct(array $config)
    {
        $this->config = $config + [
            'sections' => [],
            'fields' => [],
            'clear_name' => 'wr_clear_secret',
            'strings' => [],
            'locked' => null,
            'value' => null,
            // Pages under the Settings menu get notices from options-head.php;
            // top-level pages must print them themselves.
            'show_errors' => false,
        ];

        // English defaults; plugins pass translated strings, because i18n
        // tools only pick up __() calls with a literal text domain.
        $this->strings = $this->config['strings'] + [
            'submit' => 'Save Settings',
            'not_set' => 'Not set',
            'saved' => 'Saved — leave empty to keep',
            'defined' => 'Defined in wp-config.php',
            'locked' => 'Locked by the %s constant in wp-config.php — this field cannot be changed here.',
            'clear' => 'Clear',
            'no_js' => 'This page needs JavaScript.',
        ];
    }

    public function register(): void
    {
        foreach ($this->options() as $option => $fields) {
            $first = $fields[0];

            if (isset($first['key']) || !empty($first['multiple'])) {
                // Array values (several fields in one option, multi-selects)
                // have a plugin-specific shape, so the plugin sanitizes them.
                register_setting($this->config['group'], $option, [
                    'type' => 'array',
                    'sanitize_callback' => $first['sanitize_callback'] ?? null,
                    'default' => [],
                ]);
                continue;
            }

            register_setting($this->config['group'], $option, [
                'type' => 'string',
                'sanitize_callback' => function ($value) use ($first) {
                    return $this->sanitize($first, $value);
                },
                'default' => '',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $sections = [];

        foreach ($this->config['sections'] as $id => $section) {
            $sections[$id] = [
                'id' => (string) $id,
                'title' => (string) ($section['title'] ?? ''),
                'description' => isset($section['description']) ? wp_kses_post($section['description']) : '',
                'fields' => [],
            ];
        }

        foreach ($this->config['fields'] as $field) {
            if (isset($sections[$field['section']])) {
                $sections[$field['section']]['fields'][] = $this->field_schema($field);
            }
        }

        return [
            'sections' => array_values($sections),
            'submitLabel' => $this->strings['submit'],
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap web-revizor-container">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php
            if ($this->config['show_errors']) {
                settings_errors();
            }
            ?>
            <form action="options.php" method="post">
                <?php settings_fields($this->config['group']); ?>
                <div data-wr-settings="<?php echo esc_attr((string) wp_json_encode($this->schema())); ?>"></div>
                <noscript><p><?php echo esc_html($this->strings['no_js']); ?></p></noscript>
            </form>
        </div>
        <?php
    }

    /**
     * Fields grouped by the option they are stored in.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function options(): array
    {
        $options = [];

        foreach ($this->config['fields'] as $field) {
            $options[$field['id']][] = $field;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function field_schema(array $field): array
    {
        $type = $field['type'] ?? 'text';
        $locked = $this->locked_constant($field['id']);
        $secret = !empty($field['secret']);
        $value = $this->value($field, $locked);

        $name = $field['id'];
        if (isset($field['key'])) {
            $name .= '[' . $field['key'] . ']';
        }
        if (!empty($field['multiple'])) {
            $name .= '[]';
        }

        $schema = [
            'name' => $name,
            'id' => $field['id'] . (isset($field['key']) ? '-' . $field['key'] : ''),
            'type' => $type,
            'label' => (string) $field['label'],
            'value' => $secret ? '' : $value,
        ];

        if (!empty($field['desc'])) {
            $schema['description'] = wp_kses_post($field['desc']);
        }

        if ($secret) {
            // Secrets are never printed: the placeholder shows the state.
            if ($locked !== null) {
                $schema['placeholder'] = $this->strings['defined'];
            } elseif ($value === '') {
                $schema['placeholder'] = $this->strings['not_set'];
            } else {
                $schema['placeholder'] = $this->strings['saved'];
                $schema['clear'] = [
                    'name' => $this->config['clear_name'],
                    'value' => $field['id'],
                    'label' => $this->strings['clear'],
                ];
            }
        } elseif (isset($field['placeholder'])) {
            $schema['placeholder'] = (string) $field['placeholder'];
        }

        if ($locked !== null) {
            $schema['disabled'] = true;
            $schema['note'] = sprintf($this->strings['locked'], $locked);
        }

        if (isset($field['options'])) {
            $schema['options'] = [];
            foreach ($field['options'] as $option_value => $option_label) {
                $schema['options'][] = ['value' => (string) $option_value, 'label' => (string) $option_label];
            }
        }

        foreach (['required', 'multiple'] as $flag) {
            if (!empty($field[$flag])) {
                $schema[$flag] = true;
            }
        }

        foreach (['min', 'max', 'step', 'rows'] as $number) {
            if (isset($field[$number])) {
                $schema[$number] = $field[$number] + 0;
            }
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $field
     * @return string|string[]|bool
     */
    private function value(array $field, ?string $locked)
    {
        if (is_callable($this->config['value'])) {
            $value = call_user_func($this->config['value'], $field);
        } elseif ($locked !== null) {
            $value = constant($locked);
        } else {
            $value = get_option($field['id'], $field['default'] ?? '');
            if (isset($field['key'])) {
                $value = is_array($value) && array_key_exists($field['key'], $value)
                    ? $value[$field['key']]
                    : ($field['default'] ?? '');
            }
        }

        $type = $field['type'] ?? 'text';
        if ($type === 'checkbox') {
            return !empty($value);
        }
        if (!empty($field['multiple'])) {
            return array_map('strval', (array) $value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Name of the constant that locks this option, or null.
     */
    private function locked_constant(string $id): ?string
    {
        if (is_callable($this->config['locked'])) {
            $constant = call_user_func($this->config['locked'], $id);
            return is_string($constant) && $constant !== '' ? $constant : null;
        }

        $constant = strtoupper($id);

        return defined($constant) ? $constant : null;
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $value
     */
    private function sanitize(array $field, $value): string
    {
        $id = $field['id'];

        // A field pinned by a constant ignores whatever was posted, so
        // wp-config.php cannot be overridden from the form.
        if ($this->locked_constant($id) !== null) {
            return (string) get_option($id, '');
        }

        if (!empty($field['secret'])) {
            return $this->sanitize_secret($id, $value);
        }

        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return (string) call_user_func($field['sanitize_callback'], $value);
        }

        $value = is_scalar($value) ? (string) $value : '';

        switch ($field['type'] ?? 'text') {
            case 'url':
                return esc_url_raw(trim($value));
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'checkbox':
                return $value === '1' ? '1' : '0';
            case 'number':
                if (!is_numeric($value)) {
                    return isset($field['default']) ? (string) $field['default'] : '';
                }
                $number = $value + 0;
                if (isset($field['min'])) {
                    $number = max($field['min'], $number);
                }
                if (isset($field['max'])) {
                    $number = min($field['max'], $number);
                }
                return (string) $number;
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * An empty value means "keep the current one" (secret fields are rendered
     * without a value); the Clear button posts clear_name = option id.
     * Nonce and capability are already checked by options.php.
     *
     * @param mixed $value
     */
    private function sanitize_secret(string $id, $value): string
    {
        $clear_name = (string) $this->config['clear_name'];
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by options.php (settings_fields).
        $clear = isset($_POST[$clear_name]) ? sanitize_key(wp_unslash($_POST[$clear_name])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($clear === $id) {
            return '';
        }

        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '') {
            return (string) get_option($id, '');
        }

        return sanitize_text_field($value);
    }
}
