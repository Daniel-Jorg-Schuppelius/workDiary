<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\{Invoice, Project};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only-Darstellung einer Rechnung (MVP-718): Kopf, Beträge als
 * Dezimal-String, Kunde als Referenz und der PDF-Download-Link der API.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'number' => $this->number,
            'external_number' => $this->external_number,
            'status' => $this->status,
            'type' => $this->type,
            'category' => $this->category,
            'customer' => $this->whenLoaded('customer', fn(): array => [
                'id' => $this->customer->sqid,
                'name' => $this->customer->name,
            ]),
            'project_id' => Sqid::encodeOrNull(Project::class, $this->project_id),
            'parent_invoice_id' => Sqid::encodeOrNull(Invoice::class, $this->parent_invoice_id),
            'issued_on' => $this->issued_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'paid_on' => $this->paid_on?->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'currency' => $this->currency->value,
            'subtotal' => $this->subtotal?->getAmount(),
            'tax_amount' => $this->tax_amount?->getAmount(),
            'total' => $this->total?->getAmount(),
            'delivery_format' => $this->delivery_format->value,
            'pdf_url' => route('api.invoices.pdf', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
