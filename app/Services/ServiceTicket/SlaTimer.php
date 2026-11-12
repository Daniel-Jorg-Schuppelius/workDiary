<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaTimer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, SlaStatus};
use App\Models\Asset\Asset;
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, SlaContract};
use App\Services\Asset\Contracts\ServiceLevelResolver;
use App\Services\Calendar\HolidayService;
use Illuminate\Support\Carbon;

class SlaTimer implements ServiceLevelResolver {
    /**
     * Anteil der Restzeit (relativ zur Gesamtfrist), unter dem ein Ticket als
     * „gefährdet" (atRisk) gilt. Spiegelt die Eskalationsschwelle sla.atRisk.
     */
    public const float AT_RISK_FRACTION = 0.20;

    /**
     * HolidayService wird bewusst lazy aufgelöst: der SlaTimer wird an einigen
     * Stellen per `new SlaTimer` erzeugt (Tests, ServiceTicketServiceTest), ein
     * Pflicht-Konstruktorargument würde das brechen.
     */
    public function __construct(private ?HolidayService $holidays = null) {}

    /**
     * Berechnet Reaktions-/Lösungsfrist ab dem Meldezeitpunkt. Sind im Vertrag
     * Geschäftszeiten (`business_hours`) hinterlegt, zählen die SLA-Minuten nur
     * innerhalb dieser Fenster (Wochenenden/nicht belegte Tage und Feiertage der
     * Dienstplanung werden übersprungen). Ohne Fenster gilt Kalenderzeit — so
     * bleiben Alt-Verträge unverändert.
     *
     * Kalender der Queue (MVP-1548): ihre Geschäftszeiten gelten, wenn der
     * Vertrag keine nennt; ihre Feiertagsregion ersetzt die der Organisation.
     *
     * @param  array<int|string, mixed>|null  $fallbackBusinessHours
     * @return array{reaction_due_at: Carbon|null, resolution_due_at: Carbon|null}
     */
    public function computeDeadlines(SlaContract $contract, ServiceTicketPriority $priority, Carbon $reportedAt, ?array $fallbackBusinessHours = null, ?string $holidayProvider = null): array {
        /** @var array<string, array{reaction_minutes?: int, resolution_minutes?: int}> $table */
        $table = $contract->priority_table;
        $entry = $table[$priority->value] ?? null;
        if ($entry === null) {
            return ['reaction_due_at' => null, 'resolution_due_at' => null];
        }

        $reaction = isset($entry['reaction_minutes']) ? (int) $entry['reaction_minutes'] : null;
        $resolution = isset($entry['resolution_minutes']) ? (int) $entry['resolution_minutes'] : null;

        $windows = $this->normalizeBusinessHours($contract->business_hours);
        if ($windows === []) {
            $windows = $this->normalizeBusinessHours($fallbackBusinessHours);
        }

        return [
            'reaction_due_at' => $reaction !== null ? $this->addDuration($reportedAt, $reaction, $windows, $holidayProvider) : null,
            'resolution_due_at' => $resolution !== null ? $this->addDuration($reportedAt, $resolution, $windows, $holidayProvider) : null,
        ];
    }

