<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSessionSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Organization;
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, RemoteSession};
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportService};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use Carbon\CarbonImmutable;

/**
 * CSV-Import-Spezifikation für Fernwartungs-Sitzungen (AnyDesk-Export), eingebunden
 * in den zentralen Import-Wizard (MVP-049).
 *
 * Anders als die Stammdaten-Specs upsertet sie keine Entität, sondern reicht jede
 * Zeile als {@see RemoteSession} an den {@see RemoteSupportService} weiter: bekannte
 * Geräte-IDs werden sofort als Zeiteintrag gebucht (Created), bereits importierte
 * übersprungen und unbekannte IDs in die Fernwartungs-Inbox gelegt (jeweils Skipped).
 *
 * Den AnyDesk-Eigenheiten — Excel-`sep=`-Vorzeile und das Datumsformat
 * „d.m.Y, H:i:s" — wird in {@see preprocessRaw()} bzw. {@see normalize()} begegnet;
 * die deutschen/englischen Spaltennamen löst {@see headerAliases()} auf.
 */
class RemoteSessionSpec extends AbstractEntitySpec {
    /** Akzeptierte Datumsformate; AnyDesk exportiert „d.m.Y, H:i:s". */
    private const DATE_FORMATS = ['d.m.Y, H:i:s', 'd.m.Y H:i:s', 'd.m.Y, H:i', 'Y-m-d H:i:s'];

    /** @var array<int, array<string, mixed>> Org-ID → aufgelöste Plugin-Config */
    private array $configCache = [];

    /** @var array<int, int|null> Org-ID → buchbarer Benutzer */
    private array $userCache = [];

    public function __construct(private readonly RemoteSupportService $service) {
    }

    public function entity(): ImportEntity {
        return ImportEntity::RemoteSessions;
    }

    public function columns(): array {
        return ['session_id', 'remote_id', 'alias', 'start', 'end', 'note'];
    }

    public function requiredColumns(): array {
        return ['remote_id', 'start', 'end'];
    }

    public function headerAliases(): array {
        return [
            'Sitzungs-ID' => 'session_id',
            'Sitzungs ID' => 'session_id',
            'Session' => 'session_id',
            'Session ID' => 'session_id',
            'Nach ID' => 'remote_id',
            'To ID' => 'remote_id',
            'Alias' => 'alias',
            'Nach Alias' => 'alias',
            'To Alias' => 'alias',
            'Beginn' => 'start',
            'Start' => 'start',
            'Startzeit' => 'start',
            'Ende' => 'end',
            'End' => 'end',
            'Endzeit' => 'end',
            'Kommentar' => 'note',
            'Comment' => 'note',
            'Bemerkung' => 'note',
        ];
    }

    public function preprocessRaw(string $raw): string {
        // Excel-BOM und führende `sep=`-Hinweiszeile entfernen, damit der
        // generische CSV-Leser die echte Kopfzeile als Header erkennt.
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        return (string) preg_replace('/^\s*sep=.\s*\r?\n/i', '', $raw, 1);
    }

    public function normalize(array $row): array {
        return [
            'session_id' => $this->trimmedString($row['session_id'] ?? null),
            'remote_id' => $this->trimmedString($row['remote_id'] ?? null),
            'alias' => $this->trimmedString($row['alias'] ?? null),
            'note' => $this->trimmedString($row['note'] ?? null),
            'started_at' => $this->parseDate($row['start'] ?? null),
            'ended_at' => $this->parseDate($row['end'] ?? null),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['remote_id'] ?? null) === null) {
            $issues[] = $this->requiredIssue('remote_id');
        }
        if (! $row['started_at'] instanceof CarbonImmutable) {
            $issues[] = new ValidationIssue(ImportErrorCode::Format, 'start', (string) __('import.error.format.date'));
        }
        if (! $row['ended_at'] instanceof CarbonImmutable) {
            $issues[] = new ValidationIssue(ImportErrorCode::Format, 'end', (string) __('import.error.format.date'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        $userId = $this->bookingUserId($organization);
        if ($userId === null) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, (string) __('import.error.persist.noBookingUser'))];
        }

        $start = $row['started_at'];
        $end = $row['ended_at'];
        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Format, 'start', (string) __('import.error.format.date'))];
        }

        $remoteId = (string) $row['remote_id'];
        $sessionId = (string) ($row['session_id'] ?? '');
        if ($sessionId === '') {
            $sessionId = $remoteId . '|' . $start->getTimestamp();
        }

        $alias = isset($row['alias']) && $row['alias'] !== '' && $row['alias'] !== $remoteId
            ? (string) $row['alias']
            : null;

        $session = new RemoteSession(
            provider: AnyDeskClient::ID,
            sessionId: $sessionId,
            remoteId: $remoteId,
            startedAt: $start,
            endedAt: $end,
            note: $row['note'] !== null && $row['note'] !== '' ? (string) $row['note'] : null,
            alias: $alias,
        );

        $outcome = $this->service->bookSession($organization, $this->config($organization), $session, $userId);

        // „unmatched" wird als Pending-Session abgelegt → zählt als übersprungen.
        return [$outcome === 'created' ? ImportOutcome::Created : ImportOutcome::Skipped, null];
    }

    private function parseDate(mixed $value): ?CarbonImmutable {
        $value = $this->trimmedString($value);
        if ($value === null) {
            return null;
        }

        // Carbon wirft bei nicht passendem Format eine Exception (statt false).
        foreach (self::DATE_FORMATS as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value);
            } catch (\Throwable) {
                // nächstes Format versuchen
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(Organization $organization): array {
        return $this->configCache[$organization->id] ??= RemoteSupportConfig::resolve($organization->id);
    }

    private function bookingUserId(Organization $organization): ?int {
        if (! array_key_exists($organization->id, $this->userCache)) {
            $config = $this->config($organization);
            $this->userCache[$organization->id] = $this->service->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        }

        return $this->userCache[$organization->id];
    }
}
