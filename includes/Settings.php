<?php

namespace TLPC;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight settings reader available on both admin and frontend.
 * The admin UI is handled separately by TLPC\Admin\SettingsPage.
 */
final class Settings {
    public const OPTION_KEY = 'tlpc_settings';

    public static function get_settings(): array {
        $defaults = [
            'enabled_course_ids'        => [],
            'max_tab_switches_default'  => 1,
            'max_tab_switches_by_course' => [],
            'preflight_required'        => 1,
        ];

        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge($defaults, $settings);
    }
}
