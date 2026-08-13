<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAttendanceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\{Attendance, AttendanceTerminal, DiaryEntry, SickLeave, TimeEntry, User, Vacation};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Notfall-Anwesenheitsliste (Feature 103, MVP-518): zeitpunktbezogene
 * Einteilung aller aktiven Mitarbeitenden in „Im Gebäude", „Außer Haus",
 * „Abwesend" und „Ohne Meldung" — für Evakuierungs- und Notfalllagen.
 *
 * Die Liste ist eine reine Ableitung vorhandener Daten (Anwesenheitsstempel,
 * Einsatz-/Zeitbuchungssignale, Urlaub/Krankmeldung) und trifft keine
 * eigene Wahrheit: Widersprüche werden angezeigt, nie korrigiert.
 *
 * Standortzuordnung: Terminal-Stempel tragen den Terminalnamen in
 * `started_device` (Feature 061) — darüber wird der Standort des Terminals
 * aufgelöst. Browser-/manuelle Stempel haben keine Standortzuordnung; ein
 * Standortfilter darf diese Personen deshalb NIE verstecken, sie erscheinen
 * gesondert als „ohne Standortzuordnung" (Sicherheitsprinzip: lieber eine
 * Person zu viel auf der Liste als eine zu wenig).
 */
class EmergencyAttendanceService {
    /**
     * Erstellt die Momentaufnahme zum Zeitpunkt $at (Default: jetzt).
     *
     * @return array{
     *     at: CarbonImmutable,
     *     is_live: bool,
     *     present: list<array{user: User, since: ?CarbonImmutable, on_break: bool, site_id: ?int, site_name: ?string}>,
     *     present_unmapped: list<array{user: User, since: ?CarbonImmutable, on_break: bool, site_id: ?int, site_name: ?string}>,
     *     off_site: list<array{user: User, since: ?CarbonImmutable, context: ?string}>,
     *     absent: list<array{user: User, reason: 'sick'|'vacation'}>,
     *     unaccounted: list<array{user: User}>,
     * }
     */
    public function snapshot(int $organizationId, ?CarbonImmutable $at = null, ?int $siteId = null): array {
        $at ??= CarbonImmutable::now();
        // „Live" = Momentaufnahme der Gegenwart; nur dann sind reine
        // Zustandssignale (Pause läuft, Einsatz „in Arbeit") aussagekräftig.
        $isLive = abs($at->diffInMinutes(CarbonImmutable::now(), false)) < 5;
        $localDay = $at->setTimezone(Tz::current())->startOfDay();

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get();

        $attendanceByUser = $this->attendanceByUser($organizationId, $at);
        $terminalSites = $this->terminalSiteMap($organizationId);
        $offSiteContext = $this->offSiteContextByUser($organizationId, $at, $isLive);
        $sickUserIds = SickLeave::query()
            ->where('organization_id', $organizationId)
            ->activeOn($localDay)
            ->pluck('user_id')->flip();
        $vacationUserIds = Vacation::query()
            ->where('organization_id', $organizationId)
            // MVP-536: bei aktiver Vorbehalts-Eintragung zählen auch beantragte
            // Fehlzeiten als abwesend (Planungswirkung, keine Abrechnung).
            ->effective()
            ->overlapping($localDay, $localDay)
            ->pluck('user_id')->flip();

        $present = $presentUnmapped = $offSite = $absent = $unaccounted = [];

        foreach ($users as $user) {
            $attendance = $attendanceByUser->get($user->id);

            if ($attendance instanceof Attendance) {
                $since = $attendance->started_at !== null ? CarbonImmutable::parse($attendance->started_at) : null;

                // MVP-532: offener Zwischen-Status — Person arbeitet, ist aber
                // NICHT im Gebäude (evakuierungsrelevant) → „außer Haus".
                if ($isLive && $attendance->ended_at === null && $attendance->homeoffice_started_at !== null) {
                    $offSite[] = ['user' => $user, 'since' => $since, 'context' => (string) __('attendance.intermediate.homeoffice')];

                    continue;
                }
                if ($isLive && $attendance->ended_at === null && $attendance->errand_started_at !== null) {
                    $offSite[] = ['user' => $user, 'since' => $since, 'context' => (string) __('attendance.intermediate.errand')];

                    continue;
                }

                if ($offSiteContext->has($user->id)) {
                    $offSite[] = ['user' => $user, 'since' => $since, 'context' => $offSiteContext->get($user->id)];

                    continue;
                }

                $site = $this->resolveSite($attendance, $terminalSites);
                $row = [
                    'user' => $user,
                    'since' => $since,
                    'on_break' => $isLive && $attendance->ended_at === null && $attendance->break_started_at !== null,
                    'site_id' => $site['id'],
                    'site_name' => $site['name'],
                ];

                if ($siteId !== null && $site['id'] !== $siteId) {
                    // Nie verstecken: ohne Zuordnung → gesonderte Gruppe;
                    // eindeutig anderer Standort → gehört nicht auf diese Liste.
                    if ($site['id'] === null) {
                        $presentUnmapped[] = $row;
                    }

                    continue;
                }

                $present[] = $row;

                continue;
            }

            if ($sickUserIds->has($user->id)) {
                $absent[] = ['user' => $user, 'reason' => 'sick'];

                continue;
            }
            if ($vacationUserIds->has($user->id)) {
                $absent[] = ['user' => $user, 'reason' => 'vacation'];

                continue;
            }

            $unaccounted[] = ['user' => $user];
        }

        return [
            'at' => $at,
            'is_live' => $isLive,
            'present' => $present,
            'present_unmapped' => $presentUnmapped,
            'off_site' => $offSite,
            'absent' => $absent,
            'unaccounted' => $unaccounted,
        ];
    }

