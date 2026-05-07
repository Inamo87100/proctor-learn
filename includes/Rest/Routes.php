<?php

namespace TLPC\Rest;

use TLPC\Settings;
use TLPC\Tutor\AttemptService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Routes {
    private AttemptService $attempt_service;

    public function __construct() {
        $this->attempt_service = new AttemptService();
    }

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

        register_rest_route('tlpc/v1', '/force-submit', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_force_submit'],
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

        $settings = $this->read_settings();
        if ($settings instanceof WP_REST_Response) {
            return $settings;
        }
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

        return new WP_REST_Response([
            'ok' => true,
            'invalidate' => $invalidate,
            'max' => $max,
        ]);
    }

    public function handle_force_submit(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $course_id = absint($params['course_id'] ?? 0);
        $quiz_id = absint($params['quiz_id'] ?? 0);
        $reason = sanitize_text_field($params['reason'] ?? '');

        if (!$course_id || !$quiz_id) {
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_params'], 400);
        }

        $settings = $this->read_settings();
        if ($settings instanceof WP_REST_Response) {
            return $settings;
        }
        $enabled_courses = $settings['enabled_course_ids'] ?? [];
        if (!in_array($course_id, $enabled_courses, true)) {
            return new WP_REST_Response(['ok' => true, 'ignored' => true]);
        }

        $result = $this->attempt_service->force_submit_quiz_with_zero_answers(get_current_user_id(), $quiz_id, $course_id, $reason);

        $status = 200;
        if (empty($result['ok'])) {
            $status = match ($result['error'] ?? '') {
                'missing_tables' => 503,
                'missing_attempt' => 404,
                'attempt_not_active' => 409,
                default => 500,
            };
        }

        return new WP_REST_Response($result, $status);
    }

    public function handle_preflight_pass(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['ok' => true, 'deprecated' => true]);
    }

    /**
     * Read plugin settings for REST handlers.
     *
     * @return array|WP_REST_Response Settings array on success, JSON error response on failure.
     */
    private function read_settings() {
        try {
            if (!class_exists(Settings::class)) {
                throw new \RuntimeException('Settings class unavailable');
            }
            $settings = Settings::get_settings();
        } catch (\Throwable $e) {
            return new WP_REST_Response(['ok' => false, 'error' => 'settings_unavailable'], 500);
        }

        if (!is_array($settings)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'settings_unavailable'], 500);
        }

        return $settings;
    }
}
