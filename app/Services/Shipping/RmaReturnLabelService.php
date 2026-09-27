<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RmaReturnLabelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Shipping\{CarrierConnection, Shipment};
use App\Services\Claims\Contracts\RmaReturnLabelIssuer;
use RuntimeException;
use Throwable;

/**
 * Retourenlabel einer RMA (MVP-917) über die bestehende Versandlogik: ein
 * Versandauftrag mit `is_return`, Label und Sendungsverfolgung wie beim
 * Versand. Lehnt der Carrier ab, wird der Entwurf verworfen.
 */
final class RmaReturnLabelService implements RmaReturnLabelIssuer {
    public function __construct(private readonly ShipmentService $shipping) {}

    public function carriers(Organization $organization): array {
        return CarrierConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'carrier')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    public function issue(ClaimRmaReturn $rma, User $actor, string $carrier, int $weightGrams): Shipment {
        $case = $rma->claimCase;
        $customer = $case?->customer;
        if (! $customer instanceof Customer || blank($customer->address_street) || blank($customer->address_city)) {
            throw new RuntimeException((string) __('claims.return_label.no_address'));
        }
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($rma->organization_id);
        if (! array_key_exists($carrier, $this->carriers($organization))) {
            throw new RuntimeException((string) __('shipping.flash.no_connection'));
        }

        $shipment = Shipment::query()->create([
            'organization_id' => $rma->organization_id,
            'claim_rma_return_id' => $rma->id,
            'carrier' => $carrier,
            'status' => ShipmentStatus::Draft->value,
            'is_return' => true,
            'created_by' => $actor->id,
        ]);
        $request = new ShipmentRequest(
            ShipperAddress::fromOrganization($organization)->toRecipient(),
            [new ShipmentPackage($weightGrams)],
            $rma->rma_number,
            returnFrom: ShipmentRecipient::fromCustomer($customer),
        );

        try {
            $this->shipping->createLabel($shipment, $request);
        } catch (Throwable $e) {
            $shipment->delete();

            throw $e;
        }

        return $shipment;
    }
}
