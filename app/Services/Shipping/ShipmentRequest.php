<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

/**
 * Providerneutrale Anforderung eines Versandlabels (Feature 059, MVP-128):
 * Empfänger, Packstücke, eine fachliche Referenz (Auslieferungs-/Auftragsnummer)
 * und die optionale Carrier-Abrechnungs-/Kostenstellenreferenz.
 */
final class ShipmentRequest {
    /**
     * @param  list<ShipmentPackage>  $packages
     */
    public function __construct(
        public readonly ShipmentRecipient $recipient,
        public readonly array $packages,
        public readonly string $reference,
        public readonly ?string $billingNumber = null,
    ) {}
}
