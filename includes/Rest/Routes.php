<?php

namespace TLPC\Rest;

use TLPC\Admin\SettingsPage;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Routes {
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('tlpc/v1', '/event', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_event'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('tlpc/v1', '/preflight-pass', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_preflight_pass'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function handle_event(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $course_id = absint($params['course_id'] ?? 0);
        $quiz_id = absint($params['quiz_id'] ?? 0);
        $event = sanitize_text_field($params['event'] ?? '');
        $count = absint($params['count'] ?? 0);

        if (!$course_id || !$quiz_id || !$event) {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_params'], 400);
        }

        $settings = SettingsPage::get_settings();
        $enabled_courses = $settings['enabled_course_ids'] ?? [];
        if (!in_array($course_id, $enabled_courses, true)) {
            return new WP_REST_Response(['ok' => true, 'ignored' => true]);
        }

        $max = (int) ($settings['max_tab_switches_default'] ?? 1);

        // Log minimo (puoi espandere su custom table).
        $user_id = get_current_user_id();
        update_user_meta($user_id, "tlpc_last_event_{$course_id}", [
            't' => time(),
            'quiz_id' => $quiz_id,
            'event' => $event,
            'count' => $count,
        ]);

        $invalidate = ($count > $max);

        // TODO: qui va chiamata l'integrazione TutorLMS per autosubmit con 0 risposte.
        // Per ora ritorniamo al client che deve bloccare la UI e chiedere reload.

        return new WP_REST_Response([
            'ok' => true,
            'invalidate' => $invalidate,
            'max' => $max,
        ]);
    }

    public function handle_preflight_pass(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $course_id = absint($params['course_id'] ?? 0);
        if (!$course_id) {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_course_id'], 400);
        }

        $user_id = get_current_user_id();
        update_user_meta($user_id, "tlpc_preflight_passed_{$course_id}", time());

        return new WP_REST_Response(['ok' => true]);
    }
}
