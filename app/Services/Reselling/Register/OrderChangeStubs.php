<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderChangeStubs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * Stummel beim Bestellungswechsel (Phase 166, MVP-1450): verlängert der
 * Anbieter ein Abo als neue Bestellung, endet die alte wenige Tage nach dem
 * Beginn der neuen. Der Planer legt am Ende der alten eine kurze Restperiode
 * an, der Lauf füllt sie mit einem Monat der Jahresrechnung — und der ersten
 * Periode des Nachfolgers fehlt dieser Monat für immer. Die Restperiode gilt
 * als Bestellungswechsel: verzichtet mit diesem Grund, ihre Bezüge gehen an
 * die erste Periode des Nachfolgers. Entscheidung des Inhabers 2026-10-31.
 */
final class OrderChangeStubs {
    public const AUDIT = 'resale_period.order_change';

    /** Der Vorgänger darf bis so viele Tage vor dem Beginn des Nachfolgers enden … */
    public const END_BEFORE_DAYS = 7;

    /** … und bis so viele Tage danach. */
    public const END_AFTER_DAYS = 60;

    /** Die Restperiode beginnt höchstens so viele Tage vor dem Nachfolger — sonst ist sie eine echte Periode. */
    public const STUB_LEAD_DAYS = 60;

    /**
     * Läuft im Vorschlagslauf ohne Modell-Events; die Audit-Einträge (Periode,
     * Payload) schreibt der Aufrufer nach dem Block, sonst fehlte der Ketten-Hash.
     *
     * @param  Collection<int, ResaleSubscription>  $subscriptions  mit Rechnungsempfänger geladen
     * @return list<array{0: ResalePeriod, 1: array<string, mixed>}>
     */
    public function settle(Organization $organization, Collection $subscriptions): array {
        /** @var array<string, list<ResaleSubscription>> $groups Empfänger × Produkt → Abos */
        $groups = [];
        foreach ($subscriptions as $subscription) {
            $recipient = $subscription->billedTo()?->id;
            if ($recipient === null || $subscription->is_own_holding) {
                continue;
            }
            $groups[$recipient . '|' . $subscription->productKey()][] = $subscription;
        }
        $audits = [];
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $predecessor) {
                if ($predecessor->ends_on === null) {
                    continue;
                }
                $successor = self::successorOf($predecessor, $group);
                if ($successor === null) {
                    continue;
                }
                $audit = $this->move($organization, $predecessor, $successor);
                if ($audit !== null) {
                    $audits[] = $audit;
                }
            }
        }

        return $audits;
    }

    /**
     * Späteres Abo der Gruppe, in dessen Beginn-Fenster der Vorgänger endet — bei mehreren das früheste.
     *
     * @param  list<ResaleSubscription>  $group
     */
    private static function successorOf(ResaleSubscription $predecessor, array $group): ?ResaleSubscription {
        $end = $predecessor->ends_on;
        if ($end === null) {
            return null;
        }
        $best = null;
        foreach ($group as $candidate) {
            if ($candidate->id === $predecessor->id || ! $candidate->starts_on->greaterThan($predecessor->starts_on)) {
                continue;
            }
            if ($end->lessThan($candidate->starts_on->subDays(self::END_BEFORE_DAYS)) || $end->greaterThan($candidate->starts_on->addDays(self::END_AFTER_DAYS))) {
                continue;
            }
            if ($best === null || $candidate->starts_on->lessThan($best->starts_on)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @return array{0: ResalePeriod, 1: array<string, mixed>}|null
     */
    private function move(Organization $organization, ResaleSubscription $predecessor, ResaleSubscription $successor): ?array {
        $stub = ResalePeriod::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->where('subscription_id', $predecessor->id)->orderByDesc('ends_on')->orderByDesc('id')->with('links')->first();
        if ($stub === null || in_array($stub->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
            return null;
        }
        if ($stub->starts_on->lessThan($successor->starts_on->subDays(self::STUB_LEAD_DAYS))) {
            return null; // echte Periode des Vorgängers, kein Stummel
        }
        $target = ResalePeriod::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
            ->where('subscription_id', $successor->id)->orderBy('starts_on')->orderBy('id')->with('links')->first();
        if ($target === null || in_array($target->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
            return null;
        }
        // Nur so viel, wie die erste Periode des Nachfolgers noch offen hat (Phase 167, MVP-1457) — sonst bleibt der Stummel.
        $moving = (float) $stub->links->sum(static fn (ResalePeriodLink $link): float => (float) $link->months);
        if ($moving > $target->openMonths() + 0.001) {
            return null;
        }

        $moved = [];
        $term = $target->termMonths();
        foreach ($stub->links as $link) {
            $existing = $target->links->first(static fn (ResalePeriodLink $l): bool => $l->linkable_type === $link->linkable_type && (int) $l->linkable_id === (int) $link->linkable_id);
            if ($existing !== null) {
                // Dieselbe Position hängt schon am Nachfolger: Monate und Betrag addieren, ein Bezug je Position und Periode.
                $months = round((float) $existing->months + (float) $link->months, 2);
                $amount = $existing->amount !== null && $link->amount !== null ? Money::sum([$existing->amount, $link->amount], $existing->currency, 2) : ($existing->amount ?? $link->amount);
                $existing->forceFill(['months' => $months, 'quantity' => round($months / $term, 3), 'amount' => $amount])->save();
                $link->delete();
            } else {
                $link->forceFill(['period_id' => $target->id, 'subscription_id' => $successor->id, 'quantity' => round((float) $link->months / $term, 3)])->save();
            }
            $moved[] = ['voucher_number' => $link->voucher_number, 'months' => (float) $link->months];
        }
        $before = $stub->status->value;
        $stub->forceFill([
            'status' => PeriodStatus::Waived,
            'waived_reason' => (string) __('resale.link.reason_order_change', ['date' => $successor->starts_on->format('d.m.Y')]),
            'decided_by_user_id' => null,
            'decided_at' => now(),
        ])->save();
        $target->unsetRelation('links');
        $target->load('links');
        if ($target->decided_at === null) {
            $target->forceFill(['status' => $target->statusFromCoverage($target->coveredMonths())])->save();
        }

        return [$stub, ['status_before' => $before, 'successor_subscription_id' => $successor->id, 'successor_period_id' => $target->id, 'moved' => $moved]];
    }
}
