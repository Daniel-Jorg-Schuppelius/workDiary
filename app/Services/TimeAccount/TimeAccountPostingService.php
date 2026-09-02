<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountPostingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeAccount;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\TimeAccount\{CarryoverPolicy, TimeAccountSource};
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attendance, ExternalWageItem, Organization, ScheduledShift, SickLeave, TimeAccount, TimeAccountEntry, TimeAccountRule, User, Vacation};
use App\Models\Surcharge\TimeRuleResult;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Bebucht Zeitkonten aus dem Bestand (MVP-526): `time_rule_results`,
 * Anwesenheits-Netto, genehmigte Abwesenheiten, Dienst-Zähler und externe
 * Positionen — deklarativ über {@see TimeAccountRule}. Das Journal ist
 * append-only: Idempotenz über (Konto, Quelle, Quell-ID, Buchungstag);
 * stornierte Buchungen geben den Slot wieder frei (Repost). Korrekturen
 * laufen als Storno-Gegenbuchung, nie als Update.
 */
final class TimeAccountPostingService {
    /** Quell-Kennung der Kappungsbuchung beim Monatsabschluss. */
    public const SOURCE_CAP = 'cap';

    /**
     * @return array{posted: int, skipped: int, capped: int}
     */
    public function postRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $accounts = TimeAccount::query()
            ->where('organization_id', $organization->getKey())
            ->where('is_active', true)
            ->with('rules')
            ->get()
            ->filter(fn (TimeAccount $a): bool => $this->accountActiveInRange($a, $from, $to));

        $stats = ['posted' => 0, 'skipped' => 0, 'capped' => 0];
        /** @var array<int, array<int, bool>> $touched */
        $touched = [];

        foreach ($accounts as $account) {
            foreach ($account->rules as $rule) {
                foreach ($this->candidates($organization, $rule, $from, $to) as $candidate) {
                    if (! $rule->appliesOn(new \DateTimeImmutable($candidate['booking_date']))) {
                        continue;
                    }
                    if ($this->slotOccupied($account, $candidate)) {
                        $stats['skipped']++;

                        continue;
                    }

                    TimeAccountEntry::query()->create([
                        'organization_id' => $organization->getKey(),
                        'time_account_id' => $account->getKey(),
                        'user_id' => $candidate['user_id'],
                        'booking_date' => $candidate['booking_date'],
                        'quantity' => round($candidate['quantity'] * (float) $rule->factor, 2),
                        'source_type' => $rule->source_type->value,
                        'source_id' => $candidate['source_id'],
                        'note' => null,
                        'posted_by' => null,
                    ]);
                    $touched[(int) $account->getKey()][$candidate['user_id']] = true;
                    $stats['posted']++;
                }
            }
        }

        $stats['capped'] = $this->applyMonthlyCaps($organization, $accounts, $to, $touched);

        foreach ($touched as $accountId => $users) {
            $this->rebuildBalances((int) $accountId, array_map(intval(...), array_keys($users)));
        }

