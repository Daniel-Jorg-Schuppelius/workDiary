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
use App\Exceptions\AssetValidationException;
use App\Models\{Asset, Room, User};
use Illuminate\Support\Carbon;

class AssetService {
    public function __construct(
        private readonly AssetNumberGenerator $numbers,
        private readonly AssetStatusMachine $statusMachine,
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
            'serial_no' => $payload['serial_no'] ?? null,
            'inventory_no' => $payload['inventory_no'] ?? null,
            'customer_id' => $payload['customer_id'] ?? null,
            'foreign_customer_id' => $payload['foreign_customer_id'] ?? null,
            'room_id' => $payload['room_id'] ?? null,
            'owned_by' => $ownedBy->value,
            'location_text' => $payload['location_text'] ?? null,
            'location_lat' => $payload['location_lat'] ?? null,
            'location_lng' => $payload['location_lng'] ?? null,
            'status' => $status->value,
            'health' => (string) ($payload['health'] ?? AssetHealth::Ok->value),
            'commissioned_on' => $payload['commissioned_on'] ?? null,
            'decommissioned_on' => $payload['decommissioned_on'] ?? null,
            'warranty_until' => $payload['warranty_until'] ?? null,
            'next_maintenance_on' => $payload['next_maintenance_on'] ?? null,
            'next_inspection_on' => $payload['next_inspection_on'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'custom' => $payload['custom'] ?? null,
        ]);

        $asset->audit('asset.created', ['asset_no' => $asset->asset_no]);

        return $asset->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function update(Asset $asset, User $actor, array $payload): Asset {
        $nextStatus = array_key_exists('status', $payload)
            ? $this->parseStatus((string) $payload['status'])
            : $asset->status;

        if ($nextStatus !== $asset->status) {
            $this->statusMachine->ensureTransition($asset->status, $nextStatus);
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

        $asset->fill($payload);
        $asset->status = $nextStatus;
        $asset->owned_by = $nextOwnedBy;
        $asset->customer_id = $nextCustomer;
        $asset->foreign_customer_id = $nextForeignCustomer;
        $asset->room_id = $nextRoom;
        $asset->save();

        if ($nextStatus !== $asset->getOriginal('status')) {
            $asset->audit('asset.statusChanged', [
                'from' => $asset->getOriginal('status'),
                'to' => $nextStatus->value,
                'actor_id' => $actor->id,
            ]);
        } else {
            $asset->audit('asset.updated', ['actor_id' => $actor->id]);
        }

        return $asset->refresh();
    }

    public function decommission(Asset $asset, User $actor, string $date): Asset {
        $this->statusMachine->ensureTransition($asset->status, AssetStatus::Decommissioned);

        $asset->status = AssetStatus::Decommissioned;
        $asset->decommissioned_on = $this->normalizeDate($date);
        $asset->save();

        $asset->audit('asset.decommissioned', [
            'actor_id' => $actor->id,
            'decommissioned_on' => $date,
        ]);

        return $asset->refresh();
    }

    public function transferOwnership(Asset $asset, User $actor, AssetOwnership $ownership, ?int $customerId = null): Asset {
        $this->validateOwnershipConsistency($ownership, $customerId);

        $asset->owned_by = $ownership;
        $asset->customer_id = $customerId;
        $asset->save();

        $asset->audit('asset.ownershipTransferred', [
            'actor_id' => $actor->id,
            'owned_by' => $ownership->value,
            'customer_id' => $customerId,
        ]);

        return $asset->refresh();
    }

    public function move(Asset $asset, User $actor, ?string $locationText, ?string $lat = null, ?string $lng = null): Asset {
        $asset->location_text = $locationText;
        $asset->location_lat = $lat;
        $asset->location_lng = $lng;
        $asset->save();

        $asset->audit('asset.moved', [
            'actor_id' => $actor->id,
            'location_text' => $locationText,
            'location_lat' => $lat,
            'location_lng' => $lng,
        ]);

        return $asset->refresh();
    }

    public function scheduleMaintenance(Asset $asset, User $actor, ?string $nextMaintenanceOn, ?string $nextInspectionOn): Asset {
        $asset->next_maintenance_on = $this->normalizeDate($nextMaintenanceOn);
        $asset->next_inspection_on = $this->normalizeDate($nextInspectionOn);
        $asset->save();

        $asset->audit('asset.updated', [
            'actor_id' => $actor->id,
            'next_maintenance_on' => $nextMaintenanceOn,
            'next_inspection_on' => $nextInspectionOn,
        ]);

        return $asset->refresh();
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
