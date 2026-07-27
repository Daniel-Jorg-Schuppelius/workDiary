<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Customer, Organization};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Kunden — Round-Trip zur {@see \App\Services\Import\Specs\CustomerSpec}.
 *
 * Filter:
 * - `status`: active|archived (Standard: alle)
 * - `q`: Freitextsuche über Name / Nummer / Firma
 */
class CustomerExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::Customers;
    }

    public function columns(): array {
        return [
            'name',
            'number',
            'company',
            'vat_id',
            'contact_name',
            'email',
            'phone',
            'mobile',
            'fax',
            'homepage',
            'address',
            'address_street',
            'address_zip',
            'address_city',
            'country',
            'currency',
            'hourly_rate',
            'internal_rate',
            'comment',
            'invoice_text',
            'billable',
            // Roundtrip-Parität zum Import (Vollaudit 2026-07, N55); Export ⊆
            // Import erzwingt der SpecColumnContractTest. `external_id` wird
            // bewusst NICHT exportiert: Import löst sie als Fremd-ID über
            // ExternalReferences auf (mehrere Provider je Kunde möglich) —
            // eine einzelne Export-Spalte wäre mehrdeutig.
            'tags',
        ];
    }

    public function query(Organization $organization, array $filters): iterable {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['q'] ?? ''));

        return Customer::query()
            ->with('tags:id,name')
            ->where('organization_id', $organization->id)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->whereLikeEscaped('name', $search)
                        ->orWhereLikeEscaped('number', $search)
                        ->orWhereLikeEscaped('company', $search);
                });
            })
            ->orderBy('name')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var Customer $model */
        return [
            'name' => $this->str($model->name),
            'number' => $this->str($model->number),
            'company' => $this->str($model->company),
            'vat_id' => $this->str($model->vat_id),
            'contact_name' => $this->str($model->contact_name),
            'email' => $this->str($model->email),
            'phone' => $this->str($model->phone),
            'mobile' => $this->str($model->mobile),
            'fax' => $this->str($model->fax),
            'homepage' => $this->str($model->homepage),
            'address' => $this->str($model->address),
            'address_street' => $this->str($model->address_street),
            'address_zip' => $this->str($model->address_zip),
            'address_city' => $this->str($model->address_city),
            'country' => $this->str($model->country),
            'currency' => $this->str($model->currency->value),
            'hourly_rate' => $this->decimalCell($model->hourly_rate?->getAmount()),
            'internal_rate' => $this->decimalCell($model->internal_rate?->getAmount()),
            'comment' => $this->str($model->comment),
            'invoice_text' => $this->str($model->invoice_text),
            'billable' => $this->boolCell($model->billable),
            // Kommagetrennt — der Import splittet an ,/; (AppliesValueMappings).
            'tags' => $model->tags->pluck('name')->implode(', '),
        ];
    }
}
