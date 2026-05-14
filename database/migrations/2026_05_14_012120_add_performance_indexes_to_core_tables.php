<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes are based on current controller/service query patterns:
     * filters, joins, existence checks, and date-desc pagination.
     *
     * @var array<string, list<array{0: list<string>, 1: string}>>
     */
    private const INDEXES = [
        'users' => [
            [['created_at'], 'users_created_at_idx'],
            [['is_active', 'created_at'], 'users_active_created_idx'],
            [['user_type', 'created_at'], 'users_type_created_idx'],
            [['office', 'created_at'], 'users_office_created_idx'],
            [['department', 'created_at'], 'users_dept_created_idx'],
            [['office_id', 'created_at'], 'users_office_id_created_idx'],
            [['campus_id', 'is_active', 'user_type'], 'users_campus_active_type_idx'],
        ],
        'permissions' => [
            [['module', 'name'], 'permissions_module_name_idx'],
        ],
        'role_has_permissions' => [
            [['role_id'], 'role_has_permissions_role_idx'],
        ],
        'achievements' => [
            [['user_id', 'date_received'], 'achievements_user_date_idx'],
        ],
        'trainings' => [
            [['user_id', 'date_from'], 'trainings_user_date_idx'],
        ],
        'announcements' => [
            [['status', 'created_at'], 'ann_status_created_idx'],
            [['category_id', 'created_at'], 'ann_category_created_idx'],
            [['priority', 'created_at'], 'ann_priority_created_idx'],
            [['status', 'visibility', 'published_at'], 'ann_status_visibility_pub_idx'],
            [['status', 'is_pinned', 'published_at'], 'ann_status_pinned_pub_idx'],
        ],
        'announcement_attachments' => [
            [['announcement_id', 'created_at'], 'ann_attach_announcement_created_idx'],
        ],
        'announcement_targets' => [
            [['announcement_id'], 'ann_targets_announcement_idx'],
            [['role_id'], 'ann_targets_role_idx'],
            [['office_id'], 'ann_targets_office_idx'],
            [['department_id'], 'ann_targets_department_idx'],
            [['campus_id'], 'ann_targets_campus_idx'],
        ],
        'announcement_activity_logs' => [
            [['user_id', 'created_at'], 'ann_logs_user_created_idx'],
            [['model_type', 'model_id', 'created_at'], 'ann_logs_model_created_idx'],
        ],
        'internet_account_requests' => [
            [['user_id', 'created_at'], 'internet_user_created_idx'],
            [['status', 'created_at'], 'internet_status_created_idx'],
            [['term_id', 'status', 'created_at'], 'internet_term_status_created_idx'],
        ],
        'semesters' => [
            [['campus_id', 'is_active'], 'semesters_campus_active_idx'],
            [['is_active', 'start_date', 'end_date'], 'semesters_active_dates_idx'],
        ],
        'offices' => [
            [['name'], 'offices_name_idx'],
            [['code'], 'offices_code_idx'],
        ],
        'clearance_updates' => [
            [['status', 'created_at'], 'clear_updates_status_created_idx'],
            [['semester_id', 'status', 'created_at'], 'clear_updates_sem_status_created_idx'],
            [['status', 'start_date', 'end_date'], 'clear_updates_status_dates_idx'],
        ],
        'clearance_update_offices' => [
            [['clearance_update_id', 'office_id'], 'clear_update_offices_update_office_idx'],
            [['office_id', 'sequence'], 'clear_update_offices_office_seq_idx'],
        ],
        'student_semester_clearances' => [
            [['student_id', 'created_at'], 'student_clear_student_created_idx'],
            [['student_id', 'status', 'created_at'], 'student_clear_student_status_idx'],
            [['clearance_update_id', 'status'], 'student_clear_update_status_idx'],
        ],
        'clearance_accountabilities' => [
            [['clearance_update_id', 'status', 'created_at'], 'clear_acc_update_status_created_idx'],
            [['student_id', 'clearance_update_id', 'status'], 'clear_acc_student_update_status_idx'],
            [['office_id', 'status', 'created_at'], 'clear_acc_office_status_created_idx'],
            [['parent_id'], 'clear_acc_parent_idx'],
        ],
        'clearance_accountability_uploads' => [
            [['clearance_update_id', 'office_id', 'created_at'], 'clear_acc_uploads_update_office_idx'],
            [['status', 'created_at'], 'clear_acc_uploads_status_created_idx'],
        ],
        'clearance_logs' => [
            [['clearance_update_id', 'created_at'], 'clear_logs_update_created_idx'],
            [['student_id', 'created_at'], 'clear_logs_student_created_idx'],
            [['student_semester_clearance_id', 'created_at'], 'clear_logs_clearance_created_idx'],
        ],
        'clearance_certificates' => [
            [['student_semester_clearance_id'], 'clear_certs_clearance_idx'],
            [['issued_by', 'issued_at'], 'clear_certs_issued_by_at_idx'],
        ],
        'evaluation_periods' => [
            [['status', 'created_at'], 'eval_periods_status_created_idx'],
            [['status', 'start_date', 'end_date'], 'eval_periods_status_dates_idx'],
        ],
        'evaluation_requests' => [
            [['student_id', 'created_at'], 'eval_requests_student_created_idx'],
            [['evaluation_period_id', 'status', 'created_at'], 'eval_requests_period_status_idx'],
            [['status', 'created_at'], 'eval_requests_status_created_idx'],
            [['evaluated_by', 'evaluated_at'], 'eval_requests_evaluated_by_at_idx'],
        ],
        'evaluation_feedbacks' => [
            [['evaluation_request_id', 'visibility', 'created_at'], 'eval_feedback_request_visibility_idx'],
            [['user_id', 'created_at'], 'eval_feedback_user_created_idx'],
        ],
        'evaluation_activity_logs' => [
            [['model_type', 'model_id', 'created_at'], 'eval_logs_model_created_idx'],
            [['user_id', 'created_at'], 'eval_logs_user_created_idx'],
        ],
        'faq_categories' => [
            [['is_active', 'visibility', 'sort_order'], 'faq_categories_active_visible_idx'],
            [['sort_order'], 'faq_categories_sort_idx'],
        ],
        'faqs' => [
            [['status', 'visibility', 'sort_order'], 'faqs_status_visible_sort_idx'],
            [['faq_category_id', 'status', 'sort_order'], 'faqs_category_status_sort_idx'],
            [['is_featured', 'status'], 'faqs_featured_status_idx'],
            [['status', 'published_at'], 'faqs_status_published_idx'],
        ],
        'faq_feedback' => [
            [['faq_id', 'created_at'], 'faq_feedback_faq_created_idx'],
            [['user_id', 'created_at'], 'faq_feedback_user_created_idx'],
            [['faq_id', 'is_helpful'], 'faq_feedback_faq_helpful_idx'],
        ],
        'faq_search_logs' => [
            [['keyword', 'created_at'], 'faq_search_keyword_created_idx'],
            [['user_id', 'created_at'], 'faq_search_user_created_idx'],
        ],
        'site_campuses' => [
            [['status'], 'site_campuses_status_idx'],
            [['campus_name'], 'site_campuses_name_idx'],
        ],
        'site_academic_terms' => [
            [['status'], 'site_terms_status_idx'],
            [['site_campus_id', 'status'], 'site_terms_campus_status_idx'],
            [['site_campus_id', 'status', 'start_date', 'end_date'], 'site_terms_campus_status_dates_idx'],
        ],
        'site_grade_viewing_rules' => [
            [['site_campus_id', 'is_active', 'bypass_evaluation'], 'grade_rules_campus_active_bypass_idx'],
        ],
        'site_grade_viewing_logs' => [
            [['rule_id', 'created_at'], 'grade_logs_rule_created_idx'],
            [['user_id', 'created_at'], 'grade_logs_user_created_idx'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as [$columns, $name]) {
                $this->addIndexIfMissing($table, $columns, $name);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as $table => $indexes) {
            foreach (array_reverse($indexes) as [, $name]) {
                $this->dropIndexIfExists($table, $name);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! $this->tableHasColumns($tableName, $columns) || $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function tableHasColumns(string $tableName, array $columns): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if (strtolower($index['name'] ?? '') === strtolower($indexName)) {
                return true;
            }
        }

        return false;
    }
};
