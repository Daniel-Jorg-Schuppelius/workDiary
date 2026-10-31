<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolderTakeover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Übernahme durch eine andere Firma ab Stichtag (Phase 166, MVP-1451): eine
 * Firma geht insolvent, eine andere führt ihre Lizenzen weiter. Ab dem
 * Stichtag rechnet der neue Halter ab; offene Perioden davor werden auf Wunsch
 * als Forderungsausfall verzichtet. Der Stichtag entscheidet, wer die offenen
 * Rechnungen trägt: am Beginn der ersten offenen Periode übernimmt der
 * Nachfolger sie, später gewählt bleiben sie beim bisherigen Halter.
 *
 * - Stichtag am oder vor Abo-Beginn: das ganze Abo wechselt den Halter.
 * - Vertrag: Abtretung aller im Zeitraum freien Lizenzen ab Stichtag.
 * - Abtretung: sie endet am Vortag, eine neue Abtretung derselben Menge am
 *   Vertrag beginnt am Stichtag.
 */
final class HolderTakeover {
    public const AUDIT = 'resale_subscription.taken_over';

    private readonly PeriodPlanner $planner;

    private readonly LicenceAssignments $assignments;

    public function __construct(?PeriodPlanner $planner = null, ?LicenceAssignments $assignments = null) {
        $this->planner = $planner ?? new PeriodPlanner;
        $this->assignments = $assignments ?? new LicenceAssignments($this->planner);
    }

    /** Lizenzen, die ab dem Stichtag an den neuen Halter gehen (0: am Vertrag schon alles abgetreten). */
    public static function quantityFrom(ResaleSubscription $subscription, CarbonImmutable $from): int {
        if (self::isWhole($subscription, $from) || $subscription->isAssignment()) {
            return $subscription->quantity;
        }
        $subscription->loadMissing('assignments');

        return $subscription->transferableQuantityBetween($from, $subscription->ends_on);
    }

    public static function isWhole(ResaleSubscription $subscription, CarbonImmutable $from): bool {
        return ! $from->greaterThan($subscription->starts_on);
    }

    /**
     * Offene oder teilweise gedeckte, fällige Perioden des bisherigen Halters vor dem Stichtag.
     *
     * @return list<ResalePeriod>
     */
    public static function openBefore(ResaleSubscription $subscription, CarbonImmutable $from): array {
        $today = ResalePeriod::today();

        return array_values($subscription->periods()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', $from->toDateString())
            ->where('starts_on', '<', DateRange::dayAfter($today))
            ->get()
            ->all());
    }

    /**
     * Vorgabe für den Stichtag: Beginn der ersten offenen fälligen Periode — dann
     * übernimmt der Nachfolger die offenen Rechnungen —, sonst der nächsten Periode.
     */
    public static function defaultFrom(ResaleSubscription $subscription): CarbonImmutable {
        $today = ResalePeriod::today();
        $open = $subscription->periods()->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($today))->orderBy('starts_on')->first();
        if ($open !== null) {
            return $open->starts_on;
        }
        $next = $subscription->periods()->where('starts_on', '>=', DateRange::dayAfter($today))->orderBy('starts_on')->first();

        return $next->starts_on ?? $today;
    }

    /**
     * @param  array{customer: Customer, foreign: ForeignCustomer|null}  $holder
     * @return array{subscription: ResaleSubscription, whole: bool, waived: int}
     */
    public function apply(ResaleSubscription $subscription, array $holder, CarbonImmutable $from, bool $waiveBefore, ?string $note, ?int $userId): array {
        $before = ['customer_id' => $subscription->customer_id, 'foreign_customer_id' => $subscription->foreign_customer_id, 'label' => $subscription->holderLabel()];
        $newLabel = $holder['foreign'] !== null ? $holder['foreign']->name : $holder['customer']->name;

        return DB::transaction(function () use ($subscription, $holder, $from, $waiveBefore, $note, $userId, $before, $newLabel): array {
            $whole = self::isWhole($subscription, $from);
            if ($whole) {
                $subscription->forceFill([
                    'customer_id' => $holder['foreign'] === null ? $holder['customer']->id : null,
                    'foreign_customer_id' => $holder['foreign']?->id,
                    'is_own_holding' => false,
                    'company_name' => $newLabel,
                ])->save();
                $target = $subscription;
            } elseif ($subscription->isAssignment() && $subscription->parent !== null) {
                $contract = $subscription->parent;
                $endsOn = $subscription->ends_on;
                $subscription->forceFill(['ends_on' => $from->subDay()->toDateString()])->save();
                $this->planner->sync($subscription);
                $target = $this->assignments->create($contract, $holder, $subscription->quantity, $from->toDateString(), $endsOn?->toDateString(), $subscription->sale_unit_price?->getAmount(), $note, $userId);
            } else {
                $target = $this->assignments->create($subscription, $holder, self::quantityFrom($subscription, $from), $from->toDateString(), $subscription->ends_on?->toDateString(), $subscription->sale_unit_price?->getAmount(), $note, $userId);
            }

            $waived = 0;
            if ($waiveBefore && ! $whole) {
                $reason = (string) __('resale.takeover.reason', ['holder' => $before['label'], 'customer' => $newLabel, 'date' => $from->format('d.m.Y')]);
                foreach (self::openBefore($subscription, $from) as $period) {
                    // Vorschläge sind keine Entscheidung: sie gehen mit dem Verzicht weg (wie im Sammelverzicht).
                    $period->links()->where('origin', LinkOrigin::Proposed->value)->delete();
                    $period->forceFill(['status' => PeriodStatus::Waived, 'waived_reason' => mb_substr($reason, 0, 255), 'decided_by_user_id' => $userId, 'decided_at' => now()])->save();
                    $period->audit('resale_period.waived', ['reason' => $reason]);
                    $waived++;
                }
            }
            $subscription->audit(self::AUDIT, [
                'from' => $from->toDateString(),
                'before' => ['customer_id' => $before['customer_id'], 'foreign_customer_id' => $before['foreign_customer_id']],
                'after' => ['customer_id' => $holder['foreign'] === null ? $holder['customer']->id : null, 'foreign_customer_id' => $holder['foreign']?->id],
                'whole' => $whole,
                'assignment_id' => $whole ? null : $target->id,
                'waived' => $waived,
            ]);

            return ['subscription' => $target, 'whole' => $whole, 'waived' => $waived];
        });
    }
}
