<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContinuedPaymentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Sickness;

use App\Models\Absence\SickLeave;
use App\Models\Platform\User;
use App\Support\Sickness\ContinuedPaymentStatus;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Collection;

/**
 * Lohnfortzahlungs-Status nach § 3 EntgFG (MVP-1093).
 *
 *  - Anspruch = `sickness.continued_pay_weeks` Wochen ≙ Kalendertage der
 *    Arbeitsunfähigkeit; Arbeitstage zwischen zwei Krankmeldungen zählen nie.
 *  - Verhinderungsfall: Krankmeldungen, die sich überschneiden, nahtlos
 *    anschließen oder Folgebescheinigung sind, bilden einen Fall — auch bei
 *    einer neuen Krankheit während der laufenden (Einheit des Verhinderungsfalls).
 *  - Fortsetzungserkrankung: Fälle, die über `continuation_of_id` dieselbe
 *    Krankheit nennen, teilen sich den Anspruch. Neu entsteht er nach
 *    `sickness.chain_reset_after_months` Monaten ohne Arbeitsunfähigkeit
 *    wegen dieser Krankheit oder zwölf Monate nach Beginn der ersten.
 *    Ohne Verweis beginnt jeder Fall mit vollem Anspruch.
 */
class ContinuedPaymentService {
    private const NEW_ENTITLEMENT_AFTER_MONTHS = 12;

    public function statusFor(User $user, ?CarbonInterface $reference = null): ContinuedPaymentStatus {
        $ref = CarbonImmutable::parse(($reference ?? CarbonImmutable::now())->toDateString());
        $entitlement = (int) config('sickness.continued_pay_weeks', 6) * 7;
        $resetMonths = (int) config('sickness.chain_reset_after_months', 6);

        /** @var Collection<int, SickLeave> $leaves */
        $leaves = SickLeave::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'start_date', 'end_date', 'follow_up_for_id', 'continuation_of_id']);

        $cases = $this->cases($leaves);
        $current = $this->caseAt($cases, $ref);
        if ($current === null) {
            return new ContinuedPaymentStatus(
                entitlementDays: $entitlement,
                usedDays: 0,
                remainingDays: $entitlement,
                chainStart: null,
                exhaustionDate: null,
                exhausted: false,
            );
        }

        $block = $this->entitlementBlock($cases, $current, $resetMonths);
        $days = [];
        foreach ($block as $index) {
            $days += $cases[$index]['days'];
        }
        $days = array_keys(array_filter($days, static fn (bool $_, string $day): bool => $day <= $ref->toDateString(), ARRAY_FILTER_USE_BOTH));
        sort($days);

        $used = count($days);
        $remaining = max(0, $entitlement - $used);
        $exhausted = $used >= $entitlement;
        $exhaustion = match (true) {
            $exhausted && $entitlement > 0 => CarbonImmutable::parse($days[$entitlement - 1]),
            $cases[$current]['end']->gte($ref) => $ref->addDays($remaining),
            default => null,
        };

        return new ContinuedPaymentStatus(
            entitlementDays: $entitlement,
            usedDays: $used,
            remainingDays: $remaining,
            chainStart: $cases[$block[0]]['start'],
            exhaustionDate: $exhaustion,
            exhausted: $exhausted,
        );
    }

    /**
     * Verhinderungsfälle in Beginn-Reihenfolge; `illness` verbindet Fälle
     * derselben Krankheit (Fortsetzungserkrankung).
     *
     * @param  Collection<int, SickLeave>  $leaves
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, days: array<string, true>, illness: int}>
     */
    private function cases(Collection $leaves): array {
        $cases = [];
        $caseOfLeave = [];
        $continues = [];

        foreach ($leaves as $leave) {
            $start = CarbonImmutable::parse($leave->start_date->toDateString());
            $end = CarbonImmutable::parse($leave->end_date->toDateString());

            $target = $leave->follow_up_for_id !== null ? ($caseOfLeave[$leave->follow_up_for_id] ?? null) : null;
            $last = array_key_last($cases);
            if ($target === null && $last !== null && $start->lte($cases[$last]['end']->addDay())) {
                $target = $last;
            }
            if ($target === null) {
                $cases[] = ['start' => $start, 'end' => $end, 'days' => [], 'illness' => count($cases)];
                $target = array_key_last($cases);
            }

            $case = &$cases[$target];
            $case['start'] = $case['start']->min($start);
            $case['end'] = $case['end']->max($end);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $case['days'][$day->toDateString()] = true;
            }
            unset($case);

            $caseOfLeave[(int) $leave->id] = $target;
            if ($leave->continuation_of_id !== null) {
                $continues[] = [$target, (int) $leave->continuation_of_id];
            }
        }

        foreach ($continues as [$case, $leaveId]) {
            $earlier = $caseOfLeave[$leaveId] ?? null;
            if ($earlier === null || $earlier === $case) {
                continue;
            }
            $from = $cases[$case]['illness'];
            $into = $cases[$earlier]['illness'];
            foreach ($cases as $index => $other) {
                if ($other['illness'] === $from) {
                    $cases[$index]['illness'] = $into;
                }
            }
        }

        return $cases;
    }

    /**
     * Laufender Fall am Stichtag, sonst der zuletzt vor ihm begonnene.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable, days: array<string, true>, illness: int}>  $cases
     */
    private function caseAt(array $cases, CarbonImmutable $ref): ?int {
        $found = null;
        foreach ($cases as $index => $case) {
            if ($case['start']->lte($ref)) {
                $found = $index;
            }
        }

        return $found;
    }

    /**
     * Fälle derselben Krankheit, die sich mit dem aktuellen einen Anspruch teilen.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable, days: array<string, true>, illness: int}>  $cases
     * @return non-empty-list<int>
     */
    private function entitlementBlock(array $cases, int $current, int $resetMonths): array {
        $block = [];
        $previous = null;
        foreach ($cases as $index => $case) {
            if ($case['illness'] !== $cases[$current]['illness'] || $index > $current) {
                continue;
            }
            $fresh = $previous !== null && (
                $cases[$previous]['end']->addMonths($resetMonths)->lte($case['start'])
                || $cases[$block[0]]['start']->addMonths(self::NEW_ENTITLEMENT_AFTER_MONTHS)->lte($case['start'])
            );
            $block = $fresh ? [$index] : [...$block, $index];
            $previous = $index;
        }

        return $block === [] ? [$current] : $block;
    }
}
