<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubFeePositionKind, ClubFeeProration, ClubGroupMembershipStatus};
use App\Enums\Finance\RecurringInterval;
use App\Models\Club\{ClubFeeAssignment, ClubFeeExemption, ClubFeeSurcharge, ClubFeeTariff, ClubFeeTariffRate, ClubGroupMembership, ClubMember};
use App\Models\Platform\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Collection;

/**
 * Beitragsberechnung (Feature 159, MVP-849) für einen Abrechnungsmonat: alle
 * Perioden, die in diesem Monat beginnen — je Tarifrhythmus und Anker. Ohne
 * Float-Arithmetik: Anteile als Decimal, Rundung je Position auf Cent.
 *
 * Regeln: Familientarif = eine Position je Beitragskonto und Periode
 * (Einzelnachlässe gelten dort nicht); Einzeltarif je Mitglied mit Nachlass;
 * Abteilungszuschlag je Mitglied mit aktiver Gruppenzuordnung; Aufnahmegebühr
 * einmal in der Eintrittsperiode. Anteilsregel je Satz: volle Periode oder
 * taggenau nach aktiven Kalendertagen (Zuordnung ∩ Mitgliedschaft ∩ Periode);
 * Befreiungen wirken nur ausdrücklich, eine Pause der Mitgliedschaft nicht.
 */
