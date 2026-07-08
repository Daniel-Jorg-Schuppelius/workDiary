<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchBoardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\Diary\DispatchStatus;
use App\Enums\ServiceTicket\SlaStatus;
use App\Models\{DiaryEntry, ServiceTicket};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aggregiert die reinen ANZEIGE-Daten für Dispatch-Board (Leitstelle) und
 * Karten-Sicht (Feature 029). Es wird NICHTS dupliziert:
 *  - Dispositionsstatus → {@see DispatchStatusResolver} (Feature 028).
 *  - Harte Konflikte → {@see DispatchConflictChecker} (Feature 028).
 *  - SLA-Risiko → abgeleiteter {@see SlaStatus} der offenen ServiceTickets
 *    (Feature 010), je Auftrag über den Kunden (und – falls vorhanden – das
 *    Objekt/Asset) zugeordnet.
 *  - Zeitraumfilter → {@see DiaryEntry::scopeOverlappingDateRange()}.
 *
 * Bewusst KEINE Tourenoptimierung, KEIN Echtzeit-Tracking, KEINE dauerhafte
 * Standortüberwachung — nur Visualisierung vorhandener Plan-/Geo-/Statusdaten.
 *
 * @phpstan-type BoardItem array{
 *     entry: DiaryEntry,
 *     dispatch: DispatchStatus,
 *     sla: SlaStatus,
 *     hasHardConflict: bool
 * }
 */
final class DispatchBoardService {
    /** Hoechstzahl geladener Auftraege (Schutz vor Riesen-Zeitraeumen). */
    private const MAX_ENTRIES = 500;

    public function __construct(
        private readonly DispatchStatusResolver $resolver,
        private readonly DispatchConflictChecker $conflicts,
    ) {}

    /**
     * Aufträge des Zeitraums (modus-bewusst) für Board und Karte. Optional auf
     * einen Mitarbeiter (assigned_user_id ODER user_id) eingeschränkt.
     *
     * @return Collection<int, DiaryEntry>
     */
    public function entries(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $userId = null,
    ): Collection {
        $query = DiaryEntry::query()
            ->with(['customer:id,name,address_lat,address_lng', 'assignedUser:id,name', 'user:id,name'])
            ->where('is_archived', false)
            ->overlappingDateRange($from->toDateString(), $to->toDateString());

        if ($userId !== null) {
            $query->where(function ($q) use ($userId): void {
                $q->where('assigned_user_id', $userId)
                    ->orWhere('user_id', $userId);
            });
        }

        /** @var Collection<int, DiaryEntry> $entries */
        $entries = $query
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at')
            ->orderBy('id')
            ->limit(self::MAX_ENTRIES)
            ->get();

        return $entries;
    }

    /**
     * Kalender-Matrix (Rang 52): Zeilen = Mitarbeitende, Zellen = Tage mit den
     * dort geplanten Aufträgen (Einträge ohne Startzeit bleiben dem Board
     * vorbehalten). Ein Eintrag erscheint an jedem überlappten Tag des
     * Fensters; Statuswechsel bleiben bewusst fachliche Aktionen (kein Drag).
     *
     * @param  list<array{entry: DiaryEntry, dispatch: \App\Enums\Diary\DispatchStatus, sla: \App\Enums\ServiceTicket\SlaStatus, hasHardConflict: bool}>  $items
     * @return array{days: list<string>, rows: list<array{name: string, byDay: array<string, list<array{entry: DiaryEntry, dispatch: \App\Enums\Diary\DispatchStatus, sla: \App\Enums\ServiceTicket\SlaStatus, hasHardConflict: bool}>>}>}
     */
    public function calendar(array $items, CarbonImmutable $from, CarbonImmutable $to): array {
        $days = [];
        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $days[] = $cursor->toDateString();
        }

