<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Auftragsbuch (Tagebuch), offene Punkte, Bereitschaft“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DiaryManifest extends Manifest {
    public function code(): string {
        return 'diary';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Auftragsbuch (Tagebuch), offene Punkte, Bereitschaft';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Diary',
            'OpenIssue',
            'Recurrence',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'day_closures',
            'day_correction_requests',
            'diary_entries',
            'diary_entry_events',
            'diary_entry_qualifications',
            'emergency_assignments',
            'on_call_shifts',
            'open_issue_events',
            'open_issues',
            'recurrence_rules',
            'tours',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Diary,
            PermissionGroup::OpenIssues,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Diary\DeadlineScans\OpenIssueDeadlineScan::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Diary\Search\DiaryEntrySource::class,
                \App\Services\Diary\Search\OpenIssueSource::class,
            ],
            \App\Services\Sync\Contracts\SyncCommandHandler::class => [
                \App\Services\Diary\Sync\DiaryCommentSyncHandler::class,
            ],
            \App\Automation\Actions\RuleAction::class => [
                \App\Services\Diary\Automation\CreateFollowUpOrderAction::class,
            ],
            \App\Automation\Triggers\RuleTrigger::class => [
                \App\Services\OpenIssue\Automation\OpenIssueCreatedTrigger::class,
            ],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Time\TimeEntryCreated::class => [
                \App\Listeners\Diary\StartOrderFromTimeEntry::class,
            ],
        ];
    }
}
