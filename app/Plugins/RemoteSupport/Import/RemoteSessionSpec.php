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
use App\Models\Platform\Organization;
use App\Plugins\RemoteSupport\Api\{AnyDeskClient, RustDeskProClient};
use App\Plugins\RemoteSupport\Providers\{RemoteProviders, RemoteSession};
use App\Plugins\RemoteSupport\RemoteSupportConfig;
use App\Plugins\RemoteSupport\Services\RemoteSessionImporter;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\DateTimeFormat;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use CommonToolkit\Helper\Data\{DateHelper, StringHelper};

/**
 * CSV-Import-Spezifikation für Fernwartungs-Sitzungen (AnyDesk-Export und
 * Verbindungsexport von RustDesk Server Pro), eingebunden in den zentralen
 * Import-Wizard (MVP-049). Den Anbieter erkennt {@see normalize()} an der
 * Kopfzeile (RustDesk: Spalten „Controlled Device“/„Gesteuertes Gerät“ und
 * „Type“); eine Spalte `provider` überstimmt die Erkennung.
 *
 * Anders als die Stammdaten-Specs upsertet sie keine Entität, sondern reicht jede
 * Zeile als {@see RemoteSession} an den {@see RemoteSessionImporter} weiter: bekannte
 * Geräte-IDs werden sofort als Zeiteintrag gebucht (Created), bereits importierte
 * übersprungen und unbekannte IDs in die Fernwartungs-Inbox gelegt (jeweils Skipped).
 *
 * Den AnyDesk-Eigenheiten — Excel-`sep=`-Vorzeile und das Datumsformat
 * „d.m.Y, H:i:s" — wird in {@see preprocessRaw()} bzw. {@see normalize()} begegnet;
 * RustDesk Pro exportiert `toLocaleString()` des Browsers (deutsch
 * „d.m.Y, H:i:s", englisch „m/d/Y, h:i:s A", ISO); die deutschen/englischen
 * Spaltennamen beider Exporte löst {@see headerAliases()} auf.
 */
class RemoteSessionSpec extends AbstractEntitySpec {
    /** @var array<int, array<string, mixed>> Org-ID → aufgelöste Plugin-Config */
    private array $configCache = [];

    /** @var array<int, int|null> Org-ID → buchbarer Benutzer */
    private array $userCache = [];

    public function __construct(private readonly RemoteSessionImporter $service) {}

    public function entity(): ImportEntity {
        return ImportEntity::RemoteSessions;
    }

    public function columns(): array {
        return ['session_id', 'remote_id', 'alias', 'start', 'end', 'note', 'provider', 'kind', 'controller'];
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
            'Anbieter' => 'provider',
            'Provider' => 'provider',
            // RustDesk Server Pro, Verbindungsprotokoll „Export as csv“ (englisch und deutsch).
            'Controlled Device' => 'remote_id',
            'Gesteuertes Gerät' => 'remote_id',
            'Controlling Side' => 'controller',
            'Steuernde Seite' => 'controller',
            'Start Time' => 'start',
            'End Time' => 'end',
            'Note' => 'note',
            'Notiz' => 'note',
            'Type' => 'kind',
            'Typ' => 'kind',
        ];
    }

    public function preprocessRaw(string $raw): string {
        // Excel-BOM und führende `sep=`-Hinweiszeile entfernen, damit der
        // generische CSV-Leser die echte Kopfzeile als Header erkennt.
        $raw = StringHelper::stripBom($raw); // Feature 052

        return CsvStringHelper::stripExcelSeparatorHint($raw);
    }

    public function normalize(array $row): array {
        $provider = $this->detectProvider($row);
        $remoteId = $this->trimmedString($row['remote_id'] ?? null);
        if ($provider === RustDeskProClient::ID && $remoteId !== null) {
            $remoteId = RustDeskProClient::normalizeId($remoteId);
        }

        // RustDesk nennt die Art der Verbindung und die steuernde Seite; beides wandert in die Notiz.
        $note = $this->trimmedString($row['note'] ?? null);
        if ($provider === RustDeskProClient::ID) {
            $kind = $this->trimmedString($row['kind'] ?? null);
            $controller = $this->trimmedString($row['controller'] ?? null);
            $parts = array_filter([
                $kind !== null && ! in_array(strtolower($kind), ['normal', 'remote desktop', 'fernzugriff', '-'], true) ? $kind : null,
                $note,
                $controller,
            ]);
            $note = $parts !== [] ? implode(' · ', $parts) : null;
        }

        return [
            'provider' => $provider,
            'session_id' => $this->trimmedString($row['session_id'] ?? null),
            'remote_id' => $remoteId,
            'alias' => $this->trimmedString($row['alias'] ?? null),
            'note' => $note,
            'started_at' => $this->parseDate($row['start'] ?? null),
            'ended_at' => $this->parseDate($row['end'] ?? null),
        ];
    }

    /**
     * Anbieter der Zeile: ausdrückliche Spalte `provider` (Kennung oder
     * Bezeichnung), sonst die Kopfzeile — ein RustDesk-Pro-Export trägt die
     * Spalten „Controlling Side“ und „Type“; alles andere ist AnyDesk wie bisher.
     *
     * @param  array<string, mixed>  $row
     */
    private function detectProvider(array $row): string {
        $explicit = strtolower((string) ($this->trimmedString($row['provider'] ?? null) ?? ''));
        if ($explicit !== '') {
            foreach (RemoteProviders::all() as $id => $definition) {
                if ($explicit === $id || $explicit === strtolower($definition->label)) {
                    return $id;
                }
            }
        }

        // Der Zeilenleser liefert jede Spalte der Spec, auch leer: entscheidend ist ein Wert.
        return $this->trimmedString($row['kind'] ?? null) !== null || $this->trimmedString($row['controller'] ?? null) !== null
            ? RustDeskProClient::ID
            : AnyDeskClient::ID;
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
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, (string) __('remote-support::import.error.persist.noBookingUser'))];
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

        // AnyDesk und RustDesk exportieren Ortszeit: gebucht wird UTC, der Ersatzschlüssel oben bleibt auf der Wanduhr (Re-Import).
        $tz = Tz::ofOrganization($organization);
        $session = new RemoteSession(
            provider: (string) ($row['provider'] ?? AnyDeskClient::ID),
            sessionId: $sessionId,
            remoteId: $remoteId,
            startedAt: $start->shiftTimezone($tz)->utc(),
            endedAt: $end->shiftTimezone($tz)->utc(),
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

        // AnyDesk exportiert „d.m.Y, H:i:s“ — das Komma kennt das Toolkit nicht.
        $value = str_replace(', ', ' ', $value);
        $iso = DateHelper::normalizeToIso($value, DateTimeFormat::DE);

        // RustDesk Pro exportiert `toLocaleString()` des Browsers — englisch „11/4/2026 2:15:57 PM“.
        if ($iso === null && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{2}):(\d{2}) ?(AM|PM)$/i', $value, $m) === 1) {
            $hour = (int) $m[4] % 12 + (strtoupper($m[7]) === 'PM' ? 12 : 0);
            $iso = sprintf('%04d-%02d-%02d %02d:%s:%s', (int) $m[3], (int) $m[1], (int) $m[2], $hour, $m[5], $m[6]);
            if (! DateHelper::isDateTime($iso)) {
                return null;
            }
        }

        // Nur Wanduhrzeit mit Uhrzeit (`Y-m-d H:i:s`): kein reines Datum, kein Offset.
        return $iso === null || strlen($iso) !== 19 ? null : CarbonImmutable::parse($iso);
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
