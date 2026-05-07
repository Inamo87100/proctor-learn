<?php

namespace TLPC\Admin;

use TLPC\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsPage {
    /** @deprecated Use Settings::OPTION_KEY */
    public const OPTION_KEY = Settings::OPTION_KEY;

    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu(): void {
        add_options_page(
            __('TutorLMS Proctor', 'tutorlms-proctor-custom'),
            __('TutorLMS Proctor', 'tutorlms-proctor-custom'),
            'manage_options',
            'tlpc-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting('tlpc_settings_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'enabled_course_ids' => [],
                'max_tab_switches_default' => 1,
                'max_tab_switches_by_course' => [],
                'preflight_required' => 1,
            ],
        ]);

        add_settings_section('tlpc_main', __('Impostazioni', 'tutorlms-proctor-custom'), function () {
            echo '<p>' . esc_html__('Configura il proctoring per TutorLMS.', 'tutorlms-proctor-custom') . '</p>';
        }, 'tlpc-settings');

        add_settings_field('enabled_course_ids', __('Corsi con proctor attivo', 'tutorlms-proctor-custom'), [$this, 'field_enabled_courses'], 'tlpc-settings', 'tlpc_main');
        add_settings_field('max_tab_switches_default', __('Max tab switch (default)', 'tutorlms-proctor-custom'), [$this, 'field_max_tab_switches_default'], 'tlpc-settings', 'tlpc_main');
        add_settings_field('preflight_required', __('Pre-flight richiesto (solo senza tentativi nel corso)', 'tutorlms-proctor-custom'), [$this, 'field_preflight_required'], 'tlpc-settings', 'tlpc_main');

        // (Optional) per-course override potrebbe diventare una tabella, per ora lasciamo base.
    }

    public function sanitize_settings($input): array {
        $out = [];

        $out['enabled_course_ids'] = array_values(array_filter(array_map('absint', $input['enabled_course_ids'] ?? [])));
        $out['max_tab_switches_default'] = max(0, absint($input['max_tab_switches_default'] ?? 1));
        $out['preflight_required'] = !empty($input['preflight_required']) ? 1 : 0;

        // Placeholder per override per corso.
        $out['max_tab_switches_by_course'] = is_array($input['max_tab_switches_by_course'] ?? null) ? $input['max_tab_switches_by_course'] : [];

        return $out;
    }

    /** @deprecated Use Settings::get_settings() */
    public static function get_settings(): array {
        return Settings::get_settings();
    }

    public function field_enabled_courses(): void {
        $settings = self::get_settings();
        $enabled = $settings['enabled_course_ids'] ?? [];

        if (!post_type_exists('courses')) {
            echo '<p><strong>' . esc_html__('TutorLMS non sembra attivo (post type corsi non trovato).', 'tutorlms-proctor-custom') . '</strong></p>';
            return;
        }

        $courses = get_posts([
            'post_type' => 'courses',
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        if (!$courses) {
            echo '<p>' . esc_html__('Nessun corso trovato.', 'tutorlms-proctor-custom') . '</p>';
            return;
        }

        echo '<div style="max-height: 280px; overflow:auto; border:1px solid #ccd0d4; padding:10px; background:#fff;">';
        foreach ($courses as $course) {
            $checked = in_array((int) $course->ID, $enabled, true) ? 'checked' : '';
            printf(
                '<label style="display:block; margin: 6px 0;"><input type="checkbox" name="%s[enabled_course_ids][]" value="%d" %s> %s (#%d)</label>',
                esc_attr(self::OPTION_KEY),
                (int) $course->ID,
                $checked,
                esc_html($course->post_title),
                (int) $course->ID
            );
        }
        echo '</div>';
    }

    public function field_max_tab_switches_default(): void {
        $settings = self::get_settings();
        printf(
            '<input type="number" min="0" step="1" name="%s[max_tab_switches_default]" value="%d" class="small-text" />',
            esc_attr(self::OPTION_KEY),
            (int) ($settings['max_tab_switches_default'] ?? 1)
        );
        echo '<p class="description">' . esc_html__('Numero massimo di tab switch/perdita focus consentiti prima di invalidare il quiz.', 'tutorlms-proctor-custom') . '</p>';
    }

    public function field_preflight_required(): void {
        $settings = self::get_settings();
        $checked = !empty($settings['preflight_required']) ? 'checked' : '';
        printf(
            '<label><input type="checkbox" name="%s[preflight_required]" value="1" %s> %s</label>',
            esc_attr(self::OPTION_KEY),
            $checked,
            esc_html__('Mostra il pre-flight solo se l’utente non ha ancora alcun tentativo quiz nel corso.', 'tutorlms-proctor-custom')
        );
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('TutorLMS Proctor', 'tutorlms-proctor-custom') . '</h1>';
        echo '<form method="post" action="options.php">';

        settings_fields('tlpc_settings_group');
        do_settings_sections('tlpc-settings');
        submit_button();

        echo '</form>';
        echo '</div>';
    }
}
