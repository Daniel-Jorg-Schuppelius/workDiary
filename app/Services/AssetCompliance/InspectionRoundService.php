<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionRoundService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionRoundStatus;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetInspectionRound, AssetInspectionRoundItem};
use App\Models\Platform\{Organization, User};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Prüfmittelrunden (MVP-899): Soll-Liste fälliger Prüfpflichten eines
 * Standorts oder einer Gruppe, Scan des Objekt-Codes, Schnellerfassung über
 * {@see AssetComplianceService::recordInspection()}.
 */
final class InspectionRoundService {
    public function __construct(private readonly AssetComplianceService $compliance) {}

    /**
     * @param  array{name: string, due_until: string, location_text?: ?string, category_code?: ?string, asset_compliance_profile_id?: ?int, customer_id?: ?int}  $data
     */
    public function open(Organization $organization, User $actor, array $data): AssetInspectionRound {
        $dueUntil = CarbonImmutable::parse($data['due_until']);
        $assignments = AssetComplianceAssignment::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('next_due_on')->orWhere('next_due_on', '<', DateRange::dayAfter($dueUntil)))
            ->when(($data['asset_compliance_profile_id'] ?? null) !== null, fn ($q) => $q->where('asset_compliance_profile_id', $data['asset_compliance_profile_id']))
            ->whereHas('asset', function ($q) use ($data): void {
                $q->whereNull('decommissioned_on');
                foreach (['location_text', 'category_code', 'customer_id'] as $column) {
                    if (($data[$column] ?? null) !== null && $data[$column] !== '') {
                        $q->where($column, $data[$column]);
                    }
                }
            })
            ->get();

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages(['due_until' => __('inspection_round.empty')]);
        }

        return DB::transaction(function () use ($organization, $actor, $data, $dueUntil, $assignments): AssetInspectionRound {
            /** @var AssetInspectionRound $round */
            $round = AssetInspectionRound::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'location_text' => $data['location_text'] ?? null,
                'category_code' => $data['category_code'] ?? null,
                'asset_compliance_profile_id' => $data['asset_compliance_profile_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'due_until' => $dueUntil->toDateString(),
                'status' => AssetInspectionRoundStatus::Open,
                'created_by' => $actor->id,
            ]);
            foreach ($assignments as $assignment) {
                $round->items()->create([
                    'organization_id' => $organization->id,
                    'asset_compliance_assignment_id' => $assignment->id,
                    'asset_id' => $assignment->asset_id,
                    'due_on' => $assignment->next_due_on,
                ]);
            }

            return $round;
        });
    }

    /**
     * Offene Positionen der Runde zum gescannten Objekt.
     *
     * @return Collection<int, AssetInspectionRoundItem>
     */
    public function pendingForCode(AssetInspectionRound $round, string $code): Collection {
        $asset = Asset::findByCode($code);
        if (! $asset instanceof Asset) {
            throw new RuntimeException((string) __('inspection_round.scan_unknown'));
        }
        $items = $round->items()->where('asset_id', $asset->id)->with('assignment.profile')->get();
        if ($items->isEmpty()) {
            throw new RuntimeException((string) __('inspection_round.scan_not_in_round', ['asset' => $asset->name]));
        }

        return $items->reject(fn (AssetInspectionRoundItem $item): bool => $item->isDone())->values();
    }

    /** @param  array{result: string, note?: ?string, signature_name?: ?string}  $data */
    public function record(AssetInspectionRoundItem $item, User $actor, array $data): AssetInspectionRoundItem {
        $round = $item->round()->firstOrFail();
        if ($round->status !== AssetInspectionRoundStatus::Open) {
            throw new RuntimeException((string) __('inspection_round.closed'));
        }
        if ($item->isDone()) {
            throw new RuntimeException((string) __('inspection_round.already_done'));
        }

        return DB::transaction(function () use ($item, $actor, $data): AssetInspectionRoundItem {
            $event = $this->compliance->recordInspection($item->assignment()->firstOrFail(), $actor, [
                'result' => $data['result'],
                'note' => $data['note'] ?? null,
                'signature_name' => $data['signature_name'] ?? null,
            ]);
            $item->forceFill(['asset_inspection_event_id' => $event->id])->save();

            return $item;
        });
    }

    public function close(AssetInspectionRound $round, User $actor): AssetInspectionRound {
        if (! $round->status->canTransitionTo(AssetInspectionRoundStatus::Closed)) {
            throw new RuntimeException((string) __('inspection_round.closed'));
        }
        $round->forceFill(['status' => AssetInspectionRoundStatus::Closed, 'closed_at' => now(), 'updated_by' => $actor->id])->save();

        return $round;
    }
}
