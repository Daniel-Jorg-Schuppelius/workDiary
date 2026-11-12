<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\{AssetHealth, AssetOwnership, AssetStatus};
use App\Events\Asset\AssetDecommissioned;
use App\Exceptions\AssetValidationException;
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Disposal\DisposalItem;
use App\Models\Facility\Room;
use App\Models\Platform\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssetService {
    public function __construct(
        private readonly AssetNumberGenerator $numbers,
        private readonly AssetBlockService $blocks,
        private readonly AssetHealthService $health,
        private readonly AssetLifecycleService $lifecycle,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(User $actor, array $payload): Asset {
        $ownedBy = $this->parseOwnership($payload['owned_by'] ?? AssetOwnership::Organization->value);
        $status = $this->parseStatus($payload['status'] ?? AssetStatus::Active->value);

        $this->validateOwnershipConsistency($ownedBy, $payload['customer_id'] ?? null);
        $this->validateDecommissionConsistency($status, $payload['decommissioned_on'] ?? null);
        $this->validateRoomConsistency(
            $payload['room_id'] ?? null,
            $payload['customer_id'] ?? null,
        );

        $asset = Asset::query()->create([
            'organization_id' => (int) $actor->organization_id,
            'asset_no' => (string) ($payload['asset_no'] ?? $this->numbers->generate((int) $actor->organization_id)),
            'asset_class' => (string) $payload['asset_class'],
            'category_code' => $payload['category_code'] ?? null,
            'name' => (string) $payload['name'],
            'manufacturer' => $payload['manufacturer'] ?? null,
            'model' => $payload['model'] ?? null,
            'product_id' => $payload['product_id'] ?? null,
            'serial_no' => $payload['serial_no'] ?? null,
            'inventory_no' => $payload['inventory_no'] ?? null,
            'customer_id' => $payload['customer_id'] ?? null,
            'foreign_customer_id' => $payload['foreign_customer_id'] ?? null,
            'sla_contract_id' => $payload['sla_contract_id'] ?? null,
            'room_id' => $payload['room_id'] ?? null,
            'owned_by' => $ownedBy->value,
            'location_text' => $payload['location_text'] ?? null,
            'location_lat' => $payload['location_lat'] ?? null,
            'location_lng' => $payload['location_lng'] ?? null,
            'status' => $status->value,
            'health' => AssetHealth::Ok->value,
            'commissioned_on' => $payload['commissioned_on'] ?? null,
            'decommissioned_on' => $payload['decommissioned_on'] ?? null,
            'warranty_until' => $payload['warranty_until'] ?? null,
            'next_maintenance_on' => $payload['next_maintenance_on'] ?? null,
            'next_inspection_on' => $payload['next_inspection_on'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'custom' => $payload['custom'] ?? null,
        ]);

        $asset->audit('asset.created', ['asset_no' => $asset->asset_no]);
        $this->blocks->syncStatusBlock($asset, $actor);
        $this->health->sync($asset);

        return $asset->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function update(Asset $asset, User $actor, array $payload): Asset {
        $nextStatus = array_key_exists('status', $payload)
            ? $this->parseStatus((string) $payload['status'])
            : $asset->status;

        // Aus „außer Betrieb“ führt nur {@see recommission()} mit Grund heraus.
        $leavesDecommission = $asset->status === AssetStatus::Decommissioned && $nextStatus !== AssetStatus::Decommissioned;
        if ($nextStatus !== $asset->status && ($leavesDecommission || ! $asset->status->canTransitionTo($nextStatus))) {
            throw AssetValidationException::invalidStatusTransition($asset->status->value, $nextStatus->value);
        }

        $nextOwnedBy = array_key_exists('owned_by', $payload)
            ? $this->parseOwnership((string) $payload['owned_by'])
            : $asset->owned_by;
        $nextCustomer = array_key_exists('customer_id', $payload)
            ? ($payload['customer_id'] !== null ? (int) $payload['customer_id'] : null)
            : $asset->customer_id;
        $nextForeignCustomer = array_key_exists('foreign_customer_id', $payload)
            ? ($payload['foreign_customer_id'] !== null && $payload['foreign_customer_id'] !== '' ? (int) $payload['foreign_customer_id'] : null)
            : $asset->foreign_customer_id;
        // Ohne Kunde kann kein Fremdkunde bestehen bleiben.
        if ($nextCustomer === null) {
            $nextForeignCustomer = null;
        }
        $nextRoom = array_key_exists('room_id', $payload)
            ? ($payload['room_id'] !== null && $payload['room_id'] !== '' ? (int) $payload['room_id'] : null)
            : $asset->room_id;

        $this->validateOwnershipConsistency($nextOwnedBy, $nextCustomer);
        $this->validateDecommissionConsistency($nextStatus, $payload['decommissioned_on'] ?? $asset->decommissioned_on);
        $this->validateRoomConsistency($nextRoom, $nextCustomer);

        // Eigentum wechselt nur über die Historie (append-only), nie still im Speichern.
        $this->lifecycle->changeOwnership($asset, $actor, $nextOwnedBy, $nextCustomer, isset($payload['ownership_note']) ? (string) $payload['ownership_note'] : null);
        unset($payload['ownership_note']);

        $location = [
            'location_text' => array_key_exists('location_text', $payload) ? $payload['location_text'] : $asset->location_text,
            'room_id' => $nextRoom,
        ];
        $previousLocation = ['location_text' => $asset->location_text, 'room_id' => $asset->room_id];
        $previousStatus = $asset->status;

        $asset->fill($payload);
        $asset->status = $nextStatus;
        $asset->owned_by = $nextOwnedBy;
        $asset->customer_id = $nextCustomer;
        $asset->foreign_customer_id = $nextForeignCustomer;
        $asset->room_id = $nextRoom;
        $asset->save();

        if ($nextStatus !== $previousStatus) {
            $asset->audit('asset.statusChanged', [
                'from' => $previousStatus->value,
                'to' => $nextStatus->value,
                'actor_id' => $actor->id,
            ]);
            $this->blocks->syncStatusBlock($asset, $actor);
        } else {
            $asset->audit('asset.updated', ['actor_id' => $actor->id]);
        }

        if (self::locationKey($location) !== self::locationKey($previousLocation)) {
            $this->recordMove($asset, $actor, $previousLocation, $location);
        }
        $this->health->sync($asset);

        return $asset->refresh();
    }

    /**
     * Nimmt ein Asset endgültig außer Betrieb. Eine offene Ausgabe muss vorher
     * zurückgenommen sein; Wartungspläne ruhen danach, Prüfpflichten und
     * geplante Prüftermine schließt das Prüfwesen über {@see AssetDecommissioned}.
     */
    public function decommission(Asset $asset, User $actor, string $date, ?string $reason = null): Asset {
        if ($asset->status !== AssetStatus::Decommissioned && ! $asset->status->canTransitionTo(AssetStatus::Decommissioned)) {
            throw AssetValidationException::invalidStatusTransition($asset->status->value, AssetStatus::Decommissioned->value);
        }
        if ($asset->assignments()->whereNull('returned_at')->exists()) {
            throw AssetValidationException::checkedOutOnDecommission();
        }

        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        DB::transaction(function () use ($asset, $actor, $date, $reason): void {
            $from = $asset->status;
            $asset->status = AssetStatus::Decommissioned;
            $asset->decommissioned_on = $this->normalizeDate($date);
            $asset->decommission_reason = $reason;
            $asset->next_maintenance_on = null;
            $asset->save();

            MaintenancePlan::query()->where('asset_id', $asset->id)->where('is_active', true)->update(['is_active' => false]);

            $asset->audit('asset.decommissioned', [
                'actor_id' => $actor->id,
                'from' => $from->value,
                'decommissioned_on' => $date,
                'reason' => $reason,
            ]);
            $this->blocks->syncStatusBlock($asset, $actor);
            $this->health->sync($asset);
        });

        AssetDecommissioned::dispatch($asset, $actor->id);

        return $asset->refresh();
    }

    /**
     * Wieder in Betrieb nehmen (MVP-1628), Gegenaktion zu {@see decommission()}
     * mit Pflichtgrund. Pausierte Wartungspläne und ruhende Prüfpflichten bleiben
     * aus — die Akte nimmt sie einzeln wieder auf. Ein über einen
     * Entsorgungsauftrag entsorgtes Asset ist physisch weg und bleibt außer Betrieb.
     */
    public function recommission(Asset $asset, User $actor, string $reason): Asset {
        DB::transaction(function () use ($asset, $actor, $reason): void {
            $locked = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== AssetStatus::Decommissioned || ! $locked->status->canTransitionTo(AssetStatus::Active)) {
                throw AssetValidationException::invalidStatusTransition($locked->status->value, AssetStatus::Active->value);
            }
            $disposed = DisposalItem::query()
                ->where('asset_id', $locked->id)
                ->whereHas('job', fn ($q) => $q->whereNotNull('completed_at'))
                ->exists();
            if ($disposed) {
                throw AssetValidationException::disposed();
            }

            $previous = ['decommissioned_on' => $locked->decommissioned_on?->toDateString(), 'decommission_reason' => $locked->decommission_reason];
            $locked->status = AssetStatus::Active;
            $locked->decommissioned_on = null;
            $locked->decommission_reason = null;
            $locked->save();

            $locked->audit('asset.recommissioned', ['actor_id' => $actor->id, 'reason' => trim($reason)] + $previous);
            $this->blocks->syncStatusBlock($locked, $actor);
            $this->health->sync($locked);
        });

        return $asset->refresh();
    }

    /**
     * @param array{location_text: mixed, room_id: mixed} $from
     * @param array{location_text: mixed, room_id: mixed} $to
     */
    private function recordMove(Asset $asset, User $actor, array $from, array $to): void {
        $asset->audit('asset.moved', [
            'actor_id' => $actor->id,
            'from_location_text' => $from['location_text'],
            'to_location_text' => $to['location_text'],
            'from_room_id' => $from['room_id'],
            'to_room_id' => $to['room_id'],
        ]);
    }

    /** @param array{location_text: mixed, room_id: mixed} $location */
    private static function locationKey(array $location): string {
        return trim((string) ($location['location_text'] ?? '')) . '|' . (int) ($location['room_id'] ?? 0);
    }

    private function validateOwnershipConsistency(AssetOwnership $ownedBy, mixed $customerId): void {
        $hasCustomer = $customerId !== null && (int) $customerId > 0;

        if ($ownedBy->requiresCustomer() && ! $hasCustomer) {
            throw AssetValidationException::customerRequired();
        }

        if ($ownedBy === AssetOwnership::Organization && $hasCustomer) {
            throw AssetValidationException::customerForbidden();
        }
    }

    private function validateDecommissionConsistency(AssetStatus $status, mixed $decommissionDate): void {
        if ($status === AssetStatus::Decommissioned && empty($decommissionDate)) {
            throw AssetValidationException::decommissionDateRequired();
        }
    }

    private function validateRoomConsistency(mixed $roomId, mixed $customerId): void {
        if ($roomId === null || $roomId === '') {
            return;
        }

        $room = Room::query()->find($roomId);
        if (! $room instanceof Room) {
            return;
        }

        if (
            $room->customer_id !== null
            && $customerId !== null && $customerId !== ''
            && (int) $room->customer_id !== (int) $customerId
        ) {
            throw AssetValidationException::roomCustomerMismatch();
        }
    }

    private function parseStatus(string $value): AssetStatus {
        return AssetStatus::from($value);
    }

    private function parseOwnership(string $value): AssetOwnership {
        return AssetOwnership::from($value);
    }

    private function normalizeDate(?string $value): ?Carbon {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }
}
