<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Warranty;

use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Models\{Protocol, User};
use App\Models\Warranty\WarrantyPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Gewährleistungsfristen (Feature 115, MVP-604).
 *
 * Der Dienst rechnet und vergleicht — er entscheidet nichts. Eine automatisch
 * „verlängerte" Frist wäre eine Zusage, die die Software nicht halten kann:
 * Hemmung und Neubeginn sind Rechtsfragen. Was er leistet, ist die
 * Sichtbarkeit, an der es heute scheitert.
 */
class WarrantyService {
    /**
     * Frist aus einer Abnahme ableiten. Fristbeginn ist der Abnahmetag —
     * nicht das Rechnungs- oder Fertigstellungsdatum.
     */
    /** @param array<string, mixed> $attributes */
    public function fromAcceptance(
        Protocol $protocol,
        WarrantySide $side,
        WarrantyBasis $basis,
        ?string $endsOn = null,
        ?string $overrideReason = null,
        ?User $actor = null,
        array $attributes = [],
    ): WarrantyPeriod {
        // occurred_at ist am Protokoll gesetzt; der Abnahmetag ist der
        // Fristbeginn — nicht Rechnung oder Fertigstellung.
        $start = CarbonImmutable::parse($protocol->occurred_at->toDateString());

        return $this->create($side, $basis, $start->toDateString(), $endsOn, $overrideReason, $actor, $attributes + [
            'protocol_id' => $protocol->id,
            'organization_id' => $protocol->organization_id,
        ]);
    }

    /**
     * Frist anlegen. Ohne eigenes Enddatum ergibt es sich aus der Grundlage;
     * mit eigenem Enddatum braucht es eine Begründung — sonst ist später nicht
     * nachvollziehbar, warum die Frist von der Grundlage abweicht.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(
        WarrantySide $side,
        WarrantyBasis $basis,
        string $startsOn,
        ?string $endsOn = null,
        ?string $overrideReason = null,
        ?User $actor = null,
        array $attributes = [],
    ): WarrantyPeriod {
        $start = CarbonImmutable::parse($startsOn);
        $months = $basis->months();

        if ($endsOn === null) {
            if ($months === null) {
                throw new RuntimeException((string) __('warranty.custom_needs_end'));
            }
            $end = $start->addMonths($months);
        } else {
            $end = CarbonImmutable::parse($endsOn);
            if ($end->lessThanOrEqualTo($start)) {
                throw new RuntimeException((string) __('warranty.end_before_start'));
            }
            if ($months !== null && ! $end->equalTo($start->addMonths($months)) && trim((string) $overrideReason) === '') {
                throw new RuntimeException((string) __('warranty.override_needs_reason'));
            }
        }

        $period = WarrantyPeriod::query()->create($attributes + [
            'side' => $side->value,
            'basis' => $basis->value,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'override_reason' => $overrideReason,
            'status' => WarrantyStatus::Open->value,
            'created_by' => $actor?->id,
        ]);
        $period->audit('warranty.created', [
            'side' => $side->value,
            'basis' => $basis->value,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ]);

        return $period;
    }

    /**
     * Subunternehmer-Fristen, die VOR der eigenen Haftung enden — der teure
     * Fall: Danach haftet man allein für einen Mangel, den ein anderer
     * verursacht hat.
     *
     * Verglichen wird je Projekt: die früheste eigene Frist gegen jede
     * einforderbare. Ohne eigene Frist im Projekt gibt es nichts zu vergleichen
     * — dann ist die Sub-Frist für sich zu überwachen, nicht als Sonderfall.
     *
     * @return Collection<int, WarrantyPeriod>
     */
    public function subcontractorsEndingFirst(?int $organizationId = null, int $leadDays = 90): Collection {
        $open = WarrantyPeriod::query()
            ->where('status', WarrantyStatus::Open->value)
            ->whereNotNull('project_id')
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->get();

        /** @var array<int, string> $ownEndByProject */
        $ownEndByProject = [];
        foreach ($open->where('side', WarrantySide::Owed) as $period) {
            $projectId = (int) $period->project_id;
            $end = $period->ends_on->toDateString();
            if (! isset($ownEndByProject[$projectId]) || $end < $ownEndByProject[$projectId]) {
                $ownEndByProject[$projectId] = $end;
            }
        }

        $threshold = CarbonImmutable::today()->addDays($leadDays)->toDateString();

        return $open
            ->where('side', WarrantySide::Claimable)
            ->filter(function (WarrantyPeriod $period) use ($ownEndByProject, $threshold): bool {
                $ownEnd = $ownEndByProject[(int) $period->project_id] ?? null;
                if ($ownEnd === null) {
                    return false;
                }

                // Nur melden, wenn die Sub-Frist wirklich früher endet UND der
                // Zeitpunkt näher rückt — eine Frist in drei Jahren ist keine
                // Handlung, sondern Rauschen.
                return $period->ends_on->toDateString() < $ownEnd
                    && $period->ends_on->toDateString() <= $threshold;
            })
            ->values();
    }

    /** Frist mit einer Reklamation verknüpfen (Rüge innerhalb der Frist). */
    public function markClaimed(WarrantyPeriod $period, int $claimCaseId, ?User $actor = null): WarrantyPeriod {
        if ($period->status !== WarrantyStatus::Open) {
            throw new RuntimeException((string) __('warranty.not_open'));
        }

        $period->forceFill([
            'status' => WarrantyStatus::Claimed->value,
            'claim_case_id' => $claimCaseId,
        ])->save();

        $period->audit('warranty.claimed', ['claim_case_id' => $claimCaseId, 'by' => $actor?->id]);

        return $period->refresh();
    }

    /** Frist abschließen (abgelaufen bzw. erledigt). */
    public function close(WarrantyPeriod $period, ?User $actor = null): WarrantyPeriod {
        if ($period->status === WarrantyStatus::Closed) {
            return $period;
        }

        $period->forceFill(['status' => WarrantyStatus::Closed->value])->save();
        $period->audit('warranty.closed', ['by' => $actor?->id]);

        return $period->refresh();
    }
}
