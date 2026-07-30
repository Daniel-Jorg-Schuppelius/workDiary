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
use App\Plugins\Support\AbstractTimeEntryPushService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bucht in workDiary erfasste Zeiten als OpenProject-time_entries zurück
 * ({@see AbstractTimeEntryPushService}-Skelett).
 *
 * Kandidaten sind nicht-exportierte Projekt-Zeiteinträge, deren Projekt einem
 * OpenProject-Projekt zugeordnet ist ({@see OpenProjectStructureSync}). Pro
 * Eintrag wird ein OpenProject-Zeiteintrag angelegt (Aufgabe → Work Package und
 * Benutzer → OpenProject-Benutzer, sofern gemappt). Rate-Limits brechen den
 * Lauf ab — der Rest bleibt offen für den nächsten Durchgang.
 */
class OpenProjectExportService extends AbstractTimeEntryPushService {
    public function __construct(private readonly OpenProjectStructureSync $structure) {}

    private ?OpenProjectApiClient $client = null;

    private ?string $activityId = null;

    /** @var array<int, string|null> Cache Projekt-ID → externe OpenProject-ID (je Lauf) */
    private array $projectExternalIds = [];

    protected function pluginId(): string {
        return OpenProjectPlugin::ID;
    }

    protected function prepareExport(Organization $organization, array $config): ?string {
        $this->projectExternalIds = [];

        $this->client = new OpenProjectApiClient($config['api_token'] ?? null, $config['base_url'] ?? null);
        if (! $this->client->isConfigured()) {
            return (string) __('OpenProject ist nicht konfiguriert.');
        }

        $this->activityId = $this->stringOrNull($config['default_activity_id'] ?? null);
        if ($this->activityId === null) {
            return (string) __('Keine OpenProject-Activity-ID hinterlegt — Rückbuchung nicht möglich.');
        }

        return null;
    }

    protected function exportableProjectIds(Organization $organization): array {
        return array_values(ExternalReference::query()
            ->forPlugin($organization, OpenProjectPlugin::ID, OpenProjectStructureSync::EXT_TYPE_PROJECT)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('referenceable_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    protected function scopeCandidates(Builder $query): Builder {
        return $query->with(['project', 'task', 'user']);
    }

    /** Ohne Projekt-Mapping keine Rückbuchung. */
    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        return $this->projectExternalId($organization, $entry) === null;
    }

    protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string {
        assert($this->client instanceof OpenProjectApiClient && $this->activityId !== null);

        $workPackageExternalId = $entry->task instanceof Task
            ? $this->structure->externalIdFor($organization, $entry->task, OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE)
            : null;
        $userExternalId = $entry->user instanceof User
            ? $this->structure->externalIdFor($organization, $entry->user, OpenProjectStructureSync::EXT_TYPE_USER)
            : null;

        return $this->client->createTimeEntry(
            projectExternalId: (string) $this->projectExternalId($organization, $entry),
            workPackageExternalId: $workPackageExternalId,
            userExternalId: $userExternalId,
            activityId: $this->activityId,
            spentOn: CarbonImmutable::parse($entry->date ?? $entry->started_at ?? now()),
            minutes: (int) $entry->minutes,
            comment: $entry->description,
        );
    }

    protected function shouldAbort(\Throwable $e): bool {
        return $e instanceof OpenProjectRateLimitException;
    }

    protected function isExpectedFailure(\Throwable $e): bool {
        return $e instanceof OpenProjectApiException;
    }

    protected function pushedPayload(TimeEntry $entry): ?array {
        return ['minutes' => (int) $entry->minutes];
    }

    /** Externe Projekt-ID des Eintrags, je Lauf gecacht (Skip + Remote-Anlage). */
    private function projectExternalId(Organization $organization, TimeEntry $entry): ?string {
        $project = $entry->project;
        if (! $project instanceof Project) {
            return null;
        }

        $key = (int) $project->id;
        if (! array_key_exists($key, $this->projectExternalIds)) {
            $this->projectExternalIds[$key] = $this->structure->externalIdFor($organization, $project, OpenProjectStructureSync::EXT_TYPE_PROJECT);
        }

        return $this->projectExternalIds[$key];
    }

    private function stringOrNull(mixed $value): ?string {
        return is_numeric($value) || (is_string($value) && trim($value) !== '') ? (string) $value : null;
    }
}
