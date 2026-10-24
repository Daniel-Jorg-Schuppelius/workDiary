<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsTaskImportLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Models\Platform\User;
use App\Models\Project\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gemeinsame Spalten und Laufstand der Projektzuordnungen aus
 * {@see TaskImportLink}. Erwartet `source_project_id`, `source_project_key`,
 * `source_project_name`, `project_id`, `status`, `paused_reason`,
 * `import_confirmed_at`, `confirmed_by`, `checkpoint_at`, `last_full_sync_at`,
 * `last_run_at`, `is_last_run_complete`, `last_run_counters`, `last_error`.
 */
trait IsTaskImportLink {
    public function initializeIsTaskImportLink(): void {
        $this->mergeCasts([
            'status' => TaskImportLinkStatus::class,
            'import_confirmed_at' => 'immutable_datetime',
            'checkpoint_at' => 'immutable_datetime',
            'last_full_sync_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'is_last_run_complete' => 'boolean',
            'last_run_counters' => 'array',
        ]);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function organizationId(): int {
        return (int) $this->getAttribute('organization_id');
    }

    public function sourceProjectId(): string {
        return (string) $this->getAttribute('source_project_id');
    }

    public function targetProjectId(): int {
        return (int) $this->getAttribute('project_id');
    }

    public function sourceLabel(): string {
        foreach (['source_project_name', 'source_project_key', 'source_project_id'] as $column) {
            $value = trim((string) $this->getAttribute($column));
            if ($value !== '') {
                return $value;
            }
        }

        return '—';
    }

    public function isRunnable(): bool {
        return $this->getAttribute('status') === TaskImportLinkStatus::Active
            && $this->getAttribute('import_confirmed_at') !== null;
    }

    public function checkpointAt(): ?CarbonImmutable {
        $value = $this->getAttribute('checkpoint_at');

        return $value instanceof CarbonImmutable ? $value : null;
    }

    /** @param array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} $counters */
    public function recordRun(array $counters, bool $complete, CarbonImmutable $startedAt, bool $full, ?string $error = null): void {
        $attributes = [
            'last_run_at' => CarbonImmutable::now(),
            'is_last_run_complete' => $complete,
            'last_run_counters' => $counters,
            'last_error' => $error !== null ? mb_substr($error, 0, 300) : null,
        ];
        if ($complete) {
            $attributes['checkpoint_at'] = $startedAt;
            if ($full) {
                $attributes['last_full_sync_at'] = $startedAt;
            }
        }
        $this->forceFill($attributes)->save();
    }
}
