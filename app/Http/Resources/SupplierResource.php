<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung eines Lieferanten (MVP-718). Bewusst OHNE Bank-
 * verbindung, Steuernummer und Ansprechpartner-Liste — nur Firmen-/
 * Kontaktstammdaten, die für Integrationen nötig sind.
 *
 * @mixin Supplier
 */
class SupplierResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'name' => $this->name,
            'number' => $this->number,
            'vendor_number' => $this->vendor_number,
            'company' => $this->company,
            'vat_id' => $this->vat_id,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'homepage' => $this->homepage,
            'address_street' => $this->address_street,
            'address_zip' => $this->address_zip,
            'address_city' => $this->address_city,
            'country' => $this->country,
            'currency' => $this->currency->value,
            'active' => (bool) $this->active,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
