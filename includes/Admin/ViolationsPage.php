<?php

namespace TLPC\Admin;

use TLPC\Violations\Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class ViolationsPage {
    private Repository $repository;

    public function __construct() {
        $this->repository = new Repository();
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void {
        add_submenu_page(
            'tlpc-settings',
            __('Infrazioni', 'tutorlms-proctor-custom'),
            __('Infrazioni', 'tutorlms-proctor-custom'),
            'manage_options',
            'tlpc-violations',
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!class_exists(\WP_List_Table::class)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $list_table = new ViolationsListTable($this->repository);
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Infrazioni Proctor', 'tutorlms-proctor-custom') . '</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="tlpc-violations" />';
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }
}

final class ViolationsListTable extends \WP_List_Table {
    private Repository $repository;

    public function __construct(Repository $repository) {
        $this->repository = $repository;
        parent::__construct([
            'singular' => 'tlpc-violation',
            'plural' => 'tlpc-violations',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array {
        return [
            'name' => __('Name', 'tutorlms-proctor-custom'),
            'email' => __('Email', 'tutorlms-proctor-custom'),
            'quiz' => __('Quiz', 'tutorlms-proctor-custom'),
            'course' => __('Course', 'tutorlms-proctor-custom'),
            'reason' => __('Reason', 'tutorlms-proctor-custom'),
            'occurred_at' => __('Date/Time', 'tutorlms-proctor-custom'),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'occurred_at' => ['occurred_at', true],
        ];
    }

    public function prepare_items(): void {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $order_by = sanitize_key((string) ($_GET['orderby'] ?? 'occurred_at'));
        $requested_order = strtolower((string) ($_GET['order'] ?? 'desc'));
        $order = 'desc';

        if ($order_by === 'occurred_at' && $requested_order === 'asc') {
            $order = 'asc';
        }

        $this->items = $this->repository->get_items($per_page, $current_page, $order);

        $this->set_pagination_args([
            'total_items' => $this->repository->count_items(),
            'per_page' => $per_page,
            'total_pages' => (int) ceil($this->repository->count_items() / $per_page),
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    public function column_default($item, $column_name): string {
        switch ($column_name) {
            case 'name':
                $full_name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
                if ($full_name === '') {
                    $full_name = sprintf(__('User #%d', 'tutorlms-proctor-custom'), (int) ($item['user_id'] ?? 0));
                }
                return esc_html($full_name);
            case 'email':
                return esc_html((string) ($item['email'] ?? ''));
            case 'quiz':
                $quiz_id = (int) ($item['quiz_id'] ?? 0);
                $quiz_title = (string) ($item['quiz_title'] ?? '');
                if ($quiz_title === '' && $quiz_id > 0) {
                    $quiz_title = (string) get_the_title($quiz_id);
                }
                return esc_html(trim($quiz_title) !== '' ? sprintf('%s (#%d)', $quiz_title, $quiz_id) : sprintf('Quiz #%d', $quiz_id));
            case 'course':
                $course_id = (int) ($item['course_id'] ?? 0);
                $course_title = $course_id > 0 ? (string) get_the_title($course_id) : '';
                return esc_html(trim($course_title) !== '' ? sprintf('%s (#%d)', $course_title, $course_id) : sprintf('Course #%d', $course_id));
            case 'reason':
                return esc_html((string) ($item['reason'] ?? ''));
            case 'occurred_at':
                return esc_html((string) ($item['occurred_at'] ?? ''));
            default:
                return '';
        }
    }
}
