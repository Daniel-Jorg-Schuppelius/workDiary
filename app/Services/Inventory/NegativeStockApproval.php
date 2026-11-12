<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NegativeStockApproval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{NegativeStockRequestStatus, OwnershipType};
use App\Enums\User\Permission as P;
use App\Models\Approval\Approval;
use App\Models\Article\ArticleVariant;
use App\Models\Audit\AuditLog;
use App\Models\Inventory\{NegativeStockRequest, StockLot, Warehouse, WarehouseBin};
use App\Models\Platform\{Organization, User};
use App\Services\Approval\ApprovalService;
use App\Settings\SettingsRegistry;
use App\Support\MorphMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Gate};
use RuntimeException;

/**
 * Vier-Augen-Freigabe für Entnahmen in den Negativbestand (MVP-1626, Feature
 * 048 „Berechtigungen und Freigaben“). Verlangt die Organisation sie, bucht
 * die Entnahme erst, wenn eine zweite Person mit `inventory.negative` im
 * Genehmigungs-Eingang freigibt; Antragsteller und Freigebende stehen im Audit.
 */
final class NegativeStockApproval {
    public const string SETTING = 'inventory.four_eyes.negative';

    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly InventoryLedger $ledger,
        private readonly SettingsRegistry $settings,
    ) {}

    public function required(Organization $organization): bool {
        return (bool) $this->settings->effective(self::SETTING, $organization)->value;
    }

    public function request(ArticleVariant $variant, Warehouse $warehouse, EnteredQuantity $entered, OwnershipType $ownership, User $requester, ?WarehouseBin $bin = null, ?StockLot $lot = null): NegativeStockRequest {
        return DB::transaction(function () use ($variant, $warehouse, $entered, $ownership, $requester, $bin, $lot): NegativeStockRequest {
            $request = NegativeStockRequest::query()->create([
                'organization_id' => $variant->organization_id,
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'bin_id' => $bin?->id,
                'stock_lot_id' => $lot?->id,
                'ownership_kind' => $ownership,
                'quantity' => $entered->base,
                'entered_qty' => $entered->originalQty,
                'entered_unit' => $entered->originalUnit,
                'status' => NegativeStockRequestStatus::Pending,
                'requested_by' => $requester->id,
            ]);
            $this->approvals->createChain($request, [['type' => 'permission', 'value' => P::InventoryNegative->value]]);
            $request->audit('inventory.negativeRequested', ['warehouse_id' => $warehouse->id, 'qty' => $entered->base]);

            return $request;
        });
    }

    /**
     * Entscheidet die Stufe; bei Freigabe wird die Entnahme gebucht — als
     * Bewegung der beantragenden Person. Scheitert die Buchung, bleibt der
     * Antrag offen.
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        Gate::forUser($actor)->authorize(P::InventoryNegative->value);
        if ($decision === 'delegated') {
            $delegate = $delegateUserId !== null ? User::query()->find($delegateUserId) : null;
            if (! $delegate instanceof User || Gate::forUser($delegate)->denies(P::InventoryNegative->value)) {
                throw new \InvalidArgumentException((string) __('inventory.negative.delegate_without_right'));
            }
        }

        DB::transaction(function () use ($approval, $actor, $decision, $reason, $delegateUserId): void {
            $request = NegativeStockRequest::query()->whereKey($approval->approvable_id)->lockForUpdate()->firstOrFail();
            if (! $request->status->canTransitionTo(NegativeStockRequestStatus::Approved)) {
                throw new RuntimeException((string) __('inventory.negative.not_open'));
            }

            $result = $this->approvals->decide($approval, $actor, $decision, $reason, $request->requested_by !== null ? (int) $request->requested_by : null, $delegateUserId);
            if ($result === 'approved_all') {
                $this->book($request);
                $request->forceFill(['status' => NegativeStockRequestStatus::Approved, 'decided_by' => $actor->id, 'decided_at' => Carbon::now(), 'note' => $reason])->save();
                $this->auditDecision($request, $actor, 'inventory.negativeApproved');
            } elseif ($result === 'rejected') {
                $request->forceFill(['status' => NegativeStockRequestStatus::Rejected, 'decided_by' => $actor->id, 'decided_at' => Carbon::now(), 'note' => $reason])->save();
                $this->auditDecision($request, $actor, 'inventory.negativeRejected');
            }
        });
    }

    private function book(NegativeStockRequest $request): void {
        $variant = $request->variant()->with('article')->firstOrFail();
        $warehouse = $request->warehouse()->firstOrFail();
        $entered = EnteredQuantity::of($variant, (string) ($request->entered_qty ?? $request->quantity), $request->entered_unit);

        $this->ledger->issue(
            $variant,
            $warehouse,
            $entered->base,
            $request->ownership_kind,
            allowNegative: true,
            actorUserId: $request->requested_by !== null ? (int) $request->requested_by : null,
            bin: $request->bin,
            lot: $request->lot,
            requireLot: (bool) ($variant->article->batch_required ?? false),
            entered: $entered,
        );
    }

    /** Antragsteller und Freigebende ins Audit (Feature 048: „Grenzwerte und Genehmigende“). */
    private function auditDecision(NegativeStockRequest $request, User $actor, string $event): void {
        AuditLog::query()->create([
            'organization_id' => $request->organization_id,
            'user_id' => $actor->id,
            'event' => $event,
            'auditable_type' => MorphMap::stableKey(ArticleVariant::class),
            'auditable_id' => $request->article_variant_id,
            'changes' => [
                'warehouse_id' => $request->warehouse_id,
                'qty' => (string) $request->quantity,
                'four_eyes' => true,
                'requested_by' => $request->requested_by,
                'decided_by' => $actor->id,
                'request_id' => $request->id,
            ],
        ]);
    }
}