    /**
     * Findet den passenden SLA-Vertrag: Asset → Projekt → Kunde → Queue → Org-Default.
     * Die Direktzuordnung am Asset ist die engste Zusage (ein Objekt); eine Zusage an
     * Projekt oder Kunde ist spezifischer als die Vorgabe der Queue; die Queue ist
     * spezifischer als die der Organisation (MVP-1548). Projektgebundene Verträge
     * greifen nur über ihr Projekt, OLA-Verträge (internes Ziel je Team) nie als
     * Kundenzusage, der Vertrag eines anderen Kunden nie über das Asset.
     */
    public function resolveContract(int $organizationId, ?int $customerId, ?int $projectId = null, ?int $queueId = null, ?int $assetId = null): ?SlaContract {
        $contracts = fn () => SlaContract::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('is_ola', false);

        if ($assetId !== null) {
            $assetBound = $contracts()
                ->whereKey(Asset::query()->where('organization_id', $organizationId)->whereKey($assetId)->select('sla_contract_id'))
                ->whereNull('project_id')
                ->where(fn ($q) => $q->whereNull('customer_id')->when($customerId !== null, fn ($qq) => $qq->orWhere('customer_id', $customerId)))
                ->first();
            if ($assetBound !== null) {
                return $assetBound;
            }
        }

        if ($projectId !== null) {
            $projectBound = $contracts()->where('project_id', $projectId)->first();
            if ($projectBound !== null) {
                return $projectBound;
            }
        }

        if ($customerId !== null) {
            $specific = $contracts()->where('customer_id', $customerId)->whereNull('project_id')->first();
            if ($specific !== null) {
                return $specific;
            }
        }

        if ($queueId !== null) {
            $queueDefault = $contracts()
                ->whereKey(ServiceQueue::query()->where('organization_id', $organizationId)->whereKey($queueId)->select('default_sla_contract_id'))
                ->first();
            if ($queueDefault !== null) {
                return $queueDefault;
            }
        }

        return $contracts()
            ->whereNull('customer_id')
            ->whereNull('project_id')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Internes Ziel des Queue-Teams am Ticket (OLA, MVP-1548): Fristen aus dem
     * OLA-Vertrag des Teams ab Meldezeitpunkt, reine Anzeige ohne Verletzungsregister.
     *
     * @return array{contract: SlaContract, reaction_due_at: Carbon|null, resolution_due_at: Carbon|null, reaction_status: SlaStatus, resolution_status: SlaStatus}|null
     */
    public function olaTargets(ServiceTicket $ticket): ?array {
        $queue = $ticket->queue;
        if ($queue === null || $queue->team_id === null || $ticket->reported_at === null) {
            return null;
        }
        $contract = SlaContract::query()
            ->where('organization_id', $ticket->organization_id)
            ->where('is_active', true)
            ->where('is_ola', true)
            ->where('ola_team_id', $queue->team_id)
            ->orderBy('id')
            ->first();
        if ($contract === null) {
            return null;
        }
        $due = $this->computeDeadlines($contract, $ticket->priority, $ticket->reported_at, $queue->business_hours, $queue->holiday_region);

        return [
            'contract' => $contract,
            ...$due,
            'reaction_status' => $this->statusFor($due['reaction_due_at'], $ticket->reported_at, $ticket->acknowledged_at, false, null),
            'resolution_status' => $this->statusFor($due['resolution_due_at'], $ticket->reported_at, $ticket->resolved_at, false, null),
        ];
    }

    /**
     * Abgeleiteter Lösungs-SLA-Status eines Tickets (reine Anzeige). Bezieht
     * sich auf die Lösungsfrist (resolution_due_at) als maßgebliche SLA-Frist.
     */
    public function resolutionStatus(ServiceTicket $ticket, ?Carbon $now = null): SlaStatus {
        return $this->statusFor(
            due: $ticket->resolution_due_at,
            start: $ticket->reported_at,
            completed: $ticket->resolved_at,
            breachedFlag: (bool) $ticket->resolution_breached,
            now: $now,
        );
    }

    /**
     * Abgeleiteter Reaktions-SLA-Status eines Tickets (reine Anzeige). Bezieht
     * sich auf die Reaktionsfrist (reaction_due_at); als „erfüllt" gilt sie ab
     * der ersten Bestätigung (acknowledged_at).
     */
    public function reactionStatus(ServiceTicket $ticket, ?Carbon $now = null): SlaStatus {
        return $this->statusFor(
            due: $ticket->reaction_due_at,
            start: $ticket->reported_at,
            completed: $ticket->acknowledged_at,
            breachedFlag: (bool) $ticket->reaction_breached,
            now: $now,
        );
    }

    /**
     * Verbleibende Minuten bis zur Frist (negativ = überfällig); null ohne Frist.
     */
    public function minutesRemaining(?Carbon $due, ?Carbon $now = null): ?int {
        if ($due === null) {
            return null;
        }
        $now ??= Carbon::now();

        return (int) round($now->diffInMinutes($due, false));
    }

    /**
     * Kernlogik der Statusableitung: erst der erledigte/markierte Endzustand,
     * dann die Restzeit gegen die at-risk-Schwelle.
     */
    private function statusFor(?Carbon $due, ?Carbon $start, ?Carbon $completed, bool $breachedFlag, ?Carbon $now): SlaStatus {
        if ($due === null) {
            return SlaStatus::None;
        }
        $now ??= Carbon::now();

        // Abgeschlossen: erfüllt, wenn rechtzeitig, sonst verletzt.
        if ($completed !== null) {
            return $completed->lessThanOrEqualTo($due) ? SlaStatus::Met : SlaStatus::Breached;
        }

        // Offen: ein gesetztes Breach-Flag oder eine überschrittene Frist ⇒ verletzt.
        if ($breachedFlag || $now->greaterThan($due)) {
            return SlaStatus::Breached;
        }

        // Restzeit relativ zur Gesamtfrist gegen die Schwelle.
        $remaining = $now->diffInSeconds($due, false);
        if ($start !== null) {
            $total = $start->diffInSeconds($due, false);
            if ($total > 0 && ($remaining / $total) <= self::AT_RISK_FRACTION) {
                return SlaStatus::AtRisk;
            }
        }

        return SlaStatus::OnTrack;
    }

    /**
     * Addiert eine Dauer entweder in Kalenderzeit (ohne Geschäftszeit-Fenster,
     * Rückwärtskompatibilität) oder nur innerhalb der Geschäftszeiten.
     *
     * @param  array<int, list<array{from: int, to: int}>>  $windows
     */
    private function addDuration(Carbon $start, int $minutes, array $windows, ?string $holidayProvider = null): Carbon {
        if ($windows === []) {
            return $start->copy()->addMinutes($minutes);
        }

        return $this->addBusinessMinutes($start, $minutes, $windows, $holidayProvider);
    }

    /**
     * Legt $minutes ab $start nur innerhalb der Geschäftszeit-Fenster ab und
     * überspringt nicht belegte Wochentage sowie Feiertage.
     *
     * @param  array<int, list<array{from: int, to: int}>>  $windows
     */
    private function addBusinessMinutes(Carbon $start, int $minutes, array $windows, ?string $holidayProvider = null): Carbon {
        $cursor = $start->copy();
        $remaining = max(0, $minutes);
        if ($remaining === 0) {
            return $cursor;
        }
        $holidays = $this->holidays();

        // Sicherheitsgrenze (max. ~10 Jahre Tage) gegen eine Endlosschleife,
        // falls ein Fenster nie erreichbar ist.
        for ($guardDays = 0; $guardDays <= 3660; $guardDays++) {
            $dayWindows = $this->isBusinessDay($cursor, $windows, $holidays, $holidayProvider)
                ? ($windows[$cursor->dayOfWeekIso] ?? [])
                : [];

            foreach ($dayWindows as $window) {
                $winStart = $cursor->copy()->startOfDay()->addMinutes($window['from']);
                $winEnd = $cursor->copy()->startOfDay()->addMinutes($window['to']);
                if ($cursor->greaterThanOrEqualTo($winEnd)) {
                    continue; // Fenster liegt bereits in der Vergangenheit
                }

                $segStart = $cursor->lessThan($winStart) ? $winStart->copy() : $cursor->copy();
                $available = (int) $segStart->diffInMinutes($winEnd, false);
                if ($remaining <= $available) {
                    return $segStart->addMinutes($remaining);
                }
                $remaining -= $available;
                $cursor = $winEnd->copy();
            }

            // Tag erschöpft → nächster Tagesbeginn.
            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        return $cursor; // theoretisch unerreichbar
    }

    /**
     * Liegt der Zeitpunkt (Wanduhrzeit der Organisation) in einem Fenster?
     * Ohne Fenster gilt jeder Zeitpunkt als Geschäftszeit (MVP-1258).
     *
     * @param  array<int|string, mixed>|null  $businessHours
     */
    public function isWithinBusinessHours(?array $businessHours, Carbon $at, ?string $holidayProvider = null): bool {
        $windows = $this->normalizeBusinessHours($businessHours);
        if ($windows === []) {
            return true;
        }
        if (! $this->isBusinessDay($at, $windows, $this->holidays(), $holidayProvider)) {
            return false;
        }
        $minute = $at->hour * 60 + $at->minute;
        foreach ($windows[$at->dayOfWeekIso] ?? [] as $window) {
            if ($minute >= $window['from'] && $minute < $window['to']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, list<array{from: int, to: int}>>  $windows
     */
    private function isBusinessDay(Carbon $date, array $windows, HolidayService $holidays, ?string $holidayProvider = null): bool {
        if (($windows[$date->dayOfWeekIso] ?? []) === []) {
            return false;
        }

        return ! $holidays->isHoliday($date, $holidayProvider);
    }

    /**
     * Normalisiert `business_hours` zu Wochentag(1–7 ISO) → sortierten
     * Minuten-Fenstern. Unterstützt die Datenform `[{weekday,from,to}, …]` sowie
     * die nach Wochentag indizierte Form; ungültige Einträge fallen still weg.
     *
     * @param  array<int|string, mixed>|null  $businessHours
     * @return array<int, list<array{from: int, to: int}>>
     */
    private function normalizeBusinessHours(?array $businessHours): array {
        if ($businessHours === null || $businessHours === []) {
            return [];
        }

        $map = [];
        foreach ($businessHours as $key => $window) {
            if (! is_array($window)) {
                continue;
            }
            $weekday = isset($window['weekday']) ? (int) $window['weekday'] : (is_int($key) ? $key : null);
            $from = $this->minuteOfDay($window['from'] ?? null);
            $to = $this->minuteOfDay($window['to'] ?? null);
            if ($weekday === null || $weekday < 1 || $weekday > 7 || $from === null || $to === null || $to <= $from) {
                continue;
            }
            $map[$weekday][] = ['from' => $from, 'to' => $to];
        }

        foreach ($map as &$list) {
            usort($list, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);
        }

        return $map;
    }

    private function minuteOfDay(mixed $value): ?int {
        if (! is_string($value) || preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m) !== 1) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    private function holidays(): HolidayService {
        return $this->holidays ??= app(HolidayService::class);
    }
}
