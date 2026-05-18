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

use App\Models\SickLeave;
use App\Models\User;
use App\Support\Sickness\ContinuedPaymentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Berechnet den Lohnfortzahlungs-Status nach § 3 EntgFG.
 *
 * Regelwerk:
 *  - Anspruch = `sickness.continued_pay_weeks` Wochen ≙ Kalendertage.
 *  - Eine "Krankheits-Episode" umfasst zusammenhängende SickLeave-Einträge
 *    (Lücke ≤ 1 Tag oder explizite Folgebescheinigung via follow_up_for_id).
 *  - Reset: Liegen zwischen dem letzten arbeitsunfähigen Tag und dem neuen
 *    Beginn mindestens `sickness.chain_reset_after_months` Monate, beginnt
 *    der Anspruch neu (vereinfachte Heuristik ohne Diagnose-Vergleich).
 */
class ContinuedPaymentService
{
    public function statusFor(User $user, ?CarbonInterface $reference = null): ContinuedPaymentStatus
    {
        $ref = CarbonImmutable::parse(($reference ?? CarbonImmutable::now())->toDateString());
        $entitlement = (int) config('sickness.continued_pay_weeks', 6) * 7;
        $resetMonths = (int) config('sickness.chain_reset_after_months', 6);

        /** @var Collection<int, SickLeave> $leaves */
        $leaves = SickLeave::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->orderBy('start_date')
            ->get();

        if ($leaves->isEmpty()) {
            return new ContinuedPaymentStatus(
                entitlementDays: $entitlement,
                usedDays: 0,
                remainingDays: $entitlement,
                chainStart: null,
                exhaustionDate: null,
                exhausted: false,
            );
        }

        $episodes = $this->groupEpisodes($leaves, $resetMonths);

        // Aktive Episode am Stichtag — sonst zuletzt abgeschlossene vor dem Stichtag.
        $current = $this->episodeContaining($episodes, $ref);
        if ($current === null) {
            $current = $this->lastEpisodeBefore($episodes, $ref);
        }
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

        $chainStart = $current['start'];
        // Bei einer aktiven Episode zählen wir nur bis zum Stichtag.
        $endForCount = $current['end']->greaterThan($ref) ? $ref : $current['end'];
        $used = (int) $chainStart->diffInDays($endForCount) + 1;
        $used = max(0, min($used, $entitlement * 2));
        $remaining = max(0, $entitlement - $used);
        $exhaustion = $chainStart->copy()->addDays($entitlement - 1);

        return new ContinuedPaymentStatus(
            entitlementDays: $entitlement,
            usedDays: $used,
            remainingDays: $remaining,
            chainStart: $chainStart,
            exhaustionDate: $exhaustion,
            exhausted: $used >= $entitlement,
        );
    }

    /**
     * @param  Collection<int, SickLeave>  $leaves
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function groupEpisodes(Collection $leaves, int $resetMonths): array
    {
        /** @var list<array{start: CarbonImmutable, end: CarbonImmutable}> $episodes */
        $episodes = [];
        $current = null;

        foreach ($leaves as $leave) {
            $start = CarbonImmutable::parse($leave->start_date->toDateString());
            $end = CarbonImmutable::parse($leave->end_date->toDateString());

            if ($current === null) {
                $current = ['start' => $start, 'end' => $end];

                continue;
            }

            $gapDays = (int) $current['end']->diffInDays($start);
            $monthsApart = (int) $current['end']->diffInMonths($start);
            $isFollowUp = $leave->follow_up_for_id !== null;

            if ($isFollowUp || $gapDays <= 1) {
                if ($end->greaterThan($current['end'])) {
                    $current['end'] = $end;
                }

                continue;
            }

            if ($monthsApart >= $resetMonths) {
                $episodes[] = $current;
                $current = ['start' => $start, 'end' => $end];

                continue;
            }

            // Lücke kürzer als Reset-Frist, aber keine Folgebescheinigung → weiterhin
            // dieselbe Anspruchs-Episode (konservative Auslegung; Diagnose unbekannt).
            if ($end->greaterThan($current['end'])) {
                $current['end'] = $end;
            }
        }

        if ($current !== null) {
            $episodes[] = $current;
        }

        return $episodes;
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $episodes
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function episodeContaining(array $episodes, CarbonImmutable $ref): ?array
    {
        foreach ($episodes as $ep) {
            if ($ref->betweenIncluded($ep['start'], $ep['end'])) {
                return $ep;
            }
        }

        return null;
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $episodes
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function lastEpisodeBefore(array $episodes, CarbonImmutable $ref): ?array
    {
        $found = null;
        foreach ($episodes as $ep) {
            if ($ep['end']->lessThanOrEqualTo($ref)) {
                $found = $ep;
            }
        }

        return $found;
    }
}
