<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\{ImportValueMapping, IntegrationInboxItem, Organization, Project, Task, TimeEntry, User};
use App\Services\Import\{HasMappableValues, ImportOutcome, InboxFirstSpec, ValidationIssue};
use App\Services\Import\Specs\Concerns\{BindsTimeImportReference, ParsesLocalDateTime, ResolvesImportUsers};
use App\Services\TimeApproval\MonthClosureService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Throwable;

/**
 * Import-Spezifikation für Projektzeiten (MVP-438).
 *
 * Erzeugt {@see TimeEntry}-Buchungen (`activity_type=project`) aus CSV/XLSX/iCal.
 * Unkritischer als Stempelungen (keine Anwesenheitswahrheit) → breiter vergebbar
 * (`project-time.import`). Die Kunde-/Projektzuordnung läuft **Inbox-First**: ein
 * Projekt wird über Nummer/Namen aufgelöst, unklare Fälle landen in der
 * Zuordnungs-Inbox (MVP-103) — **niemals** wird blind ein Projekt angelegt.
 *
 * Bewusste Abgrenzung (Feature-Doc 094): Die Projektauflösung ist konservativ
 * deterministisch (exakte Nummer bzw. **eindeutiger** Namenstreffer, Muster
 * {@see \App\Plugins\Support\MatchingTimeImportService::matchProject()}); mehrere
 * Namenstreffer gelten als unklar und werden gestaged statt geraten. Das
 * spätere Zurückbuchen gestagter Zeilen nach manueller Projektzuordnung ist
 * „Später".
 */
class ProjectTimeSpec extends AbstractEntitySpec implements HasMappableValues, InboxFirstSpec {
    use BindsTimeImportReference;
    use ParsesLocalDateTime;
    use ResolvesImportUsers;

    private const EXTERNAL_TYPE = 'project-time';

    public function __construct(private readonly MonthClosureService $monthClosures) {}

    public function entity(): ImportEntity {
        return ImportEntity::ProjectTimes;
    }

    public function columns(): array {
        return ['user_email', 'date', 'start_time', 'end_time', 'project', 'task', 'description', 'billable', 'external_id'];
    }

    public function requiredColumns(): array {
        return ['user_email', 'date', 'start_time', 'end_time', 'project'];
    }

    public function headerAliases(): array {
        return [
            'mitarbeiter' => 'user_email',
            'benutzer' => 'user_email',
            'email' => 'user_email',
            'e-mail' => 'user_email',
            'datum' => 'date',
            'beginn' => 'start_time',
            'startzeit' => 'start_time',
            'von' => 'start_time',
            'ende' => 'end_time',
            'endzeit' => 'end_time',
            'bis' => 'end_time',
            'projekt' => 'project',
            'aufgabe' => 'task',
            'beschreibung' => 'description',
            'notiz' => 'description',
            'abrechenbar' => 'billable',
            'fremd-id' => 'external_id',
            'fremdschluessel' => 'external_id',
            'uid' => 'external_id',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'user_email' => ($v = $this->trimmedString($raw)) !== null ? mb_strtolower($v) : null,
                'date' => $this->normalizeImportDate($this->trimmedString($raw)),
                'start_time', 'end_time' => $this->normalizeImportTime($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['user_email'] ?? null) === null) {
            $issues[] = $this->requiredIssue('user_email');
        } elseif (! filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = $this->formatIssue('user_email', (string) __('import.error.format.email'));
        }

        foreach (['date', 'start_time', 'end_time', 'project'] as $required) {
            if (($row[$required] ?? null) === null) {
                $issues[] = $this->requiredIssue($required);
            }
        }

        foreach (['start_time', 'end_time'] as $f) {
            if (! empty($row[$f]) && ! preg_match('/^\d{2}:\d{2}$/', (string) $row[$f])) {
                $issues[] = $this->formatIssue($f, (string) __('import.error.format.time'));
            }
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        // Projektzeiten legen NIE blind ein Projekt an — auch im auto_create-Modus
        // wird eine unklare Zuordnung gestaged (Feature 094).
        return $this->book($row, $organization);
    }

    public function upsertOrStage(array $row, Organization $organization): array {
        return $this->book($row, $organization);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ImportOutcome, 1: ?ValidationIssue}
     */
    private function book(array $row, Organization $organization): array {
        try {
            // Konto-Treffer (case-insensitiv) oder Benutzer-Mapping; ignorierte
            // Adressen überspringen die Zeile statt zu scheitern.
            $user = $this->resolveImportUser($organization, (string) $row['user_email']);
            if ($user === ImportValueMapping::KIND_IGNORE) {
                return [ImportOutcome::Skipped, null];
            }

            if (! $user instanceof User) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::FkMissing,
                        'user_email',
                        (string) __('import.error.fkMissing.user', ['value' => (string) $row['user_email']]),
                    ),
                ];
            }

            $date = (string) $row['date'];

