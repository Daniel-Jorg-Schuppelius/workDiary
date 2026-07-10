CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "team_id" integer,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "roles_team_foreign_key_index" on "roles"("team_id");
CREATE UNIQUE INDEX "roles_team_id_name_guard_name_unique" on "roles"(
  "team_id",
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "team_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("team_id", "permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE INDEX "model_has_permissions_team_foreign_key_index" on "model_has_permissions"(
  "team_id"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "team_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("team_id", "role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE INDEX "model_has_roles_team_foreign_key_index" on "model_has_roles"(
  "team_id"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "taggables"(
  "tag_id" integer not null,
  "taggable_type" varchar not null,
  "taggable_id" integer not null,
  foreign key("tag_id") references "tags"("id") on delete cascade,
  primary key("tag_id", "taggable_id", "taggable_type")
);
CREATE INDEX "taggables_taggable_type_taggable_id_index" on "taggables"(
  "taggable_type",
  "taggable_id"
);
CREATE TABLE IF NOT EXISTS "organizations"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "plan" varchar not null default 'free',
  "locale" varchar not null default 'de',
  "timezone" varchar not null default 'Europe/Berlin',
  "settings" text,
  "is_active" tinyint(1) not null default '1',
  "owner_id" integer,
  "trial_ends_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deactivated_at" datetime,
  "is_demo" tinyint(1) not null default '0',
  "demo_seeded_at" datetime,
  "two_factor_required" tinyint(1) not null default '0',
  "tenant_status" varchar,
  "license_key" text,
  "license_uid" varchar,
  "legal_region" varchar not null default 'DE',
  foreign key("owner_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "organizations_slug_unique" on "organizations"("slug");
CREATE TABLE IF NOT EXISTS "on_call_shifts"(
  "id" integer primary key autoincrement not null,
  "legacy_id" integer,
  "user_id" integer not null,
  "start_at" datetime not null,
  "end_at" datetime not null,
  "note" varchar,
  "is_archived" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "on_call_shifts_is_archived_index" on "on_call_shifts"(
  "is_archived"
);
CREATE UNIQUE INDEX "on_call_shifts_legacy_id_unique" on "on_call_shifts"(
  "legacy_id"
);
CREATE INDEX "on_call_shifts_start_at_end_at_index" on "on_call_shifts"(
  "start_at",
  "end_at"
);
CREATE INDEX "on_call_shifts_user_id_start_at_end_at_index" on "on_call_shifts"(
  "user_id",
  "start_at",
  "end_at"
);
CREATE INDEX "idx_on_call_shifts_org" on "on_call_shifts"("organization_id");
CREATE TABLE IF NOT EXISTS "emergency_assignments"(
  "id" integer primary key autoincrement not null,
  "legacy_id" integer,
  "user_id" integer not null,
  "on_call_shift_id" integer,
  "start_at" datetime not null,
  "end_at" datetime not null,
  "reason" varchar,
  "is_archived" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("on_call_shift_id") references on_call_shifts("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "emergency_assignments_is_archived_index" on "emergency_assignments"(
  "is_archived"
);
CREATE UNIQUE INDEX "emergency_assignments_legacy_id_unique" on "emergency_assignments"(
  "legacy_id"
);
CREATE INDEX "emergency_assignments_start_at_end_at_index" on "emergency_assignments"(
  "start_at",
  "end_at"
);
CREATE INDEX "emergency_assignments_user_id_start_at_end_at_index" on "emergency_assignments"(
  "user_id",
  "start_at",
  "end_at"
);
CREATE INDEX "idx_emergency_assignments_org" on "emergency_assignments"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "shift_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "abbreviation" varchar not null,
  "color" varchar not null default('#3b82f6'),
  "default_start_time" time,
  "default_end_time" time,
  "is_active" tinyint(1) not null default('1'),
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "idx_shift_types_org" on "shift_types"("organization_id");
CREATE TABLE IF NOT EXISTS "holidays"(
  "id" integer primary key autoincrement not null,
  "date" date not null,
  "name" varchar not null,
  "is_recurring" tinyint(1) not null default('0'),
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "recurrence_type" varchar not null default('fixed'),
  "recurrence_weekday" integer,
  "recurrence_week" integer,
  "recurrence_month" integer,
  "organization_id" integer,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "holidays_date_is_recurring_unique" on "holidays"(
  "date",
  "is_recurring"
);
CREATE INDEX "holidays_is_recurring_date_index" on "holidays"(
  "is_recurring",
  "date"
);
CREATE INDEX "idx_holidays_org" on "holidays"("organization_id");
CREATE TABLE IF NOT EXISTS "vacations"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "start_date" date not null,
  "end_date" date not null,
  "type" varchar not null default('vacation'),
  "status" varchar not null default('pending'),
  "note" text,
  "reject_reason" text,
  "decided_by" integer,
  "decided_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("decided_by") references users("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "vacations_status_start_date_index" on "vacations"(
  "status",
  "start_date"
);
CREATE INDEX "vacations_user_id_start_date_end_date_index" on "vacations"(
  "user_id",
  "start_date",
  "end_date"
);
CREATE INDEX "idx_vacations_org" on "vacations"("organization_id");
CREATE TABLE IF NOT EXISTS "tags"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "color" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "tags_slug_unique" on "tags"("slug");
CREATE INDEX "idx_tags_org" on "tags"("organization_id");
CREATE TABLE IF NOT EXISTS "audit_logs"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "event" varchar not null,
  "auditable_type" varchar not null,
  "auditable_id" integer not null,
  "changes" text,
  "ip" varchar,
  "user_agent" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "prev_hash" varchar,
  "hash" varchar,
  foreign key("user_id") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "audit_logs_auditable_type_auditable_id_index" on "audit_logs"(
  "auditable_type",
  "auditable_id"
);
CREATE INDEX "audit_logs_event_created_at_index" on "audit_logs"(
  "event",
  "created_at"
);
CREATE INDEX "idx_audit_logs_org" on "audit_logs"("organization_id");
CREATE TABLE IF NOT EXISTS "duty_plans"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "period_type" varchar check("period_type" in('daily', 'weekly', 'monthly')) not null,
  "from_date" date not null,
  "to_date" date not null,
  "status" varchar check("status" in('draft', 'published')) not null default 'draft',
  "min_staff" integer not null default '0',
  "note" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "duty_plans_organization_id_from_date_to_date_index" on "duty_plans"(
  "organization_id",
  "from_date",
  "to_date"
);
CREATE INDEX "duty_plans_organization_id_status_index" on "duty_plans"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "scheduled_shifts"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "shift_type_id" integer,
  "date" date not null,
  "start_time" time,
  "end_time" time,
  "note" text,
  "status" varchar not null default('draft'),
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "duty_plan_id" integer,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("shift_type_id") references shift_types("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("duty_plan_id") references "duty_plans"("id") on delete set null
);
CREATE INDEX "idx_scheduled_shifts_org" on "scheduled_shifts"(
  "organization_id"
);
CREATE INDEX "scheduled_shifts_date_status_index" on "scheduled_shifts"(
  "date",
  "status"
);
CREATE INDEX "scheduled_shifts_date_user_id_index" on "scheduled_shifts"(
  "date",
  "user_id"
);
CREATE INDEX "scheduled_shifts_user_id_date_index" on "scheduled_shifts"(
  "user_id",
  "date"
);
CREATE INDEX "scheduled_shifts_duty_plan_id_index" on "scheduled_shifts"(
  "duty_plan_id"
);
CREATE TABLE IF NOT EXISTS "qualifications"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "abbreviation" varchar,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "qualifications_organization_id_name_unique" on "qualifications"(
  "organization_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "user_qualifications"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "qualification_id" integer not null,
  "valid_from" date,
  "valid_until" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("qualification_id") references "qualifications"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_qualifications_user_id_qualification_id_unique" on "user_qualifications"(
  "user_id",
  "qualification_id"
);
CREATE TABLE IF NOT EXISTS "shift_type_qualifications"(
  "shift_type_id" integer not null,
  "qualification_id" integer not null,
  foreign key("shift_type_id") references "shift_types"("id") on delete cascade,
  foreign key("qualification_id") references "qualifications"("id") on delete cascade,
  primary key("shift_type_id", "qualification_id")
);
CREATE TABLE IF NOT EXISTS "milestones"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_id" integer not null,
  "created_by" integer,
  "title" varchar not null,
  "description" text,
  "due_date" date,
  "is_completed" tinyint(1) not null default '0',
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "milestones_project_id_index" on "milestones"("project_id");
CREATE TABLE IF NOT EXISTS "coverage_requirements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "duty_plan_id" integer,
  "shift_type_id" integer not null,
  "weekday" integer,
  "specific_date" date,
  "min_staff" integer not null default '1',
  "max_staff" integer,
  "required_qualification_ids" text,
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("duty_plan_id") references "duty_plans"("id") on delete cascade,
  foreign key("shift_type_id") references "shift_types"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "coverage_requirements_organization_id_duty_plan_id_index" on "coverage_requirements"(
  "organization_id",
  "duty_plan_id"
);
CREATE INDEX "coverage_requirements_duty_plan_id_weekday_index" on "coverage_requirements"(
  "duty_plan_id",
  "weekday"
);
CREATE INDEX "coverage_requirements_duty_plan_id_specific_date_index" on "coverage_requirements"(
  "duty_plan_id",
  "specific_date"
);
CREATE INDEX "coverage_requirements_shift_type_id_index" on "coverage_requirements"(
  "shift_type_id"
);
CREATE TABLE IF NOT EXISTS "materials"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "sku" varchar,
  "name" varchar not null,
  "unit" varchar not null default 'Stk.',
  "default_unit_price" numeric,
  "tax_rate" numeric,
  "external_provider" varchar,
  "external_id" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "materials_organization_id_sku_unique" on "materials"(
  "organization_id",
  "sku"
);
CREATE INDEX "materials_external_provider_external_id_index" on "materials"(
  "external_provider",
  "external_id"
);
CREATE INDEX "materials_name_index" on "materials"("name");
CREATE TABLE IF NOT EXISTS "work_schedules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "weekly_minutes" integer not null default '2400',
  "daily_target_minutes" integer not null default '480',
  "working_days" text not null,
  "core_start" time,
  "core_end" time,
  "frame_start" time,
  "frame_end" time,
  "break_after_minutes" integer not null default '360',
  "break_minutes" integer not null default '30',
  "valid_from" date not null,
  "valid_to" date,
  "created_at" datetime,
  "updated_at" datetime,
  "schedule_type" varchar not null default 'flextime',
  "day_targets" text,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "work_schedules_user_id_valid_from_unique" on "work_schedules"(
  "user_id",
  "valid_from"
);
CREATE INDEX "work_schedules_user_id_valid_from_valid_to_index" on "work_schedules"(
  "user_id",
  "valid_from",
  "valid_to"
);
CREATE TABLE IF NOT EXISTS "external_references"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plugin_id" varchar not null,
  "external_type" varchar not null,
  "referenceable_type" varchar not null,
  "referenceable_id" integer not null,
  "external_id" varchar not null,
  "payload" text,
  "synced_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "external_references_referenceable_type_referenceable_id_index" on "external_references"(
  "referenceable_type",
  "referenceable_id"
);
CREATE UNIQUE INDEX "extref_unique" on "external_references"(
  "plugin_id",
  "external_type",
  "referenceable_type",
  "referenceable_id"
);
CREATE INDEX "external_references_plugin_id_external_id_index" on "external_references"(
  "plugin_id",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "lexoffice_articles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "external_id" varchar not null,
  "name" varchar not null,
  "article_number" varchar,
  "description" text,
  "type" varchar not null default 'service',
  "unit_name" varchar,
  "net_unit_price" numeric,
  "currency" varchar not null default 'EUR',
  "vat_rate" numeric,
  "synced_at" datetime,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "gtin" varchar,
  "note" text,
  "gross_unit_price" numeric,
  "leading_price" varchar,
  "external_version" integer,
  "is_dirty" tinyint(1) not null default '0',
  "last_pushed_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "lexoffice_articles_organization_id_external_id_unique" on "lexoffice_articles"(
  "organization_id",
  "external_id"
);
CREATE INDEX "lexoffice_articles_organization_id_archived_at_index" on "lexoffice_articles"(
  "organization_id",
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "project_billing_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "project_id" integer not null,
  "plugin_id" varchar not null default 'lexoffice',
  "applies_to_kind" varchar,
  "lexoffice_article_id" varchar,
  "item_type" varchar not null default 'service',
  "unit_name" varchar,
  "vat_rate" numeric,
  "net_unit_price" numeric,
  "priority" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE INDEX "pbr_proj_plugin_kind_idx" on "project_billing_rules"(
  "project_id",
  "plugin_id",
  "applies_to_kind"
);
CREATE INDEX "project_billing_rules_organization_id_index" on "project_billing_rules"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "activity_categories"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "key" varchar not null,
  "label" varchar not null,
  "activity_type" varchar not null,
  "billable_default" tinyint(1) not null default '0',
  "counts_as_work" tinyint(1) not null default '1',
  "color" varchar,
  "icon" varchar,
  "sort_order" integer not null default '100',
  "active" tinyint(1) not null default '1',
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "activity_categories_organization_id_key_unique" on "activity_categories"(
  "organization_id",
  "key"
);
CREATE INDEX "activity_categories_organization_id_active_index" on "activity_categories"(
  "organization_id",
  "active"
);
CREATE INDEX "activity_categories_activity_type_index" on "activity_categories"(
  "activity_type"
);
CREATE TABLE IF NOT EXISTS "attendances"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "started_at" datetime not null,
  "ended_at" datetime,
  "date" date not null,
  "break_minutes_auto" integer not null default '0',
  "break_minutes_manual" integer not null default '0',
  "duration_minutes" integer not null default '0',
  "source" varchar not null default 'clock',
  "status" varchar not null default 'open',
  "started_lat" numeric,
  "started_lng" numeric,
  "ended_lat" numeric,
  "ended_lng" numeric,
  "started_device" varchar,
  "ended_device" varchar,
  "note" text,
  "closed_by" integer,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "break_started_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("closed_by") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "attendances_user_id_date_index" on "attendances"(
  "user_id",
  "date"
);
CREATE INDEX "attendances_organization_id_date_index" on "attendances"(
  "organization_id",
  "date"
);
CREATE INDEX "attendances_started_at_index" on "attendances"("started_at");
CREATE INDEX "attendances_status_index" on "attendances"("status");
CREATE UNIQUE INDEX attendances_user_open_unique ON attendances(
  user_id
) WHERE ended_at IS NULL;
CREATE TABLE IF NOT EXISTS "timesheets"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_id" integer,
  "user_id" integer not null,
  "work_date" date not null,
  "status" varchar not null default('draft'),
  "customer_name" varchar,
  "customer_role" varchar,
  "customer_email" varchar,
  "signed_at" datetime,
  "signed_ip" varchar,
  "signature_attachment_id" integer,
  "signature_hash" varchar,
  "locked_at" datetime,
  "locked_by" integer,
  "notes" text,
  "totals_minutes" integer not null default('0'),
  "totals_material_net" numeric not null default('0'),
  "magic_token" varchar,
  "magic_expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "kind" varchar not null default 'project',
  "attendance_total_minutes" integer not null default '0',
  "entries_total_minutes" integer not null default '0',
  "untracked_minutes" integer not null default '0',
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("signature_attachment_id") references attachments("id") on delete set null on update no action,
  foreign key("locked_by") references users("id") on delete set null on update no action,
  foreign key("project_id") references "projects"("id") on delete set null
);
CREATE UNIQUE INDEX "timesheets_magic_token_unique" on "timesheets"(
  "magic_token"
);
CREATE INDEX "timesheets_project_id_work_date_index" on "timesheets"(
  "project_id",
  "work_date"
);
CREATE INDEX "timesheets_status_index" on "timesheets"("status");
CREATE INDEX "timesheets_user_id_work_date_index" on "timesheets"(
  "user_id",
  "work_date"
);
CREATE INDEX "timesheets_kind_index" on "timesheets"("kind");
CREATE TABLE IF NOT EXISTS "vehicles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "license_plate" varchar not null,
  "label" varchar,
  "vehicle_type" varchar not null default 'car',
  "propulsion" varchar not null default 'petrol',
  "default_user_id" integer,
  "default_rate_per_km" numeric,
  "tank_capacity_liters" numeric,
  "battery_capacity_kwh" numeric,
  "wltp_consumption" numeric,
  "odometer_km" integer,
  "notes" text,
  "archived_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "ownership" varchar not null default 'owned',
  "rental_provider" varchar,
  "rental_start" date,
  "rental_end" date,
  "rental_cost_per_day" numeric,
  "rental_included_km" integer,
  "rental_extra_cost_per_km" numeric,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("default_user_id") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "vehicles_organization_id_archived_at_index" on "vehicles"(
  "organization_id",
  "archived_at"
);
CREATE INDEX "vehicles_default_user_id_index" on "vehicles"("default_user_id");
CREATE INDEX "vehicles_license_plate_index" on "vehicles"("license_plate");
CREATE TABLE IF NOT EXISTS "energy_logs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "vehicle_id" integer not null,
  "user_id" integer not null,
  "energy_type" varchar not null default 'fuel',
  "fuel_kind" varchar,
  "unit" varchar not null default 'liter',
  "quantity" numeric not null,
  "cost_total" numeric,
  "odometer_km" integer,
  "distance_since_last" integer,
  "location_address" varchar,
  "location_lat" numeric,
  "location_lng" numeric,
  "started_at" datetime not null,
  "ended_at" datetime,
  "duration_minutes" integer not null default '0',
  "soc_before" integer,
  "soc_after" integer,
  "charger_type" varchar,
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("vehicle_id") references "vehicles"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "energy_logs_vehicle_id_started_at_index" on "energy_logs"(
  "vehicle_id",
  "started_at"
);
CREATE INDEX "energy_logs_user_id_started_at_index" on "energy_logs"(
  "user_id",
  "started_at"
);
CREATE INDEX "energy_logs_organization_id_started_at_index" on "energy_logs"(
  "organization_id",
  "started_at"
);
CREATE INDEX "energy_logs_energy_type_index" on "energy_logs"("energy_type");
CREATE TABLE IF NOT EXISTS "travel_logs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "project_id" integer,
  "task_id" integer,
  "customer_id" integer,
  "attendance_id" integer,
  "date" date not null,
  "started_at" datetime,
  "ended_at" datetime,
  "duration_minutes" integer not null default('0'),
  "from_address" varchar,
  "to_address" varchar,
  "from_lat" numeric,
  "from_lng" numeric,
  "to_lat" numeric,
  "to_lng" numeric,
  "distance_km" numeric not null default('0'),
  "vehicle" varchar not null default('private'),
  "vehicle_label" varchar,
  "purpose" varchar,
  "round_trip" tinyint(1) not null default('0'),
  "reimbursable" tinyint(1) not null default('1'),
  "rate_per_km" numeric,
  "reimbursement_total" numeric not null default('0'),
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "vehicle_id" integer,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("attendance_id") references attendances("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("task_id") references tasks("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("vehicle_id") references "vehicles"("id") on delete set null
);
CREATE INDEX "travel_logs_customer_id_index" on "travel_logs"("customer_id");
CREATE INDEX "travel_logs_organization_id_date_index" on "travel_logs"(
  "organization_id",
  "date"
);
CREATE INDEX "travel_logs_project_id_index" on "travel_logs"("project_id");
CREATE INDEX "travel_logs_reimbursable_index" on "travel_logs"("reimbursable");
CREATE INDEX "travel_logs_user_id_date_index" on "travel_logs"(
  "user_id",
  "date"
);
CREATE INDEX "travel_logs_vehicle_id_index" on "travel_logs"("vehicle_id");
CREATE TABLE IF NOT EXISTS "geocode_cache"(
  "id" integer primary key autoincrement not null,
  "query_hash" varchar not null,
  "query" varchar not null,
  "address_formatted" varchar,
  "lat" numeric not null,
  "lng" numeric not null,
  "provider" varchar not null default 'nominatim',
  "raw" text,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "geocode_cache_expires_at_index" on "geocode_cache"("expires_at");
CREATE UNIQUE INDEX "geocode_cache_query_hash_unique" on "geocode_cache"(
  "query_hash"
);
CREATE TABLE IF NOT EXISTS "tours"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "vehicle_id" integer,
  "tour_date" date not null,
  "name" varchar,
  "start_address" varchar,
  "start_lat" numeric,
  "start_lng" numeric,
  "end_address" varchar,
  "end_lat" numeric,
  "end_lng" numeric,
  "planned_distance_km" numeric not null default '0',
  "planned_duration_minutes" integer not null default '0',
  "route_geometry" text,
  "status" varchar not null default 'draft',
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "travel_billed" tinyint(1) not null default '0',
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("vehicle_id") references "vehicles"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "tours_organization_id_tour_date_index" on "tours"(
  "organization_id",
  "tour_date"
);
CREATE INDEX "tours_user_id_tour_date_index" on "tours"(
  "user_id",
  "tour_date"
);
CREATE INDEX "tours_status_index" on "tours"("status");
CREATE INDEX "tours_tour_date_index" on "tours"("tour_date");
CREATE INDEX "vehicles_ownership_rental_end_index" on "vehicles"(
  "ownership",
  "rental_end"
);
CREATE TABLE IF NOT EXISTS "entry_types"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "slug" varchar not null,
  "label" varchar not null,
  "icon" varchar not null default 'assignment',
  "color" varchar not null default 'primary',
  "description" varchar,
  "sort" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "requires_customer" tinyint(1) not null default '0',
  "requires_address" tinyint(1) not null default '0',
  "requires_schedule" tinyint(1) not null default '0',
  "requires_tour" tinyint(1) not null default '0',
  "allow_priority" tinyint(1) not null default '1',
  "allow_tour" tinyint(1) not null default '0',
  "default_status" integer not null default '2',
  "default_service_minutes" integer,
  "default_priority" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "entry_types_organization_id_slug_unique" on "entry_types"(
  "organization_id",
  "slug"
);
CREATE INDEX "entry_types_organization_id_is_active_sort_index" on "entry_types"(
  "organization_id",
  "is_active",
  "sort"
);
CREATE TABLE IF NOT EXISTS "sick_leaves"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "start_date" date not null,
  "end_date" date not null,
  "kind" varchar not null default 'initial',
  "follow_up_for_id" integer,
  "au_number" varchar,
  "doctor_name" varchar,
  "note" text,
  "kasse_notified_at" datetime,
  "reported_at" datetime,
  "recorded_by" integer,
  "cancelled_at" datetime,
  "cancel_reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("follow_up_for_id") references "sick_leaves"("id") on delete set null,
  foreign key("recorded_by") references "users"("id") on delete set null
);
CREATE INDEX "sick_leaves_user_id_start_date_end_date_index" on "sick_leaves"(
  "user_id",
  "start_date",
  "end_date"
);
CREATE INDEX "sick_leaves_start_date_end_date_index" on "sick_leaves"(
  "start_date",
  "end_date"
);
CREATE INDEX "sick_leaves_follow_up_for_id_index" on "sick_leaves"(
  "follow_up_for_id"
);
CREATE TABLE IF NOT EXISTS "recurrence_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_id" integer,
  "customer_id" integer,
  "entry_type_id" integer,
  "assigned_user_id" integer,
  "created_by" integer,
  "name" varchar not null,
  "title_template" varchar,
  "content_template" text not null,
  "default_service_minutes" integer,
  "default_priority" varchar,
  "default_location_mode" varchar not null default 'onsite',
  "frequency" varchar not null,
  "interval" integer not null default '1',
  "byweekday" varchar,
  "bymonthday" integer,
  "bymonth" integer,
  "starts_on" date not null,
  "ends_on" date,
  "last_generated_until" date,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("entry_type_id") references "entry_types"("id") on delete set null,
  foreign key("assigned_user_id") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "recurrence_rules_organization_id_is_active_index" on "recurrence_rules"(
  "organization_id",
  "is_active"
);
CREATE INDEX "recurrence_rules_project_id_index" on "recurrence_rules"(
  "project_id"
);
CREATE INDEX "recurrence_rules_last_generated_until_index" on "recurrence_rules"(
  "last_generated_until"
);
CREATE TABLE IF NOT EXISTS "user_groups"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" varchar,
  "color" varchar,
  "is_system" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_groups_organization_id_slug_unique" on "user_groups"(
  "organization_id",
  "slug"
);
CREATE INDEX "user_groups_organization_id_name_index" on "user_groups"(
  "organization_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "user_user_group"(
  "user_id" integer not null,
  "user_group_id" integer not null,
  "joined_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("user_group_id") references "user_groups"("id") on delete cascade,
  primary key("user_id", "user_group_id")
);
CREATE INDEX "user_user_group_user_group_id_index" on "user_user_group"(
  "user_group_id"
);
CREATE TABLE IF NOT EXISTS "flex_eligibilities"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "valid_from" date not null,
  "valid_to" date,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "flex_eligibilities_user_id_valid_from_unique" on "flex_eligibilities"(
  "user_id",
  "valid_from"
);
CREATE INDEX "flex_eligibilities_user_id_valid_from_valid_to_index" on "flex_eligibilities"(
  "user_id",
  "valid_from",
  "valid_to"
);
CREATE TABLE IF NOT EXISTS "event_categories"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "slug" varchar,
  "color" varchar,
  "description" text,
  "requires_certificate" tinyint(1) not null default '0',
  "certificate_valid_months" integer,
  "reminder_offsets" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "event_categories_organization_id_is_active_index" on "event_categories"(
  "organization_id",
  "is_active"
);
CREATE UNIQUE INDEX "event_categories_organization_id_slug_unique" on "event_categories"(
  "organization_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "title" varchar not null,
  "description" text,
  "topic" varchar,
  "event_type" varchar not null default 'training',
  "category_id" integer,
  "started_at" datetime not null,
  "ended_at" datetime not null,
  "is_all_day" tinyint(1) not null default '0',
  "timezone" varchar,
  "status" varchar not null default 'planned',
  "visibility" varchar not null default 'internal',
  "responsible_user_id" integer,
  "customer_id" integer,
  "external_contact_note" varchar,
  "max_participants" integer,
  "is_mandatory" tinyint(1) not null default '0',
  "certificate_valid_months" integer,
  "series_id" integer,
  "recurrence_rule" text,
  "series_until" datetime,
  "reminder_overrides" text,
  "cancelled_at" datetime,
  "cancel_reason" varchar,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("category_id") references "event_categories"("id") on delete set null,
  foreign key("responsible_user_id") references "users"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("series_id") references "events"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "events_organization_id_started_at_index" on "events"(
  "organization_id",
  "started_at"
);
CREATE INDEX "events_responsible_user_id_started_at_index" on "events"(
  "responsible_user_id",
  "started_at"
);
CREATE INDEX "events_event_type_started_at_index" on "events"(
  "event_type",
  "started_at"
);
CREATE INDEX "events_series_id_index" on "events"("series_id");
CREATE INDEX "events_status_started_at_index" on "events"(
  "status",
  "started_at"
);
CREATE TABLE IF NOT EXISTS "event_room"(
  "id" integer primary key autoincrement not null,
  "event_id" integer not null,
  "room_id" integer not null,
  "started_at" datetime not null,
  "ended_at" datetime not null,
  "setup_minutes_before" integer not null default '0',
  "teardown_minutes_after" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("event_id") references "events"("id") on delete cascade,
  foreign key("room_id") references "rooms"("id") on delete cascade
);
CREATE UNIQUE INDEX "event_room_event_id_room_id_unique" on "event_room"(
  "event_id",
  "room_id"
);
CREATE INDEX "event_room_room_id_started_at_ended_at_index" on "event_room"(
  "room_id",
  "started_at",
  "ended_at"
);
CREATE INDEX "event_room_event_id_started_at_index" on "event_room"(
  "event_id",
  "started_at"
);
CREATE TABLE IF NOT EXISTS "organization_audit_logs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "organization_slug" varchar,
  "organization_name" varchar,
  "action" varchar not null,
  "actor_user_id" integer,
  "actor_email" varchar,
  "payload" text,
  "export_hash" varchar,
  "created_at" datetime,
  "prev_hash" varchar,
  "hash" varchar
);
CREATE INDEX "organization_audit_logs_organization_id_action_index" on "organization_audit_logs"(
  "organization_id",
  "action"
);
CREATE INDEX "organization_audit_logs_created_at_index" on "organization_audit_logs"(
  "created_at"
);
CREATE TABLE IF NOT EXISTS "expense_categories"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "slug" varchar not null,
  "label" varchar not null,
  "icon" varchar,
  "color" varchar not null default 'primary',
  "description" text,
  "default_tax_rate" numeric not null default '19',
  "default_billable" tinyint(1) not null default '0',
  "requires_receipt" tinyint(1) not null default '1',
  "sort" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "expense_categories_organization_id_slug_unique" on "expense_categories"(
  "organization_id",
  "slug"
);
CREATE INDEX "expense_categories_organization_id_is_active_index" on "expense_categories"(
  "organization_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "expenses"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "expense_category_id" integer,
  "project_id" integer,
  "customer_id" integer,
  "task_id" integer,
  "attendance_id" integer,
  "date" date not null,
  "vendor" varchar,
  "description" varchar not null,
  "payment_method" varchar not null default 'private_paid',
  "currency" varchar not null default 'EUR',
  "amount_net" numeric not null default '0',
  "tax_rate" numeric not null default '0',
  "tax_amount" numeric not null default '0',
  "amount_gross" numeric not null default '0',
  "billable" tinyint(1) not null default '0',
  "status" varchar not null default 'draft',
  "decided_by" integer,
  "decided_at" datetime,
  "reject_reason" text,
  "reimbursed_at" datetime,
  "reimbursement_reference" varchar,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("expense_category_id") references "expense_categories"("id") on delete set null,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("task_id") references "tasks"("id") on delete set null,
  foreign key("attendance_id") references "attendances"("id") on delete set null,
  foreign key("decided_by") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "expenses_user_id_date_index" on "expenses"("user_id", "date");
CREATE INDEX "expenses_organization_id_date_index" on "expenses"(
  "organization_id",
  "date"
);
CREATE INDEX "expenses_status_date_index" on "expenses"("status", "date");
CREATE INDEX "expenses_organization_id_status_index" on "expenses"(
  "organization_id",
  "status"
);
CREATE INDEX "expenses_project_id_index" on "expenses"("project_id");
CREATE INDEX "expenses_customer_id_index" on "expenses"("customer_id");
CREATE INDEX "expenses_billable_index" on "expenses"("billable");
CREATE TABLE IF NOT EXISTS "per_diem_rates"(
  "id" integer primary key autoincrement not null,
  "country" varchar not null,
  "valid_from" date not null,
  "valid_to" date,
  "full_day_amount" numeric not null,
  "partial_day_amount" numeric not null,
  "overnight_amount" numeric,
  "currency" varchar not null default 'EUR',
  "source" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "region_label" varchar
);
CREATE INDEX "per_diem_rates_country_valid_from_index" on "per_diem_rates"(
  "country",
  "valid_from"
);
CREATE TABLE IF NOT EXISTS "per_diem_trips"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "user_id" integer not null,
  "project_id" integer,
  "customer_id" integer,
  "travel_log_id" integer,
  "expense_id" integer,
  "country" varchar not null default 'DE',
  "purpose" varchar not null,
  "location" varchar not null,
  "workplace_key" varchar,
  "started_at" datetime not null,
  "ended_at" datetime not null,
  "accommodation_provided" tinyint(1) not null default '0',
  "status" varchar not null default 'draft',
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("travel_log_id") references "travel_logs"("id") on delete set null,
  foreign key("expense_id") references "expenses"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "per_diem_trips_user_id_started_at_index" on "per_diem_trips"(
  "user_id",
  "started_at"
);
CREATE INDEX "per_diem_trips_organization_id_status_index" on "per_diem_trips"(
  "organization_id",
  "status"
);
CREATE INDEX "per_diem_trips_workplace_key_index" on "per_diem_trips"(
  "workplace_key"
);
CREATE INDEX "per_diem_rates_country_region_from_idx" on "per_diem_rates"(
  "country",
  "region_label",
  "valid_from"
);
CREATE TABLE IF NOT EXISTS "open_issues"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "source_type" varchar not null,
  "source_ref_id" integer,
  "title" varchar not null,
  "description" text,
  "category" varchar,
  "severity" varchar not null default 'low',
  "status" varchar not null default 'open',
  "assignee_user_id" integer,
  "due_at" datetime,
  "visibility" varchar not null default 'internal',
  "closed_at" datetime,
  "closed_by_user_id" integer,
  "closed_reason" text,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("assignee_user_id") references "users"("id") on delete set null,
  foreign key("closed_by_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "open_issues_subject_status_idx" on "open_issues"(
  "subject_type",
  "subject_id",
  "status"
);
CREATE INDEX "open_issues_assignee_idx" on "open_issues"(
  "assignee_user_id",
  "status",
  "due_at"
);
CREATE INDEX "open_issues_org_status_idx" on "open_issues"(
  "organization_id",
  "status",
  "severity"
);
CREATE TABLE IF NOT EXISTS "open_issue_events"(
  "id" integer primary key autoincrement not null,
  "open_issue_id" integer not null,
  "event" varchar not null,
  "actor_user_id" integer,
  "payload" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("open_issue_id") references "open_issues"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "open_issue_events_issue_idx" on "open_issue_events"(
  "open_issue_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "protocol_items"(
  "id" integer primary key autoincrement not null,
  "protocol_id" integer not null,
  "parent_item_id" integer,
  "sort_order" integer not null default '0',
  "item_type" varchar not null default 'checklist',
  "label" varchar not null,
  "description" text,
  "required" tinyint(1) not null default '0',
  "value_json" text,
  "result" varchar,
  "note" text,
  "measured_at" datetime,
  "measured_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("protocol_id") references "protocols"("id") on delete cascade,
  foreign key("parent_item_id") references "protocol_items"("id") on delete set null,
  foreign key("measured_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "protocol_items_order_idx" on "protocol_items"(
  "protocol_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "protocol_signatures"(
  "id" integer primary key autoincrement not null,
  "protocol_id" integer not null,
  "role" varchar not null,
  "signer_name" varchar not null,
  "signer_email" varchar,
  "signer_contact_id" integer,
  "signed_at" datetime not null,
  "method" varchar not null,
  "signature_image_path" varchar,
  "ip" varchar,
  "user_agent" text,
  "hash" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("protocol_id") references "protocols"("id") on delete cascade
);
CREATE UNIQUE INDEX "protocol_signatures_role_uniq" on "protocol_signatures"(
  "protocol_id",
  "role",
  "signer_name"
);
CREATE TABLE IF NOT EXISTS "protocol_events"(
  "id" integer primary key autoincrement not null,
  "protocol_id" integer not null,
  "event" varchar not null,
  "actor_user_id" integer not null,
  "payload" text,
  "created_at" datetime,
  foreign key("protocol_id") references "protocols"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "protocol_events_protocol_idx" on "protocol_events"(
  "protocol_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "protocol_signature_tokens"(
  "id" integer primary key autoincrement not null,
  "protocol_id" integer not null,
  "role" varchar not null,
  "signer_name" varchar,
  "signer_email" varchar,
  "token_hash" varchar not null,
  "expires_at" datetime not null,
  "opened_at" datetime,
  "used_at" datetime,
  "signed_signature_id" integer,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "decision" varchar,
  "decision_reason" text,
  "decided_at" datetime,
  foreign key("protocol_id") references "protocols"("id") on delete cascade,
  foreign key("signed_signature_id") references "protocol_signatures"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "protocol_signature_tokens_hash_uniq" on "protocol_signature_tokens"(
  "token_hash"
);
CREATE INDEX "protocol_signature_tokens_protocol_idx" on "protocol_signature_tokens"(
  "protocol_id",
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "protocol_item_photos"(
  "id" integer primary key autoincrement not null,
  "protocol_item_id" integer not null,
  "attachment_id" integer not null,
  "phase" varchar not null,
  "caption" varchar,
  "sort_order" integer not null default '0',
  "taken_at" datetime,
  "geo_lat" numeric,
  "geo_lng" numeric,
  "captured_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("protocol_item_id") references "protocol_items"("id") on delete cascade,
  foreign key("attachment_id") references "attachments"("id") on delete cascade,
  foreign key("captured_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "protocol_item_photos_pair_uniq" on "protocol_item_photos"(
  "protocol_item_id",
  "attachment_id"
);
CREATE INDEX "protocol_item_photos_item_phase_idx" on "protocol_item_photos"(
  "protocol_item_id",
  "phase",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "tasks"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_id" integer,
  "milestone_id" integer,
  "parent_task_id" integer,
  "created_by" integer,
  "assigned_to" integer,
  "title" varchar not null,
  "description" text,
  "status" varchar not null default('open'),
  "priority" varchar not null default('medium'),
  "due_date" date,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "hourly_rate" numeric,
  "internal_rate" numeric,
  "time_budget" integer not null default('0'),
  "budget" numeric not null default('0'),
  "budget_type" varchar,
  "billable" tinyint(1) not null default('1'),
  "is_global" tinyint(1) not null default('0'),
  "color" varchar,
  "archived_at" datetime,
  "start_date" date,
  foreign key("assigned_to") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("parent_task_id") references tasks("id") on delete cascade on update no action,
  foreign key("milestone_id") references milestones("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action
);
CREATE INDEX "tasks_archived_at_index" on "tasks"("archived_at");
CREATE INDEX "tasks_assigned_to_index" on "tasks"("assigned_to");
CREATE INDEX "tasks_is_global_index" on "tasks"("is_global");
CREATE INDEX "tasks_project_id_index" on "tasks"("project_id");
CREATE INDEX "tasks_status_index" on "tasks"("status");
CREATE TABLE IF NOT EXISTS "automation_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "trigger_event" varchar not null,
  "conditions" text not null,
  "actions" text not null,
  "is_active" tinyint(1) not null default '1',
  "priority" integer not null default '100',
  "created_by_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_id") references "users"("id") on delete set null
);
CREATE INDEX "automation_rules_lookup_idx" on "automation_rules"(
  "organization_id",
  "trigger_event",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "invoice_mail_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "is_default" tinyint(1) not null default '0',
  "subject" varchar not null,
  "body_html" text not null,
  "body_text" text not null,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "invoice_mail_templates_organization_id_is_default_index" on "invoice_mail_templates"(
  "organization_id",
  "is_default"
);
CREATE TABLE IF NOT EXISTS "attachments"(
  "id" integer primary key autoincrement not null,
  "attachable_type" varchar not null,
  "attachable_id" integer not null,
  "user_id" integer,
  "disk" varchar not null default('local'),
  "path" varchar not null,
  "original_name" varchar not null,
  "mime" varchar,
  "size" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "meta_type" varchar,
  "organization_id" integer,
  "customer_visible" tinyint(1) not null default '0',
  foreign key("user_id") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "attachments_attachable_meta_idx" on "attachments"(
  "attachable_type",
  "attachable_id",
  "meta_type"
);
CREATE INDEX "attachments_attachable_type_attachable_id_index" on "attachments"(
  "attachable_type",
  "attachable_id"
);
CREATE INDEX "idx_attachments_org" on "attachments"("organization_id");
CREATE TABLE IF NOT EXISTS "event_reminders"(
  "id" integer primary key autoincrement not null,
  "event_id" integer not null,
  "user_id" integer,
  "remind_at" datetime not null,
  "channel" varchar not null default('mail'),
  "sent_at" datetime,
  "error" text,
  "payload" text,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("event_id") references events("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "event_reminders_event_id_remind_at_index" on "event_reminders"(
  "event_id",
  "remind_at"
);
CREATE INDEX "event_reminders_remind_at_sent_at_index" on "event_reminders"(
  "remind_at",
  "sent_at"
);
CREATE INDEX "event_reminders_user_id_sent_at_index" on "event_reminders"(
  "user_id",
  "sent_at"
);
CREATE INDEX "idx_event_reminders_org" on "event_reminders"("organization_id");
CREATE TABLE IF NOT EXISTS "push_subscriptions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "endpoint" varchar not null,
  "p256dh" varchar not null,
  "auth" varchar not null,
  "content_encoding" varchar not null default('aesgcm'),
  "user_agent" varchar,
  "last_used_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "push_subscriptions_endpoint_unique" on "push_subscriptions"(
  "endpoint"
);
CREATE INDEX "push_subscriptions_user_id_index" on "push_subscriptions"(
  "user_id"
);
CREATE INDEX "idx_push_subscriptions_org" on "push_subscriptions"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "flex_balances"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "year" integer not null,
  "month" integer not null,
  "target_minutes" integer not null default('0'),
  "actual_minutes" integer not null default('0'),
  "balance_minutes" integer not null default('0'),
  "carry_over_minutes" integer not null default('0'),
  "computed_at" datetime,
  "locked" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "flex_balances_user_id_year_month_unique" on "flex_balances"(
  "user_id",
  "year",
  "month"
);
CREATE INDEX "idx_flex_balances_org" on "flex_balances"("organization_id");
CREATE TABLE IF NOT EXISTS "per_diem_days"(
  "id" integer primary key autoincrement not null,
  "per_diem_trip_id" integer not null,
  "date" date not null,
  "kind" varchar not null,
  "country" varchar not null default('DE'),
  "per_diem_rate_id" integer,
  "base_amount" numeric not null default('0.00'),
  "deduction_breakfast" numeric not null default('0.00'),
  "deduction_lunch" numeric not null default('0.00'),
  "deduction_dinner" numeric not null default('0.00'),
  "deductions_total" numeric not null default('0.00'),
  "amount" numeric not null default('0.00'),
  "meal_breakfast" tinyint(1) not null default('0'),
  "meal_lunch" tinyint(1) not null default('0'),
  "meal_dinner" tinyint(1) not null default('0'),
  "currency" varchar not null default('EUR'),
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("per_diem_rate_id") references per_diem_rates("id") on delete set null on update no action,
  foreign key("per_diem_trip_id") references per_diem_trips("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "per_diem_days_date_index" on "per_diem_days"("date");
CREATE UNIQUE INDEX "per_diem_days_per_diem_trip_id_date_unique" on "per_diem_days"(
  "per_diem_trip_id",
  "date"
);
CREATE INDEX "idx_per_diem_days_org" on "per_diem_days"("organization_id");
CREATE TABLE IF NOT EXISTS "event_user"(
  "id" integer primary key autoincrement not null,
  "event_id" integer not null,
  "user_id" integer not null,
  "role" varchar not null default('attendee'),
  "status" varchar not null default('invited'),
  "responded_at" datetime,
  "attended_at" datetime,
  "certificate_issued_at" date,
  "certificate_expires_at" date,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("event_id") references events("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "event_user_certificate_expires_at_index" on "event_user"(
  "certificate_expires_at"
);
CREATE INDEX "event_user_event_id_role_index" on "event_user"(
  "event_id",
  "role"
);
CREATE UNIQUE INDEX "event_user_event_id_user_id_unique" on "event_user"(
  "event_id",
  "user_id"
);
CREATE INDEX "event_user_user_id_status_index" on "event_user"(
  "user_id",
  "status"
);
CREATE INDEX "idx_event_user_org" on "event_user"("organization_id");
CREATE TABLE IF NOT EXISTS "automation_rule_runs"(
  "id" integer primary key autoincrement not null,
  "rule_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "decision" varchar not null,
  "log" text,
  "ran_at" datetime not null default(CURRENT_TIMESTAMP),
  "organization_id" integer,
  foreign key("rule_id") references automation_rules("id") on delete cascade on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "automation_runs_rule_time_idx" on "automation_rule_runs"(
  "rule_id",
  "ran_at"
);
CREATE INDEX "automation_runs_subject_idx" on "automation_rule_runs"(
  "subject_type",
  "subject_id"
);
CREATE INDEX "idx_automation_rule_runs_org" on "automation_rule_runs"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "procedure_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "code" varchar not null,
  "name" varchar not null,
  "description" text,
  "domain" varchar,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "procedure_templates_org_code_uniq" on "procedure_templates"(
  "organization_id",
  "code"
);
CREATE INDEX "procedure_templates_org_active_idx" on "procedure_templates"(
  "organization_id",
  "active"
);
CREATE TABLE IF NOT EXISTS "procedure_template_versions"(
  "id" integer primary key autoincrement not null,
  "procedure_template_id" integer not null,
  "version" integer not null,
  "valid_from" date,
  "valid_to" date,
  "change_note" text,
  "published_at" datetime,
  "published_by_user_id" integer,
  "risk_level" varchar not null default 'normal',
  "applicability" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("procedure_template_id") references "procedure_templates"("id") on delete cascade,
  foreign key("published_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "procedure_template_versions_uniq" on "procedure_template_versions"(
  "procedure_template_id",
  "version"
);
CREATE INDEX "procedure_template_versions_valid_idx" on "procedure_template_versions"(
  "procedure_template_id",
  "valid_from",
  "valid_to"
);
CREATE TABLE IF NOT EXISTS "procedure_step_defs"(
  "id" integer primary key autoincrement not null,
  "procedure_template_version_id" integer not null,
  "sort_order" integer not null,
  "code" varchar not null,
  "step_type" varchar not null,
  "label" varchar not null,
  "description" text,
  "required" tinyint(1) not null default '1',
  "blocking" tinyint(1) not null default '1',
  "config" text,
  "required_role" varchar,
  "required_qualification_code" varchar,
  "requires_second_person" tinyint(1) not null default '0',
  "requires_proof_type" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("procedure_template_version_id") references "procedure_template_versions"("id") on delete cascade
);
CREATE UNIQUE INDEX "procedure_step_defs_code_uniq" on "procedure_step_defs"(
  "procedure_template_version_id",
  "code"
);
CREATE INDEX "procedure_step_defs_order_idx" on "procedure_step_defs"(
  "procedure_template_version_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "time_correction_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "requested_by_user_id" integer not null,
  "scope_date" date not null,
  "status" varchar not null,
  "reason" text not null,
  "decided_at" datetime,
  "decided_by_user_id" integer,
  "decision_note" text,
  "applied_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "self_applied" tinyint(1) not null default '0',
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("requested_by_user_id") references "users"("id") on delete restrict,
  foreign key("decided_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "tcr_user_date_idx" on "time_correction_requests"(
  "user_id",
  "scope_date"
);
CREATE INDEX "tcr_org_status_idx" on "time_correction_requests"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "time_correction_items"(
  "id" integer primary key autoincrement not null,
  "time_correction_request_id" integer not null,
  "target_type" varchar not null,
  "target_id" integer,
  "action" varchar not null,
  "before" text,
  "after" text,
  foreign key("time_correction_request_id") references "time_correction_requests"("id") on delete cascade
);
CREATE INDEX "tci_request_idx" on "time_correction_items"(
  "time_correction_request_id"
);
CREATE INDEX "tci_target_idx" on "time_correction_items"(
  "target_type",
  "target_id"
);
CREATE TABLE IF NOT EXISTS "maintenance_plan_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "code" varchar not null,
  "label" varchar not null,
  "asset_class" varchar,
  "category_code" varchar,
  "interval_kind" varchar not null,
  "interval_value" integer not null,
  "tolerance_days" integer not null default '0',
  "procedure_template_code" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "maintenance_plan_templates_uniq_code" on "maintenance_plan_templates"(
  "organization_id",
  "code"
);
CREATE INDEX "maintenance_plan_templates_idx_class" on "maintenance_plan_templates"(
  "organization_id",
  "asset_class"
);
CREATE TABLE IF NOT EXISTS "key_handovers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "customer_id" integer,
  "direction" varchar not null,
  "person_name" varchar not null,
  "person_reference" varchar,
  "handed_by_user_id" integer,
  "returned_to_user_id" integer,
  "occurred_at" datetime not null,
  "expected_return_at" datetime,
  "notes" text,
  "signature_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("handed_by_user_id") references "users"("id") on delete set null,
  foreign key("returned_to_user_id") references "users"("id") on delete set null
);
CREATE INDEX "key_handovers_asset_occurred_idx" on "key_handovers"(
  "organization_id",
  "asset_id",
  "occurred_at"
);
CREATE INDEX "key_handovers_org_direction_idx" on "key_handovers"(
  "organization_id",
  "direction"
);
CREATE TABLE IF NOT EXISTS "meter_readings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "read_at" datetime not null,
  "value" numeric not null,
  "unit" varchar not null,
  "previous_value" numeric,
  "consumption" numeric,
  "read_by_user_id" integer,
  "photo_path" varchar,
  "notes" text,
  "is_estimated" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("read_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "meter_readings_asset_read_idx" on "meter_readings"(
  "organization_id",
  "asset_id",
  "read_at"
);
CREATE TABLE IF NOT EXISTS "number_formats"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "scope" varchar not null,
  "prefix" varchar not null default '',
  "prefix_separator" varchar not null default '-',
  "include_year" tinyint(1) not null default '1',
  "year_separator" varchar not null default '-',
  "padding" integer not null default '4',
  "reset_per_year" tinyint(1) not null default '1',
  "starts_at" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "source" varchar not null default 'local',
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "number_formats_org_scope_uniq" on "number_formats"(
  "organization_id",
  "scope"
);
CREATE TABLE IF NOT EXISTS "number_sequences"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "scope" varchar not null,
  "period" varchar,
  "last_value" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "number_sequences_org_scope_period_uniq" on "number_sequences"(
  "organization_id",
  "scope",
  "period"
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar,
  "email" varchar,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "legacy_user_id" integer,
  "must_change_password" tinyint(1) not null default('0'),
  "organization_id" integer,
  "hourly_rate" numeric,
  "internal_rate" numeric,
  "home_address" varchar,
  "home_lat" numeric,
  "home_lng" numeric,
  "preferences" text,
  "is_new_system" tinyint(1) not null default('0'),
  "calendar_feed_token" varchar,
  "customer_id" integer,
  "personnel_number" varchar,
  "payroll_hourly_wage" numeric,
  "tax_identification_number" varchar,
  "social_security_number" varchar,
  "date_of_birth" date,
  "health_insurance" varchar,
  "tax_class" varchar,
  "child_allowances" numeric,
  "church_tax" tinyint(1) not null default '0',
  "employment_start_date" date,
  "employment_end_date" date,
  "employment_type" varchar,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime,
  "cti_extension" text,
  "cti_extension_hash" varchar,
  "first_name" varchar,
  "middle_names" varchar,
  "last_name" varchar,
  "phone" varchar,
  "mobile" varchar,
  "fax" varchar,
  "compensation_model" varchar,
  "flat_amount" numeric,
  "flat_interval" varchar,
  "compensation_rate" numeric,
  "deactivated_at" datetime,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("customer_id") references "customers"("id") on delete set null
);
CREATE INDEX "idx_users_org" on "users"("organization_id");
CREATE UNIQUE INDEX "users_calendar_feed_token_unique" on "users"(
  "calendar_feed_token"
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE INDEX "users_is_new_system_index" on "users"("is_new_system");
CREATE INDEX "users_customer_id_index" on "users"("customer_id");
CREATE TABLE IF NOT EXISTS "procedure_runs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "procedure_template_version_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "status" varchar not null default 'open',
  "assigned_user_id" integer,
  "started_at" datetime,
  "completed_at" datetime,
  "aborted_at" datetime,
  "abort_reason" text,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("procedure_template_version_id") references "procedure_template_versions"("id") on delete restrict,
  foreign key("assigned_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete restrict
);
CREATE INDEX "procedure_runs_subject_idx" on "procedure_runs"(
  "subject_type",
  "subject_id",
  "status"
);
CREATE INDEX "procedure_runs_org_status_idx" on "procedure_runs"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "procedure_step_runs"(
  "id" integer primary key autoincrement not null,
  "procedure_run_id" integer not null,
  "procedure_step_def_id" integer not null,
  "status" varchar not null default 'pending',
  "value_json" text,
  "executed_by_user_id" integer,
  "executed_at" datetime,
  "second_person_user_id" integer,
  "second_person_signed_at" datetime,
  "proof_attachment_id" integer,
  "note" text,
  "deviation_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "wait_started_at" datetime,
  "wait_until" datetime,
  foreign key("procedure_run_id") references "procedure_runs"("id") on delete cascade,
  foreign key("procedure_step_def_id") references "procedure_step_defs"("id") on delete restrict,
  foreign key("executed_by_user_id") references "users"("id") on delete set null,
  foreign key("second_person_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "procedure_step_runs_uniq" on "procedure_step_runs"(
  "procedure_run_id",
  "procedure_step_def_id"
);
CREATE INDEX "procedure_step_runs_run_idx" on "procedure_step_runs"(
  "procedure_run_id"
);
CREATE TABLE IF NOT EXISTS "procedure_run_events"(
  "id" integer primary key autoincrement not null,
  "procedure_run_id" integer not null,
  "procedure_step_run_id" integer,
  "event_type" varchar not null,
  "payload" text,
  "actor_user_id" integer,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("procedure_run_id") references "procedure_runs"("id") on delete cascade,
  foreign key("procedure_step_run_id") references "procedure_step_runs"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "procedure_run_events_run_type_idx" on "procedure_run_events"(
  "procedure_run_id",
  "event_type"
);
CREATE TABLE IF NOT EXISTS "procedure_backup_proofs"(
  "id" integer primary key autoincrement not null,
  "procedure_step_run_id" integer not null,
  "backup_scope" varchar not null,
  "source_label" varchar not null,
  "taken_at" datetime not null,
  "size_bytes" integer not null default '0',
  "checksum_algo" varchar,
  "checksum_value" varchar,
  "storage_target" varchar not null,
  "attachment_id" integer,
  "external_ref" varchar,
  "verified" tinyint(1) not null default '0',
  "verified_at" datetime,
  "verified_by_user_id" integer,
  "verify_method" varchar not null,
  "verify_note" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("procedure_step_run_id") references "procedure_step_runs"("id") on delete cascade,
  foreign key("verified_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "procedure_backup_proofs_step_uniq" on "procedure_backup_proofs"(
  "procedure_step_run_id"
);
CREATE TABLE IF NOT EXISTS "procedure_deviations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "procedure_step_run_id" integer not null,
  "deviation_type" varchar not null,
  "severity" varchar not null,
  "reason_text" text not null,
  "proposed_action" varchar,
  "open_issue_id" integer,
  "follow_up_diary_entry_id" integer,
  "risk_accepted_by_user_id" integer,
  "risk_accepted_at" datetime,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("procedure_step_run_id") references "procedure_step_runs"("id") on delete cascade,
  foreign key("risk_accepted_by_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "procedure_deviations_org_type_sev_idx" on "procedure_deviations"(
  "organization_id",
  "deviation_type",
  "severity"
);
CREATE UNIQUE INDEX "procedure_deviations_step_uniq" on "procedure_deviations"(
  "procedure_step_run_id"
);
CREATE TABLE IF NOT EXISTS "classifications"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "domain" varchar not null,
  "code" varchar not null,
  "label" varchar not null,
  "label_i18n" text,
  "sort_order" integer not null default '100',
  "color_hex" varchar,
  "icon" varchar,
  "active" tinyint(1) not null default '1',
  "deprecated_at" datetime,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "classifications_uniq_code" on "classifications"(
  "organization_id",
  "domain",
  "code"
);
CREATE INDEX "classifications_lookup_idx" on "classifications"(
  "organization_id",
  "domain",
  "active"
);
CREATE TABLE IF NOT EXISTS "classification_requirements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "entry_type_code" varchar not null,
  "required_domain" varchar not null,
  "enforce_phase" varchar not null,
  "severity" varchar not null,
  "allow_multi" tinyint(1) not null default '0',
  "min_count" integer not null default '1',
  "max_count" integer,
  "only_if_json" text,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "classification_requirements_uniq_req" on "classification_requirements"(
  "organization_id",
  "entry_type_code",
  "required_domain",
  "enforce_phase"
);
CREATE INDEX "classification_requirements_lookup_idx" on "classification_requirements"(
  "organization_id",
  "entry_type_code",
  "enforce_phase"
);
CREATE TABLE IF NOT EXISTS "material_usages"(
  "id" integer primary key autoincrement not null,
  "timesheet_id" integer not null,
  "material_id" integer,
  "description" varchar not null,
  "quantity" numeric not null,
  "unit" varchar not null default('Stk.'),
  "unit_price" numeric,
  "tax_rate" numeric,
  "line_total_net" numeric not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "asset_id" integer,
  "billed" tinyint(1) not null default '0',
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("timesheet_id") references timesheets("id") on delete cascade on update no action,
  foreign key("material_id") references materials("id") on delete set null on update no action,
  foreign key("asset_id") references "assets"("id") on delete set null
);
CREATE INDEX "idx_material_usages_org" on "material_usages"("organization_id");
CREATE INDEX "material_usages_timesheet_id_index" on "material_usages"(
  "timesheet_id"
);
CREATE INDEX "material_usages_org_asset_idx" on "material_usages"(
  "organization_id",
  "asset_id"
);
CREATE UNIQUE INDEX "users_org_personnel_number_unique" on "users"(
  "organization_id",
  "personnel_number"
);
CREATE TABLE IF NOT EXISTS "teams"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" varchar,
  "color" varchar,
  "lead_user_id" integer,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("lead_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "teams_organization_id_slug_unique" on "teams"(
  "organization_id",
  "slug"
);
CREATE INDEX "teams_organization_id_archived_at_index" on "teams"(
  "organization_id",
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "team_user"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "user_id" integer not null,
  "is_lead" tinyint(1) not null default '0',
  "joined_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "team_user_team_id_user_id_unique" on "team_user"(
  "team_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "project_team"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "team_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE UNIQUE INDEX "project_team_project_id_team_id_unique" on "project_team"(
  "project_id",
  "team_id"
);
CREATE TABLE IF NOT EXISTS "project_user"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "project_user_project_id_user_id_unique" on "project_user"(
  "project_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "onboarding_progress"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "step_code" varchar not null,
  "state" varchar not null default 'open',
  "done_at" datetime,
  "done_by_user_id" integer,
  "skipped_reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("done_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "uniq_onboarding_org_step" on "onboarding_progress"(
  "organization_id",
  "step_code"
);
CREATE INDEX "idx_onboarding_org_state" on "onboarding_progress"(
  "organization_id",
  "state"
);
CREATE TABLE IF NOT EXISTS "task_user"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "task_user_task_id_user_id_unique" on "task_user"(
  "task_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "minimum_wages"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "valid_from" date not null,
  "hourly_amount" numeric not null,
  "note" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "minimum_wages_organization_id_valid_from_unique" on "minimum_wages"(
  "organization_id",
  "valid_from"
);
CREATE INDEX "minimum_wages_organization_id_valid_from_index" on "minimum_wages"(
  "organization_id",
  "valid_from"
);
CREATE TABLE IF NOT EXISTS "minimum_wage_references"(
  "id" integer primary key autoincrement not null,
  "country" varchar not null,
  "valid_from" date not null,
  "monthly_amount" numeric not null,
  "currency" varchar not null default 'EUR',
  "source" varchar not null default 'eurostat',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "minimum_wage_references_country_valid_from_currency_unique" on "minimum_wage_references"(
  "country",
  "valid_from",
  "currency"
);
CREATE INDEX "minimum_wage_references_country_index" on "minimum_wage_references"(
  "country"
);
CREATE TABLE IF NOT EXISTS "help_topics"(
  "id" integer primary key autoincrement not null,
  "topic" varchar not null,
  "locale" varchar not null,
  "title" varchar not null,
  "audience" text,
  "version" integer not null default '1',
  "body_md" text not null,
  "body_html" text not null,
  "related" text,
  "source_updated_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "uniq_help_topic_locale" on "help_topics"(
  "topic",
  "locale"
);
CREATE INDEX "idx_help_topic" on "help_topics"("topic");
CREATE TABLE IF NOT EXISTS "help_views"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "topic" varchar not null,
  "locale" varchar not null,
  "was_helpful" tinyint(1),
  "created_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "idx_help_views_topic_locale" on "help_views"(
  "topic",
  "locale",
  "created_at"
);
CREATE INDEX "idx_help_views_org_time" on "help_views"(
  "organization_id",
  "created_at"
);
CREATE INDEX "idx_organizations_is_demo" on "organizations"("is_demo");
CREATE TABLE IF NOT EXISTS "two_factor_credentials"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "type" varchar not null,
  "label" varchar,
  "secret" text,
  "data" text,
  "credential_id" varchar,
  "confirmed_at" datetime,
  "last_used_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "tfc_user_type_index" on "two_factor_credentials"(
  "user_id",
  "type"
);
CREATE INDEX "tfc_credential_id_index" on "two_factor_credentials"(
  "credential_id"
);
CREATE TABLE IF NOT EXISTS "user_bookmarks"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "label" varchar not null,
  "url" text not null,
  "icon" varchar,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "user_bookmarks_user_id_sort_order_index" on "user_bookmarks"(
  "user_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "communication_notes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "notable_type" varchar not null,
  "notable_id" integer not null,
  "type" varchar not null,
  "direction" varchar not null,
  "occurred_at" datetime not null,
  "subject" varchar not null,
  "body" text not null,
  "result" text,
  "next_action" varchar,
  "next_action_due_at" datetime,
  "next_action_user_id" integer,
  "next_action_completed_at" datetime,
  "next_action_completed_by_user_id" integer,
  "visibility" varchar not null default 'internal',
  "confidential" tinyint(1) not null default '0',
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("next_action_user_id") references "users"("id") on delete set null,
  foreign key("next_action_completed_by_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "comm_notes_notable_idx" on "communication_notes"(
  "notable_type",
  "notable_id",
  "occurred_at"
);
CREATE INDEX "comm_notes_org_idx" on "communication_notes"(
  "organization_id",
  "occurred_at"
);
CREATE INDEX "comm_notes_followup_idx" on "communication_notes"(
  "next_action_user_id",
  "next_action_due_at"
);
CREATE TABLE IF NOT EXISTS "feature_usage_counters"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "feature" varchar not null,
  "period_date" date not null,
  "count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "fuc_org_feature_period_uq" on "feature_usage_counters"(
  "organization_id",
  "feature",
  "period_date"
);
CREATE INDEX "fuc_org_period_idx" on "feature_usage_counters"(
  "organization_id",
  "period_date"
);
CREATE TABLE IF NOT EXISTS "communication_note_participants"(
  "id" integer primary key autoincrement not null,
  "communication_note_id" integer not null,
  "user_id" integer,
  "customer_contact_id" integer,
  "name" varchar not null,
  "role" varchar,
  "party" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("communication_note_id") references "communication_notes"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "comm_note_part_unique" on "communication_note_participants"(
  "communication_note_id",
  "user_id",
  "customer_contact_id",
  "name"
);
CREATE INDEX "comm_note_part_note_idx" on "communication_note_participants"(
  "communication_note_id"
);
CREATE TABLE IF NOT EXISTS "documents"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "documentable_type" varchar,
  "documentable_id" integer,
  "title" varchar not null,
  "document_type" varchar not null,
  "status" varchar not null default 'active',
  "valid_from" date,
  "valid_until" date,
  "description" text,
  "created_by_user_id" integer not null,
  "current_version_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "webdav_mirror_detached" tinyint(1) not null default '0',
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "documents_org_status_idx" on "documents"(
  "organization_id",
  "status"
);
CREATE INDEX "documents_org_type_idx" on "documents"(
  "organization_id",
  "document_type"
);
CREATE INDEX "documents_org_valid_idx" on "documents"(
  "organization_id",
  "valid_until"
);
CREATE INDEX "documents_documentable_idx" on "documents"(
  "documentable_type",
  "documentable_id"
);
CREATE TABLE IF NOT EXISTS "document_versions"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "version_no" integer not null,
  "disk" varchar not null default 'local',
  "path" varchar not null,
  "original_name" varchar not null,
  "mime" varchar,
  "size" integer not null default '0',
  "uploaded_by_user_id" integer not null,
  "note" varchar,
  "created_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade,
  foreign key("uploaded_by_user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "doc_versions_doc_no_unique" on "document_versions"(
  "document_id",
  "version_no"
);
CREATE INDEX "doc_versions_doc_idx" on "document_versions"("document_id");
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "notifiable_type" varchar not null,
  "notifiable_id" integer not null,
  "data" text not null,
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" on "notifications"(
  "notifiable_type",
  "notifiable_id"
);
CREATE TABLE IF NOT EXISTS "notification_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "event" varchar not null,
  "enabled" tinyint(1) not null default '1',
  "channels" text not null,
  "notify_affected" tinyint(1) not null default '1',
  "recipient_roles" text,
  "recipient_user_ids" text,
  "escalation_enabled" tinyint(1) not null default '0',
  "escalate_after_hours" integer,
  "escalation_role" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "notif_rules_org_event_uq" on "notification_rules"(
  "organization_id",
  "event"
);
CREATE TABLE IF NOT EXISTS "notification_dispatch_log"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "event" varchar not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "stage" varchar not null default 'initial',
  "recipient_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "notif_dispatch_uq" on "notification_dispatch_log"(
  "organization_id",
  "event",
  "subject_type",
  "subject_id",
  "stage"
);
CREATE INDEX "notif_dispatch_event_idx" on "notification_dispatch_log"(
  "event",
  "stage"
);
CREATE TABLE IF NOT EXISTS "surcharge_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "code" varchar not null,
  "label" varchar not null,
  "kind" varchar not null,
  "window_start" time,
  "window_end" time,
  "percentage" numeric not null default '0',
  "wage_type_code" varchar,
  "priority" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "valid_from" date,
  "valid_until" date,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "tax_free_limit_pct" numeric,
  "taxable_wage_type_code" varchar,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "sur_rules_org_code_uq" on "surcharge_rules"(
  "organization_id",
  "code"
);
CREATE INDEX "sur_rules_org_active_idx" on "surcharge_rules"(
  "organization_id",
  "active",
  "kind"
);
CREATE TABLE IF NOT EXISTS "knowledge_articles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "slug" varchar not null,
  "problem" text not null,
  "solution" text not null,
  "category" varchar,
  "status" varchar not null default 'draft',
  "visibility" varchar not null default 'internal',
  "created_by_user_id" integer not null,
  "published_at" datetime,
  "helpful_count" integer not null default '0',
  "not_helpful_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "knowledge_org_slug_uq" on "knowledge_articles"(
  "organization_id",
  "slug"
);
CREATE INDEX "knowledge_org_status_idx" on "knowledge_articles"(
  "organization_id",
  "status"
);
CREATE INDEX "knowledge_org_category_idx" on "knowledge_articles"(
  "organization_id",
  "category"
);
CREATE TABLE IF NOT EXISTS "knowledge_article_links"(
  "id" integer primary key autoincrement not null,
  "knowledge_article_id" integer not null,
  "linkable_type" varchar not null,
  "linkable_id" integer not null,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  foreign key("knowledge_article_id") references "knowledge_articles"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "knowledge_link_uq" on "knowledge_article_links"(
  "knowledge_article_id",
  "linkable_type",
  "linkable_id"
);
CREATE INDEX "knowledge_link_linkable_idx" on "knowledge_article_links"(
  "linkable_type",
  "linkable_id"
);
CREATE TABLE IF NOT EXISTS "knowledge_article_feedback"(
  "id" integer primary key autoincrement not null,
  "knowledge_article_id" integer not null,
  "user_id" integer not null,
  "helpful" tinyint(1) not null,
  "created_at" datetime,
  foreign key("knowledge_article_id") references "knowledge_articles"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "knowledge_feedback_uq" on "knowledge_article_feedback"(
  "knowledge_article_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "form_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "description" text,
  "status" varchar not null default 'draft',
  "fields" text not null,
  "created_by_user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "form_tpl_org_status_idx" on "form_templates"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "form_submissions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "form_template_id" integer not null,
  "fields_snapshot" text not null,
  "values" text not null,
  "subject_type" varchar,
  "subject_id" integer,
  "submitted_by_user_id" integer not null,
  "submitted_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("form_template_id") references "form_templates"("id") on delete cascade,
  foreign key("submitted_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "form_sub_subject_idx" on "form_submissions"(
  "subject_type",
  "subject_id"
);
CREATE INDEX "form_sub_org_tpl_idx" on "form_submissions"(
  "organization_id",
  "form_template_id"
);
CREATE INDEX "form_sub_org_at_idx" on "form_submissions"(
  "organization_id",
  "submitted_at"
);
CREATE TABLE IF NOT EXISTS "isms_controls"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "description" text,
  "implementation_status" varchar not null default 'open',
  "evidence_note" text,
  "owner_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE INDEX "isms_controls_org_status_idx" on "isms_controls"(
  "organization_id",
  "implementation_status"
);
CREATE TABLE IF NOT EXISTS "isms_control_risk"(
  "id" integer primary key autoincrement not null,
  "control_id" integer not null,
  "risk_id" integer not null,
  foreign key("control_id") references "isms_controls"("id") on delete cascade,
  foreign key("risk_id") references "isms_risks"("id") on delete cascade
);
CREATE UNIQUE INDEX "isms_control_risk_uq" on "isms_control_risk"(
  "control_id",
  "risk_id"
);
CREATE INDEX "isms_control_risk_risk_idx" on "isms_control_risk"("risk_id");
CREATE TABLE IF NOT EXISTS "billing_transfers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "customer_id" integer not null,
  "channel" varchar not null,
  "target" varchar not null,
  "status" varchar not null default 'draft',
  "period_from" date,
  "period_to" date,
  "position_count" integer not null default '0',
  "total_amount" numeric,
  "total_quantity" numeric,
  "payload_hash" varchar not null,
  "external_reference_id" integer,
  "file_path" varchar,
  "created_by_user_id" integer,
  "transferred_at" datetime,
  "failure_reason" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("external_reference_id") references "external_references"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "bt_org_status_idx" on "billing_transfers"(
  "organization_id",
  "status"
);
CREATE INDEX "bt_org_customer_idx" on "billing_transfers"(
  "organization_id",
  "customer_id"
);
CREATE INDEX "bt_channel_status_idx" on "billing_transfers"(
  "channel",
  "status"
);
CREATE TABLE IF NOT EXISTS "billing_transfer_items"(
  "id" integer primary key autoincrement not null,
  "billing_transfer_id" integer not null,
  "source_type" varchar not null,
  "source_id" integer not null,
  "amount" numeric,
  "quantity" numeric,
  "created_at" datetime,
  "unit" varchar,
  "unit_price" numeric,
  "tax_rate" numeric,
  "cost_position" varchar,
  foreign key("billing_transfer_id") references "billing_transfers"("id") on delete cascade
);
CREATE UNIQUE INDEX "bti_transfer_source_unique" on "billing_transfer_items"(
  "billing_transfer_id",
  "source_type",
  "source_id"
);
CREATE INDEX "bti_source_idx" on "billing_transfer_items"(
  "source_type",
  "source_id"
);
CREATE TABLE IF NOT EXISTS "billing_transfer_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "billing_transfer_id" integer,
  "event" varchar not null,
  "actor_user_id" integer,
  "payload" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "bte_transfer_event_idx" on "billing_transfer_events"(
  "billing_transfer_id",
  "event"
);
CREATE INDEX "bte_hash_idx" on "billing_transfer_events"("hash");
CREATE TABLE IF NOT EXISTS "isms_scopes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "description" text,
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE INDEX "isms_scopes_org_default_idx" on "isms_scopes"(
  "organization_id",
  "is_default"
);
CREATE TABLE IF NOT EXISTS "isms_requirements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "norm" varchar not null,
  "edition" varchar not null,
  "ref_no" varchar not null,
  "title" varchar not null,
  "source" varchar not null default 'custom',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "description" text,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "isms_req_org_ref_uq" on "isms_requirements"(
  "organization_id",
  "norm",
  "edition",
  "ref_no"
);
CREATE INDEX "isms_req_org_source_idx" on "isms_requirements"(
  "organization_id",
  "source"
);
CREATE TABLE IF NOT EXISTS "isms_control_requirement"(
  "id" integer primary key autoincrement not null,
  "control_id" integer not null,
  "requirement_id" integer not null,
  foreign key("control_id") references "isms_controls"("id") on delete cascade,
  foreign key("requirement_id") references "isms_requirements"("id") on delete cascade
);
CREATE UNIQUE INDEX "isms_ctrl_req_uq" on "isms_control_requirement"(
  "control_id",
  "requirement_id"
);
CREATE INDEX "isms_ctrl_req_req_idx" on "isms_control_requirement"(
  "requirement_id"
);
CREATE TABLE IF NOT EXISTS "isms_applicability_statements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "isms_requirement_id" integer not null,
  "applicable" tinyint(1) not null default '1',
  "justification" text,
  "implementation_status" varchar not null default 'open',
  "evidence_note" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete cascade,
  foreign key("isms_requirement_id") references "isms_requirements"("id") on delete cascade
);
CREATE UNIQUE INDEX "isms_stmt_scope_req_uq" on "isms_applicability_statements"(
  "isms_scope_id",
  "isms_requirement_id"
);
CREATE INDEX "isms_stmt_org_status_idx" on "isms_applicability_statements"(
  "organization_id",
  "implementation_status"
);
CREATE TABLE IF NOT EXISTS "isms_risks"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "risk_no" integer not null,
  "title" varchar not null,
  "description" text,
  "category" varchar not null,
  "asset_ref" varchar,
  "threat" text,
  "likelihood" integer not null,
  "impact" integer not null,
  "score" integer not null,
  "treatment" varchar not null,
  "status" varchar not null default('identified'),
  "owner_user_id" integer,
  "review_due_on" date,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "isms_scope_id" integer,
  foreign key("owner_user_id") references users("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_risks_org_no_uq" on "isms_risks"(
  "organization_id",
  "risk_no"
);
CREATE INDEX "isms_risks_org_review_idx" on "isms_risks"(
  "organization_id",
  "review_due_on"
);
CREATE INDEX "isms_risks_org_status_idx" on "isms_risks"(
  "organization_id",
  "status",
  "score"
);
CREATE TABLE IF NOT EXISTS "isms_software_products"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "vendor" varchar,
  "product_version" varchar,
  "category" varchar,
  "owner_user_id" integer,
  "support_status" varchar not null default 'unknown',
  "eol_on" date,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE INDEX "isms_sw_org_name_idx" on "isms_software_products"(
  "organization_id",
  "name"
);
CREATE INDEX "isms_sw_org_status_idx" on "isms_software_products"(
  "organization_id",
  "support_status"
);
CREATE INDEX "isms_sw_org_eol_idx" on "isms_software_products"(
  "organization_id",
  "eol_on"
);
CREATE TABLE IF NOT EXISTS "isms_software_installations"(
  "id" integer primary key autoincrement not null,
  "isms_software_product_id" integer not null,
  "organization_id" integer not null,
  "installed_version" varchar,
  "asset_ref" varchar,
  "location" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("isms_software_product_id") references "isms_software_products"("id") on delete cascade,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE INDEX "isms_swi_org_product_idx" on "isms_software_installations"(
  "organization_id",
  "isms_software_product_id"
);
CREATE TABLE IF NOT EXISTS "isms_norm_statuses"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "norm" varchar not null,
  "edition" varchar not null,
  "status" varchar not null default 'notAssessed',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "profile_version" varchar,
  "profile_as_of" date,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete cascade
);
CREATE UNIQUE INDEX "isms_nstat_org_scope_norm_uq" on "isms_norm_statuses"(
  "organization_id",
  "isms_scope_id",
  "norm",
  "edition"
);
CREATE INDEX "isms_nstat_org_status_idx" on "isms_norm_statuses"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_certificates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_norm_status_id" integer not null,
  "certified_organization" varchar not null,
  "scope_description" text not null,
  "certification_body" varchar not null,
  "certificate_no" varchar not null,
  "issued_on" date not null,
  "valid_from" date not null,
  "valid_until" date not null,
  "surveillance_audit_1_on" date,
  "surveillance_audit_2_on" date,
  "document_id" integer,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_norm_status_id") references "isms_norm_statuses"("id") on delete cascade,
  foreign key("document_id") references "documents"("id") on delete set null
);
CREATE INDEX "isms_cert_org_valid_idx" on "isms_certificates"(
  "organization_id",
  "valid_until"
);
CREATE INDEX "isms_cert_status_valid_idx" on "isms_certificates"(
  "isms_norm_status_id",
  "valid_until"
);
CREATE TABLE IF NOT EXISTS "isms_audit_findings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_audit_id" integer not null,
  "finding_no" integer not null,
  "kind" varchar not null default 'observation',
  "title" varchar not null,
  "description" text,
  "isms_requirement_id" integer,
  "status" varchar not null default 'open',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_audit_id") references "isms_audits"("id") on delete cascade,
  foreign key("isms_requirement_id") references "isms_requirements"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_finding_audit_no_uq" on "isms_audit_findings"(
  "isms_audit_id",
  "finding_no"
);
CREATE INDEX "isms_finding_org_status_idx" on "isms_audit_findings"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_corrective_actions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_audit_finding_id" integer not null,
  "title" varchar not null,
  "root_cause" text,
  "action_plan" text,
  "owner_user_id" integer,
  "due_on" date,
  "status" varchar not null default 'open',
  "effectiveness_note" text,
  "completed_on" date,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_audit_finding_id") references "isms_audit_findings"("id") on delete cascade,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE INDEX "isms_corr_org_status_idx" on "isms_corrective_actions"(
  "organization_id",
  "status"
);
CREATE INDEX "isms_corr_org_due_idx" on "isms_corrective_actions"(
  "organization_id",
  "due_on"
);
CREATE TABLE IF NOT EXISTS "isms_management_reviews"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "review_no" integer not null,
  "held_on" date not null,
  "participants" text not null,
  "inputs" text not null,
  "decisions" text not null,
  "follow_ups" text,
  "status" varchar not null default 'draft',
  "approved_by_user_id" integer,
  "approved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete cascade,
  foreign key("approved_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_review_org_no_uq" on "isms_management_reviews"(
  "organization_id",
  "review_no"
);
CREATE INDEX "isms_review_org_status_idx" on "isms_management_reviews"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_risk_assessments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_risk_id" integer not null,
  "assessment_no" integer not null,
  "kind" varchar not null,
  "likelihood" integer not null,
  "impact" integer not null,
  "score" integer not null,
  "rationale" text,
  "status" varchar not null default 'draft',
  "approved_by_user_id" integer,
  "approved_at" datetime,
  "valid_until" date,
  "created_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_risk_id") references "isms_risks"("id") on delete cascade,
  foreign key("approved_by_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_assessment_risk_no_uq" on "isms_risk_assessments"(
  "isms_risk_id",
  "assessment_no"
);
CREATE INDEX "isms_assessment_org_risk_idx" on "isms_risk_assessments"(
  "organization_id",
  "isms_risk_id",
  "kind",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_audit_packages"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "package_no" integer not null,
  "title" varchar not null,
  "as_of_date" date not null,
  "norm" varchar,
  "edition" varchar,
  "status" varchar not null default 'draft',
  "file_path" varchar,
  "file_hash" varchar,
  "finalized_by_user_id" integer,
  "finalized_at" datetime,
  "created_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete cascade,
  foreign key("finalized_by_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_pkg_org_no_uq" on "isms_audit_packages"(
  "organization_id",
  "package_no"
);
CREATE INDEX "isms_pkg_org_status_idx" on "isms_audit_packages"(
  "organization_id",
  "status"
);
CREATE INDEX "isms_pkg_scope_status_idx" on "isms_audit_packages"(
  "isms_scope_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_audit_package_tokens"(
  "id" integer primary key autoincrement not null,
  "isms_audit_package_id" integer not null,
  "token_hash" varchar not null,
  "label" varchar not null,
  "expires_at" datetime not null,
  "created_by_user_id" integer,
  "last_accessed_at" datetime,
  "revoked_at" datetime,
  "created_at" datetime,
  foreign key("isms_audit_package_id") references "isms_audit_packages"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_pkg_token_hash_uq" on "isms_audit_package_tokens"(
  "token_hash"
);
CREATE TABLE IF NOT EXISTS "day_closures"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "day" date not null,
  "status" varchar not null default 'open',
  "closed_at" datetime,
  "closed_by_user_id" integer,
  "reopened_at" datetime,
  "reopened_by_user_id" integer,
  "reopen_reason" text,
  "attendance_locked" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("closed_by_user_id") references "users"("id") on delete set null,
  foreign key("reopened_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "day_closures_org_user_day_unique" on "day_closures"(
  "organization_id",
  "user_id",
  "day"
);
CREATE INDEX "day_closures_status_idx" on "day_closures"(
  "organization_id",
  "status"
);
CREATE INDEX "day_closures_user_day_idx" on "day_closures"("user_id", "day");
CREATE TABLE IF NOT EXISTS "day_correction_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "day_closure_id" integer not null,
  "requested_by_user_id" integer not null,
  "reason" text not null,
  "status" varchar not null default 'pending',
  "decided_at" datetime,
  "decided_by_user_id" integer,
  "decision_note" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("day_closure_id") references "day_closures"("id") on delete cascade,
  foreign key("requested_by_user_id") references "users"("id") on delete cascade,
  foreign key("decided_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "day_corr_requests_status_idx" on "day_correction_requests"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "bank_accounts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "label" varchar not null,
  "iban" text not null,
  "iban_hash" varchar not null,
  "bic" varchar,
  "account_holder" varchar,
  "datev_account_no" varchar,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "bank_accounts_org_iban_uq" on "bank_accounts"(
  "organization_id",
  "iban_hash"
);
CREATE INDEX "bank_accounts_org_active_idx" on "bank_accounts"(
  "organization_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "bank_statements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bank_account_id" integer,
  "source_format" varchar not null,
  "file_path" varchar not null,
  "file_hash" varchar not null,
  "statement_iban_hash" varchar,
  "opening_balance" numeric,
  "closing_balance" numeric,
  "period_from" date,
  "period_to" date,
  "tx_count" integer not null default '0',
  "balance_check" varchar not null default 'unknown',
  "imported_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bank_account_id") references "bank_accounts"("id") on delete set null,
  foreign key("imported_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "bank_stmt_org_file_uq" on "bank_statements"(
  "organization_id",
  "file_hash"
);
CREATE INDEX "bank_stmt_org_acct_idx" on "bank_statements"(
  "organization_id",
  "bank_account_id"
);
CREATE TABLE IF NOT EXISTS "bank_transactions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bank_statement_id" integer not null,
  "line_index" integer not null,
  "booking_date" date not null,
  "valuta_date" date,
  "amount" numeric not null,
  "direction" varchar not null,
  "currency" varchar not null default 'EUR',
  "end_to_end_id" varchar,
  "mandate_ref" varchar,
  "counterparty_name" text,
  "counterparty_iban" text,
  "counterparty_iban_hash" varchar,
  "purpose" text,
  "extracted_refs" text,
  "is_reversal" tinyint(1) not null default '0',
  "fingerprint" varchar not null,
  "match_status" varchar not null default 'unmatched',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bank_statement_id") references "bank_statements"("id") on delete cascade
);
CREATE UNIQUE INDEX "bank_tx_org_fp_uq" on "bank_transactions"(
  "organization_id",
  "fingerprint"
);
CREATE INDEX "bank_tx_stmt_line_idx" on "bank_transactions"(
  "bank_statement_id",
  "line_index"
);
CREATE INDEX "bank_tx_org_status_idx" on "bank_transactions"(
  "organization_id",
  "match_status"
);
CREATE INDEX "bank_tx_cp_iban_idx" on "bank_transactions"(
  "counterparty_iban_hash"
);
CREATE TABLE IF NOT EXISTS "payment_allocations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bank_transaction_id" integer not null,
  "allocatable_type" varchar not null,
  "allocatable_id" integer not null,
  "amount" numeric not null,
  "kind" varchar not null,
  "note" varchar,
  "confirmed_by_user_id" integer,
  "confirmed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bank_transaction_id") references "bank_transactions"("id") on delete cascade,
  foreign key("confirmed_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "pay_alloc_target_idx" on "payment_allocations"(
  "allocatable_type",
  "allocatable_id"
);
CREATE INDEX "pay_alloc_org_tx_idx" on "payment_allocations"(
  "organization_id",
  "bank_transaction_id"
);
CREATE TABLE IF NOT EXISTS "payment_reconciliation_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "bank_transaction_id" integer,
  "event" varchar not null,
  "actor_user_id" integer,
  "payload" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "pre_tx_event_idx" on "payment_reconciliation_events"(
  "bank_transaction_id",
  "event"
);
CREATE INDEX "pre_hash_idx" on "payment_reconciliation_events"("hash");
CREATE TABLE IF NOT EXISTS "datev_booking_batches"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "batch_no" integer not null default '0',
  "period_from" date not null,
  "period_to" date not null,
  "status" varchar not null default 'draft',
  "skr" varchar not null,
  "advisor_number" integer not null,
  "client_number" integer not null,
  "file_path" varchar,
  "file_hash" varchar,
  "booking_count" integer not null default '0',
  "total_amount" numeric not null default '0',
  "finalized_locked" tinyint(1) not null default '0',
  "created_by_user_id" integer,
  "finalized_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "dbb_org_status_idx" on "datev_booking_batches"(
  "organization_id",
  "status"
);
CREATE UNIQUE INDEX "dbb_org_batchno_uq" on "datev_booking_batches"(
  "organization_id",
  "batch_no"
);
CREATE TABLE IF NOT EXISTS "datev_booking_sources"(
  "id" integer primary key autoincrement not null,
  "datev_booking_batch_id" integer not null,
  "source_type" varchar not null,
  "source_id" integer not null,
  "debtor_account" varchar not null,
  "revenue_account" varchar not null,
  "soll_haben" varchar not null,
  "amount" numeric not null,
  "tax_key" varchar,
  "document_ref" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("datev_booking_batch_id") references "datev_booking_batches"("id") on delete cascade
);
CREATE INDEX "dbs_source_idx" on "datev_booking_sources"(
  "source_type",
  "source_id"
);
CREATE UNIQUE INDEX "dbs_batch_source_uq" on "datev_booking_sources"(
  "datev_booking_batch_id",
  "source_type",
  "source_id"
);
CREATE TABLE IF NOT EXISTS "datev_booking_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "datev_booking_batch_id" integer,
  "event" varchar not null,
  "actor_user_id" integer,
  "payload" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "dbe_batch_event_idx" on "datev_booking_events"(
  "datev_booking_batch_id",
  "event"
);
CREATE INDEX "dbe_hash_idx" on "datev_booking_events"("hash");
CREATE TABLE IF NOT EXISTS "isms_security_incidents"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer,
  "incident_no" integer not null,
  "title" varchar not null,
  "description" text,
  "category" varchar not null,
  "severity" varchar not null,
  "status" varchar not null default 'reported',
  "detected_at" datetime,
  "occurred_at" datetime,
  "contained_at" datetime,
  "closed_at" datetime,
  "reporter_user_id" integer,
  "owner_user_id" integer,
  "impact" text,
  "root_cause" text,
  "lessons_learned" text,
  "personal_data_affected" tinyint(1) not null default '0',
  "privacy_incident_ref" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete set null,
  foreign key("reporter_user_id") references "users"("id") on delete set null,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_si_org_no_uq" on "isms_security_incidents"(
  "organization_id",
  "incident_no"
);
CREATE INDEX "isms_si_org_status_idx" on "isms_security_incidents"(
  "organization_id",
  "status",
  "severity"
);
CREATE INDEX "isms_si_org_detected_idx" on "isms_security_incidents"(
  "organization_id",
  "detected_at"
);
CREATE TABLE IF NOT EXISTS "isms_incident_risk"(
  "incident_id" integer not null,
  "risk_id" integer not null,
  foreign key("incident_id") references "isms_security_incidents"("id") on delete cascade,
  foreign key("risk_id") references "isms_risks"("id") on delete cascade,
  primary key("incident_id", "risk_id")
);
CREATE INDEX "isms_incident_risk_risk_idx" on "isms_incident_risk"("risk_id");
CREATE TABLE IF NOT EXISTS "isms_incident_control"(
  "incident_id" integer not null,
  "control_id" integer not null,
  foreign key("incident_id") references "isms_security_incidents"("id") on delete cascade,
  foreign key("control_id") references "isms_controls"("id") on delete cascade,
  primary key("incident_id", "control_id")
);
CREATE INDEX "isms_incident_ctrl_ctrl_idx" on "isms_incident_control"(
  "control_id"
);
CREATE TABLE IF NOT EXISTS "isms_advisories"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "format" varchar not null,
  "document_id_ref" varchar,
  "file_path" varchar not null,
  "file_hash" varchar not null,
  "imported_by_user_id" integer,
  "vuln_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("imported_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_adv_org_hash_uq" on "isms_advisories"(
  "organization_id",
  "file_hash"
);
CREATE INDEX "isms_adv_org_format_idx" on "isms_advisories"(
  "organization_id",
  "format"
);
CREATE TABLE IF NOT EXISTS "isms_vulnerabilities"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "vuln_no" integer not null,
  "title" varchar not null,
  "identifier" varchar,
  "cvss_score" numeric,
  "severity" varchar not null,
  "affected_component" varchar,
  "isms_software_product_id" integer,
  "isms_advisory_id" integer,
  "status" varchar not null default 'open',
  "exploitability" varchar not null default 'underInvestigation',
  "exploitability_note" text,
  "owner_user_id" integer,
  "due_on" date,
  "source" varchar not null default 'manual',
  "advisory_ref" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_software_product_id") references "isms_software_products"("id") on delete set null,
  foreign key("isms_advisory_id") references "isms_advisories"("id") on delete set null,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_vuln_org_no_uq" on "isms_vulnerabilities"(
  "organization_id",
  "vuln_no"
);
CREATE INDEX "isms_vuln_org_status_idx" on "isms_vulnerabilities"(
  "organization_id",
  "status",
  "severity"
);
CREATE INDEX "isms_vuln_org_due_idx" on "isms_vulnerabilities"(
  "organization_id",
  "due_on"
);
CREATE INDEX "isms_vuln_org_ident_idx" on "isms_vulnerabilities"(
  "organization_id",
  "identifier"
);
CREATE TABLE IF NOT EXISTS "restore_tests"(
  "id" integer primary key autoincrement not null,
  "source" varchar not null,
  "tested_on" date not null,
  "result" varchar not null,
  "scope" varchar,
  "restored_size_bytes" integer,
  "duration_minutes" integer,
  "notes" text,
  "next_due_on" date,
  "performed_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "restore_tests_tested_on_idx" on "restore_tests"("tested_on");
CREATE INDEX "restore_tests_result_tested_idx" on "restore_tests"(
  "result",
  "tested_on"
);
CREATE INDEX "restore_tests_next_due_idx" on "restore_tests"("next_due_on");
CREATE TABLE IF NOT EXISTS "sla_violations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "sla_contract_id" integer,
  "kind" varchar not null,
  "target_at" datetime,
  "breached_at" datetime,
  "overdue_minutes" integer not null default '0',
  "priority" varchar,
  "cause" varchar,
  "acknowledged_at" datetime,
  "acknowledged_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade,
  foreign key("sla_contract_id") references "sla_contracts"("id") on delete set null,
  foreign key("acknowledged_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "sla_violations_uniq_ticket_kind" on "sla_violations"(
  "service_ticket_id",
  "kind"
);
CREATE INDEX "sla_violations_idx_org_kind" on "sla_violations"(
  "organization_id",
  "kind"
);
CREATE INDEX "sla_violations_idx_org_breach" on "sla_violations"(
  "organization_id",
  "breached_at"
);
CREATE TABLE IF NOT EXISTS "asset_assignments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "assigned_to_user_id" integer,
  "assigned_to_team_id" integer,
  "diary_entry_id" integer,
  "checked_out_at" datetime not null,
  "checked_out_by_user_id" integer,
  "expected_return_at" datetime,
  "returned_at" datetime,
  "returned_by_user_id" integer,
  "condition_out" varchar,
  "condition_in" varchar,
  "note" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("assigned_to_user_id") references "users"("id") on delete set null,
  foreign key("assigned_to_team_id") references "teams"("id") on delete set null,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete set null,
  foreign key("checked_out_by_user_id") references "users"("id") on delete set null,
  foreign key("returned_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "asgn_idx_org_asset" on "asset_assignments"(
  "organization_id",
  "asset_id"
);
CREATE INDEX "asgn_idx_asset_open" on "asset_assignments"(
  "asset_id",
  "returned_at"
);
CREATE INDEX "asgn_idx_user_open" on "asset_assignments"(
  "assigned_to_user_id",
  "returned_at"
);
CREATE INDEX "asgn_idx_overdue" on "asset_assignments"(
  "expected_return_at",
  "returned_at"
);
CREATE TABLE IF NOT EXISTS "asset_defects"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "reported_by_user_id" integer,
  "reported_at" datetime not null,
  "severity" varchar not null,
  "title" varchar not null,
  "description" text,
  "status" varchar not null default 'open',
  "blocks_usage" tinyint(1) not null default '0',
  "resolved_at" datetime,
  "resolved_by_user_id" integer,
  "resolution_note" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("reported_by_user_id") references "users"("id") on delete set null,
  foreign key("resolved_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "adft_idx_org_asset" on "asset_defects"(
  "organization_id",
  "asset_id"
);
CREATE INDEX "adft_idx_asset_status" on "asset_defects"("asset_id", "status");
CREATE INDEX "adft_idx_block" on "asset_defects"(
  "asset_id",
  "blocks_usage",
  "status"
);
CREATE TABLE IF NOT EXISTS "safety_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "event_no" integer not null,
  "kind" varchar not null,
  "severity" varchar not null default 'low',
  "occurred_at" datetime not null,
  "location" varchar,
  "subject_type" varchar,
  "subject_id" integer,
  "reported_by_user_id" integer not null,
  "affected_person" varchar,
  "description" text not null,
  "immediate_action" text,
  "status" varchar not null default 'reported',
  "root_cause" text,
  "closed_at" datetime,
  "closed_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("reported_by_user_id") references "users"("id") on delete cascade,
  foreign key("closed_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "safety_events_org_no_uq" on "safety_events"(
  "organization_id",
  "event_no"
);
CREATE INDEX "safety_events_org_status_idx" on "safety_events"(
  "organization_id",
  "status",
  "severity"
);
CREATE INDEX "safety_events_kind_idx" on "safety_events"(
  "kind",
  "occurred_at"
);
CREATE INDEX "safety_events_subject_idx" on "safety_events"(
  "subject_type",
  "subject_id"
);
CREATE TABLE IF NOT EXISTS "webhook_endpoints"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "label" varchar not null,
  "url" varchar not null,
  "secret" text not null,
  "events" text not null,
  "active" tinyint(1) not null default '1',
  "created_by_user_id" integer,
  "last_delivery_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "webhook_endpoints_org_active_idx" on "webhook_endpoints"(
  "organization_id",
  "active"
);
CREATE TABLE IF NOT EXISTS "webhook_deliveries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "webhook_endpoint_id" integer not null,
  "event" varchar not null,
  "payload_hash" varchar not null,
  "status" varchar not null default 'pending',
  "http_status" integer,
  "attempt" integer not null default '1',
  "response_excerpt" varchar,
  "dispatched_at" datetime,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("webhook_endpoint_id") references "webhook_endpoints"("id") on delete cascade
);
CREATE INDEX "webhook_deliveries_endpoint_idx" on "webhook_deliveries"(
  "webhook_endpoint_id",
  "created_at"
);
CREATE INDEX "webhook_deliveries_org_status_idx" on "webhook_deliveries"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "vehicle_reservations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "vehicle_id" integer not null,
  "diary_entry_id" integer,
  "reserved_by_user_id" integer not null,
  "reserved_from" datetime not null,
  "reserved_to" datetime not null,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("vehicle_id") references "vehicles"("id") on delete cascade,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete set null,
  foreign key("reserved_by_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "veh_res_org_vehicle_idx" on "vehicle_reservations"(
  "organization_id",
  "vehicle_id"
);
CREATE INDEX "veh_res_window_idx" on "vehicle_reservations"(
  "vehicle_id",
  "reserved_from",
  "reserved_to"
);
CREATE INDEX "veh_res_diary_idx" on "vehicle_reservations"("diary_entry_id");
CREATE TABLE IF NOT EXISTS "availability_windows"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "weekday" integer,
  "specific_date" date,
  "start_time" time,
  "end_time" time,
  "kind" varchar not null default 'available',
  "valid_from" date,
  "valid_until" date,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "avail_win_org_user_idx" on "availability_windows"(
  "organization_id",
  "user_id"
);
CREATE INDEX "avail_win_user_weekday_idx" on "availability_windows"(
  "user_id",
  "weekday"
);
CREATE INDEX "avail_win_user_date_idx" on "availability_windows"(
  "user_id",
  "specific_date"
);
CREATE TABLE IF NOT EXISTS "desired_shifts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "date" date not null,
  "shift_type_id" integer,
  "preference" varchar not null default 'want',
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("shift_type_id") references "shift_types"("id") on delete cascade
);
CREATE INDEX "desired_shift_org_user_idx" on "desired_shifts"(
  "organization_id",
  "user_id"
);
CREATE INDEX "desired_shift_user_date_idx" on "desired_shifts"(
  "user_id",
  "date"
);
CREATE INDEX "desired_shift_date_type_idx" on "desired_shifts"(
  "date",
  "shift_type_id"
);
CREATE TABLE IF NOT EXISTS "shift_exchanges"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "scheduled_shift_id" integer not null,
  "requested_by_user_id" integer not null,
  "target_user_id" integer,
  "offered_shift_id" integer,
  "status" varchar not null default 'requested',
  "decided_by_user_id" integer,
  "decided_at" datetime,
  "reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("scheduled_shift_id") references "scheduled_shifts"("id") on delete cascade,
  foreign key("requested_by_user_id") references "users"("id") on delete cascade,
  foreign key("target_user_id") references "users"("id") on delete set null,
  foreign key("offered_shift_id") references "scheduled_shifts"("id") on delete set null,
  foreign key("decided_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "shift_exch_org_status_idx" on "shift_exchanges"(
  "organization_id",
  "status"
);
CREATE INDEX "shift_exch_shift_idx" on "shift_exchanges"("scheduled_shift_id");
CREATE INDEX "shift_exch_target_status_idx" on "shift_exchanges"(
  "target_user_id",
  "status"
);
CREATE INDEX "shift_exch_requester_idx" on "shift_exchanges"(
  "requested_by_user_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "suppliers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "slug" varchar,
  "number" varchar,
  "vendor_number" varchar,
  "company" varchar,
  "vat_id" varchar,
  "contact_name" varchar,
  "contact_persons" text,
  "email" varchar,
  "phone" varchar,
  "mobile" varchar,
  "fax" varchar,
  "homepage" varchar,
  "address" text,
  "address_street" varchar,
  "address_zip" varchar,
  "address_city" varchar,
  "address_lat" numeric,
  "address_lng" numeric,
  "country" varchar,
  "currency" varchar not null default 'EUR',
  "timezone" varchar,
  "color" varchar,
  "comment" text,
  "bank_account_holder" varchar,
  "bank_iban" varchar,
  "bank_bic" varchar,
  "bank_name" varchar,
  "active" tinyint(1) not null default '1',
  "archived_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "tax_number" varchar,
  "number_source" varchar not null default 'local',
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "suppliers_organization_id_index" on "suppliers"(
  "organization_id"
);
CREATE INDEX "suppliers_archived_at_index" on "suppliers"("archived_at");
CREATE INDEX "suppliers_name_idx" on "suppliers"("name");
CREATE UNIQUE INDEX "suppliers_organization_id_number_unique" on "suppliers"(
  "organization_id",
  "number"
);
CREATE UNIQUE INDEX "suppliers_org_slug_unique" on "suppliers"(
  "organization_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "isms_supplier_assessments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "assessment_no" integer not null,
  "supplier_id" integer,
  "supplier_name" varchar not null,
  "criticality" varchar not null default 'medium',
  "service_description" text,
  "isms_scope_id" integer,
  "security_requirements" text,
  "has_nda" tinyint(1) not null default '0',
  "has_dpa" tinyint(1) not null default '0',
  "dpa_ref" varchar,
  "audit_right" tinyint(1) not null default '0',
  "last_review_on" date,
  "next_review_on" date,
  "risk_rating" varchar not null default 'medium',
  "status" varchar not null default 'draft',
  "findings" text,
  "owner_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete set null,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete set null,
  foreign key("owner_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_supplier_org_no_uq" on "isms_supplier_assessments"(
  "organization_id",
  "assessment_no"
);
CREATE INDEX "isms_supplier_org_status_idx" on "isms_supplier_assessments"(
  "organization_id",
  "status",
  "risk_rating"
);
CREATE INDEX "isms_supplier_org_review_idx" on "isms_supplier_assessments"(
  "organization_id",
  "next_review_on"
);
CREATE TABLE IF NOT EXISTS "room_requirements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "room_id" integer not null,
  "kind" varchar not null,
  "level" varchar,
  "note" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("room_id") references "rooms"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "room_req_idx_room" on "room_requirements"(
  "room_id",
  "is_active"
);
CREATE INDEX "room_req_idx_kind" on "room_requirements"(
  "organization_id",
  "kind"
);
CREATE TABLE IF NOT EXISTS "external_participant_events"(
  "id" integer primary key autoincrement not null,
  "external_participant_id" integer not null,
  "event" varchar not null,
  "payload" text,
  "ip" varchar,
  "user_agent" varchar,
  "created_at" datetime,
  foreign key("external_participant_id") references "external_participants"("id") on delete cascade
);
CREATE INDEX "ext_part_event_idx" on "external_participant_events"(
  "external_participant_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "comments"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "body" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  "commentable_type" varchar,
  "commentable_id" integer,
  "organization_id" integer,
  "external_participant_id" integer,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("external_participant_id") references "external_participants"("id") on delete set null
);
CREATE INDEX "comments_commentable_type_commentable_id_index" on "comments"(
  "commentable_type",
  "commentable_id"
);
CREATE INDEX "idx_comments_org" on "comments"("organization_id");
CREATE TABLE IF NOT EXISTS "report_targets"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "metric" varchar not null,
  "scope" varchar not null default 'org',
  "scope_id" integer,
  "target_value" numeric not null,
  "period" varchar,
  "valid_from" date,
  "valid_until" date,
  "note" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "report_targets_idx_org_metric" on "report_targets"(
  "organization_id",
  "metric"
);
CREATE INDEX "report_targets_idx_lookup" on "report_targets"(
  "organization_id",
  "metric",
  "scope",
  "scope_id"
);
CREATE TABLE IF NOT EXISTS "room_requirement_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "code" varchar not null,
  "kind" varchar not null,
  "label" varchar not null,
  "level" varchar,
  "note" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "room_req_tpl_uq_code" on "room_requirement_templates"(
  "organization_id",
  "code"
);
CREATE INDEX "room_req_tpl_idx_kind" on "room_requirement_templates"(
  "organization_id",
  "kind"
);
CREATE TABLE IF NOT EXISTS "customer_queries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "customer_id" integer,
  "signature_token_id" integer,
  "asker_name" varchar,
  "asker_email" varchar,
  "question" text not null,
  "answer" text,
  "status" varchar not null default 'open',
  "answered_at" datetime,
  "answered_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("signature_token_id") references "protocol_signature_tokens"("id") on delete set null,
  foreign key("answered_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "customer_queries_org_status_idx" on "customer_queries"(
  "organization_id",
  "status"
);
CREATE INDEX "customer_queries_subject_idx" on "customer_queries"(
  "subject_type",
  "subject_id"
);
CREATE INDEX "customer_queries_customer_idx" on "customer_queries"(
  "customer_id"
);
CREATE TABLE IF NOT EXISTS "articles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "number" varchar,
  "gtin" varchar,
  "name" varchar not null,
  "description" text,
  "type" varchar not null default 'consumable',
  "base_unit" varchar not null default 'Stk',
  "tax_class" varchar,
  "stockable" tinyint(1) not null default '1',
  "purchasable" tinyint(1) not null default '1',
  "sellable" tinyint(1) not null default '1',
  "manufacturable" tinyint(1) not null default '0',
  "batch_required" tinyint(1) not null default '0',
  "serial_required" tinyint(1) not null default '0',
  "shelf_life_required" tinyint(1) not null default '0',
  "status" varchar not null default 'draft',
  "default_procedure_template_version_id" integer,
  "default_purchase_price" numeric,
  "default_sale_price" numeric,
  "currency" varchar not null default 'EUR',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "valuation_method" varchar,
  "serial_scheme" text,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("default_procedure_template_version_id") references "procedure_template_versions"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "articles_organization_id_index" on "articles"("organization_id");
CREATE INDEX "articles_status_index" on "articles"("status");
CREATE INDEX "articles_type_index" on "articles"("type");
CREATE UNIQUE INDEX "articles_org_number_unique" on "articles"(
  "organization_id",
  "number"
);
CREATE UNIQUE INDEX "articles_org_gtin_unique" on "articles"(
  "organization_id",
  "gtin"
);
CREATE TABLE IF NOT EXISTS "article_option_definitions"(
  "id" integer primary key autoincrement not null,
  "article_id" integer not null,
  "code" varchar not null,
  "name" varchar not null,
  "position" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("article_id") references "articles"("id") on delete cascade
);
CREATE UNIQUE INDEX "article_opt_def_unique" on "article_option_definitions"(
  "article_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "article_option_values"(
  "id" integer primary key autoincrement not null,
  "article_option_definition_id" integer not null,
  "code" varchar not null,
  "label" varchar not null,
  "position" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("article_option_definition_id") references "article_option_definitions"("id") on delete cascade
);
CREATE UNIQUE INDEX "article_opt_val_unique" on "article_option_values"(
  "article_option_definition_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "article_variants"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_id" integer not null,
  "sku" varchar,
  "gtin" varchar,
  "name" varchar,
  "status" varchar not null default 'active',
  "is_default" tinyint(1) not null default '0',
  "option_signature" varchar not null default '',
  "purchase_price" numeric,
  "sale_price" numeric,
  "currency" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "article_variants_organization_id_index" on "article_variants"(
  "organization_id"
);
CREATE INDEX "article_variants_article_id_index" on "article_variants"(
  "article_id"
);
CREATE INDEX "article_variants_status_index" on "article_variants"("status");
CREATE UNIQUE INDEX "article_variant_sig_unique" on "article_variants"(
  "article_id",
  "option_signature"
);
CREATE UNIQUE INDEX "article_variant_sku_unique" on "article_variants"(
  "organization_id",
  "sku"
);
CREATE TABLE IF NOT EXISTS "article_variant_option_values"(
  "id" integer primary key autoincrement not null,
  "article_variant_id" integer not null,
  "article_option_value_id" integer not null,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("article_option_value_id") references "article_option_values"("id") on delete cascade
);
CREATE UNIQUE INDEX "article_variant_optval_unique" on "article_variant_option_values"(
  "article_variant_id",
  "article_option_value_id"
);
CREATE INDEX "article_variant_optval_value_idx" on "article_variant_option_values"(
  "article_option_value_id"
);
CREATE TABLE IF NOT EXISTS "article_units"(
  "id" integer primary key autoincrement not null,
  "article_id" integer not null,
  "code" varchar not null,
  "label" varchar,
  "kind" varchar not null default 'packaging',
  "factor_to_base" numeric not null default '1',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("article_id") references "articles"("id") on delete cascade
);
CREATE UNIQUE INDEX "article_units_code_unique" on "article_units"(
  "article_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "external_article_mappings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plugin_id" varchar not null,
  "external_id" varchar not null,
  "article_id" integer,
  "article_variant_id" integer,
  "external_parent_id" varchar,
  "external_number" varchar,
  "unit" varchar,
  "sync_status" varchar not null default 'pending',
  "last_synced_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade
);
CREATE INDEX "external_article_mappings_organization_id_index" on "external_article_mappings"(
  "organization_id"
);
CREATE INDEX "external_article_mappings_article_id_index" on "external_article_mappings"(
  "article_id"
);
CREATE INDEX "external_article_mappings_article_variant_id_index" on "external_article_mappings"(
  "article_variant_id"
);
CREATE UNIQUE INDEX "ext_article_map_unique" on "external_article_mappings"(
  "organization_id",
  "plugin_id",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "warehouses"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "code" varchar,
  "name" varchar not null,
  "is_default" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "blocked" tinyint(1) not null default '0',
  "location_note" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "warehouses_organization_id_index" on "warehouses"(
  "organization_id"
);
CREATE UNIQUE INDEX "warehouses_org_code_unique" on "warehouses"(
  "organization_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "stock_reservations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "quantity" numeric not null,
  "consumed_qty" numeric not null default '0',
  "ownership_type" varchar not null default 'own',
  "owner_ref" varchar,
  "status" varchar not null default 'active',
  "priority" integer not null default '100',
  "source_type" varchar,
  "source_id" integer,
  "reserved_at" datetime not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "stock_reservations_organization_id_index" on "stock_reservations"(
  "organization_id"
);
CREATE INDEX "stock_resv_bucket_idx" on "stock_reservations"(
  "article_variant_id",
  "warehouse_id",
  "status"
);
CREATE INDEX "stock_resv_source_idx" on "stock_reservations"(
  "source_type",
  "source_id"
);
CREATE TABLE IF NOT EXISTS "stock_level_settings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "min_stock" numeric not null default '0',
  "reorder_point" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade
);
CREATE INDEX "stock_level_settings_organization_id_index" on "stock_level_settings"(
  "organization_id"
);
CREATE UNIQUE INDEX "stock_level_variant_wh_unique" on "stock_level_settings"(
  "article_variant_id",
  "warehouse_id"
);
CREATE TABLE IF NOT EXISTS "stock_counts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "warehouse_id" integer not null,
  "status" varchar not null default 'counting',
  "counted_at" datetime not null,
  "note" text,
  "created_by" integer,
  "reviewed_by" integer,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "count_type" varchar not null default 'full',
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("reviewed_by") references "users"("id") on delete set null
);
CREATE INDEX "stock_counts_organization_id_index" on "stock_counts"(
  "organization_id"
);
CREATE INDEX "stock_counts_wh_status_idx" on "stock_counts"(
  "warehouse_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "stock_count_lines"(
  "id" integer primary key autoincrement not null,
  "stock_count_id" integer not null,
  "article_variant_id" integer not null,
  "stock_state" varchar not null default 'physical',
  "ownership_type" varchar not null default 'own',
  "book_qty" numeric not null default '0',
  "counted_qty" numeric,
  "applied" tinyint(1) not null default '0',
  "counted_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("stock_count_id") references "stock_counts"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("counted_by") references "users"("id") on delete set null
);
CREATE INDEX "stock_count_lines_stock_count_id_index" on "stock_count_lines"(
  "stock_count_id"
);
CREATE TABLE IF NOT EXISTS "stock_valuations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "avg_cost" numeric not null default '0',
  "qty_on_hand" numeric not null default '0',
  "currency" varchar not null default 'EUR',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade
);
CREATE INDEX "stock_valuations_organization_id_index" on "stock_valuations"(
  "organization_id"
);
CREATE UNIQUE INDEX "stock_valuation_variant_wh_unique" on "stock_valuations"(
  "article_variant_id",
  "warehouse_id"
);
CREATE TABLE IF NOT EXISTS "article_variant_bom_overrides"(
  "id" integer primary key autoincrement not null,
  "article_variant_id" integer not null,
  "position_code" varchar not null,
  "action" varchar not null,
  "article_id" integer,
  "quantity_kind" varchar,
  "quantity" numeric,
  "ratio_part" numeric,
  "unit" varchar,
  "waste_surcharge" numeric,
  "is_tool" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete set null
);
CREATE UNIQUE INDEX "article_variant_bom_override_unique" on "article_variant_bom_overrides"(
  "article_variant_id",
  "position_code",
  "action"
);
CREATE TABLE IF NOT EXISTS "stock_deliveries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "manufacturing_order_id" integer,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "customer_id" integer,
  "quantity" numeric not null,
  "unit" varchar not null,
  "sku_snapshot" varchar,
  "name_snapshot" varchar not null,
  "unit_price_snapshot" numeric,
  "currency" varchar not null default 'EUR',
  "stock_status" varchar not null default 'delivered',
  "facturation_status" varchar not null default 'pending',
  "facturation_target" varchar,
  "external_id" varchar,
  "delivered_at" datetime not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("manufacturing_order_id") references "manufacturing_orders"("id") on delete set null,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "stock_deliveries_organization_id_index" on "stock_deliveries"(
  "organization_id"
);
CREATE INDEX "stock_deliveries_manufacturing_order_id_index" on "stock_deliveries"(
  "manufacturing_order_id"
);
CREATE INDEX "stock_deliveries_variant_wh_idx" on "stock_deliveries"(
  "article_variant_id",
  "warehouse_id"
);
CREATE TABLE IF NOT EXISTS "material_substitutes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "manufacturing_order_id" integer not null,
  "manufacturing_order_material_id" integer,
  "planned_article_id" integer not null,
  "planned_variant_id" integer,
  "substitute_article_id" integer not null,
  "substitute_variant_id" integer,
  "quantity" numeric not null,
  "status" varchar not null default 'requested',
  "reason" text not null,
  "requested_by" integer,
  "approved_by" integer,
  "decided_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("manufacturing_order_id") references "manufacturing_orders"("id") on delete cascade,
  foreign key("manufacturing_order_material_id") references "manufacturing_order_materials"("id") on delete set null,
  foreign key("planned_article_id") references "articles"("id") on delete cascade,
  foreign key("planned_variant_id") references "article_variants"("id") on delete set null,
  foreign key("substitute_article_id") references "articles"("id") on delete cascade,
  foreign key("substitute_variant_id") references "article_variants"("id") on delete set null,
  foreign key("requested_by") references "users"("id") on delete set null,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE INDEX "material_substitutes_organization_id_index" on "material_substitutes"(
  "organization_id"
);
CREATE INDEX "material_substitutes_manufacturing_order_id_index" on "material_substitutes"(
  "manufacturing_order_id"
);
CREATE TABLE IF NOT EXISTS "procurement_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_id" integer not null,
  "article_variant_id" integer,
  "warehouse_id" integer,
  "quantity" numeric not null,
  "status" varchar not null default 'open',
  "source_type" varchar,
  "source_id" integer,
  "note" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete set null,
  foreign key("warehouse_id") references "warehouses"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "procurement_requests_organization_id_index" on "procurement_requests"(
  "organization_id"
);
CREATE INDEX "procurement_status_article_idx" on "procurement_requests"(
  "status",
  "article_id"
);
CREATE INDEX "procurement_source_idx" on "procurement_requests"(
  "source_type",
  "source_id"
);
CREATE TABLE IF NOT EXISTS "stock_serials"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "article_id" integer not null,
  "article_variant_id" integer not null,
  "serial_no" varchar not null,
  "status" varchar not null default 'in_stock',
  "source" varchar not null default 'manufactured',
  "warehouse_id" integer,
  "customer_id" integer,
  "manufacturing_order_id" integer,
  "stock_delivery_id" integer,
  "blocked_reason" varchar,
  "note" text,
  "shipped_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("manufacturing_order_id") references "manufacturing_orders"("id") on delete set null,
  foreign key("stock_delivery_id") references "stock_deliveries"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "stock_serials_org_article_no_uq" on "stock_serials"(
  "organization_id",
  "article_id",
  "serial_no"
);
CREATE INDEX "stock_serials_org_status_idx" on "stock_serials"(
  "organization_id",
  "status"
);
CREATE INDEX "stock_serials_variant_status_idx" on "stock_serials"(
  "article_variant_id",
  "status"
);
CREATE INDEX "stock_serials_no_idx" on "stock_serials"("serial_no");
CREATE TABLE IF NOT EXISTS "inventory_outbox"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "plugin_id" varchar,
  "operation" varchar not null,
  "payload" text not null,
  "idempotency_key" varchar not null,
  "status" varchar not null default 'pending',
  "attempts" integer not null default '0',
  "last_error" text,
  "stock_movement_id" integer,
  "confirmed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("stock_movement_id") references "stock_movements"("id") on delete set null
);
CREATE UNIQUE INDEX "inventory_outbox_idem_uq" on "inventory_outbox"(
  "organization_id",
  "idempotency_key"
);
CREATE INDEX "inventory_outbox_org_status_idx" on "inventory_outbox"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "stock_lots"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "article_variant_id" integer not null,
  "lot_no" varchar not null,
  "mfg_date" date,
  "best_before" date,
  "supplier_ref" varchar,
  "status" varchar not null default 'active',
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete cascade
);
CREATE UNIQUE INDEX "stock_lots_org_variant_no_uq" on "stock_lots"(
  "organization_id",
  "article_variant_id",
  "lot_no"
);
CREATE INDEX "stock_lots_variant_bb_idx" on "stock_lots"(
  "article_variant_id",
  "best_before"
);
CREATE TABLE IF NOT EXISTS "stock_valuation_layers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "qty_remaining" numeric not null,
  "unit_cost" numeric not null,
  "currency" varchar not null default('EUR'),
  "source_movement_id" integer,
  "acquired_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "stock_lot_id" integer,
  "best_before" date,
  foreign key("source_movement_id") references stock_movements("id") on delete set null on update no action,
  foreign key("warehouse_id") references warehouses("id") on delete cascade on update no action,
  foreign key("article_variant_id") references article_variants("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("stock_lot_id") references "stock_lots"("id") on delete set null
);
CREATE INDEX "stock_val_layers_fifo_idx" on "stock_valuation_layers"(
  "article_variant_id",
  "warehouse_id",
  "acquired_at"
);
CREATE INDEX "stock_valuation_layers_organization_id_index" on "stock_valuation_layers"(
  "organization_id"
);
CREATE INDEX "stock_val_layers_fefo_idx" on "stock_valuation_layers"(
  "article_variant_id",
  "warehouse_id",
  "best_before"
);
CREATE TABLE IF NOT EXISTS "user_dashboard_widgets"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "widget_key" varchar not null,
  "sort_order" integer not null default '0',
  "hidden" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "user_dashboard_widgets_user_id_widget_key_unique" on "user_dashboard_widgets"(
  "user_id",
  "widget_key"
);
CREATE INDEX "user_dashboard_widgets_user_id_sort_order_index" on "user_dashboard_widgets"(
  "user_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "stock_movements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "article_variant_id" integer not null,
  "warehouse_id" integer not null,
  "stock_state" varchar not null,
  "ownership_type" varchar not null default('own'),
  "owner_ref" varchar,
  "movement_type" varchar not null,
  "qty_base" numeric not null,
  "original_qty" numeric,
  "original_unit" varchar,
  "occurred_at" datetime not null,
  "actor_user_id" integer,
  "source_type" varchar,
  "source_id" integer,
  "idempotency_key" varchar,
  "cost_unit" numeric,
  "cost_total" numeric,
  "currency" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "stock_lot_id" integer,
  "stock_serial_id" integer,
  foreign key("actor_user_id") references users("id") on delete set null on update no action,
  foreign key("warehouse_id") references warehouses("id") on delete cascade on update no action,
  foreign key("article_variant_id") references article_variants("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("stock_lot_id") references "stock_lots"("id") on delete set null,
  foreign key("stock_serial_id") references "stock_serials"("id") on delete set null
);
CREATE INDEX "stock_mov_bucket_idx" on "stock_movements"(
  "article_variant_id",
  "warehouse_id",
  "stock_state"
);
CREATE UNIQUE INDEX "stock_mov_idem_unique" on "stock_movements"(
  "organization_id",
  "idempotency_key"
);
CREATE INDEX "stock_mov_source_idx" on "stock_movements"(
  "source_type",
  "source_id"
);
CREATE INDEX "stock_movements_organization_id_index" on "stock_movements"(
  "organization_id"
);
CREATE INDEX "stock_movements_lot_idx" on "stock_movements"("stock_lot_id");
CREATE TABLE IF NOT EXISTS "article_supplies"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "article_id" integer not null,
  "supplier_id" integer not null,
  "supplier_sku" varchar,
  "moq" numeric not null default '1',
  "pack_size" numeric not null default '1',
  "lead_time_days" integer not null default '0',
  "purchase_price" numeric,
  "currency" varchar not null default 'EUR',
  "is_preferred" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE UNIQUE INDEX "article_supplies_unique" on "article_supplies"(
  "organization_id",
  "article_id",
  "supplier_id"
);
CREATE INDEX "article_supplies_preferred_idx" on "article_supplies"(
  "article_id",
  "is_preferred"
);
CREATE TABLE IF NOT EXISTS "purchase_orders"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "number" varchar not null,
  "supplier_id" integer not null,
  "warehouse_id" integer not null,
  "status" varchar not null default 'draft',
  "currency" varchar not null default 'EUR',
  "ordered_at" datetime,
  "expected_at" date,
  "note" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "freight_cost" numeric,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade,
  foreign key("warehouse_id") references "warehouses"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "purchase_orders_org_number_uq" on "purchase_orders"(
  "organization_id",
  "number"
);
CREATE INDEX "purchase_orders_org_status_idx" on "purchase_orders"(
  "organization_id",
  "status"
);
CREATE INDEX "purchase_orders_supplier_id_index" on "purchase_orders"(
  "supplier_id"
);
CREATE TABLE IF NOT EXISTS "purchase_order_lines"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "purchase_order_id" integer not null,
  "article_id" integer not null,
  "article_variant_id" integer,
  "supplier_sku" varchar,
  "description" varchar not null,
  "ordered_qty" numeric not null,
  "received_qty" numeric not null default '0',
  "unit" varchar not null default 'Stk',
  "unit_price" numeric,
  "currency" varchar not null default 'EUR',
  "created_at" datetime,
  "updated_at" datetime,
  "note" varchar,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("purchase_order_id") references "purchase_orders"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("article_variant_id") references "article_variants"("id") on delete set null
);
CREATE INDEX "purchase_order_lines_purchase_order_id_index" on "purchase_order_lines"(
  "purchase_order_id"
);
CREATE INDEX "po_lines_article_idx" on "purchase_order_lines"(
  "article_id",
  "article_variant_id"
);
CREATE TABLE IF NOT EXISTS "manufacturing_order_reports"(
  "id" integer primary key autoincrement not null,
  "manufacturing_order_id" integer not null,
  "produced_qty" numeric not null default('0'),
  "good_qty" numeric not null default('0'),
  "scrap_qty" numeric not null default('0'),
  "rework_qty" numeric not null default('0'),
  "note" text,
  "reported_by" integer,
  "reported_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "stock_lot_id" integer,
  foreign key("reported_by") references users("id") on delete set null on update no action,
  foreign key("manufacturing_order_id") references manufacturing_orders("id") on delete cascade on update no action,
  foreign key("stock_lot_id") references "stock_lots"("id") on delete set null
);
CREATE INDEX "manufacturing_order_reports_manufacturing_order_id_index" on "manufacturing_order_reports"(
  "manufacturing_order_id"
);
CREATE TABLE IF NOT EXISTS "work_centers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "capacity_minutes" integer not null default '480',
  "setup_minutes" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "work_centers_org_code_uq" on "work_centers"(
  "organization_id",
  "code"
);
CREATE INDEX "work_centers_organization_id_index" on "work_centers"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "manufacturing_orders"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "number" varchar,
  "article_id" integer not null,
  "article_variant_id" integer,
  "target_qty" numeric not null,
  "unit" varchar not null,
  "status" varchar not null default('draft'),
  "priority" integer not null default('100'),
  "planned_start" date,
  "due_at" datetime,
  "customer_id" integer,
  "project_id" integer,
  "responsible_user_id" integer,
  "warehouse_id" integer,
  "procurement_mode" varchar,
  "procedure_template_version_id" integer,
  "bom_snapshot" text,
  "variant_snapshot" text,
  "parameter_snapshot" text,
  "procedure_run_id" integer,
  "created_by" integer,
  "released_at" datetime,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "subcontract_purchase_order_id" integer,
  "work_center_id" integer,
  "planned_minutes" integer,
  "parameters" text,
  foreign key("subcontract_purchase_order_id") references purchase_orders("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("article_id") references articles("id") on delete cascade on update no action,
  foreign key("article_variant_id") references article_variants("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("responsible_user_id") references users("id") on delete set null on update no action,
  foreign key("warehouse_id") references warehouses("id") on delete set null on update no action,
  foreign key("procedure_template_version_id") references procedure_template_versions("id") on delete set null on update no action,
  foreign key("procedure_run_id") references procedure_runs("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("work_center_id") references "work_centers"("id") on delete set null
);
CREATE UNIQUE INDEX "manufacturing_orders_org_number_unique" on "manufacturing_orders"(
  "organization_id",
  "number"
);
CREATE INDEX "manufacturing_orders_organization_id_index" on "manufacturing_orders"(
  "organization_id"
);
CREATE INDEX "manufacturing_orders_status_index" on "manufacturing_orders"(
  "status"
);
CREATE TABLE IF NOT EXISTS "purchase_order_advices"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "purchase_order_id" integer not null,
  "reference" varchar,
  "carrier" varchar,
  "tracking" varchar,
  "expected_at" date,
  "shipped_at" datetime,
  "status" varchar not null default 'announced',
  "note" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("purchase_order_id") references "purchase_orders"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "po_advices_org_status_idx" on "purchase_order_advices"(
  "organization_id",
  "status"
);
CREATE INDEX "purchase_order_advices_purchase_order_id_index" on "purchase_order_advices"(
  "purchase_order_id"
);
CREATE TABLE IF NOT EXISTS "purchase_order_advice_lines"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "purchase_order_advice_id" integer not null,
  "purchase_order_line_id" integer not null,
  "qty" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("purchase_order_advice_id") references "purchase_order_advices"("id") on delete cascade,
  foreign key("purchase_order_line_id") references "purchase_order_lines"("id") on delete cascade
);
CREATE INDEX "purchase_order_advice_lines_purchase_order_advice_id_index" on "purchase_order_advice_lines"(
  "purchase_order_advice_id"
);
CREATE TABLE IF NOT EXISTS "label_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "paper_size" varchar not null default 'a7',
  "orientation" varchar not null default 'landscape',
  "with_qr" tinyint(1) not null default '1',
  "fields" text not null,
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "label_templates_org_name_uq" on "label_templates"(
  "organization_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "user_filter_presets"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "scope" varchar not null,
  "name" varchar not null,
  "query" text not null,
  "is_default" tinyint(1) not null default '0',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "user_filter_presets_user_id_scope_sort_order_index" on "user_filter_presets"(
  "user_id",
  "scope",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "permits"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "event_id" integer,
  "title" varchar not null,
  "permit_type" varchar,
  "authority" varchar,
  "reference_no" varchar,
  "status" varchar not null default 'required',
  "applied_at" date,
  "valid_from" date,
  "valid_until" date,
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("event_id") references "events"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "permits_org_idx" on "permits"("organization_id");
CREATE INDEX "permits_org_status_idx" on "permits"(
  "organization_id",
  "status"
);
CREATE INDEX "permits_org_deadline_idx" on "permits"(
  "organization_id",
  "valid_until"
);
CREATE INDEX "permits_event_idx" on "permits"("event_id");
CREATE TABLE IF NOT EXISTS "invoice_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "header_text" text,
  "footer_text" text,
  "accent_color" varchar,
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "invoice_templates_organization_id_slug_unique" on "invoice_templates"(
  "organization_id",
  "slug"
);
CREATE INDEX "invoice_templates_organization_id_is_default_index" on "invoice_templates"(
  "organization_id",
  "is_default"
);
CREATE TABLE IF NOT EXISTS "customers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "number" varchar,
  "company" varchar,
  "vat_id" varchar,
  "contact_name" varchar,
  "email" varchar,
  "phone" varchar,
  "mobile" varchar,
  "fax" varchar,
  "homepage" varchar,
  "address" text,
  "country" varchar,
  "currency" varchar not null default('EUR'),
  "timezone" varchar,
  "color" varchar,
  "hourly_rate" numeric,
  "internal_rate" numeric,
  "comment" text,
  "invoice_text" text,
  "billable" tinyint(1) not null default('1'),
  "archived_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "address_street" varchar,
  "address_zip" varchar,
  "address_city" varchar,
  "contact_persons" text,
  "address_lat" numeric,
  "address_lng" numeric,
  "slug" varchar,
  "billing_mode" varchar,
  "buyer_reference" varchar,
  "debtor_no" varchar,
  "invoice_template_id" integer,
  "bank_account_holder" varchar,
  "bank_iban" varchar,
  "bank_bic" varchar,
  "bank_name" varchar,
  "tax_number" varchar,
  "lexoffice_contact_number" varchar,
  "number_source" varchar not null default 'local',
  "billing_increment_minutes" integer,
  "billing_grouping_gap_minutes" integer,
  "travel_settings" text,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("invoice_template_id") references "invoice_templates"("id") on delete set null
);
CREATE INDEX "customers_archived_at_index" on "customers"("archived_at");
CREATE INDEX "customers_name_idx" on "customers"("name");
CREATE UNIQUE INDEX "customers_org_slug_unique" on "customers"(
  "organization_id",
  "slug"
);
CREATE INDEX "customers_organization_id_index" on "customers"(
  "organization_id"
);
CREATE UNIQUE INDEX "customers_organization_id_number_unique" on "customers"(
  "organization_id",
  "number"
);
CREATE TABLE IF NOT EXISTS "procedure_parameter_definitions"(
  "id" integer primary key autoincrement not null,
  "procedure_template_version_id" integer not null,
  "code" varchar not null,
  "label" varchar not null,
  "type" varchar not null default 'text',
  "constraints" text,
  "position" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("procedure_template_version_id") references "procedure_template_versions"("id") on delete cascade
);
CREATE UNIQUE INDEX "proc_param_def_code_unique" on "procedure_parameter_definitions"(
  "procedure_template_version_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "supplier_catalog_sources"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "supplier_id" integer not null,
  "name" varchar not null,
  "format" varchar not null default 'csv',
  "source_type" varchar not null default 'upload',
  "encoding" varchar not null default 'UTF-8',
  "delimiter" varchar not null default ';',
  "has_header" tinyint(1) not null default '1',
  "decimal_separator" varchar not null default ',',
  "active" tinyint(1) not null default '1',
  "last_imported_at" datetime,
  "last_file_hash" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "remote_url" varchar,
  "remote_host" varchar,
  "remote_port" integer,
  "remote_path" varchar,
  "remote_username" varchar,
  "remote_password" text,
  "mapping" text,
  "fetch_interval_minutes" integer,
  "next_fetch_at" datetime,
  "punchout_url" varchar,
  "punchout_username" varchar,
  "punchout_password" text,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE INDEX "scs_org_sup_idx" on "supplier_catalog_sources"(
  "organization_id",
  "supplier_id"
);
CREATE TABLE IF NOT EXISTS "supplier_catalog_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "supplier_catalog_source_id" integer not null,
  "supplier_id" integer not null,
  "external_no" varchar not null,
  "manufacturer_no" varchar,
  "manufacturer" varchar,
  "brand" varchar,
  "gtin" varchar,
  "category" varchar,
  "name" varchar not null,
  "description" text,
  "product_url" varchar,
  "purchase_price" numeric,
  "currency" varchar not null default 'EUR',
  "pack_size" numeric not null default '1',
  "base_qty" numeric not null default '1',
  "availability" varchar,
  "lead_time_days" integer,
  "status" varchar not null default 'new',
  "raw_hash" varchar not null,
  "article_id" integer,
  "article_variant_id" integer,
  "last_seen_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "classification_system" varchar,
  "classification_code" varchar,
  "image_url" varchar,
  "datasheet_url" varchar,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_catalog_source_id") references "supplier_catalog_sources"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete set null,
  foreign key("article_variant_id") references "article_variants"("id") on delete set null
);
CREATE UNIQUE INDEX "sci_src_extno_unique" on "supplier_catalog_items"(
  "supplier_catalog_source_id",
  "external_no"
);
CREATE INDEX "sci_org_status_idx" on "supplier_catalog_items"(
  "organization_id",
  "status"
);
CREATE INDEX "sci_gtin_idx" on "supplier_catalog_items"("gtin");
CREATE TABLE IF NOT EXISTS "supplier_catalog_item_prices"(
  "id" integer primary key autoincrement not null,
  "supplier_catalog_item_id" integer not null,
  "purchase_price" numeric not null,
  "currency" varchar not null default 'EUR',
  "captured_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("supplier_catalog_item_id") references "supplier_catalog_items"("id") on delete cascade
);
CREATE INDEX "scip_item_captured_idx" on "supplier_catalog_item_prices"(
  "supplier_catalog_item_id",
  "captured_at"
);
CREATE TABLE IF NOT EXISTS "pricing_margin_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "supplier_id" integer,
  "category" varchar,
  "markup_percent" numeric,
  "target_margin" numeric,
  "min_margin" numeric,
  "min_sale_price" numeric,
  "rounding" varchar not null default 'none',
  "priority" integer not null default '0',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_id") references "suppliers"("id") on delete cascade
);
CREATE INDEX "pmr_org_sup_idx" on "pricing_margin_rules"(
  "organization_id",
  "supplier_id"
);
CREATE TABLE IF NOT EXISTS "supplier_catalog_imports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "supplier_catalog_source_id" integer not null,
  "trigger" varchar not null default 'manual',
  "status" varchar not null default 'success',
  "rows" integer not null default '0',
  "created" integer not null default '0',
  "updated" integer not null default '0',
  "unchanged" integer not null default '0',
  "price_changed" integer not null default '0',
  "discontinued" integer not null default '0',
  "error" text,
  "file_hash" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_catalog_source_id") references "supplier_catalog_sources"("id") on delete cascade
);
CREATE INDEX "scimp_src_created_idx" on "supplier_catalog_imports"(
  "supplier_catalog_source_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "supplier_catalog_item_price_tiers"(
  "id" integer primary key autoincrement not null,
  "supplier_catalog_item_id" integer not null,
  "min_qty" numeric not null,
  "unit_price" numeric not null,
  "currency" varchar not null default 'EUR',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("supplier_catalog_item_id") references "supplier_catalog_items"("id") on delete cascade
);
CREATE UNIQUE INDEX "scipt_item_minqty_unique" on "supplier_catalog_item_price_tiers"(
  "supplier_catalog_item_id",
  "min_qty"
);
CREATE TABLE IF NOT EXISTS "bill_of_quantities"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "project_id" integer,
  "diary_entry_id" integer,
  "name" varchar not null,
  "external_id" varchar,
  "gaeb_version" varchar,
  "phase" varchar,
  "currency" varchar not null default 'EUR',
  "status" varchar not null default 'imported',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "boq_org_prj_idx" on "bill_of_quantities"(
  "organization_id",
  "project_id"
);
CREATE TABLE IF NOT EXISTS "gaeb_imports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bill_of_quantity_id" integer,
  "filename" varchar not null,
  "file_hash" varchar not null,
  "gaeb_version" varchar,
  "phase" varchar,
  "status" varchar not null default 'pending',
  "section_count" integer not null default '0',
  "item_count" integer not null default '0',
  "preflight" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bill_of_quantity_id") references "bill_of_quantities"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "gimp_org_status_idx" on "gaeb_imports"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "boq_sections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bill_of_quantity_id" integer not null,
  "parent_id" integer,
  "reference_no" varchar not null,
  "label" varchar,
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bill_of_quantity_id") references "bill_of_quantities"("id") on delete cascade,
  foreign key("parent_id") references "boq_sections"("id") on delete cascade
);
CREATE INDEX "boqs_boq_parent_idx" on "boq_sections"(
  "bill_of_quantity_id",
  "parent_id"
);
CREATE TABLE IF NOT EXISTS "boq_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bill_of_quantity_id" integer not null,
  "boq_section_id" integer,
  "reference_no" varchar not null,
  "item_no" varchar,
  "type" varchar not null default 'standard',
  "status" varchar not null default 'imported',
  "short_text" varchar,
  "long_text" text,
  "quantity" numeric,
  "unit" varchar,
  "unit_price" numeric,
  "total_price" numeric,
  "currency" varchar not null default 'EUR',
  "is_addendum" tinyint(1) not null default '0',
  "external_id" varchar,
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bill_of_quantity_id") references "bill_of_quantities"("id") on delete cascade,
  foreign key("boq_section_id") references "boq_sections"("id") on delete set null
);
CREATE UNIQUE INDEX "boqi_boq_refno_unique" on "boq_items"(
  "bill_of_quantity_id",
  "reference_no"
);
CREATE INDEX "boqi_org_status_idx" on "boq_items"("organization_id", "status");
CREATE TABLE IF NOT EXISTS "boq_item_price_snapshots"(
  "id" integer primary key autoincrement not null,
  "boq_item_id" integer not null,
  "gaeb_import_id" integer,
  "phase" varchar,
  "unit_price" numeric,
  "total_price" numeric,
  "currency" varchar not null default 'EUR',
  "captured_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("boq_item_id") references "boq_items"("id") on delete cascade,
  foreign key("gaeb_import_id") references "gaeb_imports"("id") on delete set null
);
CREATE INDEX "boqps_item_captured_idx" on "boq_item_price_snapshots"(
  "boq_item_id",
  "captured_at"
);
CREATE TABLE IF NOT EXISTS "boq_item_progress"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "boq_item_id" integer not null,
  "quantity" numeric not null,
  "source" varchar not null default 'manual',
  "diary_entry_id" integer,
  "material_usage_id" integer,
  "note" varchar,
  "captured_at" datetime not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("boq_item_id") references "boq_items"("id") on delete cascade,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete set null,
  foreign key("material_usage_id") references "material_usages"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "boqp_item_captured_idx" on "boq_item_progress"(
  "boq_item_id",
  "captured_at"
);
CREATE TABLE IF NOT EXISTS "boq_item_mappings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "boq_item_id" integer not null,
  "mappable_type" varchar not null,
  "mappable_id" integer not null,
  "factor" numeric not null default '1',
  "note" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("boq_item_id") references "boq_items"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "boq_item_mappings_mappable_type_mappable_id_index" on "boq_item_mappings"(
  "mappable_type",
  "mappable_id"
);
CREATE UNIQUE INDEX "boqm_item_target_unique" on "boq_item_mappings"(
  "boq_item_id",
  "mappable_type",
  "mappable_id"
);
CREATE TABLE IF NOT EXISTS "boq_exports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "bill_of_quantity_id" integer not null,
  "phase" varchar not null,
  "gaeb_version" varchar not null default '3.3',
  "file_hash" varchar not null,
  "item_count" integer not null default '0',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("bill_of_quantity_id") references "bill_of_quantities"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "boqe_boq_phase_idx" on "boq_exports"(
  "bill_of_quantity_id",
  "phase"
);
CREATE TABLE IF NOT EXISTS "month_closures"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "period_year" integer not null,
  "period_month" integer not null,
  "status" varchar not null default 'draft',
  "submitted_at" datetime,
  "submitted_by_user_id" integer,
  "decided_at" datetime,
  "decided_by_user_id" integer,
  "decision_note" text,
  "locked_at" datetime,
  "locked_by_user_id" integer,
  "totals" text,
  "days_total" integer not null default '0',
  "days_with_attendance" integer not null default '0',
  "days_closed" integer not null default '0',
  "days_open" integer not null default '0',
  "warnings_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("submitted_by_user_id") references "users"("id") on delete set null,
  foreign key("decided_by_user_id") references "users"("id") on delete set null,
  foreign key("locked_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "month_closures_period_unique" on "month_closures"(
  "organization_id",
  "user_id",
  "period_year",
  "period_month"
);
CREATE INDEX "month_closures_status_idx" on "month_closures"(
  "organization_id",
  "status",
  "period_year",
  "period_month"
);
CREATE TABLE IF NOT EXISTS "month_closure_events"(
  "id" integer primary key autoincrement not null,
  "month_closure_id" integer not null,
  "event" varchar not null,
  "actor_user_id" integer not null,
  "note" text,
  "payload" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("month_closure_id") references "month_closures"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete cascade
);
CREATE INDEX "month_closure_events_chrono_idx" on "month_closure_events"(
  "month_closure_id",
  "created_at"
);
CREATE INDEX "month_closure_events_event_index" on "month_closure_events"(
  "event"
);
CREATE TABLE IF NOT EXISTS "time_exports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "profile" varchar not null,
  "period_year" integer not null,
  "period_month" integer not null,
  "scope" varchar not null,
  "scope_user_id" integer,
  "scope_team_id" integer,
  "status" varchar not null default 'preparing',
  "rows_count" integer not null default '0',
  "totals" text,
  "payload_hash" varchar,
  "file_path" varchar,
  "file_format" varchar,
  "created_by_user_id" integer,
  "delivered_at" datetime,
  "delivered_by_user_id" integer,
  "delivery_note" text,
  "superseded_by_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("scope_user_id") references "users"("id") on delete set null,
  foreign key("created_by_user_id") references "users"("id") on delete set null,
  foreign key("delivered_by_user_id") references "users"("id") on delete set null,
  foreign key("superseded_by_id") references "time_exports"("id") on delete set null
);
CREATE INDEX "time_exports_period_idx" on "time_exports"(
  "organization_id",
  "period_year",
  "period_month"
);
CREATE INDEX "time_exports_status_idx" on "time_exports"(
  "organization_id",
  "status"
);
CREATE INDEX "time_exports_hash_idx" on "time_exports"("payload_hash");
CREATE TABLE IF NOT EXISTS "time_export_events"(
  "id" integer primary key autoincrement not null,
  "time_export_id" integer not null,
  "event" varchar not null,
  "actor_user_id" integer,
  "note" text,
  "payload" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("time_export_id") references "time_exports"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "tee_export_event_idx" on "time_export_events"(
  "time_export_id",
  "event"
);
CREATE TABLE IF NOT EXISTS "time_export_lines"(
  "id" integer primary key autoincrement not null,
  "time_export_id" integer not null,
  "user_id" integer not null,
  "wage_type" varchar not null,
  "cost_center" varchar,
  "quantity" numeric not null default('0'),
  "unit" varchar not null default('h'),
  "period_start" date not null,
  "period_end" date not null,
  "note" text,
  "source_refs" text,
  "created_at" datetime,
  "updated_at" datetime,
  "surcharge_rule_id" integer,
  "wage_type_code" varchar,
  "percentage" numeric,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("time_export_id") references time_exports("id") on delete cascade on update no action,
  foreign key("surcharge_rule_id") references "surcharge_rules"("id") on delete set null
);
CREATE INDEX "tel_export_user_idx" on "time_export_lines"(
  "time_export_id",
  "user_id"
);
CREATE INDEX "tel_export_wage_idx" on "time_export_lines"(
  "time_export_id",
  "wage_type"
);
CREATE TABLE IF NOT EXISTS "backup_heartbeats"(
  "id" integer primary key autoincrement not null,
  "occurred_at" datetime not null,
  "size_bytes" integer,
  "manifest_hash" varchar,
  "source" varchar,
  "ip" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "backup_heartbeats_occurred_at_index" on "backup_heartbeats"(
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "pricing_change_alerts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "supplier_catalog_item_id" integer not null,
  "article_id" integer not null,
  "supplier_id" integer,
  "old_purchase_price" numeric,
  "new_purchase_price" numeric,
  "sale_price" numeric,
  "new_margin" numeric,
  "min_margin" numeric,
  "status" varchar not null default('open'),
  "acknowledged_by" integer,
  "acknowledged_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "type" varchar not null default 'margin',
  "impacts" text,
  foreign key("acknowledged_by") references users("id") on delete set null on update no action,
  foreign key("supplier_id") references suppliers("id") on delete set null on update no action,
  foreign key("article_id") references articles("id") on delete cascade on update no action,
  foreign key("supplier_catalog_item_id") references supplier_catalog_items("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action
);
CREATE INDEX "pca_org_status_idx" on "pricing_change_alerts"(
  "organization_id",
  "status"
);
CREATE INDEX "users_cti_ext_hash_idx" on "users"("cti_extension_hash");
CREATE TABLE IF NOT EXISTS "import_runs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "entity" varchar not null,
  "state" varchar not null default 'preflight',
  "input_filename" varchar not null,
  "input_hash" varchar not null,
  "storage_path" varchar not null,
  "delimiter" varchar not null default ';',
  "encoding" varchar not null default 'UTF-8',
  "rows_total" integer not null default '0',
  "rows_created" integer not null default '0',
  "rows_updated" integer not null default '0',
  "rows_skipped" integer not null default '0',
  "rows_failed" integer not null default '0',
  "preview" text,
  "started_at" datetime,
  "finished_at" datetime,
  "created_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "match_policy" varchar not null default 'auto_create',
  "unresolved_values" text,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "import_runs_org_entity_state_idx" on "import_runs"(
  "organization_id",
  "entity",
  "state"
);
CREATE INDEX "import_runs_hash_idx" on "import_runs"("input_hash");
CREATE TABLE IF NOT EXISTS "import_run_errors"(
  "id" integer primary key autoincrement not null,
  "import_run_id" integer not null,
  "row_number" integer not null,
  "field" varchar,
  "code" varchar not null,
  "message" text not null,
  "row_data" text,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("import_run_id") references "import_runs"("id") on delete cascade
);
CREATE INDEX "import_run_errors_row_idx" on "import_run_errors"(
  "import_run_id",
  "row_number"
);
CREATE INDEX "import_run_errors_code_idx" on "import_run_errors"(
  "import_run_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "pending_external_conflicts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plugin_id" varchar not null,
  "conflict_type" varchar not null,
  "referenceable_type" varchar not null,
  "referenceable_id" integer not null,
  "external_id" varchar,
  "local_snapshot" text not null,
  "remote_snapshot" text not null,
  "diff_fields" text,
  "status" varchar not null default 'open',
  "resolved_by" integer,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "pec_referenceable_idx" on "pending_external_conflicts"(
  "referenceable_type",
  "referenceable_id"
);
CREATE INDEX "pec_org_plugin_status_idx" on "pending_external_conflicts"(
  "organization_id",
  "plugin_id",
  "status"
);
CREATE INDEX "pending_external_conflicts_plugin_id_external_id_index" on "pending_external_conflicts"(
  "plugin_id",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "plugin_settings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "plugin_id" varchar not null,
  "enabled" tinyint(1) not null default '0',
  "settings" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "plugin_settings_organization_id_plugin_id_unique" on "plugin_settings"(
  "organization_id",
  "plugin_id"
);
CREATE TABLE IF NOT EXISTS "license_flag_overrides"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "flag" varchar not null,
  "reason" text,
  "disabled_at" datetime not null,
  "disabled_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("disabled_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "license_flag_overrides_unique" on "license_flag_overrides"(
  "organization_id",
  "flag"
);
CREATE INDEX "license_flag_overrides_flag_index" on "license_flag_overrides"(
  "flag"
);
CREATE TABLE IF NOT EXISTS "sites"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "customer_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "address_street" varchar,
  "address_zip" varchar,
  "address_city" varchar,
  "country" varchar,
  "geo_lat" numeric,
  "geo_lng" numeric,
  "is_active" tinyint(1) not null default '1',
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "sites_idx_org_customer" on "sites"(
  "organization_id",
  "customer_id",
  "is_active"
);
CREATE UNIQUE INDEX "sites_uniq_code_per_customer" on "sites"(
  "customer_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "buildings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "site_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "gross_area_m2" numeric,
  "year_built" integer,
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("site_id") references "sites"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "buildings_idx_org_site" on "buildings"(
  "organization_id",
  "site_id"
);
CREATE UNIQUE INDEX "buildings_uniq_code_per_site" on "buildings"(
  "site_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "floors"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "building_id" integer not null,
  "level" integer not null,
  "label" varchar not null,
  "gross_area_m2" numeric,
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("building_id") references "buildings"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "floors_idx_org_building" on "floors"(
  "organization_id",
  "building_id"
);
CREATE UNIQUE INDEX "floors_uniq_level_per_building" on "floors"(
  "building_id",
  "level"
);
CREATE TABLE IF NOT EXISTS "cleaning_profiles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "code" varchar not null,
  "label" varchar not null,
  "interval_days" integer,
  "requirements" text,
  "notes" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "cleaning_profiles_idx_org_active" on "cleaning_profiles"(
  "organization_id",
  "is_active"
);
CREATE UNIQUE INDEX "cleaning_profiles_uniq_code_per_org" on "cleaning_profiles"(
  "organization_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "rooms"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "name" varchar not null,
  "code" varchar,
  "building" varchar,
  "floor" varchar,
  "capacity" integer,
  "equipment" text,
  "color" varchar,
  "is_active" tinyint(1) not null default('1'),
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "floor_id" integer,
  "customer_id" integer,
  "usage_type" varchar not null default('office'),
  "net_area_m2" numeric,
  "cleaning_profile_id" integer,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("floor_id") references floors("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("cleaning_profile_id") references "cleaning_profiles"("id") on delete set null
);
CREATE INDEX "rooms_idx_floor" on "rooms"("floor_id");
CREATE INDEX "rooms_idx_org_customer" on "rooms"(
  "organization_id",
  "customer_id",
  "is_active"
);
CREATE UNIQUE INDEX "rooms_organization_id_code_unique" on "rooms"(
  "organization_id",
  "code"
);
CREATE INDEX "rooms_organization_id_is_active_index" on "rooms"(
  "organization_id",
  "is_active"
);
CREATE INDEX "rooms_idx_org_cleaning_profile" on "rooms"(
  "organization_id",
  "cleaning_profile_id"
);
CREATE TABLE IF NOT EXISTS "software"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "vendor" varchar,
  "kind" varchar not null,
  "license_type" varchar not null,
  "default_version" varchar,
  "notes" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "software_idx_org_kind" on "software"("organization_id", "kind");
CREATE INDEX "software_idx_org_active" on "software"(
  "organization_id",
  "is_active"
);
CREATE UNIQUE INDEX "software_uniq_name_vendor" on "software"(
  "organization_id",
  "name",
  "vendor"
);
CREATE TABLE IF NOT EXISTS "software_installations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "software_id" integer not null,
  "version" varchar,
  "license_key" text,
  "seats" integer,
  "installed_on" date,
  "expires_on" date,
  "is_operating_system" tinyint(1) not null default '0',
  "notes" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("software_id") references "software"("id") on delete restrict,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "sw_installs_idx_asset" on "software_installations"("asset_id");
CREATE INDEX "sw_installs_idx_software" on "software_installations"(
  "software_id"
);
CREATE INDEX "sw_installs_idx_org_expiry" on "software_installations"(
  "organization_id",
  "expires_on"
);
CREATE UNIQUE INDEX sw_installs_uniq_os_per_asset ON software_installations(
  asset_id
) WHERE is_operating_system = 1;
CREATE TABLE IF NOT EXISTS "export_runs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "entity" varchar not null,
  "format" varchar not null default 'csv',
  "state" varchar not null default 'preparing',
  "filters" text,
  "output_filename" varchar not null,
  "storage_path" varchar not null default '',
  "rows_total" integer not null default '0',
  "error_message" text,
  "created_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "export_runs_org_entity_state_idx" on "export_runs"(
  "organization_id",
  "entity",
  "state"
);
CREATE TABLE IF NOT EXISTS "remote_pending_sessions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "provider" varchar not null,
  "remote_id" varchar not null,
  "session_id" varchar not null,
  "started_at" datetime not null,
  "ended_at" datetime not null,
  "note" varchar,
  "status" varchar not null default('open'),
  "time_entry_id" integer,
  "resolved_by" integer,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "alias" varchar,
  "asset_id" integer,
  foreign key("resolved_by") references users("id") on delete set null on update no action,
  foreign key("time_entry_id") references time_entries("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("asset_id") references "assets"("id") on delete set null
);
CREATE INDEX "rps_group_idx" on "remote_pending_sessions"(
  "organization_id",
  "status",
  "provider",
  "remote_id"
);
CREATE UNIQUE INDEX "rps_unique_session" on "remote_pending_sessions"(
  "organization_id",
  "provider",
  "session_id"
);
CREATE INDEX "rps_asset_idx" on "remote_pending_sessions"(
  "organization_id",
  "status",
  "asset_id"
);
CREATE TABLE IF NOT EXISTS "lexoffice_vouchers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "external_id" varchar not null,
  "contact_external_id" varchar,
  "customer_id" integer,
  "supplier_id" integer,
  "voucher_type" varchar,
  "voucher_status" varchar,
  "voucher_number" varchar,
  "voucher_date" date,
  "due_date" date,
  "total_amount" numeric,
  "open_amount" numeric,
  "currency" varchar not null default 'EUR',
  "archived" tinyint(1) not null default '0',
  "payload" text,
  "synced_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("supplier_id") references "suppliers"("id") on delete set null
);
CREATE UNIQUE INDEX "lexoffice_vouchers_organization_id_external_id_unique" on "lexoffice_vouchers"(
  "organization_id",
  "external_id"
);
CREATE INDEX "lexoffice_vouchers_organization_id_customer_id_index" on "lexoffice_vouchers"(
  "organization_id",
  "customer_id"
);
CREATE INDEX "lexoffice_vouchers_organization_id_supplier_id_index" on "lexoffice_vouchers"(
  "organization_id",
  "supplier_id"
);
CREATE INDEX "lexoffice_vouchers_organization_id_voucher_type_index" on "lexoffice_vouchers"(
  "organization_id",
  "voucher_type"
);
CREATE INDEX "lexoffice_vouchers_contact_external_id_index" on "lexoffice_vouchers"(
  "contact_external_id"
);
CREATE TABLE IF NOT EXISTS "contact_addresses"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "addressable_type" varchar not null,
  "addressable_id" integer not null,
  "kind" varchar not null default 'billing',
  "supplement" varchar,
  "street" varchar,
  "zip" varchar,
  "city" varchar,
  "country_code" varchar,
  "is_primary" tinyint(1) not null default '0',
  "external_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "contact_addresses_addressable_type_addressable_id_index" on "contact_addresses"(
  "addressable_type",
  "addressable_id"
);
CREATE INDEX "contact_addresses_organization_id_index" on "contact_addresses"(
  "organization_id"
);
CREATE INDEX "contact_addresses_kind_idx" on "contact_addresses"(
  "addressable_type",
  "addressable_id",
  "kind"
);
CREATE TABLE IF NOT EXISTS "contact_bank_accounts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "accountable_type" varchar not null,
  "accountable_id" integer not null,
  "account_holder" varchar,
  "iban" varchar,
  "bic" varchar,
  "bank_name" varchar,
  "is_primary" tinyint(1) not null default '0',
  "external_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "contact_bank_accounts_accountable_type_accountable_id_index" on "contact_bank_accounts"(
  "accountable_type",
  "accountable_id"
);
CREATE INDEX "contact_bank_accounts_organization_id_index" on "contact_bank_accounts"(
  "organization_id"
);
CREATE INDEX "contact_bank_accounts_owner_idx" on "contact_bank_accounts"(
  "accountable_type",
  "accountable_id"
);
CREATE TABLE IF NOT EXISTS "foreign_customers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "customer_id" integer not null,
  "name" varchar not null,
  "number" varchar,
  "company" varchar,
  "contact_name" varchar,
  "email" varchar,
  "phone" varchar,
  "mobile" varchar,
  "homepage" varchar,
  "address" text,
  "country" varchar,
  "color" varchar,
  "comment" text,
  "archived_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "foreign_customers_organization_id_index" on "foreign_customers"(
  "organization_id"
);
CREATE INDEX "foreign_customers_customer_id_index" on "foreign_customers"(
  "customer_id"
);
CREATE INDEX "foreign_customers_archived_at_index" on "foreign_customers"(
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "projects"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "color" varchar,
  "status" varchar not null default('active'),
  "starts_on" date,
  "ends_on" date,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "customer_id" integer,
  "number" varchar,
  "hourly_rate" numeric,
  "internal_rate" numeric,
  "time_budget" integer not null default('0'),
  "budget" numeric not null default('0'),
  "budget_type" varchar,
  "billable" tinyint(1),
  "invoice_text" text,
  "global_activities" tinyint(1) not null default('1'),
  "archived_at" datetime,
  "is_default" tinyint(1) not null default('0'),
  "parent_id" integer,
  "is_maintenance" tinyint(1) not null default('0'),
  "default_location_mode" varchar,
  "weather_auto_fetch" tinyint(1),
  "foreign_customer_id" integer,
  "billing_increment_minutes" integer,
  "billing_grouping_gap_minutes" integer,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("parent_id") references projects("id") on delete set null on update no action,
  foreign key("foreign_customer_id") references "foreign_customers"("id") on delete set null
);
CREATE INDEX "idx_projects_org" on "projects"("organization_id");
CREATE INDEX "projects_archived_at_index" on "projects"("archived_at");
CREATE INDEX "projects_customer_id_index" on "projects"("customer_id");
CREATE INDEX "projects_customer_id_is_default_index" on "projects"(
  "customer_id",
  "is_default"
);
CREATE UNIQUE INDEX "projects_customer_slug_unique" on "projects"(
  "customer_id",
  "slug"
);
CREATE INDEX "projects_is_maintenance_idx" on "projects"("is_maintenance");
CREATE INDEX "projects_parent_id_index" on "projects"("parent_id");
CREATE INDEX "projects_status_index" on "projects"("status");
CREATE TABLE IF NOT EXISTS "invoice_item_time_entries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "invoice_item_id" integer not null,
  "time_entry_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("invoice_item_id") references "invoice_items"("id") on delete cascade,
  foreign key("time_entry_id") references "time_entries"("id") on delete cascade
);
CREATE UNIQUE INDEX "iite_item_entry_unique" on "invoice_item_time_entries"(
  "invoice_item_id",
  "time_entry_id"
);
CREATE INDEX "invoice_item_time_entries_time_entry_id_index" on "invoice_item_time_entries"(
  "time_entry_id"
);
CREATE INDEX "material_usages_billed_index" on "material_usages"("billed");
CREATE INDEX "tours_travel_billed_index" on "tours"("travel_billed");
CREATE TABLE IF NOT EXISTS "invoice_items"(
  "id" integer primary key autoincrement not null,
  "invoice_id" integer not null,
  "time_entry_id" integer,
  "description" varchar not null,
  "quantity" numeric not null default('1'),
  "unit" varchar not null default('h'),
  "unit_price" numeric not null default('0'),
  "amount" numeric not null default('0'),
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "expense_id" integer,
  "organization_id" integer,
  "service_date" date,
  "material_usage_id" integer,
  "tour_id" integer,
  "tax_rate" numeric,
  foreign key("material_usage_id") references material_usages("id") on delete set null on update no action,
  foreign key("expense_id") references expenses("id") on delete set null on update no action,
  foreign key("invoice_id") references invoices("id") on delete cascade on update no action,
  foreign key("time_entry_id") references time_entries("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("tour_id") references "tours"("id") on delete set null
);
CREATE INDEX "idx_invoice_items_org" on "invoice_items"("organization_id");
CREATE INDEX "invoice_items_expense_id_index" on "invoice_items"("expense_id");
CREATE INDEX "invoice_items_invoice_id_index" on "invoice_items"("invoice_id");
CREATE TABLE IF NOT EXISTS "plugin_states"(
  "id" integer primary key autoincrement not null,
  "plugin_id" varchar not null,
  "installed_version" varchar,
  "installed_at" datetime,
  "last_health_check_at" datetime,
  "last_health_status" varchar,
  "last_health_message" text,
  "failure_count" integer not null default('0'),
  "disabled_reason" text,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  "last_ok_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "plugin_states_plugin_id_organization_id_unique" on "plugin_states"(
  "plugin_id",
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "plugin_errors"(
  "id" integer primary key autoincrement not null,
  "plugin_id" varchar not null,
  "phase" varchar not null,
  "exception_class" varchar,
  "message" text not null,
  "trace" text,
  "context" text,
  "occurred_at" datetime not null,
  "acknowledged_at" datetime,
  "acknowledged_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "organization_id" integer,
  foreign key("acknowledged_by") references users("id") on delete set null on update no action,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "plugin_errors_phase_occurred_at_index" on "plugin_errors"(
  "phase",
  "occurred_at"
);
CREATE INDEX "plugin_errors_plugin_id_acknowledged_at_index" on "plugin_errors"(
  "plugin_id",
  "acknowledged_at"
);
CREATE INDEX "plugin_errors_plugin_id_organization_id_index" on "plugin_errors"(
  "plugin_id",
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "chat_channels"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar,
  "slug" varchar,
  "description" text,
  "type" varchar not null default 'channel',
  "visibility" varchar not null default 'private',
  "is_archived" tinyint(1) not null default '0',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "chat_channels_organization_id_type_index" on "chat_channels"(
  "organization_id",
  "type"
);
CREATE UNIQUE INDEX "chat_channels_organization_id_slug_unique" on "chat_channels"(
  "organization_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "chat_channel_user"(
  "id" integer primary key autoincrement not null,
  "channel_id" integer not null,
  "user_id" integer not null,
  "role" varchar not null default 'member',
  "last_read_at" datetime,
  "muted_at" datetime,
  "joined_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("channel_id") references "chat_channels"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "chat_channel_user_channel_id_user_id_unique" on "chat_channel_user"(
  "channel_id",
  "user_id"
);
CREATE INDEX "chat_channel_user_user_id_channel_id_index" on "chat_channel_user"(
  "user_id",
  "channel_id"
);
CREATE TABLE IF NOT EXISTS "chat_message_reactions"(
  "id" integer primary key autoincrement not null,
  "message_id" integer not null,
  "user_id" integer not null,
  "emoji" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("message_id") references "chat_messages"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "chat_message_reactions_message_id_user_id_emoji_unique" on "chat_message_reactions"(
  "message_id",
  "user_id",
  "emoji"
);
CREATE INDEX "chat_message_reactions_message_id_index" on "chat_message_reactions"(
  "message_id"
);
CREATE TABLE IF NOT EXISTS "chat_polls"(
  "id" integer primary key autoincrement not null,
  "message_id" integer not null,
  "question" varchar not null,
  "multiple" tinyint(1) not null default '0',
  "closes_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("message_id") references "chat_messages"("id") on delete cascade
);
CREATE INDEX "chat_polls_message_id_index" on "chat_polls"("message_id");
CREATE TABLE IF NOT EXISTS "chat_poll_options"(
  "id" integer primary key autoincrement not null,
  "poll_id" integer not null,
  "label" varchar not null,
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("poll_id") references "chat_polls"("id") on delete cascade
);
CREATE INDEX "chat_poll_options_poll_id_index" on "chat_poll_options"(
  "poll_id"
);
CREATE TABLE IF NOT EXISTS "chat_poll_votes"(
  "id" integer primary key autoincrement not null,
  "poll_option_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("poll_option_id") references "chat_poll_options"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "chat_poll_votes_poll_option_id_user_id_unique" on "chat_poll_votes"(
  "poll_option_id",
  "user_id"
);
CREATE INDEX "chat_poll_votes_poll_option_id_index" on "chat_poll_votes"(
  "poll_option_id"
);
CREATE TABLE IF NOT EXISTS "chat_messages"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "channel_id" integer not null,
  "user_id" integer,
  "parent_id" integer,
  "body" text,
  "type" varchar not null default('text'),
  "pinned_at" datetime,
  "pinned_by" integer,
  "edited_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "quoted_id" integer,
  "forwarded_from_user_id" integer,
  foreign key("pinned_by") references users("id") on delete set null on update no action,
  foreign key("parent_id") references chat_messages("id") on delete cascade on update no action,
  foreign key("user_id") references users("id") on delete set null on update no action,
  foreign key("channel_id") references chat_channels("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("quoted_id") references "chat_messages"("id") on delete set null,
  foreign key("forwarded_from_user_id") references "users"("id") on delete set null
);
CREATE INDEX "chat_messages_channel_id_id_index" on "chat_messages"(
  "channel_id",
  "id"
);
CREATE INDEX "chat_messages_channel_id_parent_id_index" on "chat_messages"(
  "channel_id",
  "parent_id"
);
CREATE INDEX "chat_messages_channel_id_pinned_at_index" on "chat_messages"(
  "channel_id",
  "pinned_at"
);
CREATE TABLE IF NOT EXISTS "chat_message_stars"(
  "message_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  foreign key("message_id") references "chat_messages"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  primary key("message_id", "user_id")
);
CREATE TABLE IF NOT EXISTS "chat_reminders"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "message_id" integer not null,
  "channel_id" integer not null,
  "remind_at" datetime not null,
  "sent_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("message_id") references "chat_messages"("id") on delete cascade,
  foreign key("channel_id") references "chat_channels"("id") on delete cascade
);
CREATE INDEX "chat_reminders_sent_at_remind_at_index" on "chat_reminders"(
  "sent_at",
  "remind_at"
);
CREATE TABLE IF NOT EXISTS "chat_scheduled_messages"(
  "id" integer primary key autoincrement not null,
  "channel_id" integer not null,
  "user_id" integer not null,
  "body" text,
  "scheduled_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("channel_id") references "chat_channels"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "chat_scheduled_messages_scheduled_at_index" on "chat_scheduled_messages"(
  "scheduled_at"
);
CREATE INDEX "audit_logs_hash_index" on "audit_logs"("hash");
CREATE INDEX "organization_audit_logs_hash_index" on "organization_audit_logs"(
  "hash"
);
CREATE TABLE IF NOT EXISTS "audit_chain_heads"(
  "chain" varchar not null,
  "head_hash" varchar,
  "height" integer not null default '0',
  primary key("chain")
);
CREATE TABLE IF NOT EXISTS "whistleblowing_portals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "public_slug" varchar not null,
  "is_enabled" tinyint(1) not null default '0',
  "allow_anonymous" tinyint(1) not null default '1',
  "allow_confidential" tinyint(1) not null default '1',
  "allowed_locales" text,
  "default_locale" varchar,
  "intro_text" text,
  "privacy_text_version" varchar,
  "external_channels" text,
  "retention_months" integer not null default '36',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "whistleblowing_portals_organization_id_unique" on "whistleblowing_portals"(
  "organization_id"
);
CREATE UNIQUE INDEX "whistleblowing_portals_public_slug_unique" on "whistleblowing_portals"(
  "public_slug"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_case_assignments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "user_id" integer not null,
  "role" varchar not null,
  "assigned_by" integer,
  "assigned_at" datetime not null,
  "revoked_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("assigned_by") references "users"("id") on delete set null
);
CREATE INDEX "whistleblowing_case_assignments_case_id_user_id_revoked_at_index" on "whistleblowing_case_assignments"(
  "case_id",
  "user_id",
  "revoked_at"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_messages"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "author_type" varchar not null,
  "author_user_id" integer,
  "visibility" varchar not null,
  "body_ciphertext" text not null,
  "sent_at" datetime not null,
  "read_by_reporter_at" datetime,
  "created_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("author_user_id") references "users"("id") on delete set null
);
CREATE INDEX "whistleblowing_messages_case_id_visibility_index" on "whistleblowing_messages"(
  "case_id",
  "visibility"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_attachments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "message_id" integer,
  "uploaded_by_type" varchar not null,
  "storage_key" varchar not null,
  "original_name_ciphertext" text not null,
  "mime_detected" varchar,
  "size" integer not null default '0',
  "sha256" varchar,
  "scan_status" varchar not null default 'pending',
  "metadata_scrubbed" tinyint(1) not null default '0',
  "created_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("message_id") references "whistleblowing_messages"("id") on delete set null
);
CREATE UNIQUE INDEX "whistleblowing_attachments_storage_key_unique" on "whistleblowing_attachments"(
  "storage_key"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_case_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "case_id" integer,
  "actor_type" varchar not null,
  "actor_user_id" integer,
  "event" varchar not null,
  "metadata" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "whistleblowing_case_events_case_id_event_index" on "whistleblowing_case_events"(
  "case_id",
  "event"
);
CREATE INDEX "whistleblowing_case_events_hash_index" on "whistleblowing_case_events"(
  "hash"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_case_tombstones"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "case_number" varchar not null,
  "public_id" varchar not null,
  "period_from" date,
  "period_to" date,
  "closed_category" varchar,
  "deleted_at" datetime not null,
  "audit_hash" varchar
);
CREATE INDEX "whistleblowing_case_tombstones_case_number_index" on "whistleblowing_case_tombstones"(
  "case_number"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_case_conflicts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "user_id" integer not null,
  "reason_ciphertext" text,
  "declared_at" datetime not null,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "whistleblowing_case_conflicts_case_id_user_id_unique" on "whistleblowing_case_conflicts"(
  "case_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_emergency_grants"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "user_id" integer not null,
  "granted_by" integer not null,
  "reason_ciphertext" text not null,
  "granted_at" datetime not null,
  "expires_at" datetime not null,
  "revoked_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("granted_by") references "users"("id") on delete cascade
);
CREATE INDEX "whistleblowing_emergency_grants_case_id_user_id_expires_at_index" on "whistleblowing_emergency_grants"(
  "case_id",
  "user_id",
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_deadline_reminders"(
  "id" integer primary key autoincrement not null,
  "case_id" integer not null,
  "kind" varchar not null,
  "reminder_date" date not null,
  "created_at" datetime,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade
);
CREATE UNIQUE INDEX "wb_deadline_reminder_unique" on "whistleblowing_deadline_reminders"(
  "case_id",
  "kind",
  "reminder_date"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_case_subjects"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "case_id" integer not null,
  "user_id" integer not null,
  "added_by" integer,
  "note_ciphertext" text,
  "created_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("case_id") references "whistleblowing_cases"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("added_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "whistleblowing_case_subjects_case_id_user_id_unique" on "whistleblowing_case_subjects"(
  "case_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "whistleblowing_cases"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "public_id" varchar not null,
  "case_number" varchar not null,
  "access_code_hash" varchar not null,
  "access_code_lookup" varchar not null,
  "dek_wrapped" text,
  "reporter_mode" varchar not null,
  "category" varchar not null,
  "status" varchar not null,
  "priority" varchar not null default('normal'),
  "subject_ciphertext" text,
  "description_ciphertext" text,
  "contact_ciphertext" text,
  "occurred_from" date,
  "occurred_to" date,
  "acknowledgement_due_at" datetime,
  "feedback_due_at" datetime,
  "acknowledged_at" datetime,
  "feedback_sent_at" datetime,
  "closed_at" datetime,
  "retention_due_at" datetime,
  "legal_hold_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "whistleblowing_cases_access_code_lookup_unique" on "whistleblowing_cases"(
  "access_code_lookup"
);
CREATE UNIQUE INDEX "whistleblowing_cases_organization_id_case_number_unique" on "whistleblowing_cases"(
  "organization_id",
  "case_number"
);
CREATE UNIQUE INDEX "whistleblowing_cases_public_id_unique" on "whistleblowing_cases"(
  "public_id"
);
CREATE INDEX "whistleblowing_cases_status_index" on "whistleblowing_cases"(
  "status"
);
CREATE TABLE IF NOT EXISTS "plan_module_grace"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "module" varchar not null,
  "lost_at" datetime not null,
  "grace_until" datetime not null,
  "purged_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "plan_module_grace_organization_id_module_unique" on "plan_module_grace"(
  "organization_id",
  "module"
);
CREATE INDEX "plan_module_grace_grace_until_index" on "plan_module_grace"(
  "grace_until"
);
CREATE TABLE IF NOT EXISTS "privacy_processing_activities"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "purpose" text,
  "controller_role" varchar not null default 'controller',
  "area" varchar,
  "status" varchar not null default 'draft',
  "current_version_id" integer,
  "review_due_at" date,
  "dsfa_required" tinyint(1) not null default '0',
  "risk_level" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_processing_activities_organization_id_status_index" on "privacy_processing_activities"(
  "organization_id",
  "status"
);
CREATE INDEX "privacy_processing_activities_review_due_at_index" on "privacy_processing_activities"(
  "review_due_at"
);
CREATE TABLE IF NOT EXISTS "privacy_processing_activity_versions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "activity_id" integer not null,
  "version_no" integer not null,
  "payload" text not null,
  "note" varchar,
  "created_by" integer,
  "approved_by" integer,
  "approved_at" datetime,
  "valid_from" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "ppav_activity_version_unique" on "privacy_processing_activity_versions"(
  "activity_id",
  "version_no"
);
CREATE TABLE IF NOT EXISTS "privacy_data_subject_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "request_number" varchar not null,
  "type" varchar not null,
  "status" varchar not null default 'intake',
  "channel" varchar,
  "identity_verified_at" datetime,
  "assigned_user_id" integer,
  "received_at" datetime,
  "deadline_at" datetime,
  "subject_ciphertext" text,
  "content_ciphertext" text,
  "decision_note_ciphertext" text,
  "dek_wrapped" text,
  "decision" varchar,
  "decided_at" datetime,
  "closed_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("assigned_user_id") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "pdsr_org_number_unique" on "privacy_data_subject_requests"(
  "organization_id",
  "request_number"
);
CREATE INDEX "privacy_data_subject_requests_organization_id_status_index" on "privacy_data_subject_requests"(
  "organization_id",
  "status"
);
CREATE INDEX "privacy_data_subject_requests_deadline_at_index" on "privacy_data_subject_requests"(
  "deadline_at"
);
CREATE TABLE IF NOT EXISTS "privacy_request_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "request_id" integer,
  "actor_type" varchar not null,
  "actor_user_id" integer,
  "event" varchar not null,
  "metadata" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "privacy_request_events_request_id_event_index" on "privacy_request_events"(
  "request_id",
  "event"
);
CREATE INDEX "privacy_request_events_hash_index" on "privacy_request_events"(
  "hash"
);
CREATE TABLE IF NOT EXISTS "privacy_processors"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "role" varchar not null default 'processor',
  "contact" varchar,
  "location" varchar,
  "third_country" tinyint(1) not null default '0',
  "notes" text,
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_processors_organization_id_is_active_index" on "privacy_processors"(
  "organization_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "privacy_processing_agreements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "processor_id" integer not null,
  "title" varchar not null,
  "version" varchar not null default '1.0',
  "status" varchar not null default 'draft',
  "valid_from" date,
  "valid_until" date,
  "review_due_at" date,
  "data_categories" text,
  "tom_checked" tinyint(1) not null default '0',
  "document_path" varchar,
  "document_name" varchar,
  "terminated_at" datetime,
  "data_return" varchar,
  "data_return_confirmed_at" datetime,
  "notes" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("processor_id") references "privacy_processors"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_processing_agreements_organization_id_status_index" on "privacy_processing_agreements"(
  "organization_id",
  "status"
);
CREATE INDEX "privacy_processing_agreements_review_due_at_index" on "privacy_processing_agreements"(
  "review_due_at"
);
CREATE TABLE IF NOT EXISTS "privacy_subprocessors"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "agreement_id" integer not null,
  "name" varchar not null,
  "purpose" varchar,
  "location" varchar,
  "third_country" tinyint(1) not null default '0',
  "approved" tinyint(1) not null default '0',
  "added_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "safeguards" varchar,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("agreement_id") references "privacy_processing_agreements"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "privacy_agreement_activity"(
  "id" integer primary key autoincrement not null,
  "agreement_id" integer not null,
  "activity_id" integer not null,
  foreign key("agreement_id") references "privacy_processing_agreements"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade
);
CREATE UNIQUE INDEX "paa_agreement_activity_unique" on "privacy_agreement_activity"(
  "agreement_id",
  "activity_id"
);
CREATE TABLE IF NOT EXISTS "privacy_incident_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "incident_id" integer,
  "actor_type" varchar not null,
  "actor_user_id" integer,
  "event" varchar not null,
  "metadata" text,
  "prev_hash" varchar,
  "hash" varchar,
  "created_at" datetime
);
CREATE INDEX "privacy_incident_events_incident_id_event_index" on "privacy_incident_events"(
  "incident_id",
  "event"
);
CREATE INDEX "privacy_incident_events_hash_index" on "privacy_incident_events"(
  "hash"
);
CREATE TABLE IF NOT EXISTS "privacy_measures"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "incident_id" integer,
  "activity_id" integer,
  "title" varchar not null,
  "description" text,
  "due_at" date,
  "status" varchar not null default 'open',
  "assigned_user_id" integer,
  "completed_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("incident_id") references "privacy_incidents"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade,
  foreign key("assigned_user_id") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_measures_organization_id_status_index" on "privacy_measures"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "privacy_dpias"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "activity_id" integer not null,
  "necessity" text,
  "risks" text,
  "mitigations" text,
  "residual_risk" varchar,
  "outcome" varchar not null default 'open',
  "assessed_by" integer,
  "assessed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade,
  foreign key("assessed_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_dpias_organization_id_outcome_index" on "privacy_dpias"(
  "organization_id",
  "outcome"
);
CREATE TABLE IF NOT EXISTS "privacy_technical_measures"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "category" varchar not null,
  "responsible_user_id" integer,
  "implementation_status" varchar not null default 'planned',
  "protection_level" varchar,
  "current_version_id" integer,
  "valid_from" date,
  "valid_until" date,
  "next_review_at" date,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("responsible_user_id") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "ptm_org_impl_index" on "privacy_technical_measures"(
  "organization_id",
  "implementation_status"
);
CREATE INDEX "privacy_technical_measures_next_review_at_index" on "privacy_technical_measures"(
  "next_review_at"
);
CREATE TABLE IF NOT EXISTS "privacy_technical_measure_versions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "measure_id" integer not null,
  "version_no" integer not null,
  "payload" text not null,
  "note" varchar,
  "created_by" integer,
  "approved_by" integer,
  "approved_at" datetime,
  "valid_from" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("measure_id") references "privacy_technical_measures"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "ptmv_measure_version_unique" on "privacy_technical_measure_versions"(
  "measure_id",
  "version_no"
);
CREATE TABLE IF NOT EXISTS "privacy_measure_assignments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "measure_id" integer not null,
  "activity_id" integer,
  "agreement_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("measure_id") references "privacy_technical_measures"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade,
  foreign key("agreement_id") references "privacy_processing_agreements"("id") on delete cascade
);
CREATE INDEX "privacy_measure_assignments_organization_id_index" on "privacy_measure_assignments"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "privacy_measure_reviews"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "measure_id" integer not null,
  "reviewed_at" datetime,
  "result" varchar not null,
  "deviation" text,
  "follow_up" text,
  "due_at" date,
  "reviewer_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("measure_id") references "privacy_technical_measures"("id") on delete cascade,
  foreign key("reviewer_id") references "users"("id") on delete set null
);
CREATE INDEX "privacy_measure_reviews_organization_id_index" on "privacy_measure_reviews"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "privacy_joint_controller_agreements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "partner_id" integer not null,
  "title" varchar not null,
  "version" varchar not null default '1.0',
  "status" varchar not null default 'draft',
  "valid_from" date,
  "valid_until" date,
  "review_due_at" date,
  "responsibilities" text,
  "contact_point" varchar,
  "essence_provided" tinyint(1) not null default '0',
  "document_path" varchar,
  "document_name" varchar,
  "notes" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("partner_id") references "privacy_processors"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "privacy_joint_controller_agreements_organization_id_status_index" on "privacy_joint_controller_agreements"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "privacy_gvv_activity"(
  "id" integer primary key autoincrement not null,
  "gvv_id" integer not null,
  "activity_id" integer not null,
  foreign key("gvv_id") references "privacy_joint_controller_agreements"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade
);
CREATE UNIQUE INDEX "pgvva_gvv_activity_unique" on "privacy_gvv_activity"(
  "gvv_id",
  "activity_id"
);
CREATE TABLE IF NOT EXISTS "privacy_compliance_findings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "requirement_key" varchar not null,
  "label" varchar not null,
  "category" varchar,
  "status" varchar not null default 'missing',
  "trigger" varchar,
  "activity_id" integer,
  "agreement_id" integer,
  "processor_id" integer,
  "responsible_user_id" integer,
  "due_at" date,
  "justification" text,
  "auto_detected" tinyint(1) not null default '1',
  "detected_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("activity_id") references "privacy_processing_activities"("id") on delete cascade,
  foreign key("agreement_id") references "privacy_processing_agreements"("id") on delete cascade,
  foreign key("processor_id") references "privacy_processors"("id") on delete cascade,
  foreign key("responsible_user_id") references "users"("id") on delete set null
);
CREATE INDEX "privacy_compliance_findings_organization_id_status_index" on "privacy_compliance_findings"(
  "organization_id",
  "status"
);
CREATE INDEX "pcf_org_reqkey_index" on "privacy_compliance_findings"(
  "organization_id",
  "requirement_key"
);
CREATE TABLE IF NOT EXISTS "privacy_attachments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "attachable_type" varchar not null,
  "attachable_id" integer not null,
  "filename" varchar not null,
  "path" varchar not null,
  "size" integer not null default '0',
  "mime" varchar,
  "uploaded_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "valid_until" date,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("uploaded_by") references "users"("id") on delete set null
);
CREATE INDEX "pa_attachable_index" on "privacy_attachments"(
  "attachable_type",
  "attachable_id"
);
CREATE UNIQUE INDEX "orgs_license_uid_unique" on "organizations"(
  "license_uid"
);
CREATE TABLE IF NOT EXISTS "privacy_incidents"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "incident_number" varchar not null,
  "type" varchar not null,
  "status" varchar not null default('detected'),
  "occurred_at" datetime,
  "discovered_at" datetime,
  "reported_internally_at" datetime,
  "authority_deadline_at" datetime,
  "risk_level" varchar,
  "affected_count" integer,
  "notify_authority" tinyint(1) not null default('0'),
  "notify_subjects" tinyint(1) not null default('0'),
  "authority_notified_at" datetime,
  "subjects_notified_at" datetime,
  "assigned_user_id" integer,
  "summary_ciphertext" text,
  "affected_ciphertext" text,
  "measures_ciphertext" text,
  "lessons_ciphertext" text,
  "dek_wrapped" text,
  "closed_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "controller_role" varchar not null default 'controller',
  "controller_name" varchar,
  "controller_customer_id" integer,
  "controller_notified_at" datetime,
  "own_infrastructure_affected" tinyint(1) not null default '0',
  "authority_key" varchar,
  "authority_name" varchar,
  "authority_portal_url" text,
  "authority_report_type" varchar,
  "authority_report_reference" varchar,
  "authority_case_number" varchar,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("assigned_user_id") references users("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("controller_customer_id") references "customers"("id") on delete set null
);
CREATE UNIQUE INDEX "pinc_org_number_unique" on "privacy_incidents"(
  "organization_id",
  "incident_number"
);
CREATE INDEX "privacy_incidents_authority_deadline_at_index" on "privacy_incidents"(
  "authority_deadline_at"
);
CREATE INDEX "privacy_incidents_organization_id_status_index" on "privacy_incidents"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "diary_entries"(
  "id" integer primary key autoincrement not null,
  "legacy_id" integer,
  "user_id" integer not null,
  "content" text not null,
  "response" text,
  "status" integer not null default('2'),
  "start_at" datetime,
  "end_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "on_call_shift_id" integer,
  "emergency_assignment_id" integer,
  "is_archived" tinyint(1) not null default('0'),
  "archived_at" datetime,
  "project_id" integer,
  "organization_id" integer,
  "entry_type_id" integer,
  "customer_id" integer,
  "assigned_user_id" integer,
  "title" varchar,
  "address_line" varchar,
  "address_zip" varchar,
  "address_city" varchar,
  "address_country" varchar,
  "address_lat" numeric,
  "address_lng" numeric,
  "scheduled_for" date,
  "time_window_start" time,
  "time_window_end" time,
  "service_minutes" integer,
  "priority" varchar,
  "tour_id" integer,
  "tour_position" integer,
  "notes" text,
  "mode" varchar not null default('fixed'),
  "due_date" date,
  "window_start_date" date,
  "window_end_date" date,
  "location_mode" varchar not null default('onsite'),
  "recurrence_rule_id" integer,
  "asset_id" integer,
  "planned_minutes" integer,
  "planned_at" datetime,
  "planned_by_user_id" integer,
  "dispatch_status" varchar,
  "dispatch_confirmed_at" datetime,
  "dispatch_override_reason" text,
  "dispatch_override_by_user_id" integer,
  "status_legacy" integer,
  "planned_start_at" datetime,
  "planned_end_at" datetime,
  "planned_duration_min" integer,
  "accepted_at" datetime,
  "accepted_by_user_id" integer,
  "started_at" datetime,
  "paused_at" datetime,
  "pause_reason" varchar,
  "pause_note" text,
  "resumed_at" datetime,
  "wait_seconds_total" integer not null default '0',
  "completed_at" datetime,
  "completed_by_user_id" integer,
  "completion_summary" text,
  "accepted_final_at" datetime,
  "accepted_final_by" integer,
  "signature_attachment_id" integer,
  "protocol_id" integer,
  "invoiced_at" datetime,
  "invoice_reference" varchar,
  "cancelled_at" datetime,
  "cancelled_by_user_id" integer,
  "cancellation_reason" text,
  foreign key("dispatch_override_by_user_id") references users("id") on delete set null on update no action,
  foreign key("asset_id") references assets("id") on delete set null on update no action,
  foreign key("tour_id") references tours("id") on delete set null on update no action,
  foreign key("assigned_user_id") references users("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("entry_type_id") references entry_types("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("on_call_shift_id") references on_call_shifts("id") on delete set null on update no action,
  foreign key("emergency_assignment_id") references emergency_assignments("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("recurrence_rule_id") references recurrence_rules("id") on delete set null on update no action,
  foreign key("planned_by_user_id") references users("id") on delete set null on update no action,
  foreign key("accepted_by_user_id") references "users"("id") on delete set null,
  foreign key("completed_by_user_id") references "users"("id") on delete set null,
  foreign key("accepted_final_by") references "users"("id") on delete set null,
  foreign key("signature_attachment_id") references "attachments"("id") on delete set null,
  foreign key("protocol_id") references "protocols"("id") on delete set null,
  foreign key("cancelled_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "de_assigned_sched_idx" on "diary_entries"(
  "assigned_user_id",
  "scheduled_for"
);
CREATE INDEX "de_due_date_idx" on "diary_entries"("due_date");
CREATE INDEX "de_entry_type_idx" on "diary_entries"("entry_type_id");
CREATE INDEX "de_location_mode_idx" on "diary_entries"("location_mode");
CREATE INDEX "de_org_asset_idx" on "diary_entries"(
  "organization_id",
  "asset_id"
);
CREATE INDEX "de_org_mode_idx" on "diary_entries"("organization_id", "mode");
CREATE INDEX "de_org_sched_idx" on "diary_entries"(
  "organization_id",
  "scheduled_for"
);
CREATE INDEX "de_recurrence_rule_idx" on "diary_entries"("recurrence_rule_id");
CREATE INDEX "de_scheduled_for_idx" on "diary_entries"("scheduled_for");
CREATE INDEX "de_tour_pos_idx" on "diary_entries"("tour_id", "tour_position");
CREATE INDEX "diary_entries_archived_at_index" on "diary_entries"(
  "archived_at"
);
CREATE INDEX "diary_entries_is_archived_index" on "diary_entries"(
  "is_archived"
);
CREATE INDEX "diary_entries_legacy_id_index" on "diary_entries"("legacy_id");
CREATE INDEX "diary_entries_project_id_index" on "diary_entries"("project_id");
CREATE INDEX "diary_entries_start_at_index" on "diary_entries"("start_at");
CREATE INDEX "diary_entries_user_id_status_start_at_index" on "diary_entries"(
  "user_id",
  "status",
  "start_at"
);
CREATE INDEX "diary_org_dispatch_idx" on "diary_entries"(
  "organization_id",
  "dispatch_status"
);
CREATE INDEX "idx_diary_entries_org" on "diary_entries"("organization_id");
CREATE INDEX "diary_lifecycle_status_idx" on "diary_entries"(
  "organization_id",
  "status",
  "planned_start_at"
);
CREATE TABLE IF NOT EXISTS "diary_entry_events"(
  "id" integer primary key autoincrement not null,
  "diary_entry_id" integer not null,
  "organization_id" integer not null,
  "event" varchar not null,
  "from_status" varchar,
  "to_status" varchar not null,
  "actor_user_id" integer,
  "actor_kind" varchar not null default 'user',
  "note" text,
  "payload" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete cascade,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "diary_events_entry_idx" on "diary_entry_events"(
  "diary_entry_id",
  "occurred_at"
);
CREATE INDEX "diary_events_org_idx" on "diary_entry_events"(
  "organization_id",
  "event",
  "occurred_at"
);
CREATE INDEX "tasks_org_status_idx" on "tasks"("organization_id", "status");
CREATE INDEX "timesheets_org_status_idx" on "timesheets"(
  "organization_id",
  "status"
);
CREATE INDEX "sick_leaves_org_dates_idx" on "sick_leaves"(
  "organization_id",
  "start_date",
  "end_date"
);
CREATE INDEX "diary_org_start_idx" on "diary_entries"(
  "organization_id",
  "start_at"
);
CREATE TABLE IF NOT EXISTS "customer_merge_dismissals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "customer_low_id" integer not null,
  "customer_high_id" integer not null,
  "dismissed_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("dismissed_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "cmd_pair_unique" on "customer_merge_dismissals"(
  "customer_low_id",
  "customer_high_id"
);
CREATE INDEX "cmd_org_idx" on "customer_merge_dismissals"("organization_id");
CREATE TABLE IF NOT EXISTS "customer_geofences"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "customer_id" integer not null,
  "site_id" integer,
  "project_id" integer,
  "label" varchar not null,
  "center_lat" numeric not null,
  "center_lng" numeric not null,
  "radius_m" integer not null default '100',
  "min_dwell_minutes" integer not null default '5',
  "gap_merge_minutes" integer not null default '10',
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("site_id") references "sites"("id") on delete set null,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "cgf_idx_org_customer" on "customer_geofences"(
  "organization_id",
  "customer_id",
  "is_active"
);
CREATE INDEX "cgf_idx_org_active" on "customer_geofences"(
  "organization_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "integration_inbox_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plugin_id" varchar not null,
  "source" varchar,
  "target_type" varchar not null,
  "external_type" varchar not null,
  "external_id" varchar,
  "dedupe_key" varchar not null,
  "case_type" varchar not null,
  "status" varchar not null default 'open',
  "referenceable_type" varchar,
  "referenceable_id" integer,
  "candidate_ids" text,
  "resolved_to_type" varchar,
  "resolved_to_id" integer,
  "remote_snapshot" text not null,
  "mapped_snapshot" text,
  "local_snapshot" text,
  "diff_fields" text,
  "display_title" varchar,
  "display_subtitle" varchar,
  "occurred_at" datetime,
  "resolved_by" integer,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "group_key" varchar,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "iii_dedupe_unique" on "integration_inbox_items"(
  "organization_id",
  "plugin_id",
  "dedupe_key"
);
CREATE INDEX "iii_status_target_idx" on "integration_inbox_items"(
  "organization_id",
  "status",
  "target_type"
);
CREATE INDEX "iii_external_idx" on "integration_inbox_items"(
  "plugin_id",
  "external_type",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "location_visits"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "customer_geofence_id" integer not null,
  "entered_at" datetime not null,
  "left_at" datetime,
  "duration_min" integer,
  "sample_count" integer not null default '0',
  "status" varchar not null default 'open',
  "materialized" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("customer_geofence_id") references "customer_geofences"("id") on delete cascade
);
CREATE INDEX "lv_idx_org_user_status" on "location_visits"(
  "organization_id",
  "user_id",
  "status"
);
CREATE INDEX "lv_idx_geofence_status" on "location_visits"(
  "customer_geofence_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "location_pending_entries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "location_visit_id" integer not null,
  "customer_id" integer,
  "project_id" integer,
  "suggested_date" date not null,
  "started_at" datetime not null,
  "ended_at" datetime not null,
  "minutes" integer not null,
  "description" varchar,
  "status" varchar not null default 'open',
  "time_entry_id" integer,
  "resolved_by" integer,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("location_visit_id") references "location_visits"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("time_entry_id") references "time_entries"("id") on delete set null,
  foreign key("resolved_by") references "users"("id") on delete set null
);
CREATE INDEX "lpe_idx_org_user_status" on "location_pending_entries"(
  "organization_id",
  "user_id",
  "status"
);
CREATE UNIQUE INDEX "lpe_uniq_visit" on "location_pending_entries"(
  "location_visit_id"
);
CREATE TABLE IF NOT EXISTS "location_device_tokens"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "label" varchar not null,
  "token_hash" varchar not null,
  "last_used_at" datetime,
  "revoked_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "ldt_uniq_token_hash" on "location_device_tokens"(
  "token_hash"
);
CREATE INDEX "ldt_idx_org_user" on "location_device_tokens"(
  "organization_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "location_points"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "recorded_at" datetime not null,
  "lat" text not null,
  "lng" text not null,
  "accuracy_m" integer,
  "source" varchar not null default('owntracks'),
  "ingest_batch_id" varchar,
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action
);
CREATE INDEX "lp_idx_org_user_time" on "location_points"(
  "organization_id",
  "user_id",
  "recorded_at"
);
CREATE INDEX "lp_idx_user_unprocessed" on "location_points"(
  "user_id",
  "processed_at"
);
CREATE TABLE IF NOT EXISTS "project_merge_dismissals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_low_id" integer not null,
  "project_high_id" integer not null,
  "dismissed_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("dismissed_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "pmd_pair_unique" on "project_merge_dismissals"(
  "project_low_id",
  "project_high_id"
);
CREATE INDEX "pmd_org_idx" on "project_merge_dismissals"("organization_id");
CREATE INDEX "iii_group_idx" on "integration_inbox_items"(
  "organization_id",
  "plugin_id",
  "group_key",
  "status"
);
CREATE TABLE IF NOT EXISTS "external_reference_aliases"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "plugin_id" varchar not null,
  "external_type" varchar not null,
  "external_id" varchar not null,
  "referenceable_type" varchar not null,
  "referenceable_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "extref_alias_ref" on "external_reference_aliases"(
  "referenceable_type",
  "referenceable_id"
);
CREATE UNIQUE INDEX "extref_alias_unique" on "external_reference_aliases"(
  "organization_id",
  "plugin_id",
  "external_type",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "procedure_material_requirements"(
  "id" integer primary key autoincrement not null,
  "procedure_template_version_id" integer not null,
  "position_code" varchar not null,
  "article_id" integer not null,
  "article_variant_id" integer,
  "quantity_kind" varchar not null default('per_unit'),
  "quantity" numeric not null default('0'),
  "ratio_part" numeric,
  "unit" varchar not null,
  "rounding" varchar,
  "waste_surcharge" numeric,
  "is_tool" tinyint(1) not null default('0'),
  "position" integer not null default('0'),
  "active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("article_variant_id") references article_variants("id") on delete set null on update no action,
  foreign key("article_id") references articles("id") on delete cascade on update no action,
  foreign key("procedure_template_version_id") references procedure_template_versions("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "proc_mat_req_position_unique" on "procedure_material_requirements"(
  "procedure_template_version_id",
  "position_code"
);
CREATE TABLE IF NOT EXISTS "manufacturing_order_materials"(
  "id" integer primary key autoincrement not null,
  "manufacturing_order_id" integer not null,
  "article_id" integer not null,
  "article_variant_id" integer,
  "name_snapshot" varchar not null,
  "target_qty" numeric not null,
  "unit_snapshot" varchar not null,
  "reserved_qty" numeric not null default('0'),
  "consumed_qty" numeric not null default('0'),
  "stock_reservation_id" integer,
  "cost_snapshot" numeric,
  "calc_reason" varchar,
  "rounding" varchar,
  "is_tool" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "actual_cost" numeric not null default('0'),
  foreign key("stock_reservation_id") references stock_reservations("id") on delete set null on update no action,
  foreign key("article_variant_id") references article_variants("id") on delete set null on update no action,
  foreign key("article_id") references articles("id") on delete cascade on update no action,
  foreign key("manufacturing_order_id") references manufacturing_orders("id") on delete cascade on update no action
);
CREATE INDEX "manufacturing_order_materials_manufacturing_order_id_index" on "manufacturing_order_materials"(
  "manufacturing_order_id"
);
CREATE TABLE IF NOT EXISTS "price_change_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "supplier_catalog_item_id" integer not null,
  "article_id" integer not null,
  "pricing_margin_rule_id" integer,
  "purchase_price_snapshot" numeric not null,
  "suggested_price" numeric not null,
  "margin_snapshot" numeric not null,
  "status" varchar not null default 'requested',
  "requested_by" integer not null,
  "decided_by" integer,
  "decided_at" datetime,
  "decision_note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("supplier_catalog_item_id") references "supplier_catalog_items"("id") on delete cascade,
  foreign key("article_id") references "articles"("id") on delete cascade,
  foreign key("pricing_margin_rule_id") references "pricing_margin_rules"("id") on delete set null,
  foreign key("requested_by") references "users"("id") on delete cascade,
  foreign key("decided_by") references "users"("id") on delete set null
);
CREATE INDEX "pcr_org_status_idx" on "price_change_requests"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "idea_maps"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "created_by" integer not null,
  "owner_user_id" integer not null,
  "title" varchar not null,
  "description" text,
  "visibility" varchar not null default 'private',
  "customer_id" integer,
  "project_id" integer,
  "diary_entry_id" integer,
  "archived_at" datetime,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "lock_version" integer not null default '1',
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete cascade,
  foreign key("owner_user_id") references "users"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete set null
);
CREATE INDEX "ideamap_org_owner_idx" on "idea_maps"(
  "organization_id",
  "owner_user_id"
);
CREATE TABLE IF NOT EXISTS "idea_nodes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "idea_map_id" integer not null,
  "parent_id" integer,
  "is_root" tinyint(1) not null default '0',
  "title" varchar not null,
  "note" text,
  "color" varchar not null default 'default',
  "node_status" varchar,
  "pos_x" integer,
  "pos_y" integer,
  "sort_order" integer not null default '0',
  "lock_version" integer not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("idea_map_id") references "idea_maps"("id") on delete cascade,
  foreign key("parent_id") references "idea_nodes"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "ideanode_map_parent_idx" on "idea_nodes"(
  "idea_map_id",
  "parent_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "idea_map_shares"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "idea_map_id" integer not null,
  "user_id" integer,
  "team_id" integer,
  "role" varchar not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("idea_map_id") references "idea_maps"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "ideashare_unique" on "idea_map_shares"(
  "idea_map_id",
  "user_id",
  "team_id"
);
CREATE TABLE IF NOT EXISTS "idea_node_references"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "idea_node_id" integer not null,
  "target_type" varchar not null,
  "target_id" integer not null,
  "kind" varchar not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("idea_node_id") references "idea_nodes"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "idearef_conv_unique" on "idea_node_references"(
  "idea_node_id",
  "target_type",
  "kind"
);
CREATE INDEX "idearef_target_idx" on "idea_node_references"(
  "target_type",
  "target_id"
);
CREATE TABLE IF NOT EXISTS "todoist_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "todoist_user_id" varchar,
  "todoist_user_email" varchar,
  "access_token" text,
  "refresh_token" text,
  "token_expires_at" datetime,
  "scopes" varchar,
  "status" varchar not null default 'active',
  "webhook_capable" tinyint(1) not null default '0',
  "sync_cursor" varchar,
  "last_sync_at" datetime,
  "last_full_sync_at" datetime,
  "last_error" varchar,
  "connected_by" integer,
  "connected_at" datetime,
  "disconnected_by" integer,
  "disconnected_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("connected_by") references "users"("id") on delete set null,
  foreign key("disconnected_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "tdc_org_unique" on "todoist_connections"(
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "todoist_project_links"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "todoist_project_id" varchar not null,
  "todoist_project_name" varchar,
  "target_kind" varchar not null,
  "project_id" integer,
  "sync_mode" varchar not null default 'todoist_to_workdiary',
  "status" varchar not null default 'draft',
  "last_run_at" datetime,
  "last_run_counters" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE UNIQUE INDEX "tdl_unique" on "todoist_project_links"(
  "organization_id",
  "todoist_project_id"
);
CREATE TABLE IF NOT EXISTS "todoist_section_links"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "todoist_project_link_id" integer not null,
  "todoist_section_id" varchar not null,
  "name" varchar,
  "task_status" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("todoist_project_link_id") references "todoist_project_links"("id") on delete cascade
);
CREATE UNIQUE INDEX "tsl_unique" on "todoist_section_links"(
  "todoist_project_link_id",
  "todoist_section_id"
);
CREATE TABLE IF NOT EXISTS "integration_outbox"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "plugin_id" varchar not null,
  "operation" varchar not null,
  "subject_type" varchar,
  "subject_id" integer,
  "payload" text not null,
  "idempotency_key" varchar not null,
  "status" varchar not null default 'pending',
  "attempts" integer not null default '0',
  "last_error" varchar,
  "confirmed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "iob_org_key_unique" on "integration_outbox"(
  "organization_id",
  "idempotency_key"
);
CREATE INDEX "iob_plugin_status_idx" on "integration_outbox"(
  "plugin_id",
  "status"
);
CREATE INDEX "iob_subject_idx" on "integration_outbox"(
  "subject_type",
  "subject_id"
);
CREATE TABLE IF NOT EXISTS "todoist_webhook_deliveries"(
  "id" integer primary key autoincrement not null,
  "delivery_id" varchar not null,
  "event_name" varchar,
  "organization_id" integer,
  "received_at" datetime not null,
  "processed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE UNIQUE INDEX "twdel_delivery_unique" on "todoist_webhook_deliveries"(
  "delivery_id"
);
CREATE TABLE IF NOT EXISTS "idea_node_links"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "idea_map_id" integer not null,
  "source_node_id" integer not null,
  "target_node_id" integer not null,
  "label" varchar,
  "color" varchar not null default 'default',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("idea_map_id") references "idea_maps"("id") on delete cascade,
  foreign key("source_node_id") references "idea_nodes"("id") on delete cascade,
  foreign key("target_node_id") references "idea_nodes"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "idealink_pair_unique" on "idea_node_links"(
  "source_node_id",
  "target_node_id"
);
CREATE INDEX "idealink_map_idx" on "idea_node_links"("idea_map_id");
CREATE TABLE IF NOT EXISTS "idea_node_summaries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "idea_map_id" integer not null,
  "parent_node_id" integer not null,
  "start_index" integer not null,
  "end_index" integer not null,
  "label" varchar,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("idea_map_id") references "idea_maps"("id") on delete cascade,
  foreign key("parent_node_id") references "idea_nodes"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "ideasum_map_idx" on "idea_node_summaries"("idea_map_id");
CREATE TABLE IF NOT EXISTS "gobd_exports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "period_from" date not null,
  "period_to" date not null,
  "sections" text not null,
  "file_hashes" text not null,
  "package_sha256" varchar not null,
  "record_count" integer not null default '0',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "gobdexp_org_period_idx" on "gobd_exports"(
  "organization_id",
  "period_from",
  "period_to"
);
CREATE TABLE IF NOT EXISTS "weather_snapshots"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "geo_lat" numeric not null,
  "geo_lng" numeric not null,
  "snapshot_date" date not null,
  "provider" varchar not null,
  "fetched_at" datetime not null,
  "temp_min" numeric,
  "temp_max" numeric,
  "precipitation_mm" numeric,
  "wind_gust_kmh" numeric,
  "weather_code" integer,
  "raw" text not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "weathersnap_unique" on "weather_snapshots"(
  "organization_id",
  "geo_lat",
  "geo_lng",
  "snapshot_date",
  "provider"
);
CREATE INDEX "weathersnap_org_date_idx" on "weather_snapshots"(
  "organization_id",
  "snapshot_date"
);
CREATE TABLE IF NOT EXISTS "protocols"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "type" varchar not null,
  "template_id" integer,
  "template_version" integer,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "title" varchar not null,
  "description" text,
  "state_initial" text,
  "state_final" text,
  "status" varchar not null default('draft'),
  "revision" integer not null default('1'),
  "supersedes_id" integer,
  "visibility" varchar not null default('internal'),
  "occurred_at" datetime not null,
  "created_by_user_id" integer not null,
  "signed_at" datetime,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "weather_snapshot_id" integer,
  foreign key("created_by_user_id") references users("id") on delete cascade on update no action,
  foreign key("supersedes_id") references protocols("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("weather_snapshot_id") references "weather_snapshots"("id") on delete set null
);
CREATE INDEX "protocols_org_type_status_idx" on "protocols"(
  "organization_id",
  "type",
  "status"
);
CREATE INDEX "protocols_subject_idx" on "protocols"(
  "subject_type",
  "subject_id",
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "caldav_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "base_url" varchar not null,
  "username" varchar not null,
  "app_password" text not null,
  "calendar_path" varchar not null,
  "active" tinyint(1) not null default '1',
  "last_published_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "scopes" text,
  "last_error" varchar,
  "last_error_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "caldavconn_org_idx" on "caldav_connections"("organization_id");
CREATE TABLE IF NOT EXISTS "webdav_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "base_url" varchar not null,
  "username" varchar not null,
  "app_password" text not null,
  "default_folder" varchar not null default 'Dokumente',
  "folder_map" text,
  "active" tinyint(1) not null default '1',
  "last_mirrored_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "sources" text,
  "last_error" varchar,
  "last_error_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "webdavconn_org_idx" on "webdav_connections"("organization_id");
CREATE INDEX "users_deactivated_at_idx" on "users"("deactivated_at");
CREATE TABLE IF NOT EXISTS "scim_tokens"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "label" varchar not null,
  "token_hash" varchar not null,
  "last_used_at" datetime,
  "revoked_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "scimtok_org_idx" on "scim_tokens"("organization_id");
CREATE UNIQUE INDEX "scimtok_hash_unique" on "scim_tokens"("token_hash");
CREATE TABLE IF NOT EXISTS "email_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "host" varchar not null,
  "port" integer not null default '993',
  "encryption" varchar not null default 'ssl',
  "username" varchar not null,
  "password" text not null,
  "folder" varchar not null default 'INBOX',
  "processed_folder" varchar,
  "active" tinyint(1) not null default '1',
  "last_polled_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "last_error" varchar,
  "last_error_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "emailconn_org_idx" on "email_connections"("organization_id");
CREATE TABLE IF NOT EXISTS "cti_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "provider" varchar not null default 'generic',
  "webhook_token_hash" varchar not null,
  "active" tinyint(1) not null default '1',
  "last_event_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "last_error" varchar,
  "last_error_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "cticonn_org_idx" on "cti_connections"("organization_id");
CREATE UNIQUE INDEX "cticonn_token_unique" on "cti_connections"(
  "webhook_token_hash"
);
CREATE TABLE IF NOT EXISTS "chat_webhooks"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "kind" varchar not null,
  "webhook_url" text not null,
  "active" tinyint(1) not null default '1',
  "last_delivery_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "chatwh_org_idx" on "chat_webhooks"("organization_id");
CREATE TABLE IF NOT EXISTS "attendance_terminals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "site_id" integer,
  "name" varchar not null,
  "token_hash" varchar not null,
  "active" tinyint(1) not null default '1',
  "last_seen_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("site_id") references "sites"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "attterm_org_idx" on "attendance_terminals"("organization_id");
CREATE UNIQUE INDEX "attterm_token_unique" on "attendance_terminals"(
  "token_hash"
);
CREATE TABLE IF NOT EXISTS "user_badges"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer not null,
  "label" varchar,
  "badge_hash" varchar not null,
  "revoked_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "userbadge_org_hash_unique" on "user_badges"(
  "organization_id",
  "badge_hash"
);
CREATE INDEX "userbadge_org_user_idx" on "user_badges"(
  "organization_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "carrier_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "carrier" varchar not null,
  "name" varchar not null,
  "credentials" text not null,
  "billing_number" varchar,
  "sandbox" tinyint(1) not null default '0',
  "active" tinyint(1) not null default '1',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "last_error" varchar,
  "last_error_at" datetime,
  "consecutive_failures" integer not null default '0',
  "disabled_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "carrierconn_org_idx" on "carrier_connections"("organization_id");
CREATE UNIQUE INDEX "carrierconn_org_carrier_unique" on "carrier_connections"(
  "organization_id",
  "carrier"
);
CREATE TABLE IF NOT EXISTS "shipments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "stock_delivery_id" integer,
  "carrier" varchar not null,
  "status" varchar not null default 'draft',
  "tracking_number" varchar,
  "carrier_shipment_id" varchar,
  "billing_number" varchar,
  "recipient_snapshot" text,
  "events" text,
  "last_tracked_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("stock_delivery_id") references "stock_deliveries"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "shipment_org_status_idx" on "shipments"(
  "organization_id",
  "status"
);
CREATE INDEX "shipment_tracking_idx" on "shipments"("tracking_number");
CREATE TABLE IF NOT EXISTS "scim_groups"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "display_name" varchar not null,
  "external_id" varchar,
  "members" text,
  "team_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete set null
);
CREATE INDEX "scimgrp_org_idx" on "scim_groups"("organization_id");
CREATE UNIQUE INDEX "scimgrp_org_name_unique" on "scim_groups"(
  "organization_id",
  "display_name"
);
CREATE INDEX "scimgrp_org_ext_idx" on "scim_groups"(
  "organization_id",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "maintenance_plans"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer,
  "code" varchar not null,
  "label" varchar not null,
  "interval_kind" varchar not null,
  "interval_value" integer not null,
  "tolerance_days" integer not null default('0'),
  "procedure_template_code" varchar,
  "last_run_at" datetime,
  "next_due_on" date,
  "is_active" tinyint(1) not null default('1'),
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "subject_type" varchar,
  "subject_id" integer,
  "sla_contract_id" integer,
  "is_contractual" tinyint(1) not null default '0',
  "due_action" varchar not null default 'none',
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("asset_id") references assets("id") on delete cascade on update no action,
  foreign key("sla_contract_id") references "sla_contracts"("id") on delete set null
);
CREATE INDEX "maintenance_plans_idx_due" on "maintenance_plans"(
  "organization_id",
  "is_active",
  "next_due_on"
);
CREATE INDEX "maintenance_plans_idx_subject" on "maintenance_plans"(
  "subject_type",
  "subject_id"
);
CREATE UNIQUE INDEX "maintenance_plans_uniq_code_per_asset" on "maintenance_plans"(
  "asset_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "sla_contract_quotas"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "sla_contract_id" integer not null,
  "period_kind" varchar not null,
  "included_minutes" integer not null,
  "overage_rate" numeric,
  "flat_fee" numeric,
  "warn_threshold_pct" integer not null default '80',
  "last_warned_period" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("sla_contract_id") references "sla_contracts"("id") on delete cascade
);
CREATE INDEX "slaquota_org_idx" on "sla_contract_quotas"("organization_id");
CREATE UNIQUE INDEX "slaquota_contract_period_unique" on "sla_contract_quotas"(
  "sla_contract_id",
  "period_kind"
);
CREATE TABLE IF NOT EXISTS "assets"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_no" varchar not null,
  "asset_class" varchar not null,
  "category_code" varchar,
  "name" varchar not null,
  "manufacturer" varchar,
  "model" varchar,
  "serial_no" varchar,
  "inventory_no" varchar,
  "customer_id" integer,
  "owned_by" varchar not null,
  "location_text" varchar,
  "location_lat" numeric,
  "location_lng" numeric,
  "status" varchar not null,
  "health" varchar not null default('ok'),
  "commissioned_on" date,
  "decommissioned_on" date,
  "warranty_until" date,
  "next_maintenance_on" date,
  "next_inspection_on" date,
  "notes" text,
  "custom" text,
  "created_at" datetime,
  "updated_at" datetime,
  "shared_remote" tinyint(1) not null default('0'),
  "room_id" integer,
  "foreign_customer_id" integer,
  "sla_contract_id" integer,
  foreign key("foreign_customer_id") references foreign_customers("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("room_id") references rooms("id") on delete set null on update no action,
  foreign key("sla_contract_id") references "sla_contracts"("id") on delete set null
);
CREATE INDEX "assets_idx_customer" on "assets"("customer_id", "status");
CREATE INDEX "assets_idx_room" on "assets"("room_id");
CREATE INDEX "assets_idx_serial" on "assets"("organization_id", "serial_no");
CREATE INDEX "assets_idx_status" on "assets"(
  "organization_id",
  "status",
  "health"
);
CREATE UNIQUE INDEX "assets_uniq_asset_no" on "assets"(
  "organization_id",
  "asset_no"
);
CREATE TABLE IF NOT EXISTS "asset_ownership_changes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "asset_id" integer not null,
  "from_ownership" varchar,
  "to_ownership" varchar not null,
  "from_customer_id" integer,
  "to_customer_id" integer,
  "note" varchar,
  "changed_by_user_id" integer,
  "changed_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("from_customer_id") references "customers"("id") on delete set null,
  foreign key("to_customer_id") references "customers"("id") on delete set null,
  foreign key("changed_by_user_id") references "users"("id") on delete set null
);
CREATE INDEX "assetown_asset_time_idx" on "asset_ownership_changes"(
  "asset_id",
  "changed_at"
);
CREATE TABLE IF NOT EXISTS "external_contacts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "email" varchar,
  "role" varchar,
  "party" varchar not null default 'other',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE INDEX "ext_contact_org_email_idx" on "external_contacts"(
  "organization_id",
  "email"
);
CREATE TABLE IF NOT EXISTS "external_participants"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "name" varchar not null,
  "email" varchar,
  "role" varchar,
  "party" varchar not null default('other'),
  "token_hash" varchar not null,
  "abilities" text not null,
  "expires_at" datetime not null,
  "invited_by_user_id" integer,
  "accepted_at" datetime,
  "last_access_at" datetime,
  "revoked_at" datetime,
  "created_at" datetime,
  "external_contact_id" integer,
  foreign key("invited_by_user_id") references users("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("external_contact_id") references "external_contacts"("id") on delete set null
);
CREATE INDEX "ext_part_org_expires_idx" on "external_participants"(
  "organization_id",
  "expires_at"
);
CREATE INDEX "ext_part_subject_idx" on "external_participants"(
  "subject_type",
  "subject_id"
);
CREATE UNIQUE INDEX "ext_part_token_hash_uq" on "external_participants"(
  "token_hash"
);
CREATE TABLE IF NOT EXISTS "cost_center_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer,
  "team_id" integer,
  "cost_center" varchar not null,
  "priority" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE INDEX "ccr_org_prio_idx" on "cost_center_rules"(
  "organization_id",
  "priority"
);
CREATE TABLE IF NOT EXISTS "attachment_confirmations"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "attachment_id" integer not null,
  "user_id" integer not null,
  "confirmed_at" datetime not null,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("attachment_id") references "attachments"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "attconf_unique" on "attachment_confirmations"(
  "attachment_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "diary_entry_qualifications"(
  "id" integer primary key autoincrement not null,
  "diary_entry_id" integer not null,
  "qualification_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("diary_entry_id") references "diary_entries"("id") on delete cascade,
  foreign key("qualification_id") references "qualifications"("id") on delete cascade
);
CREATE UNIQUE INDEX "deq_unique" on "diary_entry_qualifications"(
  "diary_entry_id",
  "qualification_id"
);
CREATE TABLE IF NOT EXISTS "import_value_mappings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "entity" varchar not null,
  "source_value" varchar not null,
  "target_kind" varchar not null,
  "tag_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("tag_id") references "tags"("id") on delete cascade
);
CREATE UNIQUE INDEX "ivm_unique" on "import_value_mappings"(
  "organization_id",
  "entity",
  "source_value"
);
CREATE TABLE IF NOT EXISTS "time_entries"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "project_id" integer,
  "task_id" integer,
  "user_id" integer not null,
  "date" date not null,
  "minutes" integer not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "timesheet_id" integer,
  "started_at" datetime,
  "ended_at" datetime,
  "break_minutes" integer not null default('0'),
  "kind" varchar not null default('work'),
  "billable" tinyint(1) not null default('1'),
  "hourly_rate" numeric,
  "fixed_rate" numeric,
  "rate" numeric not null default('0'),
  "internal_rate" numeric not null default('0'),
  "exported" tinyint(1) not null default('0'),
  "activity_type" varchar not null default('project'),
  "activity_category_id" integer,
  "attendance_id" integer,
  "travel_log_id" integer,
  "diary_entry_id" integer,
  "manufacturing_order_id" integer,
  "rework_reason_classification_id" integer,
  "goodwill_reason_classification_id" integer,
  foreign key("diary_entry_id") references diary_entries("id") on delete set null on update no action,
  foreign key("user_id") references users("id") on delete cascade on update no action,
  foreign key("task_id") references tasks("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("timesheet_id") references timesheets("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("activity_category_id") references activity_categories("id") on delete set null on update no action,
  foreign key("attendance_id") references attendances("id") on delete set null on update no action,
  foreign key("travel_log_id") references travel_logs("id") on delete set null on update no action,
  foreign key("manufacturing_order_id") references manufacturing_orders("id") on delete set null on update no action,
  foreign key("rework_reason_classification_id") references "classifications"("id") on delete set null,
  foreign key("goodwill_reason_classification_id") references "classifications"("id") on delete set null
);
CREATE INDEX "te_diary_entry_idx" on "time_entries"("diary_entry_id");
CREATE INDEX "time_entries_activity_category_id_index" on "time_entries"(
  "activity_category_id"
);
CREATE INDEX "time_entries_activity_type_index" on "time_entries"(
  "activity_type"
);
CREATE INDEX "time_entries_attendance_id_index" on "time_entries"(
  "attendance_id"
);
CREATE INDEX "time_entries_billable_index" on "time_entries"("billable");
CREATE INDEX "time_entries_date_index" on "time_entries"("date");
CREATE INDEX "time_entries_exported_index" on "time_entries"("exported");
CREATE INDEX "time_entries_manufacturing_order_id_index" on "time_entries"(
  "manufacturing_order_id"
);
CREATE INDEX "time_entries_project_id_index" on "time_entries"("project_id");
CREATE INDEX "time_entries_started_at_index" on "time_entries"("started_at");
CREATE INDEX "time_entries_timesheet_id_index" on "time_entries"(
  "timesheet_id"
);
CREATE INDEX "time_entries_travel_log_id_index" on "time_entries"(
  "travel_log_id"
);
CREATE INDEX "time_entries_user_id_index" on "time_entries"("user_id");
CREATE TABLE IF NOT EXISTS "support_access_grants"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "granted_by_user_id" integer not null,
  "granted_to_user_id" integer,
  "scope" varchar not null default 'read_only',
  "purpose" varchar not null,
  "expires_at" datetime not null,
  "revoked_at" datetime,
  "revoked_reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("granted_by_user_id") references "users"("id") on delete cascade,
  foreign key("granted_to_user_id") references "users"("id") on delete set null
);
CREATE INDEX "sag_org_expires_idx" on "support_access_grants"(
  "organization_id",
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "security_advisories"(
  "id" integer primary key autoincrement not null,
  "source" varchar not null default 'osv',
  "external_id" varchar not null,
  "ecosystem" varchar not null,
  "package" varchar not null,
  "installed_version" varchar not null,
  "severity" varchar not null default 'unknown',
  "cvss_vector" varchar,
  "summary" varchar,
  "fixed_in" varchar,
  "statement" text,
  "modified_at" datetime,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "sa_severity_resolved_idx" on "security_advisories"(
  "severity",
  "resolved_at"
);
CREATE UNIQUE INDEX "sa_external_id_uq" on "security_advisories"(
  "external_id"
);
CREATE TABLE IF NOT EXISTS "privacy_dpia_steps"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "dpia_id" integer not null,
  "step" varchar not null,
  "position" integer not null,
  "status" varchar not null default 'pending',
  "content" text,
  "completed_by" integer,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("dpia_id") references "privacy_dpias"("id") on delete cascade,
  foreign key("completed_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "pds_dpia_step_uq" on "privacy_dpia_steps"(
  "dpia_id",
  "step"
);
CREATE TABLE IF NOT EXISTS "privacy_requirements"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "requirement_key" varchar not null,
  "label" varchar not null,
  "category" varchar,
  "check_type" varchar not null,
  "active" tinyint(1) not null default '1',
  "params" text,
  "source" varchar not null default 'manual',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "preq_org_key_uq" on "privacy_requirements"(
  "organization_id",
  "requirement_key"
);
CREATE TABLE IF NOT EXISTS "isms_audit_programs"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "name" varchar not null,
  "norm" varchar,
  "edition" varchar,
  "cycle_years" integer not null default '3',
  "starts_on" date not null,
  "status" varchar not null default 'active',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("isms_scope_id") references "isms_scopes"("id") on delete cascade
);
CREATE INDEX "iap_org_status_idx" on "isms_audit_programs"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_audits"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer not null,
  "audit_no" integer not null,
  "title" varchar not null,
  "norm" varchar,
  "edition" varchar,
  "kind" varchar not null default('internal'),
  "status" varchar not null default('planned'),
  "planned_on" date,
  "performed_from" date,
  "performed_to" date,
  "lead_auditor_user_id" integer,
  "auditors" text,
  "criteria" text,
  "independence_note" text,
  "summary" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "isms_audit_program_id" integer,
  foreign key("lead_auditor_user_id") references users("id") on delete set null on update no action,
  foreign key("isms_scope_id") references isms_scopes("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("isms_audit_program_id") references "isms_audit_programs"("id") on delete set null
);
CREATE UNIQUE INDEX "isms_audit_org_no_uq" on "isms_audits"(
  "organization_id",
  "audit_no"
);
CREATE INDEX "isms_audit_org_status_idx" on "isms_audits"(
  "organization_id",
  "status"
);
CREATE INDEX "isms_audit_scope_status_idx" on "isms_audits"(
  "isms_scope_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "retention_proposals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "area" varchar not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "retention_until" date not null,
  "reason" varchar not null,
  "status" varchar not null default 'pending',
  "decided_by" integer,
  "decided_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("decided_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "rp_org_subject_uq" on "retention_proposals"(
  "organization_id",
  "area",
  "subject_type",
  "subject_id"
);
CREATE INDEX "rp_org_status_idx" on "retention_proposals"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "isms_assessment_snapshots"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "isms_scope_id" integer,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "payload" text not null,
  "recorded_at" datetime not null,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE INDEX "ias_subject_time_idx" on "isms_assessment_snapshots"(
  "organization_id",
  "subject_type",
  "subject_id",
  "recorded_at"
);
CREATE TABLE IF NOT EXISTS "agile_boards"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "project_id" integer not null,
  "method" varchar not null default 'kanban',
  "name" varchar not null,
  "description" varchar,
  "dod_items" text,
  "lock_version" integer not null default '1',
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "agb_project_unique" on "agile_boards"("project_id");
CREATE TABLE IF NOT EXISTS "agile_board_columns"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "board_id" integer not null,
  "name" varchar not null,
  "category" varchar not null,
  "report_role" varchar,
  "position" integer not null,
  "wip_limit" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("board_id") references "agile_boards"("id") on delete cascade
);
CREATE UNIQUE INDEX "agbc_board_pos" on "agile_board_columns"(
  "board_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "agile_work_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "board_id" integer not null,
  "task_id" integer not null,
  "item_type" varchar not null default 'task',
  "column_id" integer,
  "backlog_rank" integer not null,
  "story_points" integer,
  "blocked_at" datetime,
  "blocked_reason" varchar,
  "lock_version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("board_id") references "agile_boards"("id") on delete cascade,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("column_id") references "agile_board_columns"("id") on delete set null
);
CREATE UNIQUE INDEX "agwi_task_unique" on "agile_work_items"("task_id");
CREATE INDEX "agwi_board_rank_idx" on "agile_work_items"(
  "board_id",
  "backlog_rank"
);
CREATE TABLE IF NOT EXISTS "agile_acceptance_criteria"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "work_item_id" integer not null,
  "position" integer not null,
  "text" varchar not null,
  "checked_at" datetime,
  "checked_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("work_item_id") references "agile_work_items"("id") on delete cascade,
  foreign key("checked_by") references "users"("id") on delete set null
);
CREATE INDEX "agac_item_pos_idx" on "agile_acceptance_criteria"(
  "work_item_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "agile_events"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "board_id" integer not null,
  "work_item_id" integer,
  "sprint_id" integer,
  "event" varchar not null,
  "actor_user_id" integer,
  "payload" text,
  "created_at" datetime not null,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("board_id") references "agile_boards"("id") on delete cascade,
  foreign key("work_item_id") references "agile_work_items"("id") on delete cascade,
  foreign key("actor_user_id") references "users"("id") on delete set null
);
CREATE INDEX "age_board_time_idx" on "agile_events"("board_id", "created_at");
CREATE INDEX "age_item_time_idx" on "agile_events"(
  "work_item_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "agile_sprints"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "board_id" integer not null,
  "name" varchar not null,
  "goal" varchar,
  "starts_on" date,
  "ends_on" date,
  "status" varchar not null default 'planned',
  "commitment_snapshot" text,
  "completion_snapshot" text,
  "started_at" datetime,
  "completed_at" datetime,
  "cancelled_at" datetime,
  "cancel_reason" varchar,
  "created_by" integer,
  "lock_version" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "capacity_snapshot" text,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("board_id") references "agile_boards"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "agsp_board_status_idx" on "agile_sprints"("board_id", "status");
CREATE TABLE IF NOT EXISTS "agile_sprint_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "sprint_id" integer not null,
  "work_item_id" integer not null,
  "position" integer not null,
  "added_after_start" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("sprint_id") references "agile_sprints"("id") on delete cascade,
  foreign key("work_item_id") references "agile_work_items"("id") on delete cascade
);
CREATE UNIQUE INDEX "agsi_sprint_item_unique" on "agile_sprint_items"(
  "sprint_id",
  "work_item_id"
);
CREATE INDEX "age_sprint_idx" on "agile_events"("sprint_id");
CREATE TABLE IF NOT EXISTS "service_queues"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "purpose" varchar,
  "team_id" integer,
  "data_ownership" varchar not null default 'native',
  "supported_kinds" text,
  "business_hours" text,
  "holiday_region" varchar,
  "default_sla_contract_id" integer,
  "email_connection_id" integer,
  "sender_identity" text,
  "visibility" varchar not null default 'internal',
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete set null,
  foreign key("default_sla_contract_id") references "sla_contracts"("id") on delete set null,
  foreign key("email_connection_id") references "email_connections"("id") on delete set null
);
CREATE UNIQUE INDEX "svq_org_name_unique" on "service_queues"(
  "organization_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "service_ticket_watchers"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "svtw_ticket_user_unique" on "service_ticket_watchers"(
  "service_ticket_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "sla_clock_segments"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "target" varchar not null,
  "paused_from" datetime not null,
  "paused_to" datetime,
  "reason" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE INDEX "slcs_ticket_target_idx" on "sla_clock_segments"(
  "service_ticket_id",
  "target"
);
CREATE TABLE IF NOT EXISTS "service_ticket_messages"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "kind" varchar not null,
  "author_type" varchar,
  "author_id" integer,
  "to" text,
  "cc" text,
  "subject" varchar,
  "body" text not null,
  "channel" varchar not null default 'manual',
  "message_id" varchar,
  "in_reply_to" varchar,
  "delivery_status" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE INDEX "svtm_author_idx" on "service_ticket_messages"(
  "author_type",
  "author_id"
);
CREATE INDEX "svtm_ticket_time_idx" on "service_ticket_messages"(
  "service_ticket_id",
  "created_at"
);
CREATE INDEX "svtm_message_id_idx" on "service_ticket_messages"("message_id");
CREATE TABLE IF NOT EXISTS "ticket_routing_rules"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "position" integer not null,
  "conditions" text not null,
  "actions" text not null,
  "version" integer not null default '1',
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE INDEX "trr_org_pos_idx" on "ticket_routing_rules"(
  "organization_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "ticket_rule_executions"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "ticket_routing_rule_id" integer not null,
  "service_ticket_id" integer not null,
  "rule_version" integer not null,
  "matched_conditions" text not null,
  "applied_actions" text not null,
  "dry_run" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("ticket_routing_rule_id") references "ticket_routing_rules"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE INDEX "tre_ticket_idx" on "ticket_rule_executions"("service_ticket_id");
CREATE TABLE IF NOT EXISTS "sla_contracts"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "customer_id" integer,
  "code" varchar not null,
  "label" varchar not null,
  "priority_table" text not null,
  "business_hours" text,
  "escalation_chain" text,
  "is_default" tinyint(1) not null default('0'),
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  "pause_rules" text,
  "is_ola" tinyint(1) not null default '0',
  "ola_team_id" integer,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("ola_team_id") references "teams"("id") on delete set null
);
CREATE INDEX "sla_contracts_idx_lookup" on "sla_contracts"(
  "organization_id",
  "customer_id",
  "is_active"
);
CREATE UNIQUE INDEX "sla_contracts_uniq_code" on "sla_contracts"(
  "organization_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "business_services"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "description" varchar,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE UNIQUE INDEX "bsv_org_name_unique" on "business_services"(
  "organization_id",
  "name"
);
CREATE TABLE IF NOT EXISTS "service_offerings"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "business_service_id" integer not null,
  "name" varchar not null,
  "description" varchar,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("business_service_id") references "business_services"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "request_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_offering_id" integer not null,
  "name" varchar not null,
  "description" varchar,
  "form_template_id" integer,
  "approval_chain" text,
  "sla_contract_id" integer,
  "fulfillment" varchar not null default 'task',
  "fulfillment_config" text,
  "version" integer not null default '1',
  "visibility" text,
  "active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_offering_id") references "service_offerings"("id") on delete cascade,
  foreign key("form_template_id") references "form_templates"("id") on delete set null,
  foreign key("sla_contract_id") references "sla_contracts"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "service_requests"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "request_item_id" integer not null,
  "form_snapshot" text,
  "catalog_snapshot" text not null,
  "status" varchar not null default 'pending_approval',
  "fulfilled_type" varchar,
  "fulfilled_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade,
  foreign key("request_item_id") references "request_items"("id") on delete cascade
);
CREATE INDEX "srq_fulfilled_idx" on "service_requests"(
  "fulfilled_type",
  "fulfilled_id"
);
CREATE UNIQUE INDEX "srq_ticket_unique" on "service_requests"(
  "service_ticket_id"
);
CREATE TABLE IF NOT EXISTS "service_tickets"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "ticket_no" varchar not null,
  "customer_id" integer,
  "asset_id" integer,
  "project_id" integer,
  "sla_contract_id" integer,
  "title" varchar not null,
  "description" text,
  "status" varchar not null,
  "priority" varchar not null,
  "source" varchar not null default('manual'),
  "source_reference" varchar,
  "reported_by_user_id" integer,
  "assigned_to_user_id" integer,
  "reported_at" datetime,
  "acknowledged_at" datetime,
  "scheduled_for" datetime,
  "started_at" datetime,
  "resolved_at" datetime,
  "accepted_at" datetime,
  "closed_at" datetime,
  "reaction_due_at" datetime,
  "resolution_due_at" datetime,
  "reaction_breached" tinyint(1) not null default('0'),
  "resolution_breached" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "diary_entry_id" integer,
  "queue_id" integer,
  "kind" varchar not null default('incident'),
  "requester_type" varchar,
  "requester_id" integer,
  "impact" integer,
  "urgency" integer,
  "wait_reason" varchar,
  "wait_until" datetime,
  "wait_owner_id" integer,
  "escalation_level" integer not null default('0'),
  "confidentiality" varchar not null default('normal'),
  "resolution_summary" text,
  "close_code" varchar,
  "sla_snapshot" text,
  "is_major" tinyint(1) not null default '0',
  "incident_lead_id" integer,
  "stakeholders" text,
  "comm_rhythm" varchar,
  "workaround" text,
  foreign key("wait_owner_id") references users("id") on delete set null on update no action,
  foreign key("diary_entry_id") references diary_entries("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("asset_id") references assets("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("sla_contract_id") references sla_contracts("id") on delete set null on update no action,
  foreign key("reported_by_user_id") references users("id") on delete set null on update no action,
  foreign key("assigned_to_user_id") references users("id") on delete set null on update no action,
  foreign key("queue_id") references service_queues("id") on delete set null on update no action,
  foreign key("incident_lead_id") references "users"("id") on delete set null
);
CREATE INDEX "service_tickets_idx_asset" on "service_tickets"("asset_id");
CREATE INDEX "service_tickets_idx_assignee" on "service_tickets"(
  "organization_id",
  "assigned_to_user_id",
  "status"
);
CREATE INDEX "service_tickets_idx_customer" on "service_tickets"(
  "customer_id"
);
CREATE INDEX "service_tickets_idx_due" on "service_tickets"(
  "organization_id",
  "resolution_due_at"
);
CREATE INDEX "service_tickets_idx_org_status" on "service_tickets"(
  "organization_id",
  "status",
  "priority"
);
CREATE UNIQUE INDEX "service_tickets_uniq_no" on "service_tickets"(
  "organization_id",
  "ticket_no"
);
CREATE INDEX "svt_requester_idx" on "service_tickets"(
  "requester_type",
  "requester_id"
);
CREATE TABLE IF NOT EXISTS "service_ticket_links"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "linked_type" varchar not null,
  "linked_id" integer not null,
  "kind" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE INDEX "svtl_linked_idx" on "service_ticket_links"(
  "linked_type",
  "linked_id"
);
CREATE UNIQUE INDEX "svtl_unique" on "service_ticket_links"(
  "service_ticket_id",
  "linked_type",
  "linked_id",
  "kind"
);
CREATE TABLE IF NOT EXISTS "problems"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "description" text,
  "owner_id" integer,
  "status" varchar not null default 'open',
  "root_cause" text,
  "evidence" text,
  "workaround" text,
  "permanent_fix" text,
  "visibility" varchar not null default 'internal',
  "effectiveness_check_due_at" datetime,
  "effectiveness_checked_at" datetime,
  "effectiveness_result" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("owner_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "problem_ticket"(
  "id" integer primary key autoincrement not null,
  "problem_id" integer not null,
  "service_ticket_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("problem_id") references "problems"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE UNIQUE INDEX "prt_unique" on "problem_ticket"(
  "problem_id",
  "service_ticket_id"
);
CREATE TABLE IF NOT EXISTS "approvals"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "approvable_type" varchar not null,
  "approvable_id" integer not null,
  "step" integer not null,
  "approver_rule" text not null,
  "decided_by" integer,
  "decision" varchar,
  "reason" varchar,
  "decided_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("decided_by") references "users"("id") on delete set null
);
CREATE INDEX "apv_subject_idx" on "approvals"(
  "approvable_type",
  "approvable_id"
);
CREATE TABLE IF NOT EXISTS "change_templates"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "implementation_plan" text,
  "test_plan" text,
  "rollback_plan" text,
  "version" integer not null default '1',
  "approved" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "changes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "title" varchar not null,
  "change_type" varchar not null default 'normal',
  "reason" text,
  "scope" text,
  "risk" integer,
  "impact" integer,
  "urgency" integer,
  "window_from" datetime,
  "window_to" datetime,
  "implementation_plan" text,
  "test_plan" text,
  "rollback_plan" text,
  "change_template_id" integer,
  "template_snapshot" text,
  "status" varchar not null default 'draft',
  "outcome" varchar,
  "pir_notes" text,
  "pir_done_at" datetime,
  "problem_id" integer,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("change_template_id") references "change_templates"("id") on delete set null,
  foreign key("problem_id") references "problems"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "change_ticket"(
  "id" integer primary key autoincrement not null,
  "change_id" integer not null,
  "service_ticket_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("change_id") references "changes"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade
);
CREATE UNIQUE INDEX "chtk_unique" on "change_ticket"(
  "change_id",
  "service_ticket_id"
);
CREATE TABLE IF NOT EXISTS "zammad_connections"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "base_url" varchar not null,
  "api_token" text not null,
  "webhook_secret" text,
  "active" tinyint(1) not null default('1'),
  "default_project_id" integer,
  "queue_map" text,
  "last_polled_at" datetime,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "resolved_state" varchar,
  "time_unit" varchar,
  "ticket_target" varchar not null default 'task',
  "service_queue_id" integer,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("default_project_id") references projects("id") on delete set null on update no action,
  foreign key("organization_id") references organizations("id") on delete cascade on update no action,
  foreign key("service_queue_id") references "service_queues"("id") on delete set null
);
CREATE INDEX "zammadconn_org_idx" on "zammad_connections"("organization_id");
CREATE TABLE IF NOT EXISTS "ticket_satisfaction"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "service_ticket_id" integer not null,
  "portal_user_id" integer,
  "score" integer not null,
  "comment" varchar,
  "answered_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("service_ticket_id") references "service_tickets"("id") on delete cascade,
  foreign key("portal_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "tsat_ticket_unique" on "ticket_satisfaction"(
  "service_ticket_id"
);
CREATE TABLE IF NOT EXISTS "incoming_einvoices"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "document_id" integer not null,
  "sha256" varchar not null,
  "source" varchar not null default 'upload',
  "received_at" datetime not null,
  "status" varchar not null default 'received',
  "decided_by" integer,
  "decided_at" datetime,
  "decision_note" varchar,
  "summary" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("document_id") references "documents"("id") on delete cascade,
  foreign key("decided_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "ine_org_hash_unique" on "incoming_einvoices"(
  "organization_id",
  "sha256"
);
CREATE TABLE IF NOT EXISTS "quotes"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "customer_id" integer not null,
  "project_id" integer,
  "number" varchar not null,
  "version" integer not null default '1',
  "previous_version_id" integer,
  "status" varchar not null default 'draft',
  "valid_until" date,
  "terms" text,
  "subtotal" numeric not null default '0',
  "tax_amount" numeric not null default '0',
  "total" numeric not null default '0',
  "acceptance_token_hash" varchar,
  "decided_at" datetime,
  "decision_snapshot" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete set null,
  foreign key("previous_version_id") references "quotes"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "qte_org_no_ver_unique" on "quotes"(
  "organization_id",
  "number",
  "version"
);
CREATE TABLE IF NOT EXISTS "quote_items"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "quote_id" integer not null,
  "position" integer not null,
  "description" varchar not null,
  "quantity" numeric not null default '1',
  "unit" varchar,
  "unit_price" numeric not null default '0',
  "tax_rate" numeric,
  "optional" tinyint(1) not null default '0',
  "accepted" tinyint(1),
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("quote_id") references "quotes"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "invoices"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer,
  "customer_id" integer not null,
  "project_id" integer,
  "number" varchar,
  "status" varchar not null default('draft'),
  "issued_on" date,
  "due_on" date,
  "paid_on" date,
  "currency" varchar not null default('EUR'),
  "subtotal" numeric not null default('0'),
  "tax_rate" numeric not null default('19'),
  "tax_amount" numeric not null default('0'),
  "total" numeric not null default('0'),
  "notes" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "cancelled_at" datetime,
  "cancelled_by" integer,
  "cancel_reason" text,
  "type" varchar not null default('invoice'),
  "parent_invoice_id" integer,
  "sent_at" datetime,
  "sent_count" integer not null default('0'),
  "external_number" varchar,
  "number_source" varchar not null default('local'),
  "foreign_customer_id" integer,
  "category" varchar not null default('service'),
  "is_reverse_charge" tinyint(1) not null default('0'),
  "party_snapshot" text,
  "tax_breakdown" text,
  "payment_terms_days" integer,
  "approved_at" datetime,
  "approved_by" integer,
  "dunning_level" integer not null default('0'),
  "dunned_at" datetime,
  "objection_at" datetime,
  "objection_note" varchar,
  "quote_id" integer,
  foreign key("approved_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("project_id") references projects("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete cascade on update no action,
  foreign key("organization_id") references organizations("id") on delete set null on update no action,
  foreign key("cancelled_by") references users("id") on delete set null on update no action,
  foreign key("parent_invoice_id") references invoices("id") on delete set null on update no action,
  foreign key("foreign_customer_id") references foreign_customers("id") on delete set null on update no action,
  foreign key("quote_id") references "quotes"("id") on delete set null
);
CREATE INDEX "invoices_org_status_idx" on "invoices"(
  "organization_id",
  "status"
);
CREATE INDEX "invoices_organization_id_index" on "invoices"("organization_id");
CREATE UNIQUE INDEX "invoices_organization_id_number_unique" on "invoices"(
  "organization_id",
  "number"
);
CREATE INDEX "invoices_parent_invoice_id_index" on "invoices"(
  "parent_invoice_id"
);
CREATE INDEX "invoices_status_index" on "invoices"("status");
CREATE INDEX "invoices_type_index" on "invoices"("type");
CREATE TABLE IF NOT EXISTS "system_settings"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "value" text,
  "is_sensitive" tinyint(1) not null default '0',
  "updated_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("updated_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "syset_key_unique" on "system_settings"("key");
CREATE TABLE IF NOT EXISTS "scheduled_job_overrides"(
  "id" integer primary key autoincrement not null,
  "job_key" varchar not null,
  "organization_id" integer,
  "enabled" tinyint(1) not null default '1',
  "cadence" text,
  "updated_by_user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("updated_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "sjo_job_org_unique" on "scheduled_job_overrides"(
  "job_key",
  "organization_id"
);
CREATE TABLE IF NOT EXISTS "scheduled_job_runs"(
  "id" integer primary key autoincrement not null,
  "job_key" varchar not null,
  "started_at" datetime not null,
  "finished_at" datetime,
  "status" varchar not null,
  "duration_ms" integer,
  "exit_code" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "sjr_job_started_idx" on "scheduled_job_runs"(
  "job_key",
  "started_at"
);
CREATE TABLE IF NOT EXISTS "scheduled_job_states"(
  "id" integer primary key autoincrement not null,
  "job_key" varchar not null,
  "last_started_at" datetime,
  "last_success_at" datetime,
  "last_failure_at" datetime,
  "consecutive_failures" integer not null default '0',
  "last_duration_ms" integer,
  "last_status" varchar,
  "overdue_notified_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "sjs_job_unique" on "scheduled_job_states"("job_key");
CREATE TABLE IF NOT EXISTS "operations_tasks"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "is_system" tinyint(1) not null default '0',
  "type" varchar not null,
  "severity" varchar not null,
  "status" varchar not null,
  "dedupe_key" varchar not null,
  "title_key" varchar not null,
  "params" text,
  "link_route" varchar,
  "link_params" text,
  "assigned_role" varchar,
  "assigned_user_id" integer,
  "snoozed_until" datetime,
  "first_seen_at" datetime not null,
  "last_seen_at" datetime not null,
  "resolved_at" datetime,
  "acted_by_user_id" integer,
  "acted_at" datetime,
  "note" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("assigned_user_id") references "users"("id") on delete set null,
  foreign key("acted_by_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "opt_dedupe_unique" on "operations_tasks"("dedupe_key");
CREATE INDEX "opt_org_status_idx" on "operations_tasks"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "problem_reports"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "user_id" integer,
  "reference_no" varchar not null,
  "status" varchar not null,
  "severity" varchar not null,
  "summary" varchar not null,
  "description" text not null,
  "expected_behavior" text,
  "actual_behavior" text,
  "contact_ok" tinyint(1) not null default '0',
  "page_context" text not null,
  "diagnostic_excerpt" text,
  "diagnostics_approved_by" integer,
  "delivery_target" varchar not null,
  "delivered_at" datetime,
  "delivery_error" varchar,
  "external_ref" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null,
  foreign key("diagnostics_approved_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "prr_org_ref_unique" on "problem_reports"(
  "organization_id",
  "reference_no"
);
CREATE INDEX "prr_org_status_idx" on "problem_reports"(
  "organization_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "component_updates"(
  "id" integer primary key autoincrement not null,
  "component_type" varchar not null,
  "component_key" varchar not null,
  "installed_version" varchar,
  "available_version" varchar not null,
  "channel" varchar not null default 'stable',
  "classification" varchar not null default 'normal',
  "min_app_version" varchar,
  "max_app_version" varchar,
  "compatible" tinyint(1) not null default '1',
  "changelog_url" varchar,
  "requires" text,
  "source" varchar not null default 'remote',
  "checked_at" datetime not null,
  "acknowledged_at" datetime,
  "acknowledged_by" integer,
  "snoozed_until" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("acknowledged_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "cup_component_unique" on "component_updates"(
  "component_type",
  "component_key"
);
CREATE TABLE IF NOT EXISTS "maintenance_windows"(
  "id" integer primary key autoincrement not null,
  "scope" varchar not null,
  "organization_id" integer,
  "announce_from" datetime,
  "starts_at" datetime not null,
  "ends_at" datetime not null,
  "message" varchar,
  "read_only" tinyint(1) not null default '0',
  "block_ingest" tinyint(1) not null default '0',
  "status" varchar not null,
  "created_by" integer,
  "notes" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "mwin_status_start_idx" on "maintenance_windows"(
  "status",
  "starts_at"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_04_24_000001_add_legacy_user_id_to_users_table',1);
INSERT INTO migrations VALUES(5,'2026_04_24_000002_create_diary_entries_table',1);
INSERT INTO migrations VALUES(6,'2026_04_29_215328_create_permission_tables',1);
INSERT INTO migrations VALUES(7,'2026_04_29_220000_create_on_call_shifts_table',1);
INSERT INTO migrations VALUES(8,'2026_04_29_220001_create_emergency_assignments_table',1);
INSERT INTO migrations VALUES(9,'2026_04_29_220002_extend_diary_entries_with_shift_links',1);
INSERT INTO migrations VALUES(10,'2026_04_30_000000_add_must_change_password_to_users',1);
INSERT INTO migrations VALUES(11,'2026_04_30_053546_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(12,'2026_04_30_120000_create_tags_table',1);
INSERT INTO migrations VALUES(13,'2026_04_30_130000_create_comments_table',1);
INSERT INTO migrations VALUES(14,'2026_04_30_140000_create_attachments_table',1);
INSERT INTO migrations VALUES(15,'2026_04_30_150000_create_audit_logs_table',1);
INSERT INTO migrations VALUES(16,'2026_04_30_160000_create_push_subscriptions_table',1);
INSERT INTO migrations VALUES(17,'2026_04_30_170000_create_projects_table',1);
INSERT INTO migrations VALUES(18,'2026_05_03_100000_add_performance_indexes',1);
INSERT INTO migrations VALUES(19,'2026_05_11_120000_create_holidays_table',1);
INSERT INTO migrations VALUES(20,'2026_05_11_130000_add_relative_recurrence_to_holidays_table',1);
INSERT INTO migrations VALUES(21,'2026_05_11_140000_create_vacations_table',1);
INSERT INTO migrations VALUES(22,'2026_05_11_150000_create_shift_types_table',1);
INSERT INTO migrations VALUES(23,'2026_05_11_160000_create_scheduled_shifts_table',1);
INSERT INTO migrations VALUES(24,'2026_05_12_100000_create_organizations_table',1);
INSERT INTO migrations VALUES(25,'2026_05_12_110000_add_organization_id_to_tenant_tables',1);
INSERT INTO migrations VALUES(26,'2026_05_12_120000_create_duty_plans_table',1);
INSERT INTO migrations VALUES(27,'2026_05_12_130000_create_qualifications_tables',1);
INSERT INTO migrations VALUES(28,'2026_05_12_200000_create_milestones_table',1);
INSERT INTO migrations VALUES(29,'2026_05_12_200001_create_tasks_table',1);
INSERT INTO migrations VALUES(30,'2026_05_12_200002_create_time_entries_table',1);
INSERT INTO migrations VALUES(31,'2026_05_14_100000_create_coverage_requirements_table',1);
INSERT INTO migrations VALUES(32,'2026_05_14_120000_create_timesheets_table',1);
INSERT INTO migrations VALUES(33,'2026_05_14_120001_extend_time_entries_for_timesheets',1);
INSERT INTO migrations VALUES(34,'2026_05_14_120002_create_materials_table',1);
INSERT INTO migrations VALUES(35,'2026_05_14_120003_create_material_usages_table',1);
INSERT INTO migrations VALUES(36,'2026_05_14_120004_create_work_schedules_table',1);
INSERT INTO migrations VALUES(37,'2026_05_14_120005_create_flex_balances_table',1);
INSERT INTO migrations VALUES(38,'2026_05_14_140000_create_customers_table',1);
INSERT INTO migrations VALUES(39,'2026_05_14_140001_add_kimai_fields_to_projects_table',1);
INSERT INTO migrations VALUES(40,'2026_05_14_140002_add_kimai_fields_to_tasks_table',1);
INSERT INTO migrations VALUES(41,'2026_05_14_140003_add_rates_to_users_table',1);
INSERT INTO migrations VALUES(42,'2026_05_14_140004_add_billing_fields_to_time_entries_table',1);
INSERT INTO migrations VALUES(43,'2026_05_14_150000_create_external_references_table',1);
INSERT INTO migrations VALUES(44,'2026_05_15_120000_extend_customers_table',1);
INSERT INTO migrations VALUES(45,'2026_05_15_140000_add_default_to_projects',1);
INSERT INTO migrations VALUES(46,'2026_05_16_100000_add_parent_to_projects',1);
INSERT INTO migrations VALUES(47,'2026_05_16_110000_create_lexoffice_articles_table',1);
INSERT INTO migrations VALUES(48,'2026_05_16_120000_create_project_billing_rules_table',1);
INSERT INTO migrations VALUES(49,'2026_05_16_130000_make_projects_billable_nullable',1);
INSERT INTO migrations VALUES(50,'2026_05_17_100000_create_invoices_table',1);
INSERT INTO migrations VALUES(51,'2026_05_17_120000_create_activity_categories_table',1);
INSERT INTO migrations VALUES(52,'2026_05_17_120001_create_attendances_table',1);
INSERT INTO migrations VALUES(53,'2026_05_17_120002_create_travel_logs_table',1);
INSERT INTO migrations VALUES(54,'2026_05_17_120003_extend_time_entries_for_attendance',1);
INSERT INTO migrations VALUES(55,'2026_05_17_120004_extend_timesheets_for_personal_day',1);
INSERT INTO migrations VALUES(56,'2026_05_17_140000_create_vehicles_table',1);
INSERT INTO migrations VALUES(57,'2026_05_17_140001_create_energy_logs_table',1);
INSERT INTO migrations VALUES(58,'2026_05_17_140002_add_vehicle_id_to_travel_logs',1);
INSERT INTO migrations VALUES(59,'2026_05_17_160000_create_geocode_cache_table',1);
INSERT INTO migrations VALUES(60,'2026_05_17_160001_add_geo_to_customers',1);
INSERT INTO migrations VALUES(61,'2026_05_17_180000_add_home_address_to_users',1);
INSERT INTO migrations VALUES(62,'2026_05_17_180001_create_tours_table',1);
INSERT INTO migrations VALUES(63,'2026_05_17_180002_create_service_orders_table',1);
INSERT INTO migrations VALUES(64,'2026_05_17_190000_add_rental_fields_to_vehicles',1);
INSERT INTO migrations VALUES(65,'2026_05_18_100000_create_entry_types_table',1);
INSERT INTO migrations VALUES(66,'2026_05_18_100001_extend_diary_entries_with_order_fields',1);
INSERT INTO migrations VALUES(67,'2026_05_18_100002_migrate_service_orders_to_diary_entries',1);
INSERT INTO migrations VALUES(68,'2026_05_18_100003_drop_service_orders_table',1);
INSERT INTO migrations VALUES(69,'2026_05_18_120000_make_comments_polymorphic',1);
INSERT INTO migrations VALUES(70,'2026_05_18_140000_create_sick_leaves_table',1);
INSERT INTO migrations VALUES(71,'2026_05_18_140100_migrate_vacation_sick_to_sick_leaves',1);
INSERT INTO migrations VALUES(72,'2026_05_18_160000_add_personalization_columns',1);
INSERT INTO migrations VALUES(73,'2026_05_19_094523_add_slug_to_customers_and_scope_project_slug',1);
INSERT INTO migrations VALUES(74,'2026_05_19_100000_add_diary_entry_id_to_time_entries',1);
INSERT INTO migrations VALUES(75,'2026_05_19_100001_extend_diary_entries_with_mode_and_location',1);
INSERT INTO migrations VALUES(76,'2026_05_19_100002_add_maintenance_flag_to_projects',1);
INSERT INTO migrations VALUES(77,'2026_05_19_120000_create_recurrence_rules_table',1);
INSERT INTO migrations VALUES(78,'2026_05_20_100000_add_is_new_system_to_users_table',1);
INSERT INTO migrations VALUES(79,'2026_05_21_120000_add_team_id_to_permission_tables',1);
INSERT INTO migrations VALUES(80,'2026_05_21_120100_create_user_groups_table',1);
INSERT INTO migrations VALUES(81,'2026_05_21_130000_create_flex_eligibilities_table',1);
INSERT INTO migrations VALUES(82,'2026_05_21_140000_create_rooms_table',1);
INSERT INTO migrations VALUES(83,'2026_05_21_140100_create_event_categories_table',1);
INSERT INTO migrations VALUES(84,'2026_05_21_140200_create_events_table',1);
INSERT INTO migrations VALUES(85,'2026_05_21_140300_create_event_room_table',1);
INSERT INTO migrations VALUES(86,'2026_05_21_140400_create_event_user_table',1);
INSERT INTO migrations VALUES(87,'2026_05_21_140500_create_event_reminders_table',1);
INSERT INTO migrations VALUES(88,'2026_05_21_150000_create_organization_audit_logs_and_deactivated_at',1);
INSERT INTO migrations VALUES(89,'2026_05_22_120000_create_expense_categories_table',1);
INSERT INTO migrations VALUES(90,'2026_05_22_120001_create_expenses_table',1);
INSERT INTO migrations VALUES(91,'2026_05_22_120002_add_expense_id_to_invoice_items_table',1);
INSERT INTO migrations VALUES(92,'2026_05_22_120100_create_per_diem_rates_table',1);
INSERT INTO migrations VALUES(93,'2026_05_22_120101_create_per_diem_trips_table',1);
INSERT INTO migrations VALUES(94,'2026_05_22_120102_create_per_diem_days_table',1);
INSERT INTO migrations VALUES(95,'2026_05_22_120103_add_region_label_to_per_diem_rates_table',1);
INSERT INTO migrations VALUES(96,'2026_05_23_120000_create_open_issues_table',1);
INSERT INTO migrations VALUES(97,'2026_05_23_120100_create_open_issue_events_table',1);
INSERT INTO migrations VALUES(98,'2026_05_24_120000_create_protocols_table',1);
INSERT INTO migrations VALUES(99,'2026_05_24_120100_create_protocol_items_table',1);
INSERT INTO migrations VALUES(100,'2026_05_24_120200_create_protocol_signatures_table',1);
INSERT INTO migrations VALUES(101,'2026_05_24_120300_create_protocol_events_table',1);
INSERT INTO migrations VALUES(102,'2026_05_24_120400_create_protocol_signature_tokens_table',1);
INSERT INTO migrations VALUES(103,'2026_05_24_120500_create_protocol_item_photos_table',1);
INSERT INTO migrations VALUES(104,'2026_05_25_000001_make_tasks_project_id_nullable',1);
INSERT INTO migrations VALUES(105,'2026_05_25_120000_create_automation_rules_tables',1);
INSERT INTO migrations VALUES(106,'2026_05_25_120000_extend_invoices_for_cancel_credit_mail',1);
INSERT INTO migrations VALUES(107,'2026_05_25_120100_create_invoice_mail_templates_table',1);
INSERT INTO migrations VALUES(108,'2026_05_25_130000_add_calendar_feed_token_to_users',1);
INSERT INTO migrations VALUES(109,'2026_05_25_140000_add_organization_id_to_attachments',1);
INSERT INTO migrations VALUES(110,'2026_05_25_140100_add_organization_id_to_tenant_child_tables',1);
INSERT INTO migrations VALUES(111,'2026_05_25_140200_add_organization_id_to_derived_tables',1);
INSERT INTO migrations VALUES(112,'2026_05_26_120000_create_procedure_templates_table',1);
INSERT INTO migrations VALUES(113,'2026_05_26_120100_create_procedure_template_versions_table',1);
INSERT INTO migrations VALUES(114,'2026_05_26_120200_create_procedure_step_defs_table',1);
INSERT INTO migrations VALUES(115,'2026_05_26_130000_create_time_correction_requests_table',1);
INSERT INTO migrations VALUES(116,'2026_05_26_130100_create_time_correction_items_table',1);
INSERT INTO migrations VALUES(117,'2026_05_26_135000_create_assets_table',1);
INSERT INTO migrations VALUES(118,'2026_05_26_140000_create_maintenance_plans_table',1);
INSERT INTO migrations VALUES(119,'2026_05_26_140100_create_maintenance_plan_templates_table',1);
INSERT INTO migrations VALUES(120,'2026_05_27_120000_create_sla_contracts_table',1);
INSERT INTO migrations VALUES(121,'2026_05_27_120100_create_service_tickets_table',1);
INSERT INTO migrations VALUES(122,'2026_05_28_120000_create_key_handovers_table',1);
INSERT INTO migrations VALUES(123,'2026_05_28_120100_create_meter_readings_table',1);
INSERT INTO migrations VALUES(124,'2026_05_29_120000_create_number_formats_table',1);
INSERT INTO migrations VALUES(125,'2026_05_29_120100_create_number_sequences_table',1);
INSERT INTO migrations VALUES(126,'2026_05_30_120000_add_customer_id_to_users_table',1);
INSERT INTO migrations VALUES(127,'2026_06_01_120000_add_shared_remote_to_assets',1);
INSERT INTO migrations VALUES(128,'2026_06_01_120100_add_asset_id_to_remote_pending_sessions',1);
INSERT INTO migrations VALUES(129,'2026_06_02_100000_add_lexoffice_article_fields',1);
INSERT INTO migrations VALUES(130,'2026_06_02_120000_create_procedure_runs_table',1);
INSERT INTO migrations VALUES(131,'2026_06_02_120100_create_procedure_step_runs_table',1);
INSERT INTO migrations VALUES(132,'2026_06_02_120200_create_procedure_run_events_table',1);
INSERT INTO migrations VALUES(133,'2026_06_02_140000_create_procedure_backup_proofs_table',1);
INSERT INTO migrations VALUES(134,'2026_06_02_150000_create_procedure_deviations_table',1);
INSERT INTO migrations VALUES(135,'2026_06_03_120000_create_classifications_table',1);
INSERT INTO migrations VALUES(136,'2026_06_03_130000_create_classification_requirements_table',1);
INSERT INTO migrations VALUES(137,'2026_06_03_150000_add_asset_links_to_diary_entries_and_material_usages',1);
INSERT INTO migrations VALUES(138,'2026_06_04_090000_add_planning_columns_to_diary_entries',1);
INSERT INTO migrations VALUES(139,'2026_06_04_120000_add_personnel_number_to_users_table',1);
INSERT INTO migrations VALUES(140,'2026_06_04_121000_add_payroll_fields_to_users_table',1);
INSERT INTO migrations VALUES(141,'2026_06_04_130000_create_teams_table',1);
INSERT INTO migrations VALUES(142,'2026_06_04_140000_create_project_team_and_member_tables',1);
INSERT INTO migrations VALUES(143,'2026_06_04_150000_add_start_date_to_tasks_table',1);
INSERT INTO migrations VALUES(144,'2026_06_04_160000_create_onboarding_progress_table',1);
INSERT INTO migrations VALUES(145,'2026_06_04_160000_create_task_user_table',1);
INSERT INTO migrations VALUES(146,'2026_06_04_170000_create_minimum_wages_table',1);
INSERT INTO migrations VALUES(147,'2026_06_04_171000_add_employment_type_to_users_table',1);
INSERT INTO migrations VALUES(148,'2026_06_04_180000_create_minimum_wage_references_table',1);
INSERT INTO migrations VALUES(149,'2026_06_05_120000_create_help_topics_table',1);
INSERT INTO migrations VALUES(150,'2026_06_05_120100_create_help_views_table',1);
INSERT INTO migrations VALUES(151,'2026_06_06_120000_add_demo_columns_to_organizations',1);
INSERT INTO migrations VALUES(152,'2026_06_07_120000_add_two_factor_columns',1);
INSERT INTO migrations VALUES(153,'2026_06_07_130000_widen_encrypted_pii_columns',1);
INSERT INTO migrations VALUES(154,'2026_06_08_120000_widen_address_zip_city_columns',1);
INSERT INTO migrations VALUES(155,'2026_06_10_120000_create_two_factor_credentials_table',1);
INSERT INTO migrations VALUES(156,'2026_06_10_120000_create_user_bookmarks_table',1);
INSERT INTO migrations VALUES(157,'2026_06_10_130000_create_communication_notes_table',1);
INSERT INTO migrations VALUES(158,'2026_06_10_130000_create_feature_usage_counters_table',1);
INSERT INTO migrations VALUES(159,'2026_06_10_130100_create_communication_note_participants_table',1);
INSERT INTO migrations VALUES(160,'2026_06_10_140000_create_documents_table',1);
INSERT INTO migrations VALUES(161,'2026_06_10_140100_create_document_versions_table',1);
INSERT INTO migrations VALUES(162,'2026_06_10_150000_create_notifications_table',1);
INSERT INTO migrations VALUES(163,'2026_06_10_150100_create_notification_rules_table',1);
INSERT INTO migrations VALUES(164,'2026_06_10_150200_create_notification_dispatch_log_table',1);
INSERT INTO migrations VALUES(165,'2026_06_10_160000_create_surcharge_rules_table',1);
INSERT INTO migrations VALUES(166,'2026_06_10_170000_create_knowledge_articles_table',1);
INSERT INTO migrations VALUES(167,'2026_06_10_170100_create_knowledge_article_links_table',1);
INSERT INTO migrations VALUES(168,'2026_06_10_170200_create_knowledge_article_feedback_table',1);
INSERT INTO migrations VALUES(169,'2026_06_10_180000_create_form_templates_table',1);
INSERT INTO migrations VALUES(170,'2026_06_10_180100_create_form_submissions_table',1);
INSERT INTO migrations VALUES(171,'2026_06_10_190000_create_isms_risks_table',1);
INSERT INTO migrations VALUES(172,'2026_06_10_190100_create_isms_controls_table',1);
INSERT INTO migrations VALUES(173,'2026_06_10_190200_create_isms_control_risk_table',1);
INSERT INTO migrations VALUES(174,'2026_06_10_200000_add_billing_mode_to_customers_table',1);
INSERT INTO migrations VALUES(175,'2026_06_10_200100_create_billing_transfers_table',1);
INSERT INTO migrations VALUES(176,'2026_06_10_200200_create_billing_transfer_items_table',1);
INSERT INTO migrations VALUES(177,'2026_06_10_200300_create_billing_transfer_events_table',1);
INSERT INTO migrations VALUES(178,'2026_06_11_090000_create_isms_scopes_table',1);
INSERT INTO migrations VALUES(179,'2026_06_11_090100_create_isms_requirements_table',1);
INSERT INTO migrations VALUES(180,'2026_06_11_090200_create_isms_control_requirement_table',1);
INSERT INTO migrations VALUES(181,'2026_06_11_090300_create_isms_applicability_statements_table',1);
INSERT INTO migrations VALUES(182,'2026_06_11_090400_add_scope_to_isms_risks_table',1);
INSERT INTO migrations VALUES(183,'2026_06_11_090500_migrate_isms_controls_to_requirements',1);
INSERT INTO migrations VALUES(184,'2026_06_11_090600_drop_soa_columns_from_isms_controls',1);
INSERT INTO migrations VALUES(185,'2026_06_11_100000_create_isms_software_products_table',1);
INSERT INTO migrations VALUES(186,'2026_06_11_100100_create_isms_software_installations_table',1);
INSERT INTO migrations VALUES(187,'2026_06_11_120000_create_isms_norm_statuses_table',1);
INSERT INTO migrations VALUES(188,'2026_06_11_120100_create_isms_certificates_table',1);
INSERT INTO migrations VALUES(189,'2026_06_11_130000_create_isms_audits_table',1);
INSERT INTO migrations VALUES(190,'2026_06_11_130100_create_isms_audit_findings_table',1);
INSERT INTO migrations VALUES(191,'2026_06_11_130200_create_isms_corrective_actions_table',1);
INSERT INTO migrations VALUES(192,'2026_06_11_130300_create_isms_management_reviews_table',1);
INSERT INTO migrations VALUES(193,'2026_06_11_140000_create_isms_risk_assessments_table',1);
INSERT INTO migrations VALUES(194,'2026_06_11_150000_create_isms_audit_packages_table',1);
INSERT INTO migrations VALUES(195,'2026_06_11_150100_create_isms_audit_package_tokens_table',1);
INSERT INTO migrations VALUES(196,'2026_06_12_100000_add_buyer_reference_to_customers_table',1);
INSERT INTO migrations VALUES(197,'2026_06_12_110000_create_day_closures_table',1);
INSERT INTO migrations VALUES(198,'2026_06_12_110100_create_day_correction_requests_table',1);
INSERT INTO migrations VALUES(199,'2026_06_12_120000_create_bank_accounts_table',1);
INSERT INTO migrations VALUES(200,'2026_06_12_120100_create_bank_statements_table',1);
INSERT INTO migrations VALUES(201,'2026_06_12_120200_create_bank_transactions_table',1);
INSERT INTO migrations VALUES(202,'2026_06_12_120300_create_payment_allocations_table',1);
INSERT INTO migrations VALUES(203,'2026_06_12_120400_create_payment_reconciliation_events_table',1);
INSERT INTO migrations VALUES(204,'2026_06_14_100000_create_datev_booking_batches_table',1);
INSERT INTO migrations VALUES(205,'2026_06_14_100100_create_datev_booking_sources_table',1);
INSERT INTO migrations VALUES(206,'2026_06_14_100200_create_datev_booking_events_table',1);
INSERT INTO migrations VALUES(207,'2026_06_14_100300_add_debtor_no_to_customers_table',1);
INSERT INTO migrations VALUES(208,'2026_06_14_110000_create_isms_security_incidents_table',1);
INSERT INTO migrations VALUES(209,'2026_06_14_110100_create_isms_incident_risk_table',1);
INSERT INTO migrations VALUES(210,'2026_06_14_110200_create_isms_incident_control_table',1);
INSERT INTO migrations VALUES(211,'2026_06_14_110300_create_isms_advisories_table',1);
INSERT INTO migrations VALUES(212,'2026_06_14_110400_create_isms_vulnerabilities_table',1);
INSERT INTO migrations VALUES(213,'2026_06_14_120000_create_restore_tests_table',1);
INSERT INTO migrations VALUES(214,'2026_06_14_130000_create_sla_violations_table',1);
INSERT INTO migrations VALUES(215,'2026_06_14_140000_create_asset_assignments_table',1);
INSERT INTO migrations VALUES(216,'2026_06_14_140100_create_asset_defects_table',1);
INSERT INTO migrations VALUES(217,'2026_06_14_170000_create_safety_events_table',1);
INSERT INTO migrations VALUES(218,'2026_06_14_200000_create_webhook_endpoints_table',1);
INSERT INTO migrations VALUES(219,'2026_06_14_200100_create_webhook_deliveries_table',1);
INSERT INTO migrations VALUES(220,'2026_06_14_210000_add_dispatch_status_to_diary_entries',1);
INSERT INTO migrations VALUES(221,'2026_06_14_210100_create_vehicle_reservations_table',1);
INSERT INTO migrations VALUES(222,'2026_06_14_240000_create_availability_windows_table',1);
INSERT INTO migrations VALUES(223,'2026_06_14_240100_create_desired_shifts_table',1);
INSERT INTO migrations VALUES(224,'2026_06_14_240200_create_shift_exchanges_table',1);
INSERT INTO migrations VALUES(225,'2026_06_14_255000_create_suppliers_table',1);
INSERT INTO migrations VALUES(226,'2026_06_14_260000_create_isms_supplier_assessments_table',1);
INSERT INTO migrations VALUES(227,'2026_06_14_270000_create_room_requirements_table',1);
INSERT INTO migrations VALUES(228,'2026_06_14_280000_create_external_participants_table',1);
INSERT INTO migrations VALUES(229,'2026_06_14_280100_add_external_author_to_comments_table',1);
INSERT INTO migrations VALUES(230,'2026_06_14_300000_create_report_targets_table',1);
INSERT INTO migrations VALUES(231,'2026_06_15_290000_create_room_requirement_templates_table',1);
INSERT INTO migrations VALUES(232,'2026_06_15_310000_add_tenant_status_to_organizations',1);
INSERT INTO migrations VALUES(233,'2026_06_15_320000_create_customer_queries_and_token_decisions',1);
INSERT INTO migrations VALUES(234,'2026_06_16_120000_create_openproject_pending_entries_table',1);
INSERT INTO migrations VALUES(235,'2026_06_16_130000_create_articles_table',1);
INSERT INTO migrations VALUES(236,'2026_06_16_130100_create_article_option_definitions_table',1);
INSERT INTO migrations VALUES(237,'2026_06_16_130200_create_article_option_values_table',1);
INSERT INTO migrations VALUES(238,'2026_06_16_130300_create_article_variants_table',1);
INSERT INTO migrations VALUES(239,'2026_06_16_130400_create_article_variant_option_values_table',1);
INSERT INTO migrations VALUES(240,'2026_06_16_130500_create_article_units_table',1);
INSERT INTO migrations VALUES(241,'2026_06_16_130600_create_external_article_mappings_table',1);
INSERT INTO migrations VALUES(242,'2026_06_16_140000_create_warehouses_table',1);
INSERT INTO migrations VALUES(243,'2026_06_16_140100_create_stock_movements_table',1);
INSERT INTO migrations VALUES(244,'2026_06_16_150000_create_stock_reservations_table',1);
INSERT INTO migrations VALUES(245,'2026_06_16_150100_create_stock_level_settings_table',1);
INSERT INTO migrations VALUES(246,'2026_06_16_160000_create_stock_counts_table',1);
INSERT INTO migrations VALUES(247,'2026_06_16_160100_create_stock_count_lines_table',1);
INSERT INTO migrations VALUES(248,'2026_06_16_170000_create_stock_valuations_table',1);
INSERT INTO migrations VALUES(249,'2026_06_16_180000_create_procedure_material_requirements_table',1);
INSERT INTO migrations VALUES(250,'2026_06_16_180100_create_manufacturing_orders_table',1);
INSERT INTO migrations VALUES(251,'2026_06_16_180200_create_manufacturing_order_materials_table',1);
INSERT INTO migrations VALUES(252,'2026_06_16_180300_create_article_variant_bom_overrides_table',1);
INSERT INTO migrations VALUES(253,'2026_06_16_190000_create_manufacturing_order_reports_table',1);
INSERT INTO migrations VALUES(254,'2026_06_16_190100_create_stock_deliveries_table',1);
INSERT INTO migrations VALUES(255,'2026_06_16_200000_create_material_substitutes_table',1);
INSERT INTO migrations VALUES(256,'2026_06_16_200100_create_procurement_requests_table',1);
INSERT INTO migrations VALUES(257,'2026_06_16_210000_add_wait_until_to_procedure_step_runs',1);
INSERT INTO migrations VALUES(258,'2026_06_16_220000_create_stock_serials_table',1);
INSERT INTO migrations VALUES(259,'2026_06_16_230000_create_inventory_outbox_table',1);
INSERT INTO migrations VALUES(260,'2026_06_16_240000_create_stock_valuation_layers_table',1);
INSERT INTO migrations VALUES(261,'2026_06_16_250000_create_stock_lots_table',1);
INSERT INTO migrations VALUES(262,'2026_06_16_250100_add_lot_to_stock_valuation_layers',1);
INSERT INTO migrations VALUES(263,'2026_06_17_120000_create_user_dashboard_widgets_table',1);
INSERT INTO migrations VALUES(264,'2026_06_19_100000_add_lot_serial_to_stock_movements',1);
INSERT INTO migrations VALUES(265,'2026_06_19_100100_add_valuation_and_serial_scheme_to_articles',1);
INSERT INTO migrations VALUES(266,'2026_06_19_110000_create_article_supplies_table',1);
INSERT INTO migrations VALUES(267,'2026_06_19_110100_create_purchase_orders_table',1);
INSERT INTO migrations VALUES(268,'2026_06_19_110200_create_purchase_order_lines_table',1);
INSERT INTO migrations VALUES(269,'2026_06_19_120000_add_count_type_to_stock_counts',1);
INSERT INTO migrations VALUES(270,'2026_06_19_130000_add_stock_lot_to_manufacturing_order_reports',1);
INSERT INTO migrations VALUES(271,'2026_06_19_130100_add_subcontract_to_manufacturing_orders',1);
INSERT INTO migrations VALUES(272,'2026_06_19_140000_create_work_centers_table',1);
INSERT INTO migrations VALUES(273,'2026_06_19_140100_add_work_center_to_manufacturing_orders',1);
INSERT INTO migrations VALUES(274,'2026_06_19_150000_add_actual_cost_to_manufacturing_order_materials',1);
INSERT INTO migrations VALUES(275,'2026_06_19_160000_add_manufacturing_order_to_time_entries',1);
INSERT INTO migrations VALUES(276,'2026_06_19_170000_create_purchase_order_advices_table',1);
INSERT INTO migrations VALUES(277,'2026_06_19_180000_create_label_templates_table',1);
INSERT INTO migrations VALUES(278,'2026_06_20_120000_create_user_filter_presets_table',1);
INSERT INTO migrations VALUES(279,'2026_06_22_120000_create_permits_table',1);
INSERT INTO migrations VALUES(280,'2026_06_25_120000_create_invoice_templates_table',1);
INSERT INTO migrations VALUES(281,'2026_06_26_090000_create_procedure_parameter_definitions_table',1);
INSERT INTO migrations VALUES(282,'2026_06_26_120000_create_supplier_catalog_tables',1);
INSERT INTO migrations VALUES(283,'2026_06_26_130000_create_pricing_margin_rules_table',1);
INSERT INTO migrations VALUES(284,'2026_06_26_140000_create_pricing_change_alerts_table',1);
INSERT INTO migrations VALUES(285,'2026_06_27_100000_add_remote_fields_to_supplier_catalog_sources',1);
INSERT INTO migrations VALUES(286,'2026_06_27_110000_add_schedule_to_supplier_catalog_sources',1);
INSERT INTO migrations VALUES(287,'2026_06_27_110100_create_supplier_catalog_imports_table',1);
INSERT INTO migrations VALUES(288,'2026_06_27_120000_add_classification_media_to_supplier_catalog_items',1);
INSERT INTO migrations VALUES(289,'2026_06_27_130000_create_supplier_catalog_item_price_tiers_table',1);
INSERT INTO migrations VALUES(290,'2026_06_28_100000_add_freight_and_line_note_to_purchase_orders',1);
INSERT INTO migrations VALUES(291,'2026_06_28_120000_create_gaeb_boq_tables',1);
INSERT INTO migrations VALUES(292,'2026_06_28_130000_create_boq_progress_and_mappings_tables',1);
INSERT INTO migrations VALUES(293,'2026_06_28_140000_create_boq_exports_table',1);
INSERT INTO migrations VALUES(294,'2026_07_01_120000_create_month_closures_table',1);
INSERT INTO migrations VALUES(295,'2026_07_01_120100_create_month_closure_events_table',1);
INSERT INTO migrations VALUES(296,'2026_07_01_140000_create_time_exports_table',1);
INSERT INTO migrations VALUES(297,'2026_07_01_140100_create_time_export_lines_table',1);
INSERT INTO migrations VALUES(298,'2026_07_01_140200_create_time_export_events_table',1);
INSERT INTO migrations VALUES(299,'2026_07_01_150000_add_surcharge_columns_to_time_export_lines',1);
INSERT INTO migrations VALUES(300,'2026_07_02_120000_create_backup_heartbeats_table',1);
INSERT INTO migrations VALUES(301,'2026_07_03_120000_add_type_and_impacts_to_pricing_change_alerts',1);
INSERT INTO migrations VALUES(302,'2026_07_07_100000_add_cti_extension_to_users_table',1);
INSERT INTO migrations VALUES(303,'2026_07_07_110000_add_weather_auto_fetch_to_projects',1);
INSERT INTO migrations VALUES(304,'2026_07_07_120000_add_break_started_at_to_attendances',1);
INSERT INTO migrations VALUES(305,'2026_07_10_120000_create_import_runs_table',1);
INSERT INTO migrations VALUES(306,'2026_07_10_120100_create_import_run_errors_table',1);
INSERT INTO migrations VALUES(307,'2026_07_11_120000_add_bank_columns_to_customers_table',1);
INSERT INTO migrations VALUES(308,'2026_07_11_120100_create_pending_external_conflicts_table',1);
INSERT INTO migrations VALUES(309,'2026_07_11_120200_add_version_to_lexoffice_articles_table',1);
INSERT INTO migrations VALUES(310,'2026_07_11_120300_create_plugin_settings_table',1);
INSERT INTO migrations VALUES(311,'2026_07_12_120000_create_plugin_states_table',1);
INSERT INTO migrations VALUES(312,'2026_07_12_120100_create_plugin_errors_table',1);
INSERT INTO migrations VALUES(313,'2026_07_13_120000_create_license_flag_overrides_table',1);
INSERT INTO migrations VALUES(314,'2026_07_14_120000_create_sites_table',1);
INSERT INTO migrations VALUES(315,'2026_07_14_120100_create_buildings_table',1);
INSERT INTO migrations VALUES(316,'2026_07_14_120200_create_floors_table',1);
INSERT INTO migrations VALUES(317,'2026_07_14_120300_extend_rooms_for_hierarchy',1);
INSERT INTO migrations VALUES(318,'2026_07_14_120400_add_room_id_to_assets_table',1);
INSERT INTO migrations VALUES(319,'2026_07_14_130000_make_maintenance_plans_polymorph',1);
INSERT INTO migrations VALUES(320,'2026_07_14_140000_create_cleaning_profiles_table',1);
INSERT INTO migrations VALUES(321,'2026_07_14_140100_add_cleaning_profile_id_to_rooms',1);
INSERT INTO migrations VALUES(322,'2026_07_14_150000_migrate_legacy_room_facility_strings',1);
INSERT INTO migrations VALUES(323,'2026_07_15_120000_create_software_table',1);
INSERT INTO migrations VALUES(324,'2026_07_15_120100_create_software_installations_table',1);
INSERT INTO migrations VALUES(325,'2026_07_15_120200_create_remote_pending_sessions_table',1);
INSERT INTO migrations VALUES(326,'2026_07_15_120300_create_toggl_pending_entries_table',1);
INSERT INTO migrations VALUES(327,'2026_08_01_120000_create_export_runs_table',1);
INSERT INTO migrations VALUES(328,'2026_08_01_120100_add_alias_to_remote_pending_sessions',1);
INSERT INTO migrations VALUES(329,'2026_08_02_120000_add_shared_remote_to_assets',1);
INSERT INTO migrations VALUES(330,'2026_08_02_120100_add_asset_id_to_remote_pending_sessions',1);
INSERT INTO migrations VALUES(331,'2026_08_05_130000_create_lexoffice_vouchers_table',1);
INSERT INTO migrations VALUES(332,'2026_08_06_120000_create_contact_addresses_table',1);
INSERT INTO migrations VALUES(333,'2026_08_06_120100_create_contact_bank_accounts_table',1);
INSERT INTO migrations VALUES(334,'2026_08_06_120200_add_lexoffice_fields_to_contacts',1);
INSERT INTO migrations VALUES(335,'2026_08_06_120300_add_source_to_number_formats',1);
INSERT INTO migrations VALUES(336,'2026_08_06_120400_add_external_number_to_invoices',1);
INSERT INTO migrations VALUES(337,'2026_08_07_120000_create_foreign_customers_table',1);
INSERT INTO migrations VALUES(338,'2026_08_07_120100_add_foreign_customer_id_to_projects_table',1);
INSERT INTO migrations VALUES(339,'2026_08_07_120200_add_foreign_customer_id_to_invoices_table',1);
INSERT INTO migrations VALUES(340,'2026_08_08_120000_add_billing_increment_to_projects_table',1);
INSERT INTO migrations VALUES(341,'2026_08_08_120100_add_billing_increment_to_customers_table',1);
INSERT INTO migrations VALUES(342,'2026_08_08_120200_create_invoice_item_time_entries_table',1);
INSERT INTO migrations VALUES(343,'2026_08_08_120300_add_service_date_to_invoice_items_table',1);
INSERT INTO migrations VALUES(344,'2026_08_08_120400_add_category_to_invoices_table',1);
INSERT INTO migrations VALUES(345,'2026_08_08_120500_add_material_billing_fields',1);
INSERT INTO migrations VALUES(346,'2026_08_08_120600_add_travel_billing_fields',1);
INSERT INTO migrations VALUES(347,'2026_08_08_120700_add_foreign_customer_id_to_assets_table',1);
INSERT INTO migrations VALUES(348,'2026_08_09_100000_add_material_fields_to_billing_transfer_items',1);
INSERT INTO migrations VALUES(349,'2026_08_09_120000_add_personal_fields_to_users_table',1);
INSERT INTO migrations VALUES(350,'2026_08_10_120000_add_schedule_type_to_work_schedules',1);
INSERT INTO migrations VALUES(351,'2026_08_11_120000_widen_time_entry_descriptions_to_text',1);
INSERT INTO migrations VALUES(352,'2026_08_11_120100_add_organization_to_plugin_state',1);
INSERT INTO migrations VALUES(353,'2026_08_11_120200_add_last_ok_at_to_plugin_states',1);
INSERT INTO migrations VALUES(354,'2026_08_12_000000_add_compensation_model_to_users',1);
INSERT INTO migrations VALUES(355,'2026_08_13_000001_create_chat_channels_table',1);
INSERT INTO migrations VALUES(356,'2026_08_13_000002_create_chat_messages_table',1);
INSERT INTO migrations VALUES(357,'2026_08_13_000003_create_chat_polls_table',1);
INSERT INTO migrations VALUES(358,'2026_08_13_000004_create_password_reset_tokens_table',1);
INSERT INTO migrations VALUES(359,'2026_08_13_000005_add_quote_forward_to_chat_messages',1);
INSERT INTO migrations VALUES(360,'2026_08_13_000006_create_chat_message_stars_table',1);
INSERT INTO migrations VALUES(361,'2026_08_13_000007_create_chat_reminders_table',1);
INSERT INTO migrations VALUES(362,'2026_08_13_000008_create_chat_scheduled_messages_table',1);
INSERT INTO migrations VALUES(363,'2026_08_13_000009_add_preferred_work_mode_to_users_table',1);
INSERT INTO migrations VALUES(364,'2026_08_13_000010_add_hash_chain_to_audit_logs_table',1);
INSERT INTO migrations VALUES(365,'2026_08_13_000012_add_hash_chain_to_org_audit_and_chain_heads',1);
INSERT INTO migrations VALUES(366,'2026_08_13_000013_move_preferred_work_mode_into_preferences',1);
INSERT INTO migrations VALUES(367,'2026_08_13_000014_create_whistleblowing_tables',1);
INSERT INTO migrations VALUES(368,'2026_08_13_000015_create_whistleblowing_access_and_reminder_tables',1);
INSERT INTO migrations VALUES(369,'2026_08_13_000016_create_whistleblowing_case_subjects_table',1);
INSERT INTO migrations VALUES(370,'2026_08_13_000017_make_whistleblowing_case_content_nullable',1);
INSERT INTO migrations VALUES(371,'2026_08_13_000018_grandfather_existing_orgs_to_enterprise',1);
INSERT INTO migrations VALUES(372,'2026_08_13_000019_create_plan_module_grace_table',1);
INSERT INTO migrations VALUES(373,'2026_08_13_000020_add_self_applied_to_time_correction_requests',1);
INSERT INTO migrations VALUES(374,'2026_08_13_000021_create_privacy_mvp1_tables',1);
INSERT INTO migrations VALUES(375,'2026_08_13_000022_create_privacy_avv_tables',1);
INSERT INTO migrations VALUES(376,'2026_08_13_000023_create_privacy_mvp3_tables',1);
INSERT INTO migrations VALUES(377,'2026_08_13_000024_create_privacy_tom_tables',1);
INSERT INTO migrations VALUES(378,'2026_08_13_000025_create_privacy_gvv_tables',1);
INSERT INTO migrations VALUES(379,'2026_08_13_000026_create_privacy_compliance_findings_table',1);
INSERT INTO migrations VALUES(380,'2026_08_13_000027_create_privacy_attachments_table',1);
INSERT INTO migrations VALUES(381,'2026_08_13_000028_add_license_columns_to_organizations',1);
INSERT INTO migrations VALUES(382,'2026_08_13_000029_add_processor_dimension_to_incidents',1);
INSERT INTO migrations VALUES(383,'2026_08_13_000030_add_authority_reporting_to_incidents',1);
INSERT INTO migrations VALUES(384,'2026_08_14_000000_add_order_lifecycle_to_diary_entries',1);
INSERT INTO migrations VALUES(385,'2026_08_15_000000_add_org_scoped_composite_indexes',1);
INSERT INTO migrations VALUES(386,'2026_08_20_120000_create_customer_merge_dismissals_table',1);
INSERT INTO migrations VALUES(387,'2026_08_21_120000_create_customer_geofences_table',1);
INSERT INTO migrations VALUES(388,'2026_08_21_120000_create_integration_inbox_items_table',1);
INSERT INTO migrations VALUES(389,'2026_08_21_120100_create_location_points_table',1);
INSERT INTO migrations VALUES(390,'2026_08_21_120200_create_location_visits_table',1);
INSERT INTO migrations VALUES(391,'2026_08_21_120300_create_location_pending_entries_table',1);
INSERT INTO migrations VALUES(392,'2026_08_21_120400_create_location_device_tokens_table',1);
INSERT INTO migrations VALUES(393,'2026_08_21_120500_encrypt_location_points_coordinates',1);
INSERT INTO migrations VALUES(394,'2026_08_22_120000_backfill_lexoffice_conflicts_to_inbox',1);
INSERT INTO migrations VALUES(395,'2026_08_22_120000_create_project_merge_dismissals_table',1);
INSERT INTO migrations VALUES(396,'2026_08_23_120000_add_group_key_to_integration_inbox_items',1);
INSERT INTO migrations VALUES(397,'2026_08_24_120000_backfill_toggl_pending_to_inbox',1);
INSERT INTO migrations VALUES(398,'2026_08_25_120000_backfill_openproject_pending_to_inbox',1);
INSERT INTO migrations VALUES(399,'2026_08_26_120000_add_match_policy_to_import_runs',1);
INSERT INTO migrations VALUES(400,'2026_08_27_120000_create_external_reference_aliases_table',1);
INSERT INTO migrations VALUES(401,'2026_08_28_120000_convert_material_rounding_to_enum',1);
INSERT INTO migrations VALUES(402,'2026_09_01_120000_drop_legacy_pending_entry_tables',1);
INSERT INTO migrations VALUES(403,'2026_09_01_130000_create_price_change_requests_table',1);
INSERT INTO migrations VALUES(404,'2026_09_01_140000_add_punchout_fields_to_supplier_catalog_sources',1);
INSERT INTO migrations VALUES(405,'2026_09_02_100000_create_idea_maps_tables',1);
INSERT INTO migrations VALUES(406,'2026_09_03_100000_create_todoist_connections_table',1);
INSERT INTO migrations VALUES(407,'2026_09_03_110000_create_todoist_link_tables',1);
INSERT INTO migrations VALUES(408,'2026_09_03_120000_create_integration_outbox_table',1);
INSERT INTO migrations VALUES(409,'2026_09_03_140000_create_todoist_webhook_deliveries_table',1);
INSERT INTO migrations VALUES(410,'2026_09_04_100000_add_lock_version_to_idea_maps',1);
INSERT INTO migrations VALUES(411,'2026_09_05_100000_create_idea_node_links_table',1);
INSERT INTO migrations VALUES(412,'2026_09_06_100000_create_idea_node_summaries_table',1);
INSERT INTO migrations VALUES(413,'2026_09_07_100000_create_gobd_exports_table',1);
INSERT INTO migrations VALUES(414,'2026_09_08_100000_create_weather_snapshots_table',1);
INSERT INTO migrations VALUES(415,'2026_09_08_110000_add_weather_snapshot_to_protocols',1);
INSERT INTO migrations VALUES(416,'2026_09_09_100000_create_zammad_connections_table',1);
INSERT INTO migrations VALUES(417,'2026_09_11_100000_create_caldav_connections_table',1);
INSERT INTO migrations VALUES(418,'2026_09_12_100000_create_webdav_connections_table',1);
INSERT INTO migrations VALUES(419,'2026_09_13_100000_add_deactivated_at_to_users_table',1);
INSERT INTO migrations VALUES(420,'2026_09_13_100100_create_scim_tokens_table',1);
INSERT INTO migrations VALUES(421,'2026_09_14_100000_create_email_connections_table',1);
INSERT INTO migrations VALUES(422,'2026_09_15_100000_create_cti_connections_table',1);
INSERT INTO migrations VALUES(423,'2026_09_16_100000_create_chat_webhooks_table',1);
INSERT INTO migrations VALUES(424,'2026_09_17_100000_create_attendance_terminals_table',1);
INSERT INTO migrations VALUES(425,'2026_09_17_100100_create_user_badges_table',1);
INSERT INTO migrations VALUES(426,'2026_09_18_100000_create_carrier_connections_table',1);
INSERT INTO migrations VALUES(427,'2026_09_18_100100_create_shipments_table',1);
INSERT INTO migrations VALUES(428,'2026_09_19_100000_add_resolved_state_to_zammad_connections',1);
INSERT INTO migrations VALUES(429,'2026_09_20_100000_create_scim_groups_table',1);
INSERT INTO migrations VALUES(430,'2026_09_21_100000_add_diary_entry_id_to_service_tickets',1);
INSERT INTO migrations VALUES(431,'2026_09_22_100000_add_sla_binding_to_maintenance_plans',1);
INSERT INTO migrations VALUES(432,'2026_09_23_100000_create_sla_contract_quotas_table',1);
INSERT INTO migrations VALUES(433,'2026_09_24_100000_add_sla_contract_id_to_assets',1);
INSERT INTO migrations VALUES(434,'2026_09_25_100000_create_asset_ownership_changes_table',1);
INSERT INTO migrations VALUES(435,'2026_09_26_100000_add_scopes_to_caldav_connections',1);
INSERT INTO migrations VALUES(436,'2026_09_27_100000_add_webdav_mirror_detached_to_documents',1);
INSERT INTO migrations VALUES(437,'2026_09_28_100000_add_sources_to_webdav_connections',1);
INSERT INTO migrations VALUES(438,'2026_09_29_100000_add_time_unit_to_zammad_connections',1);
INSERT INTO migrations VALUES(439,'2026_09_30_100000_create_external_contacts_table',1);
INSERT INTO migrations VALUES(440,'2026_09_30_100100_add_external_contact_id_to_external_participants_table',1);
INSERT INTO migrations VALUES(441,'2026_10_01_100000_add_tax_split_to_surcharge_rules',1);
INSERT INTO migrations VALUES(442,'2026_10_01_100100_create_cost_center_rules_table',1);
INSERT INTO migrations VALUES(443,'2026_10_01_100200_add_customer_visible_to_attachments',1);
INSERT INTO migrations VALUES(444,'2026_10_01_100300_create_attachment_confirmations_table',1);
INSERT INTO migrations VALUES(445,'2026_10_01_100400_create_diary_entry_qualifications_table',1);
INSERT INTO migrations VALUES(446,'2026_10_01_100500_create_import_value_mappings_table',1);
INSERT INTO migrations VALUES(447,'2026_10_01_100600_add_unresolved_values_to_import_runs',1);
INSERT INTO migrations VALUES(448,'2026_10_01_100700_add_rework_goodwill_to_time_entries',1);
INSERT INTO migrations VALUES(449,'2026_10_01_100800_create_support_access_grants_table',1);
INSERT INTO migrations VALUES(450,'2026_10_01_100900_create_security_advisories_table',1);
INSERT INTO migrations VALUES(451,'2026_10_01_101000_privacy_followup_tables',1);
INSERT INTO migrations VALUES(452,'2026_10_01_101100_add_description_to_isms_requirements',1);
INSERT INTO migrations VALUES(453,'2026_10_01_101200_create_isms_audit_programs_table',1);
INSERT INTO migrations VALUES(454,'2026_10_01_101300_add_profile_version_to_isms_norm_statuses',1);
INSERT INTO migrations VALUES(455,'2026_10_01_101400_create_retention_proposals_table',1);
INSERT INTO migrations VALUES(456,'2026_10_01_101500_add_reverse_charge_to_invoices',1);
INSERT INTO migrations VALUES(457,'2026_10_01_101600_create_isms_assessment_snapshots_table',1);
INSERT INTO migrations VALUES(458,'2026_10_01_101700_create_agile_core_tables',1);
INSERT INTO migrations VALUES(459,'2026_10_01_101800_create_agile_backlog_tables',1);
INSERT INTO migrations VALUES(460,'2026_10_01_101900_create_agile_sprint_tables',1);
INSERT INTO migrations VALUES(461,'2026_10_01_102000_add_capacity_snapshot_to_agile_sprints',1);
INSERT INTO migrations VALUES(462,'2026_10_01_102100_create_service_queues_table',1);
INSERT INTO migrations VALUES(463,'2026_10_01_102200_extend_service_tickets_for_helpdesk',1);
INSERT INTO migrations VALUES(464,'2026_10_01_102300_create_service_ticket_messages_table',1);
INSERT INTO migrations VALUES(465,'2026_10_01_102400_create_ticket_routing_rules_table',1);
INSERT INTO migrations VALUES(466,'2026_10_01_102500_create_service_catalog_tables',1);
INSERT INTO migrations VALUES(467,'2026_10_01_102600_add_major_incident_and_links',1);
INSERT INTO migrations VALUES(468,'2026_10_01_102700_create_problems_table',1);
INSERT INTO migrations VALUES(469,'2026_10_01_102800_create_changes_and_approvals_tables',1);
INSERT INTO migrations VALUES(470,'2026_10_01_102900_add_ticket_target_to_zammad_connections',1);
INSERT INTO migrations VALUES(471,'2026_10_01_103000_create_ticket_satisfaction_table',1);
INSERT INTO migrations VALUES(472,'2026_10_01_103100_extend_invoices_for_tax_model',1);
INSERT INTO migrations VALUES(473,'2026_10_01_103200_add_workflow_fields_to_invoices',1);
INSERT INTO migrations VALUES(474,'2026_10_01_103300_create_incoming_einvoices_table',1);
INSERT INTO migrations VALUES(475,'2026_10_01_103400_create_quotes_and_proforma',1);
INSERT INTO migrations VALUES(476,'2026_10_01_110000_create_system_settings_table',1);
INSERT INTO migrations VALUES(477,'2026_10_01_110100_create_scheduler_tables',1);
INSERT INTO migrations VALUES(478,'2026_10_01_110200_create_operations_tasks_table',1);
INSERT INTO migrations VALUES(479,'2026_10_01_110300_create_problem_reports_table',1);
INSERT INTO migrations VALUES(480,'2026_10_01_110400_create_component_updates_table',1);
INSERT INTO migrations VALUES(481,'2026_10_01_110500_create_maintenance_windows_table',1);
INSERT INTO migrations VALUES(482,'2026_10_01_110600_add_connection_health_columns',1);
