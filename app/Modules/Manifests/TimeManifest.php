<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Zeiterfassung, Stundenzettel, Zeitkonten, Anwesenheit“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class TimeManifest extends Manifest {
    public function code(): string {
        return 'time';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Zeiterfassung, Stundenzettel, Zeitkonten, Anwesenheit';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Time',
            'Attendance',
            'Timekeeping',
            'Timesheet',
            'TimeAccount',
            'TimeApproval',
            'TimeExport',
            'Flextime',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'attendance_checkpoints',
            'attendance_terminals',
            'attendances',
            'flex_balances',
            'flex_eligibilities',
            'minimum_wage_references',
            'minimum_wages',
            'month_closure_events',
            'month_closures',
            'overtime_requests',
            'time_account_balances',
            'time_account_entries',
            'time_account_rules',
            'time_accounts',
            'time_allocations',
            'time_correction_items',
            'time_correction_requests',
            'time_dimension_types',
            'time_dimension_values',
            'time_entries',
            'time_export_delivery_configs',
            'time_export_events',
            'time_export_lines',
            'time_exports',
            'time_rule_results',
            'timesheets',
            'work_schedules',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::TimeEntries,
            PermissionGroup::Timesheets,
            PermissionGroup::WorkingTime,
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'toggl',
            'kimai',
            'clockify',
            'github',
            'gitlab',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Attendance\Import\AttendanceSpec::class,
                \App\Services\Timekeeping\Import\ProjectTimeSpec::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Timekeeping\Search\TimeEntrySource::class,
                \App\Services\Timesheet\Search\TimesheetSource::class,
            ],
            \App\Services\Sync\Contracts\SyncCommandHandler::class => [
                \App\Services\Attendance\Sync\AttendanceSyncHandler::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\TimeExport\Retention\TimeRetentionPolicies::class,
            ],
        ];
    }
}
