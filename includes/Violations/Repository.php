<?php

namespace TLPC\Violations;

if (!defined('ABSPATH')) {
    exit;
}

final class Repository {
    public const DB_VERSION = '1';
    private const DB_VERSION_OPTION = 'tlpc_violations_db_version';

    public static function maybe_upgrade_schema(): void {
        $current_version = (string) get_option(self::DB_VERSION_OPTION, '');
        if ($current_version === self::DB_VERSION) {
            return;
        }

        $repository = new self();
        if ($repository->ensure_table()) {
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        }
    }

    public function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'tlpc_violations';
    }

    public function ensure_table(): bool {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            attempt_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
            course_id bigint(20) unsigned NOT NULL DEFAULT 0,
            quiz_title varchar(255) NOT NULL DEFAULT '',
            reason varchar(191) NOT NULL DEFAULT '',
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY occurred_at (occurred_at),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY course_id (course_id)
        ) {$charset_collate};";

        dbDelta($sql);

        return $this->table_exists($table_name);
    }

    public function insert_violation(array $data): bool {
        global $wpdb;

        if (!$this->table_exists($this->table_name())) {
            return false;
        }

        $row = [
            'user_id' => absint($data['user_id'] ?? 0),
            'attempt_id' => absint($data['attempt_id'] ?? 0),
            'first_name' => sanitize_text_field((string) ($data['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($data['last_name'] ?? '')),
            'email' => sanitize_email((string) ($data['email'] ?? '')),
            'quiz_id' => absint($data['quiz_id'] ?? 0),
            'course_id' => absint($data['course_id'] ?? 0),
            'quiz_title' => sanitize_text_field((string) ($data['quiz_title'] ?? '')),
            'reason' => sanitize_text_field((string) ($data['reason'] ?? '')),
            'occurred_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert(
            $this->table_name(),
            $row,
            ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            do_action('tlpc_violation_insert_failed', $row, $wpdb->last_error);
            return false;
        }

        return true;
    }

    public function count_items(string $search_term = ''): int {
        global $wpdb;

        if (!$this->table_exists($this->table_name())) {
            return 0;
        }

        $search_term = trim($search_term);
        if ($search_term === '') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()}");
        }

        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name()} WHERE email LIKE %s OR first_name LIKE %s OR last_name LIKE %s",
            $like,
            $like,
            $like
        );

        return (int) $wpdb->get_var($query);
    }

    public function get_items(int $per_page, int $page_number, string $date_order = 'DESC', string $search_term = ''): array {
        global $wpdb;

        if (!$this->table_exists($this->table_name())) {
            return [];
        }

        $order_sql = strtoupper($date_order) === 'ASC' ? 'ASC' : 'DESC';
        $per_page = max(1, $per_page);
        $offset = max(0, ($page_number - 1) * $per_page);
        $search_term = trim($search_term);

        $where_sql = '';
        $query_args = [];

        if ($search_term !== '') {
            $like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_sql = ' WHERE email LIKE %s OR first_name LIKE %s OR last_name LIKE %s';
            $query_args[] = $like;
            $query_args[] = $like;
            $query_args[] = $like;
        }

        $query_template = "SELECT id, user_id, attempt_id, first_name, last_name, email, quiz_id, course_id, quiz_title, reason, occurred_at
            FROM {$this->table_name()}
            {$where_sql}
            ORDER BY occurred_at DESC
            LIMIT %d OFFSET %d";

        if ($order_sql === 'ASC') {
            $query_template = "SELECT id, user_id, attempt_id, first_name, last_name, email, quiz_id, course_id, quiz_title, reason, occurred_at
            FROM {$this->table_name()}
            {$where_sql}
            ORDER BY occurred_at ASC
            LIMIT %d OFFSET %d";
        }

        $query_args[] = $per_page;
        $query_args[] = $offset;

        $query = $wpdb->prepare(
            $query_template,
            ...$query_args
        );

        return (array) $wpdb->get_results($query, ARRAY_A);
    }

    private function table_exists(string $table_name): bool {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return $found === $table_name;
    }
}
