<?php

namespace TLPC;

use TLPC\Admin\SettingsPage;
use TLPC\Rest\Routes;
use TLPC\Tutor\Integration;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {
    public function init(): void {
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    public function on_plugins_loaded(): void {
        // Admin settings
        if (is_admin()) {
            require_once TLPC_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
            (new SettingsPage())->init();
        }

        // REST API
        require_once TLPC_PLUGIN_DIR . 'includes/Tutor/AttemptService.php';
        require_once TLPC_PLUGIN_DIR . 'includes/Rest/Routes.php';
        (new Routes())->init();

        // TutorLMS integration + assets
        require_once TLPC_PLUGIN_DIR . 'includes/Tutor/Integration.php';
        (new Integration())->init();
    }
}