        return $stats;
    }

    /**
     * Manuelle Sonderbuchung (Q1 „Sonderbuchungen") — auditiert.
     */
    public function manualEntry(TimeAccount $account, User $target, CarbonImmutable $date, float $quantity, string $note, User $actor): TimeAccountEntry {
        $entry = TimeAccountEntry::query()->create([
            'organization_id' => $account->organization_id,
            'time_account_id' => $account->getKey(),
            'user_id' => $target->getKey(),
            'booking_date' => $date->toDateString(),
            'quantity' => round($quantity, 2),
            'source_type' => null,
            'source_id' => null,
            'note' => $note,
            'posted_by' => $actor->getKey(),
        ]);
        $entry->audit('timeAccount.manualEntry', [
            'actor_user_id' => (int) $actor->getKey(),
            'account' => $account->code,
            'user_id' => (int) $target->getKey(),
            'quantity' => $entry->quantity,
            'note' => $note,
        ]);
        $this->rebuildBalances((int) $account->getKey(), [(int) $target->getKey()]);

        return $entry;
    }

    /**
     * Storno-Gegenbuchung (append-only, auditiert) — gibt den Quell-Slot
     * für einen späteren Repost wieder frei.
     */
    public function reverseEntry(TimeAccountEntry $entry, User $actor, string $note): TimeAccountEntry {
        $reversal = TimeAccountEntry::query()->create([
            'organization_id' => $entry->organization_id,
            'time_account_id' => $entry->time_account_id,
            'user_id' => $entry->user_id,
            'booking_date' => $entry->booking_date->toDateString(),
            'quantity' => round(-1 * (float) $entry->quantity, 2),
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'note' => $note,
            'posted_by' => $actor->getKey(),
            'reversal_of_id' => $entry->getKey(),
        ]);
        $reversal->audit('timeAccount.reversed', [
            'actor_user_id' => (int) $actor->getKey(),
            'entry_id' => (int) $entry->getKey(),
            'note' => $note,
        ]);
        $this->rebuildBalances((int) $entry->time_account_id, [(int) $entry->user_id]);

        return $reversal;
    }

    /**
     * Monatsstände (Umsatz + kumulierter Stand) aus dem Journal neu aufbauen.
     *
     * @param  list<int>  $userIds
     */
    public function rebuildBalances(int $accountId, array $userIds): void {
        $account = TimeAccount::query()->withoutGlobalScopes()->findOrFail($accountId);

        // MariaDB-Zweig: strftime existiert nur in SQLite.
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $yearExpr = $driver === 'mysql' ? 'YEAR(booking_date)' : "CAST(strftime('%Y', booking_date) AS INTEGER)";
        $monthExpr = $driver === 'mysql' ? 'MONTH(booking_date)' : "CAST(strftime('%m', booking_date) AS INTEGER)";

        foreach ($userIds as $userId) {
            $rows = \Illuminate\Support\Facades\DB::table('time_account_entries')
                ->where('time_account_id', $accountId)
                ->where('user_id', $userId)
                ->selectRaw("{$yearExpr} as y, {$monthExpr} as m, SUM(quantity) as turnover")
                ->groupBy('y', 'm')
                ->orderBy('y')
                ->orderBy('m')
                ->get();

            $running = 0.0;
            foreach ($rows as $row) {
                $running += (float) $row->turnover;
                \App\Models\TimeAccountBalance::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'time_account_id' => $accountId,
                        'user_id' => $userId,
                        'year' => (int) $row->y,
                        'month' => (int) $row->m,
                    ],
                    [
                        'organization_id' => $account->organization_id,
                        'turnover' => round((float) $row->turnover, 2),
                        'balance' => round($running, 2),
                        'computed_at' => now(),
                    ],
                );
            }
        }
    }

    // ── intern ───────────────────────────────────────────────────────────

    /**
     * Kandidaten-Buchungen einer Regel im Zeitraum.
     *
     * @return list<array{user_id: int, booking_date: string, quantity: float, source_id: ?int}>
     */
    private function candidates(Organization $organization, TimeAccountRule $rule, CarbonImmutable $from, CarbonImmutable $to): array {
        $orgId = (int) $organization->getKey();
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $out = [];

        switch ($rule->source_type) {
            case TimeAccountSource::WageType:
                TimeRuleResult::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereBetween('date', [$fromStr, $toStr])
                    ->get(['id', 'user_id', 'date', 'minutes', 'wage_type_code'])
                    ->each(function (TimeRuleResult $r) use (&$out, $rule): void {
                        if ($rule->match_value !== null && ! Str::is($rule->match_value, $r->wage_type_code)) {
                            return;
                        }
                        $out[] = [
                            'user_id' => (int) $r->user_id,
                            'booking_date' => $r->date->toDateString(),
                            'quantity' => (float) $r->minutes,
                            'source_id' => (int) $r->id,
                        ];
                    });
                break;

            case TimeAccountSource::AttendanceNet:
                Attendance::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereBetween('date', [$fromStr, $toStr])
                    ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
                    ->where('duration_minutes', '>', 0)
                    ->get(['id', 'user_id', 'date', 'duration_minutes'])
                    ->each(function (Attendance $a) use (&$out): void {
                        $out[] = [
                            'user_id' => (int) $a->user_id,
                            'booking_date' => $a->date?->toDateString() ?? '',
                            'quantity' => (float) $a->duration_minutes,
                            'source_id' => (int) $a->id,
                        ];
                    });
                break;

            case TimeAccountSource::Absence:
                // Urlaubsarten je Tag (match_value = VacationType-Wert) bzw. 'sick'.
                if ($rule->match_value === 'sick') {
                    SickLeave::query()
                        ->withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->where('start_date', '<', DateRange::dayAfter($toStr))
                        ->where('end_date', '>=', DateRange::day($fromStr))
                        ->get(['id', 'user_id', 'start_date', 'end_date'])
                        ->each(function (SickLeave $s) use (&$out, $from, $to): void {
                            foreach ($this->expandDays($s->start_date->toDateString(), $s->end_date->toDateString(), $from, $to) as $day) {
                                $out[] = ['user_id' => (int) $s->user_id, 'booking_date' => $day, 'quantity' => 1.0, 'source_id' => (int) $s->id];
                            }
                        });
                    break;
                }
                Vacation::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('status', VacationStatus::Approved->value)
                    ->when($rule->match_value !== null, fn ($q) => $q->where('type', $rule->match_value))
                    ->where('start_date', '<', DateRange::dayAfter($toStr))
                    ->where('end_date', '>=', DateRange::day($fromStr))
                    ->get(['id', 'user_id', 'start_date', 'end_date'])
                    ->each(function (Vacation $v) use (&$out, $from, $to): void {
                        foreach ($this->expandDays($v->start_date->toDateString(), $v->end_date->toDateString(), $from, $to) as $day) {
                            $out[] = ['user_id' => (int) $v->user_id, 'booking_date' => $day, 'quantity' => 1.0, 'source_id' => (int) $v->id];
                        }
                    });
                break;

            case TimeAccountSource::ShiftTypeCount:
                // Nur geleistete (vergangene bzw. heutige) Dienste zählen.
                $countTo = min($to->toDateString(), CarbonImmutable::now()->toDateString());
                ScheduledShift::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereBetween('date', [$fromStr, $countTo])
                    ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
                    ->when($rule->match_value !== null, fn ($q) => $q->where('shift_type_id', (int) $rule->match_value))
                    ->get(['id', 'user_id', 'date'])
                    ->each(function (ScheduledShift $s) use (&$out): void {
                        $out[] = [
                            'user_id' => (int) $s->user_id,
                            'booking_date' => $s->date->toDateString(),
                            'quantity' => 1.0,
                            'source_id' => (int) $s->id,
                        ];
                    });
                break;

            case TimeAccountSource::ExternalItem:
                ExternalWageItem::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereBetween('item_date', [$fromStr, $toStr])
                    ->get(['id', 'user_id', 'item_date', 'wage_type_code', 'quantity'])
                    ->each(function (ExternalWageItem $i) use (&$out, $rule): void {
                        if ($rule->match_value !== null && ! Str::is($rule->match_value, $i->wage_type_code)) {
                            return;
                        }
                        $out[] = [
                            'user_id' => (int) $i->user_id,
                            'booking_date' => $i->item_date->toDateString(),
                            'quantity' => (float) $i->quantity,
                            'source_id' => (int) $i->id,
                        ];
                    });
                break;
        }

        return $out;
    }

    /**
     * Slot belegt? Eine nicht-stornierte Buchung mit gleichem Quell-Bezug
     * am selben Tag blockiert; ein Storno gibt den Slot frei.
     *
     * @param  array{user_id: int, booking_date: string, quantity: float, source_id: ?int}  $candidate
     */
    private function slotOccupied(TimeAccount $account, array $candidate): bool {
        return TimeAccountEntry::query()
            ->withoutGlobalScopes()
            ->where('time_account_id', $account->getKey())
            ->where('user_id', $candidate['user_id'])
            ->whereBetween('booking_date', DateRange::days($candidate['booking_date'], $candidate['booking_date']))
            ->where('source_id', $candidate['source_id'])
            ->whereNull('reversal_of_id')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('time_account_entries as rev')
                    ->whereColumn('rev.reversal_of_id', 'time_account_entries.id');
            })
            ->exists();
    }

    /**
     * Kappung beim Monatsabschluss (Q1 „Abschlussbuchungen"): für cap-Konten
     * wird je abgeschlossenem Monat der über `cap_amount` liegende Anteil per
     * Gegenbuchung am Monatsletzten verworfen — idempotent je Konto/Monat.
     *
     * @param  \Illuminate\Support\Collection<int, TimeAccount>  $accounts
     * @param  array<int, array<int, bool>>  $touched
     */
    private function applyMonthlyCaps(Organization $organization, $accounts, CarbonImmutable $to, array &$touched): int {
        $capped = 0;
        $lastClosedMonth = CarbonImmutable::now()->startOfMonth()->subDay(); // Ende des Vormonats

        foreach ($accounts as $account) {
            if ($account->carryover_policy !== CarryoverPolicy::Cap || $account->cap_amount === null) {
                continue;
            }
            $cap = (float) $account->cap_amount;

            $userIds = TimeAccountEntry::query()
                ->withoutGlobalScopes()
                ->where('time_account_id', $account->getKey())
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $userId = (int) $userId;
                $monthEnd = $lastClosedMonth;
                $monthKey = (int) $monthEnd->format('Ym');

                $exists = TimeAccountEntry::query()
                    ->withoutGlobalScopes()
                    ->where('time_account_id', $account->getKey())
                    ->where('user_id', $userId)
                    ->where('source_type', self::SOURCE_CAP)
                    ->where('source_id', $monthKey)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $balance = (float) TimeAccountEntry::query()
                    ->withoutGlobalScopes()
                    ->where('time_account_id', $account->getKey())
                    ->where('user_id', $userId)
                    ->where('booking_date', '<', DateRange::dayAfter($monthEnd))
                    ->sum('quantity');
                if ($balance <= $cap) {
                    continue;
                }

                TimeAccountEntry::query()->create([
                    'organization_id' => $organization->getKey(),
                    'time_account_id' => $account->getKey(),
                    'user_id' => $userId,
                    'booking_date' => $monthEnd->toDateString(),
                    'quantity' => round($cap - $balance, 2),
                    'source_type' => self::SOURCE_CAP,
                    'source_id' => $monthKey,
                    'note' => (string) __('Kappung beim Monatsabschluss auf :cap.', ['cap' => $account->unit->format($cap)]),
                    'posted_by' => null,
                ]);
                $touched[(int) $account->getKey()][$userId] = true;
                $capped++;
            }
        }

        return $capped;
    }

    private function accountActiveInRange(TimeAccount $account, CarbonImmutable $from, CarbonImmutable $to): bool {
        if ($account->valid_from !== null && $account->valid_from->format('Y-m-d') > $to->toDateString()) {
            return false;
        }

        return $account->valid_until === null || $account->valid_until->format('Y-m-d') >= $from->toDateString();
    }

    /** @return list<string> Tage eines Zeitraums, beschnitten auf [from, to]. */
    private function expandDays(string $start, string $end, CarbonImmutable $from, CarbonImmutable $to): array {
        $cursor = CarbonImmutable::parse($start);
        $cursor = $cursor->lessThan($from) ? $from->startOfDay() : $cursor;
        $limit = CarbonImmutable::parse($end);
        $limit = $limit->greaterThan($to) ? $to->startOfDay() : $limit;

        $days = [];
        while ($cursor->lessThanOrEqualTo($limit)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