        /** @var array<int|string, array{name: string, byDay: array<string, list<mixed>>}> $rows */
        $rows = [];
        foreach ($items as $item) {
            $entry = $item['entry'];
            $startAt = $entry->start_at;
            if ($startAt === null) {
                continue;
            }

            $key = $entry->assigned_user_id ?? $entry->user_id ?? 0;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'name' => $entry->assignedUser->name ?? $entry->user->name ?? (string) __('Nicht zugewiesen'),
                    'byDay' => array_fill_keys($days, []),
                ];
            }

            $entryFrom = CarbonImmutable::parse((string) $startAt)->startOfDay();
            $entryTo = $entry->end_at !== null ? CarbonImmutable::parse((string) $entry->end_at)->startOfDay() : $entryFrom;
            for ($day = $entryFrom->greaterThan($from) ? $entryFrom : $from->startOfDay(); $day->lessThanOrEqualTo($entryTo) && $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $rows[$key]['byDay'][$day->toDateString()][] = $item;
            }
        }

        uasort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return ['days' => $days, 'rows' => array_values($rows)];
    }

    /**
     * Baut die Board-Items: je Auftrag Dispositionsstatus, SLA-Risiko und ob
     * harte Konflikte vorliegen. Das SLA-Risiko wird vorab je Kunde/Asset
     * gesammelt (kein N+1 pro Auftrag).
     *
     * @param  Collection<int, DiaryEntry>  $entries
     * @return list<BoardItem>
     */
    public function items(Collection $entries): array {
        $slaByCustomer = $this->slaRiskByCustomer($entries);

        $items = [];
        foreach ($entries as $entry) {
            $items[] = [
                'entry' => $entry,
                'dispatch' => $this->resolver->resolve($entry),
                'sla' => $this->slaFor($entry, $slaByCustomer),
                'hasHardConflict' => $this->hasHardConflict($entry),
            ];
        }

        return $items;
    }

    /**
     * Gruppiert Board-Items nach Dispositionsstatus (Spalten/Swimlanes in der
     * Reihenfolge der DispatchStatus-Fälle).
     *
     * @param  list<BoardItem>  $items
     * @return array<string, list<BoardItem>>
     */
    public function groupByDispatchStatus(array $items): array {
        $grouped = [];
        foreach (DispatchStatus::cases() as $status) {
            $grouped[$status->value] = [];
        }
        foreach ($items as $item) {
            $grouped[$item['dispatch']->value][] = $item;
        }

        return $grouped;
    }

    /**
     * Gruppiert Board-Items nach Mitarbeiter (assigned_user_id bevorzugt, sonst
     * user_id). Nicht zugewiesene Aufträge landen unter dem Schlüssel 0.
     *
     * @param  list<BoardItem>  $items
     * @return array<int, array{name: string, items: list<BoardItem>}>
     */
    public function groupByEmployee(array $items): array {
        $grouped = [];
        foreach ($items as $item) {
            $entry = $item['entry'];
            $user = $entry->assignedUser ?? $entry->user;
            $key = (int) ($user->id ?? 0);
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $user->name ?? '',
                    'items' => [],
                ];
            }
            $grouped[$key]['items'][] = $item;
        }
        ksort($grouped);

        return $grouped;
    }

    /**
     * Effektives SLA-Risiko eines Auftrags: schlechtester offener Ticket-Status
     * des zugehörigen Kunden (breached schlägt atRisk schlägt … schlägt none).
     *
     * @param  array<int, SlaStatus>  $slaByCustomer
     */
    public function slaFor(DiaryEntry $entry, array $slaByCustomer): SlaStatus {
        $customerId = (int) ($entry->customer_id ?? 0);

        return $slaByCustomer[$customerId] ?? SlaStatus::None;
    }

    /** Marker-Farbe für die Karte nach SLA-Risiko bzw. Dispositionsstatus. */
    public function markerColor(SlaStatus $sla, DispatchStatus $dispatch): string {
        return match ($sla) {
            SlaStatus::Breached => '#dc2626',
            SlaStatus::AtRisk => '#f59e0b',
            default => $this->dispatchColor($dispatch),
        };
    }

    /** Farbcode für einen Dispositionsstatus (Karten-Marker). */
    public function dispatchColor(DispatchStatus $dispatch): string {
        return match ($dispatch) {
            DispatchStatus::Unplanned => '#64748b',
            DispatchStatus::Planned => '#0d9488',
            DispatchStatus::Confirmed => '#2563eb',
            DispatchStatus::EnRoute => '#7c3aed',
            DispatchStatus::Done => '#16a34a',
        };
    }

    /**
     * Sammelt je Kunde den schlechtesten offenen SLA-Status seiner Tickets.
     * Nur Risiko-relevante Zustände (atRisk/breached) werden berücksichtigt —
     * erledigte/erfüllte Tickets erzeugen kein Risiko.
     *
     * @param  Collection<int, DiaryEntry>  $entries
     * @return array<int, SlaStatus>
     */
    private function slaRiskByCustomer(Collection $entries): array {
        $customerIds = $entries
            ->pluck('customer_id')
            ->filter()
            ->map(static fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($customerIds === []) {
            return [];
        }

        /** @var Collection<int, ServiceTicket> $tickets */
        $tickets = ServiceTicket::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('resolution_due_at')
            ->whereNull('resolved_at')
            ->get();

        $worst = [];
        foreach ($tickets as $ticket) {
            $customerId = (int) $ticket->customer_id;
            $status = $ticket->slaStatus();
            if (! in_array($status, [SlaStatus::AtRisk, SlaStatus::Breached], true)) {
                continue;
            }
            $current = $worst[$customerId] ?? SlaStatus::None;
            if ($this->slaSeverity($status) > $this->slaSeverity($current)) {
                $worst[$customerId] = $status;
            }
        }

        return $worst;
    }

    /** Rangordnung für „schlechtester" SLA-Status. */
    private function slaSeverity(SlaStatus $status): int {
        return match ($status) {
            SlaStatus::Breached => 3,
            SlaStatus::AtRisk => 2,
            SlaStatus::OnTrack, SlaStatus::Met => 1,
            SlaStatus::None => 0,
        };
    }

    /** Harter (blockierender) Dispositions-Konflikt für die aktuelle Zuweisung? */
    private function hasHardConflict(DiaryEntry $entry): bool {
        if ($entry->getAttribute('assigned_user_id') === null) {
            return false;
        }

        return $this->conflicts->check($entry)->hasErrors();
    }
}