class ClubFeeCalculator {
    /**
     * @return array{positions: Collection<int, ClubFeePosition>, issues: list<array{member_id: int|null, account_id: int|null, message: string}>}
     */
    public function calculateMonth(Organization $organization, int $year, int $month): array {
        $monthStart = CarbonImmutable::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        // Perioden können bis zu ein Jahr laufen — Zuordnungen bis zum Ende des längsten Fensters laden.
        $window = $monthStart->addYear()->subDay();

        $assignments = ClubFeeAssignment::query()
            ->where('organization_id', $organization->id)
            ->overlapping($monthStart, $window)
            ->with(['member', 'account', 'tariff.rates'])
            ->get();
        $memberIds = $assignments->pluck('club_member_id')->unique()->values();
        $exemptions = ClubFeeExemption::query()
            ->where('organization_id', $organization->id)
            ->whereIn('club_member_id', $memberIds)
            ->get()
            ->groupBy('club_member_id');
        $surcharges = ClubFeeSurcharge::query()
            ->where('organization_id', $organization->id)
            ->where('valid_from', '<', DateRange::dayAfter($window))
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($monthStart)))
            ->with('department')
            ->get();
        $groupMemberships = $surcharges->isEmpty() ? collect() : ClubGroupMembership::query()
            ->where('organization_id', $organization->id)
            ->whereIn('club_member_id', $memberIds)
            ->where('status', ClubGroupMembershipStatus::Active->value)
            ->with('group:id,club_department_id')
            ->get()
            ->groupBy('club_member_id');

        $positions = collect();
        $issues = [];
        /** @var array<string, array{assignment: ClubFeeAssignment, rate: ClubFeeTariffRate, start: CarbonImmutable, end: CarbonImmutable, days: array<string, string>}> $families */
        $families = [];

        foreach ($assignments as $assignment) {
            /** @var ClubMember|null $member */
            $member = $assignment->member;
            /** @var ClubFeeTariff|null $tariff */
            $tariff = $assignment->tariff;
            if ($member === null || $tariff === null || $assignment->account === null) {
                $issues[] = ['member_id' => $assignment->club_member_id, 'account_id' => $assignment->club_fee_account_id, 'message' => (string) __('club.fees.issue.incomplete_assignment')];

                continue;
            }
            // Vorläufiger Satz zum Monatsbeginn bestimmt den Rhythmus; der Satz zum Periodenbeginn den Betrag.
            $probe = $tariff->rateOn($monthStart) ?? $tariff->rateOn($window);
            if ($probe === null) {
                $issues[] = ['member_id' => $member->id, 'account_id' => $assignment->club_fee_account_id, 'message' => (string) __('club.fees.issue.no_rate', ['tariff' => $tariff->name])];

                continue;
            }
            $period = $this->periodStartingIn($probe->interval, $probe->anchor_month, $year, $month);
            if ($period === null) {
                continue;
            }
            [$start, $end] = $period;
            $rate = $tariff->rateOn($start);
            if ($rate === null) {
                $issues[] = ['member_id' => $member->id, 'account_id' => $assignment->club_fee_account_id, 'message' => (string) __('club.fees.issue.no_rate', ['tariff' => $tariff->name])];

                continue;
            }

            $days = $this->activeDays($assignment, $member, $start, $end, $exemptions->get($member->id, collect()));
            if ($days === []) {
                continue;
            }

            if ($tariff->isFamily()) {
                $key = $assignment->club_fee_account_id . ':' . $tariff->id . ':' . $start->toDateString();
                if (! isset($families[$key])) {
                    $families[$key] = ['assignment' => $assignment, 'rate' => $rate, 'start' => $start, 'end' => $end, 'days' => $days];
                } else {
                    // Ein Tag zählt, sobald irgendein Familienmitglied aktiv ist (höchster Anteil gewinnt).
                    foreach ($days as $day => $percent) {
                        $current = $families[$key]['days'][$day] ?? null;
                        if ($current === null || Decimal::of($percent, 2)->greaterThan(Decimal::of($current, 2))) {
                            $families[$key]['days'][$day] = $percent;
                        }
                    }
                }
            } else {
                $amount = $this->prorate($rate->amount, $rate->proration, $days, $start, $end);
                $discount = $assignment->discountPercent();
                if (Decimal::of($discount, 2)->isPositive()) {
                    $amount = $amount->minusPercentage(self::numeric(Decimal::of($discount, 2)));
                }
                if (! $amount->isZero()) {
                    $positions->push(new ClubFeePosition(
                        ClubFeePositionKind::Base,
                        'assignment:' . $assignment->id . ':' . $start->toDateString(),
                        $assignment->club_fee_account_id,
                        $member->id,
                        $tariff->name,
                        $start,
                        $end,
                        $start->addDays($rate->due_days),
                        $amount,
                        $this->basis($rate, $days, $start, $end, $discount),
                    ));
                }
            }

            // Aufnahmegebühr einmal: in der Periode, die den Eintritt enthält.
            if ($rate->admission_fee !== null && ! $rate->admission_fee->isZero()
                && $member->joined_on->greaterThanOrEqualTo($start) && $member->joined_on->lessThanOrEqualTo($end)) {
                $positions->push(new ClubFeePosition(
                    ClubFeePositionKind::Admission,
                    'admission:' . $member->id,
                    $assignment->club_fee_account_id,
                    $member->id,
                    (string) __('club.fees.label.admission_fee'),
                    $start,
                    $end,
                    $start->addDays($rate->due_days),
                    $rate->admission_fee,
                    ['joined_on' => $member->joined_on->toDateString()],
                ));
            }

            // Abteilungszuschläge: aktive Gruppenzuordnung in einer Gruppe der Abteilung.
            foreach ($surcharges as $surcharge) {
                $surchargePeriod = $this->periodStartingIn($surcharge->interval, $surcharge->anchor_month, $year, $month);
                if ($surchargePeriod === null) {
                    continue;
                }
                [$sStart, $sEnd] = $surchargePeriod;
                if ($surcharge->valid_from->greaterThan($sEnd) || ($surcharge->valid_to !== null && $surcharge->valid_to->lessThan($sStart))) {
                    continue;
                }
                $groupDays = $this->departmentDays($groupMemberships->get($member->id, collect()), $surcharge->club_department_id, $sStart, $sEnd);
                if ($groupDays === []) {
                    continue;
                }
                $memberDays = $this->activeDays($assignment, $member, $sStart, $sEnd, $exemptions->get($member->id, collect()));
                $days = array_intersect_key($memberDays, $groupDays);
                if ($days === []) {
                    continue;
                }
                $amount = $this->prorate($surcharge->amount, $rate->proration, $days, $sStart, $sEnd);
                if ($amount->isZero()) {
                    continue;
                }
                $positions->push(new ClubFeePosition(
                    ClubFeePositionKind::Surcharge,
                    'surcharge:' . $surcharge->id . ':' . $member->id . ':' . $sStart->toDateString(),
                    $assignment->club_fee_account_id,
                    $member->id,
                    $surcharge->name,
                    $sStart,
                    $sEnd,
                    $sStart->addDays($rate->due_days),
                    $amount,
                    $this->basis($rate, $days, $sStart, $sEnd, '0') + ['department' => $surcharge->department?->name],
                ));
            }
        }

        // Meldegebühren (MVP-855): je aktiver Meldung mit Gebühr, fällig zum Wettkampftag, Konto aus der Beitragszuordnung des Mitglieds.
        $entries = \App\Models\Club\ClubCompetitionEntry::query()
            ->where('club_competition_entries.organization_id', $organization->id)
            ->whereIn('club_competition_entries.status', [\App\Enums\Club\ClubEntryStatus::Registered->value, \App\Enums\Club\ClubEntryStatus::NeedsReview->value])
            ->whereNotNull('club_competition_entries.fee_amount')
            ->join('events', 'events.id', '=', 'club_competition_entries.event_id')
            ->whereNull('events.cancelled_at')
            ->where('events.started_at', '>=', DateRange::dayStart($monthStart))
            ->where('events.started_at', '<', DateRange::dayAfter($monthEnd))
            ->with(['member', 'event:id,title,started_at'])
            ->get(['club_competition_entries.*']);
        foreach ($entries as $entry) {
            $member = $entry->member;
            $event = $entry->event;
            if ($member === null || $event === null || $entry->fee_amount === null || $entry->fee_amount->isZero()) {
                continue;
            }
            $day = CarbonImmutable::instance($event->started_at)->setTimezone(\App\Support\Tz::current())->startOfDay();
            $assignment = $assignments->first(fn(ClubFeeAssignment $a): bool => $a->club_member_id === $member->id && ! $day->lt($a->valid_from) && ($a->valid_to === null || ! $day->gt($a->valid_to)));
            if ($assignment === null) {
                $issues[] = ['member_id' => $member->id, 'account_id' => null, 'message' => (string) __('club.competitions.error.entry_without_account', ['name' => $member->fullName(), 'event' => (string) $event->title])];

                continue;
            }
            $positions->push(new ClubFeePosition(
                ClubFeePositionKind::Entry,
                'entry:' . $entry->id,
                $assignment->club_fee_account_id,
                $member->id,
                (string) __('club.competitions.label.entry_fee_position', ['title' => (string) $event->title, 'discipline' => $entry->discipline_code]),
                $day,
                $day,
                $day,
                $entry->fee_amount,
                ['event_id' => $event->id, 'discipline' => $entry->discipline_code],
            ));
        }
        foreach ($families as $key => $family) {
            $rate = $family['rate'];
            $amount = $this->prorate($rate->amount, $rate->proration, $family['days'], $family['start'], $family['end']);
            if ($amount->isZero()) {
                continue;
            }
            /** @var ClubFeeTariff $tariff */
            $tariff = $family['assignment']->tariff;
            $positions->push(new ClubFeePosition(
                ClubFeePositionKind::Family,
                'family:' . $key,
                $family['assignment']->club_fee_account_id,
                null,
                $tariff->name,
                $family['start'],
                $family['end'],
                $family['start']->addDays($rate->due_days),
                $amount,
                $this->basis($rate, $family['days'], $family['start'], $family['end'], '0'),
            ));
        }

        return ['positions' => $positions->sortBy(fn(ClubFeePosition $p): string => $p->accountId . '-' . ($p->memberId ?? 0) . '-' . $p->kind->value)->values(), 'issues' => $issues];
    }

    /**
     * Periode des Rhythmus, die im Monat beginnt — Anker legt den Startmonat
     * von Jahres-, Halbjahres- und Quartalsperioden fest; monatlich immer.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function periodStartingIn(RecurringInterval $interval, int $anchorMonth, int $year, int $month): ?array {
        $months = $interval->months();
        $anchor = max(1, min(12, $anchorMonth));
        if ($months > 1 && ((($month - $anchor) % $months) + $months) % $months !== 0) {
            return null;
        }
        $start = CarbonImmutable::createFromDate($year, $month, 1)->startOfDay();

        return [$start, $start->addMonthsNoOverflow($months)->subDay()];
    }

    /**
     * Zahlbare Anteile je aktivem Kalendertag (Datum → Prozent): Zuordnung ∩
     * Mitgliedschaft (Eintritt/Austritt) ∩ Periode, Befreiungen ziehen ab.
     *
     * @param  Collection<int, ClubFeeExemption>  $exemptions
     * @return array<string, string>
     */
    public function activeDays(ClubFeeAssignment $assignment, ClubMember $member, CarbonImmutable $start, CarbonImmutable $end, Collection $exemptions): array {
        $from = CarbonImmutable::instance($assignment->valid_from);
        $to = $assignment->valid_to !== null ? CarbonImmutable::instance($assignment->valid_to) : null;
        $joined = CarbonImmutable::instance($member->joined_on);
        $left = $member->left_on !== null ? CarbonImmutable::instance($member->left_on) : null;

        $rangeStart = CarbonImmutable::instance(max($start, $from, $joined));
        $rangeEnd = $end;
        foreach ([$to, $left] as $limit) {
            if ($limit !== null && $limit->lessThan($rangeEnd)) {
                $rangeEnd = $limit;
            }
        }
        if ($rangeStart->greaterThan($rangeEnd)) {
            return [];
        }

        $days = [];
        for ($day = $rangeStart; $day->lessThanOrEqualTo($rangeEnd); $day = $day->addDay()) {
            $percent = '100';
            foreach ($exemptions as $exemption) {
                if ($exemption->starts_on->lessThanOrEqualTo($day) && ($exemption->ends_on === null || $exemption->ends_on->greaterThanOrEqualTo($day))) {
                    $payable = $exemption->payablePercent();
                    if (Decimal::of($payable, 2)->lessThan(Decimal::of($percent, 2))) {
                        $percent = $payable;
                    }
                }
            }
            $days[$day->toDateString()] = $percent;
        }

        return $days;
    }

    /**
     * Betrag nach Anteilsregel: volle Periode (Befreiung nur bei lückenloser
     * Abdeckung) oder taggenau (Σ Tagesanteile / Periodentage), HalfUp auf Cent.
     *
     * @param  array<string, string>  $days
     */
    public function prorate(Money $amount, ClubFeeProration $proration, array $days, CarbonImmutable $start, CarbonImmutable $end): Money {
        $periodDays = (int) $start->diffInDays($end) + 1;
        if ($proration === ClubFeeProration::FullPeriod) {
            // Volle Periode: Anteil = kleinster über die gesamte Periode geltender Prozentsatz, sonst voll.
            if (count($days) < $periodDays) {
                return $amount;
            }
            $min = Decimal::of('100', 2);
            foreach ($days as $percent) {
                $min = Decimal::min($min, Decimal::of($percent, 2));
            }

            return $amount->percentage(self::numeric($min));
        }

        $sum = Decimal::zero(2);
        foreach ($days as $percent) {
            $sum = $sum->plus(Decimal::of($percent, 2));
        }
        // Σ Prozent / (100 × Periodentage) — erst dividieren, dann einmal runden.
        $factor = $sum->dividedBy(Decimal::of((string) ($periodDays * 100), 0), 10);

        return $amount->times(self::numeric($factor));
    }

    /**
     * Decimal als numerischer String für die Money-Arithmetik (Typvertrag, keine Float-Umwandlung).
     *
     * @return numeric-string
     */
    private static function numeric(Decimal $value): string {
        return $value->getValue();
    }

    /**
     * Tage mit aktiver Zuordnung zu einer Gruppe der Abteilung (Datum → 100).
     *
     * @param  Collection<int, ClubGroupMembership>  $memberships
     * @return array<string, string>
     */
    private function departmentDays(Collection $memberships, int $departmentId, CarbonImmutable $start, CarbonImmutable $end): array {
        $days = [];
        foreach ($memberships as $membership) {
            if ((int) ($membership->group->club_department_id ?? 0) !== $departmentId) {
                continue;
            }
            $from = CarbonImmutable::instance(max($start, CarbonImmutable::instance($membership->valid_from)));
            $to = $membership->valid_to !== null ? CarbonImmutable::instance(min($end, CarbonImmutable::instance($membership->valid_to))) : $end;
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $days[$day->toDateString()] = '100';
            }
        }

        return $days;
    }

    /**
     * @param  array<string, string>  $days
     * @return array<string, mixed>
     */
    private function basis(ClubFeeTariffRate $rate, array $days, CarbonImmutable $start, CarbonImmutable $end, string $discount): array {
        $full = count(array_filter($days, static fn(string $p): bool => $p === '100'));

        return [
            'interval' => $rate->interval->value,
            'proration' => $rate->proration->value,
            'period_days' => (int) $start->diffInDays($end) + 1,
            'active_days' => count($days),
            'full_days' => $full,
            'discount_percent' => $discount,
            'rate_amount' => $rate->amount->getAmount(),
        ];
    }
}
