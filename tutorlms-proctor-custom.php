<?php
/**
 * Plugin Name: TutorLMS Proctor (Custom)
 * Description: Proctoring personalizzato per TutorLMS: pre-flight per corso + tab-switch invalidation.
 * Version: 0.1.1
 * Author: Inamo87100
 * License: GPLv2 or later
 * Text Domain: tutorlms-proctor-custom
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TLPC_VERSION', '0.1.1');
define('TLPC_PLUGIN_FILE', __FILE__);
define('TLPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TLPC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TLPC_PLUGIN_DIR . 'includes/Plugin.php';
require_once TLPC_PLUGIN_DIR . 'includes/Violations/Repository.php';

function tlpc_activate_plugin(): void {
    TLPC\Violations\Repository::maybe_upgrade_schema();
}

register_activation_hook(TLPC_PLUGIN_FILE, 'tlpc_activate_plugin');

function tlpc_run_plugin(): void {
    $plugin = new TLPC\Plugin();
    $plugin->init();
}

tlpc_run_plugin();