    /**
     * Jüngstes Anwesenheitsintervall je Nutzer, das $at überdeckt
     * (started_at ≤ $at ≤ ended_at bzw. noch offen).
     *
     * @return Collection<int, Attendance>
     */
    private function attendanceByUser(int $organizationId, CarbonImmutable $at): Collection {
        return Attendance::query()
            ->where('organization_id', $organizationId)
            ->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at))
            ->where('status', '!=', AttendanceStatus::Cancelled->value)
            ->orderBy('started_at')
            ->get()
            ->keyBy('user_id'); // sortiert aufsteigend → letzter Treffer je Nutzer gewinnt
    }

    /**
     * Terminalname → Standort. Mehrdeutige Namen (gleicher Name, verschiedene
     * Standorte) werden als „nicht zuordenbar" behandelt.
     *
     * @return array<string, array{id: ?int, name: ?string}>
     */
    private function terminalSiteMap(int $organizationId): array {
        $map = [];
        $terminals = AttendanceTerminal::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('site_id')
            ->with('site:id,name')
            ->get();

        foreach ($terminals as $terminal) {
            $name = (string) $terminal->name;
            if (array_key_exists($name, $map) && $map[$name]['id'] !== $terminal->site_id) {
                $map[$name] = ['id' => null, 'name' => null];

                continue;
            }
            $map[$name] = ['id' => (int) $terminal->site_id, 'name' => $terminal->site?->name];
        }

        return $map;
    }

    /** @param array<string, array{id: ?int, name: ?string}> $terminalSites
     * @return array{id: ?int, name: ?string} */
    private function resolveSite(Attendance $attendance, array $terminalSites): array {
        if ($attendance->source === AttendanceSource::Terminal && $attendance->started_device !== null) {
            return $terminalSites[$attendance->started_device] ?? ['id' => null, 'name' => null];
        }

        return ['id' => null, 'name' => null];
    }

    /**
     * Außer-Haus-Signal je Nutzer mit Kontext (Kundenname): eine zum
     * Zeitpunkt laufende Zeitbuchung auf einem Kundenauftrag; live zusätzlich
     * ein laufender Kundeneinsatz (DiaryEntry „in Arbeit").
     *
     * @return Collection<int, string|null>
     */
    private function offSiteContextByUser(int $organizationId, CarbonImmutable $at, bool $isLive): Collection {
        $context = collect();

        $entries = TimeEntry::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at))
            ->whereNotNull('diary_entry_id')
            ->with('diaryEntry.customer:id,name')
            ->get();
        foreach ($entries as $entry) {
            $customer = $entry->diaryEntry?->customer;
            if ($customer !== null && $entry->user_id !== null) {
                $context->put((int) $entry->user_id, (string) $customer->name);
            }
        }

        if ($isLive) {
            $diaries = DiaryEntry::query()
                ->where('organization_id', $organizationId)
                ->inProgress()
                ->whereNotNull('assigned_user_id')
                ->whereNotNull('customer_id')
                ->with('customer:id,name')
                ->get();
            foreach ($diaries as $diary) {
                $context->put((int) $diary->assigned_user_id, $diary->customer?->name);
            }
        }

        return $context;
    }
}
