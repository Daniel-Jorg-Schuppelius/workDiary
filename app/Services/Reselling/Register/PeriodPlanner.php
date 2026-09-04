<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodPlanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Plant die erwarteten Abrechnungsperioden eines Abos (Feature 152) und
 * gleicht sie idempotent mit der Tabelle ab: fehlende Perioden entstehen,
 * offene Perioden folgen Änderungen an Menge, Preis oder Ende, entschiedene
 * Perioden (berechnet, teilweise, verzichtet, strittig) bleiben unberührt.
 *
 * Regeln aus Feature 151: Periodenlänge = Abrechnungsintervall; ein Rest am
 * Laufzeitende unter der Mindestlänge des Intervalls ist ein Ausrichtungs-
 * Stummel (Co-Term) und keine Periode. Geplant wird bis HORIZON_DAYS in die
 * Zukunft, damit die nächste Verlängerung sichtbar ist, ohne Jahre voraus
 * anzulegen.
 */
final class PeriodPlanner {
    public const HORIZON_DAYS = 90;

    private const MAX_PERIODS = 600;

    /**
     * Geplante Perioden (Beginn, Ende), ohne Datenbank.
     *
     * @return list<array{starts_on: CarbonImmutable, ends_on: CarbonImmutable}>
     */
    public function plan(ResaleSubscription $subscription, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $horizon = $reference->addDays(self::HORIZON_DAYS);
        $frequency = $subscription->interval;
        $endsOn = $subscription->ends_on;
        // Beendete und abgelöste Abos behalten ihre Vergangenheit — genau die
        // Perioden, deren Abrechnung zu prüfen ist. Ohne bekanntes Ende endet
        // ihre Planung am Stichtag statt zu wachsen.
        if (! $subscription->status->isPlanning() && $endsOn === null) {
            $endsOn = $reference;
        }
        $start = $subscription->starts_on;
        $periods = [];

        while (count($periods) < self::MAX_PERIODS) {
            if ($endsOn !== null && ! $start->lessThan($endsOn)) {
                break;
            }
            if ($start->greaterThan($horizon)) {
                break;
            }
            $next = $frequency->advance($start);
            // ends_on ist der letzte Tag des Abos (inklusiv), $next der erste Tag der Folgeperiode.
            $boundary = $endsOn !== null && $endsOn->addDay()->lessThan($next) ? $endsOn->addDay() : $next;
            $end = $boundary->subDay();
            $days = (int) $start->diffInDays($end) + 1;
            if ($endsOn !== null && $next->greaterThan($endsOn->addDay()) && $days < $frequency->minimumPeriodDays()) {
                break; // Co-Term-Stummel
            }
            $periods[] = ['starts_on' => $start, 'ends_on' => $end];
            $start = $next;
        }

        return $periods;
    }

    /**
     * Perioden mit der Datenbank abgleichen.
     *
     * @return array{created: int, updated: int, removed: int, kept: int}
     */
    public function sync(ResaleSubscription $subscription, ?CarbonImmutable $reference = null): array {
        $planned = $this->plan($subscription, $reference);
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 0];

        DB::transaction(function () use ($subscription, $planned, &$result): void {
            /** @var array<string, ResalePeriod> $existing */
            $existing = [];
            foreach ($subscription->periods()->get() as $period) {
                $existing[$period->starts_on->toDateString()] = $period;
            }
            // Auf Cent runden: die Periode speichert zwei Nachkommastellen, der
            // Stückpreis vier — sonst gilt jede Neuplanung als Änderung.
            $expectedSale = $subscription->expectedSalePerPeriod()?->withScale(2);
            $expectedPurchase = $subscription->expectedPurchasePerPeriod()?->withScale(2);

            $seen = [];
            foreach ($planned as $slot) {
                $key = $slot['starts_on']->toDateString();
                $seen[$key] = true;
                $period = $existing[$key] ?? null;
                if ($period === null) {
                    ResalePeriod::query()->create([
                        'organization_id' => $subscription->organization_id,
                        'subscription_id' => $subscription->id,
                        'starts_on' => $slot['starts_on'],
                        'ends_on' => $slot['ends_on'],
                        'quantity' => $subscription->quantity,
                        'expected_purchase' => $expectedPurchase,
                        'expected_sale' => $expectedSale,
                        'currency' => $subscription->currency,
                        'status' => PeriodStatus::Open,
                    ]);
                    $result['created']++;

                    continue;
                }
                if ($period->status->isDecided()) {
                    $result['kept']++;

                    continue;
                }
                $period->fill([
                    'ends_on' => $slot['ends_on'],
                    'quantity' => $subscription->quantity,
                    'expected_purchase' => $expectedPurchase,
                    'expected_sale' => $expectedSale,
                    'currency' => $subscription->currency,
                ]);
                if ($period->isDirty()) {
                    $period->save();
                    $result['updated']++;
                } else {
                    $result['kept']++;
                }
            }

            foreach ($existing as $key => $period) {
                if (isset($seen[$key])) {
                    continue;
                }
                if ($period->status->isDecided()) {
                    $result['kept']++; // Entscheidung bleibt, auch wenn das Abo verkürzt wurde

                    continue;
                }
                $period->delete();
                $result['removed']++;
            }
        });

        $subscription->unsetRelation('periods');

        return $result;
    }
}
