<?php

namespace TLPC\Tutor;

if (!defined('ABSPATH')) {
    exit;
}

final class AttemptService {
    public function user_has_course_attempt(int $user_id, int $course_id): bool {
        global $wpdb;

        if (!$user_id || !$course_id || !$this->table_exists($wpdb->prefix . 'tutor_quiz_attempts')) {
            return false;
        }

        $attempt_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempt_id
                FROM {$wpdb->prefix}tutor_quiz_attempts
                WHERE user_id = %d
                    AND course_id = %d
                ORDER BY attempt_id DESC
                LIMIT 1",
                $user_id,
                $course_id
            )
        );

        return !empty($attempt_id);
    }

    public function force_submit_quiz_with_zero_answers(int $user_id, int $quiz_id, int $course_id = 0, string $reason = ''): array {
        global $wpdb;

        $attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
        $answers_table = $wpdb->prefix . 'tutor_quiz_attempt_answers';

        if (!$user_id || !$quiz_id || !$this->table_exists($attempts_table)) {
            return [
                'ok' => false,
                'error' => 'missing_tables',
            ];
        }

        $attempt = $this->get_active_attempt($user_id, $quiz_id, $course_id);
        if (!$attempt) {
            return [
                'ok' => false,
                'error' => 'missing_attempt',
            ];
        }

        if (($attempt->attempt_status ?? '') !== 'attempt_started') {
            return [
                'ok' => false,
                'error' => 'attempt_not_active',
                'attempt_id' => (int) $attempt->attempt_id,
            ];
        }

        $reason = $reason ?: 'proctor_invalidation';
        $timestamp = $this->current_time_mysql();
        $attempt_info = maybe_unserialize($attempt->attempt_info ?? null);
        if (false === $attempt_info && !empty($attempt->attempt_info)) {
            $attempt_info = [
                'tlpc_original_attempt_info' => $attempt->attempt_info,
            ];
        }

        if (!is_array($attempt_info)) {
            $attempt_info = [];
        }

        $attempt_info['tlpc_proctor_invalidation'] = [
            'invalidated' => true,
            'reason' => $reason,
            'timestamp' => $timestamp,
            'quiz_id' => (int) $quiz_id,
            'course_id' => (int) ($course_id ?: ($attempt->course_id ?? 0)),
        ];

        do_action('tutor_quiz_before_finish', (int) $attempt->attempt_id, $quiz_id, $user_id);

        if ($this->table_exists($answers_table)) {
            $wpdb->delete($answers_table, ['quiz_attempt_id' => (int) $attempt->attempt_id], ['%d']);
        }

        $updated = $wpdb->update(
            $attempts_table,
            [
                'total_answered_questions' => 0,
                'earned_marks' => 0,
                'attempt_status' => 'attempt_ended',
                'attempt_ended_at' => $timestamp,
                'attempt_info' => maybe_serialize($attempt_info),
            ],
            [
                'attempt_id' => (int) $attempt->attempt_id,
            ],
            ['%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return [
                'ok' => false,
                'error' => 'update_failed',
                'attempt_id' => (int) $attempt->attempt_id,
            ];
        }

        if (class_exists('\\Tutor\\Models\\QuizModel') && method_exists('\\Tutor\\Models\\QuizModel', 'update_attempt_result')) {
            try {
                \Tutor\Models\QuizModel::update_attempt_result((int) $attempt->attempt_id);
            } catch (\Throwable $throwable) {
                do_action('tlpc_attempt_result_update_failed', (int) $attempt->attempt_id, $throwable, $attempt);
            }
        }

        update_user_meta($user_id, 'tlpc_last_invalidated_attempt', [
            'attempt_id' => (int) $attempt->attempt_id,
            'quiz_id' => (int) $quiz_id,
            'course_id' => (int) ($course_id ?: ($attempt->course_id ?? 0)),
            'reason' => $reason,
            'timestamp' => $timestamp,
        ]);

        do_action('tutor_quiz_finished', (int) $attempt->attempt_id, $quiz_id, $user_id);

        return [
            'ok' => true,
            'attempt_id' => (int) $attempt->attempt_id,
            'attempt_status' => 'attempt_ended',
            'invalidated' => true,
            'reason' => $reason,
        ];
    }

    private function get_active_attempt(int $user_id, int $quiz_id, int $course_id = 0): ?object {
        global $wpdb;

        $conditions = ['user_id = %d', 'quiz_id = %d'];
        $values = [$user_id, $quiz_id];

        if ($course_id > 0) {
            $conditions[] = 'course_id = %d';
            $values[] = $course_id;
        }

        $conditions[] = 'attempt_status = %s';
        $values[] = 'attempt_started';

        $query = "SELECT *
            FROM {$wpdb->prefix}tutor_quiz_attempts
            WHERE " . implode(' AND ', $conditions) . '
            ORDER BY attempt_id DESC
            LIMIT 1';

        return $wpdb->get_row($wpdb->prepare($query, $values));
    }

    private function table_exists(string $table_name): bool {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return $found === $table_name;
    }

    private function current_time_mysql(): string {
        if (function_exists('tutor_time')) {
            return date('Y-m-d H:i:s', tutor_time());
        }

        return current_time('mysql');
    }
}
