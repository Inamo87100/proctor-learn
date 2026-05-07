<?php

namespace TLPC\Tutor;

use TLPC\Admin\SettingsPage;
use Tutor\Models\CourseModel;

if (!defined('ABSPATH')) {
    exit;
}

final class Integration {
    private AttemptService $attempt_service;

    public function __construct() {
        $this->attempt_service = new AttemptService();
    }

    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

    }

    public function enqueue_assets(): void {
        if (is_admin()) {
            return;
        }

        // Carichiamo script solo su pagine che sembrano quiz TutorLMS.
        // In TutorLMS, i quiz sono un post type 'tutor_quiz' (come nel plugin di spunto).
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tutor_quiz') {
            return;
        }

        $course_id = $this->resolve_course_id_for_quiz($post_id);
        if (!$course_id) {
            return;
        }

        $settings = SettingsPage::get_settings();
        $enabled_courses = $settings['enabled_course_ids'] ?? [];
        if (!in_array($course_id, $enabled_courses, true)) {
            return;
        }

        wp_enqueue_style('tlpc-proctor', TLPC_PLUGIN_URL . 'assets/css/proctor.css', [], TLPC_VERSION);
        wp_enqueue_script('tlpc-proctor', TLPC_PLUGIN_URL . 'assets/js/proctor.js', [], TLPC_VERSION, true);

        $user_id = get_current_user_id();
        $preflight_required = !empty($settings['preflight_required']);
        $preflight_passed = $preflight_required ? $this->attempt_service->user_has_course_attempt($user_id, $course_id) : true;

        wp_localize_script('tlpc-proctor', 'TLPC', [
            'restUrl' => esc_url_raw(rest_url('tlpc/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'courseId' => $course_id,
            'quizId' => $post_id,
            'preflightRequired' => $preflight_required,
            'preflightPassed' => $preflight_passed,
            'maxTabSwitches' => (int) ($settings['max_tab_switches_default'] ?? 1),
            'i18n' => [
                'preflightStartButton' => __('Inizia (attiva fullscreen)', 'tutorlms-proctor-custom'),
                'preflightFailedTitle' => __('Pre-flight non superato', 'tutorlms-proctor-custom'),
                'preflightFailedMessage' => __('Non riesco ad attivare la modalità fullscreen. Abilitala per continuare.', 'tutorlms-proctor-custom'),
                'preflightTitle' => __('Controllo pre-esame', 'tutorlms-proctor-custom'),
                'preflightMessage' => __('<p>Prima di iniziare il primo quiz di questo corso devi completare un controllo rapido.</p><ul><li>Fullscreen obbligatorio</li><li>Non cambiare tab durante il quiz</li></ul>', 'tutorlms-proctor-custom'),
                'invalidationTitle' => __('Tentativo invalidato', 'tutorlms-proctor-custom'),
                'invalidationSubmittingMessage' => __('<p>Hai cambiato scheda/finestra troppe volte. Sto consegnando il quiz con 0 risposte.</p>', 'tutorlms-proctor-custom'),
                'invalidationSuccessMessage' => __('<p>Il quiz è stato invalidato e consegnato con 0 risposte.</p>', 'tutorlms-proctor-custom'),
                'invalidationErrorMessage' => __('<p>Non sono riuscito a completare il force submit. Il quiz resta comunque bloccato e la pagina verrà ricaricata.</p>', 'tutorlms-proctor-custom'),
            ],
        ]);
    }

    private function resolve_course_id_for_quiz(int $quiz_id): int {
        if (class_exists(CourseModel::class) && method_exists(CourseModel::class, 'get_course_by_quiz')) {
            $course = CourseModel::get_course_by_quiz($quiz_id);
            if (is_object($course) && !empty($course->ID)) {
                return (int) $course->ID;
            }
        }

        $direct = (int) get_post_meta($quiz_id, 'tutor_course_id', true);
        if ($direct) {
            return $direct;
        }

        $ancestors = get_post_ancestors($quiz_id);
        foreach (array_filter(array_map('absint', $ancestors)) as $ancestor_id) {
            $parent_course = (int) get_post_meta($ancestor_id, 'tutor_course_id', true);
            if ($parent_course) {
                return $parent_course;
            }

            $ancestor = get_post($ancestor_id);
            if ($ancestor && $ancestor->post_type === 'courses') {
                return (int) $ancestor_id;
            }
        }

        return 0;
    }
}
