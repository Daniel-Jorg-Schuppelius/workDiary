<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionChain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionStatus;
use App\Models\Reselling\ResaleSubscription;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vorgänger und Nachfolger von Hand verketten (Phase 185, MVP-1752). Der
 * Anbieter-Import verkettet nur, was seine Datei als Ablösung nennt; ein
 * Wechsel des Anbieters oder ein von Hand angelegtes Abo bleibt sonst ohne
 * Kette — und damit ohne Vorlagenanker und ohne gemeinsamen Verlauf.
 */
final class SubscriptionChain {
    /** Der Nachfolger beginnt höchstens so viele Tage vor dem Ende des Vorgängers … */
    public const int DAYS_BEFORE = 45;

    /** … und höchstens so viele danach (Lücke beim Anbieterwechsel). */
    public const int DAYS_AFTER = 120;

    public const string AUDIT_LINKED = 'resale_subscription.chained';

    public const string AUDIT_UNLINKED = 'resale_subscription.unchained';

    /** Kann das Abo einen Nachfolger bekommen? Beendet oder abgelöst, noch ohne Nachfolger. */
    public static function needsSuccessor(ResaleSubscription $subscription): bool {
        return $subscription->successor_id === null && $subscription->ends_on !== null && ! $subscription->status->isPlanning();
    }

    /**
     * Mögliche Nachfolger: gleicher Halter, gleiches Produkt, Beginn am Ende des Vorgängers.
     * Beste Passung zuerst (gleiche Menge, dann Abstand in Tagen).
     *
     * @return Collection<int, ResaleSubscription>
     */
    public function successorsFor(ResaleSubscription $predecessor): Collection {
        $end = $predecessor->ends_on;
        if ($end === null || ! self::needsSuccessor($predecessor)) {
            return collect();
        }

        return $this->sameHolder($predecessor)
            ->whereBetween('starts_on', DateRange::days($end->subDays(self::DAYS_BEFORE), $end->addDays(self::DAYS_AFTER)))
            ->get()
            ->filter(fn(ResaleSubscription $candidate): bool => $candidate->productKey() === $predecessor->productKey() && ! $this->reaches($candidate, $predecessor))
            ->sortBy(static fn(ResaleSubscription $candidate): array => [abs($candidate->quantity - $predecessor->quantity), abs((int) $candidate->starts_on->diffInDays($end))])
            ->values();
    }

    /**
     * Beendete Abos des Halters, die ein neues Abo ab dem Tag fortsetzen würde.
     *
     * @return Collection<int, ResaleSubscription>
     */
    public function predecessorsFor(int $organizationId, ?int $customerId, ?int $foreignCustomerId, ?string $articleRef, CarbonImmutable $startsOn): Collection {
        if ($articleRef === null || ($customerId === null && $foreignCustomerId === null)) {
            return collect();
        }

        return ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('customer_id', $foreignCustomerId === null ? $customerId : null)
            ->where('foreign_customer_id', $foreignCustomerId)
            ->where('article_ref', $articleRef)
            ->whereNull('successor_id')
            ->whereNotIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value])
            ->whereBetween('ends_on', DateRange::days($startsOn->subDays(self::DAYS_AFTER), $startsOn->addDays(self::DAYS_BEFORE)))
            ->orderByDesc('ends_on')
            ->get();
    }

    /**
     * @throws \InvalidArgumentException Vorgänger hat schon einen Nachfolger, anderer Mandant oder Ringschluss
     */
    public function link(ResaleSubscription $predecessor, ResaleSubscription $successor): void {
        if ($predecessor->successor_id !== null || $predecessor->id === $successor->id
            || $predecessor->organization_id !== $successor->organization_id || $this->reaches($successor, $predecessor)) {
            throw new \InvalidArgumentException((string) __('resale.chain.error'));
        }
        DB::transaction(static function () use ($predecessor, $successor): void {
            $before = $predecessor->status;
            $predecessor->forceFill([
                'successor_id' => $successor->id,
                'status' => $before === SubscriptionStatus::Ended ? SubscriptionStatus::Superseded : $before,
            ])->save();
            $predecessor->audit(self::AUDIT_LINKED, ['successor_id' => $successor->id, 'status_before' => $before->value]);
        });
    }

    public function unlink(ResaleSubscription $predecessor): void {
        if ($predecessor->successor_id === null) {
            return;
        }
        DB::transaction(static function () use ($predecessor): void {
            $successorId = $predecessor->successor_id;
            $predecessor->forceFill([
                'successor_id' => null,
                'status' => $predecessor->status === SubscriptionStatus::Superseded ? SubscriptionStatus::Ended : $predecessor->status,
            ])->save();
            $predecessor->audit(self::AUDIT_UNLINKED, ['successor_id' => $successorId]);
        });
    }

    /**
     * Abos ohne Nachfolger, für die es genau passende Kandidaten gibt (Überblick).
     *
     * @return array{count: int, subscription_id: int|null}
     */
    public function overview(int $organizationId): array {
        $open = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('successor_id')
            ->whereNotNull('ends_on')
            ->whereNotIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value])
            ->orderByDesc('ends_on')
            ->get();
        $result = ['count' => 0, 'subscription_id' => null];
        foreach ($open as $subscription) {
            if ($this->successorsFor($subscription)->isEmpty()) {
                continue;
            }
            $result['count']++;
            $result['subscription_id'] ??= (int) $subscription->id;
        }

        return $result;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ResaleSubscription> */
    private function sameHolder(ResaleSubscription $subscription): \Illuminate\Database\Eloquent\Builder {
        return ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $subscription->organization_id)
            ->where('kind', $subscription->kind->value)
            ->where('id', '<>', $subscription->id)
            ->where('customer_id', $subscription->customer_id)
            ->where('foreign_customer_id', $subscription->foreign_customer_id)
            ->where('is_own_holding', $subscription->is_own_holding);
    }

    /** Führt die Nachfolgerkette von $from zu $target? */
    private function reaches(ResaleSubscription $from, ResaleSubscription $target): bool {
        $seen = [];
        $current = $from;
        while ($current !== null && ! isset($seen[$current->id])) {
            if ($current->id === $target->id) {
                return true;
            }
            $seen[$current->id] = true;
            $current = $current->successor_id !== null ? ResaleSubscription::query()->withoutGlobalScopes()->find($current->successor_id) : null;
        }

        return false;
    }
}
