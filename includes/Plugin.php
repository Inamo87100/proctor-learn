<?php

namespace TLPC;

use TLPC\Rest\Routes;
use TLPC\Tutor\Integration;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {
    private ?string $admin_settings_notice = null;

    public function init(): void {
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    public function on_plugins_loaded(): void {
        // Settings (available on both admin and frontend)
        require_once TLPC_PLUGIN_DIR . 'includes/Settings.php';

        // Admin settings UI
        if (is_admin()) {
            $this->init_admin_settings_page();
        }

        // REST API
        require_once TLPC_PLUGIN_DIR . 'includes/Tutor/AttemptService.php';
        require_once TLPC_PLUGIN_DIR . 'includes/Rest/Routes.php';
        (new Routes())->init();

        // TutorLMS integration + assets
        require_once TLPC_PLUGIN_DIR . 'includes/Tutor/Integration.php';
        (new Integration())->init();
    }

    private function init_admin_settings_page(): void {
        $settings_page_file = TLPC_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
        $settings_page_class = 'TLPC\\Admin\\SettingsPage';

        if (!file_exists($settings_page_file)) {
            $this->queue_admin_settings_notice(__('TutorLMS Proctor settings UI is unavailable because includes/Admin/SettingsPage.php is missing.', 'tutorlms-proctor-custom'));
            return;
        }

        require_once $settings_page_file;

        if (!class_exists($settings_page_class, false)) {
            $this->queue_admin_settings_notice(__('TutorLMS Proctor settings UI is unavailable because the SettingsPage class could not be loaded.', 'tutorlms-proctor-custom'));
            return;
        }

        (new $settings_page_class())->init();
    }

    private function queue_admin_settings_notice(string $message): void {
        $this->admin_settings_notice = $message;
        add_action('admin_notices', [$this, 'render_admin_settings_notice']);
    }

    public function render_admin_settings_notice(): void {
        if (!$this->admin_settings_notice || !current_user_can('manage_options')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html($this->admin_settings_notice)
        );
    }
}