            // Sperr-Schutz: bereits abgeschlossene/exportierte Zeiträume nicht überschreiben.
            if ($this->monthClosures->isPeriodLockedForUser($user, CarbonImmutable::parse($date))) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::PeriodLocked,
                        'date',
                        (string) __('import.error.periodLocked.projectTime', ['date' => $date]),
                    ),
                ];
            }

            $projectName = (string) $row['project'];
            $project = $this->matchProject($organization, $projectName);
            if (! $project instanceof Project) {
                // Inbox-First: kein Blind-Projekt — Zeile in die Zuordnungs-Inbox.
                $this->stageToInbox($organization, $row, $projectName);

                return [ImportOutcome::Skipped, null];
            }

            $tz = $this->orgTimezone($organization);
            $startedAt = $this->localToUtc($date, (string) $row['start_time'], $tz);
            $endedAt = $this->localToUtc($date, (string) $row['end_time'], $tz);
            if ($endedAt->lessThanOrEqualTo($startedAt)) {
                $endedAt = $endedAt->addDay();
            }

            $key = $this->importKey(
                is_string($row['external_id'] ?? null) ? $row['external_id'] : null,
                $user->id . '|' . $date . '|' . $row['start_time'] . '|' . $projectName . '|' . (string) ($row['description'] ?? ''),
            );

            $existing = $this->findImported($organization, TimeEntry::class, self::EXTERNAL_TYPE, $key);
            if ($existing instanceof TimeEntry && $existing->exported) {
                // Bereits exportierte Buchung ist unveränderlich → überspringen.
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(
                        ImportErrorCode::PeriodLocked,
                        'date',
                        (string) __('import.error.periodLocked.projectTime', ['date' => $date]),
                    ),
                ];
            }

            $payload = [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'task_id' => $this->resolveTaskId($project, $this->trimmedString($row['task'] ?? null)),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'date' => $date,
                'break_minutes' => 0,
                'activity_type' => TimeEntryActivityType::Project,
                'kind' => TimeEntryKind::Work,
                'description' => $row['description'] ?? null,
                'billable' => $this->resolveBillable($row),
            ];

            if ($existing instanceof TimeEntry) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            $entry = TimeEntry::create($payload);
            $this->bindImported($organization, $entry, self::EXTERNAL_TYPE, $key);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    /**
     * Konservative Projektauflösung: exakte Nummer, sonst **eindeutiger**
     * (case-insensitiver) Namenstreffer. Mehrdeutig/kein Treffer → null (staged).
     */
    private function matchProject(Organization $organization, string $needle): ?Project {
        $byNumber = Project::query()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->where('number', $needle)
            ->first();
        if ($byNumber instanceof Project) {
            return $byNumber;
        }

        $byName = Project::query()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($needle)])
            ->limit(2)
            ->get();

        return $byName->count() === 1 ? $byName->first() : null;
    }

    private function resolveTaskId(Project $project, ?string $title): ?int {
        if ($title === null) {
            return null;
        }

        $id = Task::query()
            ->where('project_id', $project->id)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->value('id');

        return is_int($id) ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveBillable(array $row): bool {
        $value = $row['billable'] ?? null;

        return ($value === null || $value === '') ? true : $this->boolish($value);
    }

    /**
     * Legt die unzuordenbare Zeile in der universellen Zuordnungs-Inbox ab
     * (MVP-103) — Idempotenz über die stabile Fremd-ID bzw. den Zeilen-Hash.
     *
     * @param  array<string, mixed>  $row
     */
    private function stageToInbox(Organization $organization, array $row, string $projectName): void {
        $externalId = is_string($row['external_id'] ?? null) ? trim((string) $row['external_id']) : '';
        $dedupeKey = $externalId !== ''
            ? self::EXTERNAL_TYPE . ':' . $externalId
            : 'hash:' . CryptoHelper::hash(JsonHelper::encode($row), HashAlgorithm::SHA1);

        /** @var IntegrationInboxItem $item */
        $item = IntegrationInboxItem::query()->firstOrNew([
            'organization_id' => $organization->id,
            'plugin_id' => IntegrationInboxItem::PLUGIN_CSV,
            'dedupe_key' => $dedupeKey,
        ]);
        if (! $item->exists) {
            $item->status = IntegrationInboxItem::STATUS_OPEN;
        }
        $item->fill([
            'source' => 'csv',
            'target_type' => (new Project)->getMorphClass(),
            'external_type' => self::EXTERNAL_TYPE,
            'external_id' => $externalId !== '' ? $externalId : null,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'remote_snapshot' => $row,
            // Projektförmig (Ziel ist Project): Matcher-Strategien und das
            // „Neu anlegen"-Profil arbeiten mit diesen Attributen — die rohe
            // Zeitzeile bliebe sonst ein Projekt ohne Namen (remote_snapshot
            // behält die Originalzeile).
            'mapped_snapshot' => ['name' => $projectName],
            'display_title' => $projectName,
            'display_subtitle' => trim(((string) ($row['user_email'] ?? '')) . ' · ' . ((string) ($row['date'] ?? ''))),
        ])->save();
    }
}
