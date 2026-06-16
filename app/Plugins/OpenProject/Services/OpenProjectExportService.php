<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Services;

use App\Models\{ExternalReference, Organization, Project, Task, TimeEntry, User};
use App\Plugins\OpenProject\Exceptions\{OpenProjectApiException, OpenProjectRateLimitException};
use App\Plugins\OpenProject\OpenProjectPlugin;
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bucht in workDiary erfasste Zeiten als OpenProject-time_entries zurück.
 *
 * Kandidaten sind nicht-exportierte Projekt-Zeiteinträge, deren Projekt einem
 * OpenProject-Projekt zugeordnet ist ({@see OpenProjectStructureSync}). Pro
 * Eintrag wird ein OpenProject-Zeiteintrag angelegt (Aufgabe → Work Package und
 * Benutzer → OpenProject-Benutzer, sofern gemappt). Idempotent über die
 * `pushed_entry`-Reference; erfolgreich gebuchte Einträge werden als exportiert
 * markiert.
 */
class OpenProjectExportService {
    public const EXT_TYPE_PUSHED = 'pushed_entry';

    public function __construct(private readonly OpenProjectStructureSync $structure) {}

    /**
     * Bucht offene Projekt-Zeiten der Organisation nach OpenProject zurück.
     *
     * @param  array<string, mixed>  $config
     * @return array{pushed: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function exportPending(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $errors = [];

        $client = new OpenProjectApiClient($config['api_token'] ?? null, $config['base_url'] ?? null);
        if (! $client->isConfigured()) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('OpenProject ist nicht konfiguriert.')]];
        }

        $activityId = $this->stringOrNull($config['default_activity_id'] ?? null);
        if ($activityId === null) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('Keine OpenProject-Activity-ID hinterlegt — Rückbuchung nicht möglich.')]];
        }

        $pushed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->candidates($organization, $from, $to) as $entry) {
            if ($this->alreadyPushed($organization, $entry)) {
                $skipped++;

                continue;
            }

            $projectExternalId = $entry->project instanceof Project
                ? $this->structure->externalIdFor($organization, $entry->project, OpenProjectStructureSync::EXT_TYPE_PROJECT)
                : null;
            if ($projectExternalId === null) {
                $skipped++;

                continue;
            }

            $workPackageExternalId = $entry->task instanceof Task
                ? $this->structure->externalIdFor($organization, $entry->task, OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE)
                : null;
            $userExternalId = $entry->user instanceof User
                ? $this->structure->externalIdFor($organization, $entry->user, OpenProjectStructureSync::EXT_TYPE_USER)
                : null;

            try {
                $externalId = $client->createTimeEntry(
                    projectExternalId: $projectExternalId,
                    workPackageExternalId: $workPackageExternalId,
                    userExternalId: $userExternalId,
                    activityId: $activityId,
                    spentOn: CarbonImmutable::parse($entry->date ?? $entry->started_at ?? now()),
                    minutes: (int) $entry->minutes,
                    comment: $entry->description,
                );

                $this->recordPushed($organization, $entry, $externalId);
                $entry->forceFill(['exported' => true])->save();
                $pushed++;
            } catch (OpenProjectRateLimitException $e) {
                // Drosselung: Lauf abbrechen, der Rest bleibt offen für den nächsten Durchgang.
                $errors[] = $e->getMessage();
                break;
            } catch (OpenProjectApiException $e) {
                $errors[] = (string) __('Zeiteintrag #:id: :message', ['id' => $entry->id, 'message' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['pushed' => $pushed, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Nicht-exportierte Projekt-Zeiteinträge, deren Projekt einem OpenProject-
     * Projekt zugeordnet ist, optional im Datumsfenster.
     *
     * @return Collection<int, TimeEntry>
     */
    private function candidates(Organization $organization, ?CarbonImmutable $from, ?CarbonImmutable $to): Collection {
        $mappedProjectIds = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->where('external_type', OpenProjectStructureSync::EXT_TYPE_PROJECT)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('referenceable_id');

        if ($mappedProjectIds->isEmpty()) {
            return collect();
        }

        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        return TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('project_id', $mappedProjectIds)
            ->where('exported', false)
            ->where('minutes', '>', 0)
            ->when($fromDate !== null, fn($q) => $q->whereDate('date', '>=', $fromDate))
            ->when($toDate !== null, fn($q) => $q->whereDate('date', '<=', $toDate))
            ->with(['project', 'task', 'user'])
            ->orderBy('date')
            ->get();
    }

    private function alreadyPushed(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->where('external_type', self::EXT_TYPE_PUSHED)
            ->where('referenceable_type', $entry->getMorphClass())
            ->where('referenceable_id', $entry->getKey())
            ->exists();
    }

    private function recordPushed(Organization $organization, TimeEntry $entry, string $externalId): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => OpenProjectPlugin::ID,
                'external_type' => self::EXT_TYPE_PUSHED,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
            ],
            [
                'external_id' => $externalId,
                'payload' => ['minutes' => (int) $entry->minutes],
                'synced_at' => now(),
            ],
        );
    }

    private function stringOrNull(mixed $value): ?string {
        return is_numeric($value) || (is_string($value) && trim($value) !== '') ? (string) $value : null;
    }
}
