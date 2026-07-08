<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Shipping;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Shipping\ShipmentStatus;
use App\Models\{Attachment, CarrierConnection, ExternalReference, Shipment};
use App\Plugins\Contracts\ShippingProvider;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Providerneutrale Versandlogik (Feature 059, MVP-128). Bindet einen
 * {@see Shipment} über die {@see ShippingProviderRegistry} an den passenden
 * Carrier-{@see ShippingProvider}, erzeugt/storniert Labels und normalisiert den
 * Sendungsverlauf. Der Kern ist bewusst frei von Carrier-Spezifika — jede
 * Anbindung liefert nur den Adapter.
 *
 * Idempotenz: Ein bereits gelabelter Versandauftrag ({@see Shipment::isLabeled()})
 * wird bei erneutem {@see createLabel()} unverändert zurückgegeben; die
 * Carrier-Sendungs-ID wird zusätzlich als {@see ExternalReference} verankert.
 */
class ShipmentService {
    public function __construct(
        private readonly ShippingProviderRegistry $registry,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Erzeugt (idempotent) das Versandlabel und legt das PDF als Attachment
     * (`meta_type = shipping_label`) am Versandauftrag ab.
     */
    public function createLabel(Shipment $shipment, ShipmentRequest $request): Shipment {
        if ($shipment->isLabeled()) {
            return $shipment;
        }

        $connection = $this->connectionFor($shipment);
        $provider = $this->providerFor($shipment);

        $label = $provider->createShipment($connection, $request);

        $this->storeLabel($shipment, $label->labelPdfBase64);

        $shipment->forceFill([
            'status' => ShipmentStatus::Labeled->value,
            'tracking_number' => $label->trackingNumber,
            'carrier_shipment_id' => $label->carrierShipmentId,
            'billing_number' => $shipment->billing_number ?? $connection->billing_number,
            'recipient_snapshot' => $request->recipient->toArray(),
        ])->save();

        ExternalReference::withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => $shipment->carrier,
                'external_type' => 'shipment',
                'referenceable_type' => $shipment->getMorphClass(),
                'referenceable_id' => $shipment->getKey(),
            ],
            [
                'organization_id' => $shipment->organization_id,
                'external_id' => $label->carrierShipmentId,
                'payload' => ['tracking_number' => $label->trackingNumber],
                'synced_at' => now(),
            ],
        );

        return $shipment;
    }

    /**
     * Storniert das Label beim Carrier, solange der Auftrag stornierbar ist
     * (Entwurf/gelabelt, noch nicht unterwegs).
     */
    public function cancel(Shipment $shipment): bool {
        if (! $shipment->status->isCancellable()) {
            throw new RuntimeException('Shipment is not cancellable.');
        }

        $carrierShipmentId = $shipment->carrier_shipment_id;
        if ($carrierShipmentId === null || $carrierShipmentId === '') {
            $shipment->forceFill(['status' => ShipmentStatus::Cancelled->value])->save();

            return true;
        }

        $connection = $this->connectionFor($shipment);
        $provider = $this->providerFor($shipment);

        $ok = $provider->cancelShipment($connection, $carrierShipmentId);
        if ($ok) {
            $shipment->forceFill(['status' => ShipmentStatus::Cancelled->value])->save();
        }

        return $ok;
    }

    /** Ruft den aktuellen Sendungsverlauf beim Carrier ab und übernimmt ihn. */
    public function refreshTracking(Shipment $shipment): Shipment {
        $tracking = $shipment->tracking_number;
        if ($tracking === null || $tracking === '') {
            return $shipment;
        }

        $connection = $this->connectionFor($shipment);
        $provider = $this->providerFor($shipment);

        return $this->applyTracking($shipment, $provider->track($connection, $tracking));
    }

    /**
     * Übernimmt ein Tracking-Ergebnis: Verlauf + Status fortschreiben und beim
     * Übergang auf {@see ShipmentStatus::Problem} eine Zustellproblem-
     * Benachrichtigung auslösen (an die Leitung — betrifft keine Einzelperson).
     */
    public function applyTracking(Shipment $shipment, TrackingResult $result): Shipment {
        $wasProblem = $shipment->status === ShipmentStatus::Problem;

        $events = [];
        foreach ($result->events as $event) {
            $events[] = $event->toArray();
        }

        $shipment->forceFill([
            'status' => $result->status->value,
            'events' => $events,
            'last_tracked_at' => now(),
        ])->save();

        if ($result->status === ShipmentStatus::Problem && ! $wasProblem) {
            $this->notifications->notify(
                NotificationEvent::ShipmentDeliveryProblem,
                $shipment,
                null,
                [
                    'title' => (string) __('shipping.notify.delivery_problem.title'),
                    'message' => (string) __('shipping.notify.delivery_problem.message', [
                        'tracking' => $shipment->tracking_number ?? '—',
                        'carrier' => strtoupper($shipment->carrier),
                    ]),
                    'url' => null,
                ],
            );
        }

        return $shipment;
    }

    /** Aktive Carrier-Anbindung der Organisation zum Carrier des Auftrags. */
    private function connectionFor(Shipment $shipment): CarrierConnection {
        /** @var CarrierConnection|null $connection */
        $connection = CarrierConnection::withoutGlobalScopes()
            ->where('organization_id', $shipment->organization_id)
            ->where('carrier', $shipment->carrier)
            ->where('active', true)
            ->first();

        if ($connection === null) {
            throw new RuntimeException("No active carrier connection for '{$shipment->carrier}'.");
        }

        return $connection;
    }

    private function providerFor(Shipment $shipment): ShippingProvider {
        $provider = $this->registry->for($shipment->carrier);
        if ($provider === null) {
            throw new RuntimeException("No shipping provider registered for '{$shipment->carrier}'.");
        }

        return $provider;
    }

    /** Label-PDF (Base64) als polymorphen Attachment ablegen. */
    private function storeLabel(Shipment $shipment, string $labelPdfBase64): Attachment {
        $binary = base64_decode($labelPdfBase64, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Carrier returned an empty label.');
        }

        $path = 'shipments/labels/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.pdf';
        Storage::disk('local')->put($path, $binary);

        /** @var Attachment $attachment */
        $attachment = $shipment->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'label.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($binary),
            'meta_type' => Shipment::LABEL_META,
        ]);

        return $attachment;
    }
}
