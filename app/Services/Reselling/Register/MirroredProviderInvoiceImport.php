<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredProviderInvoiceImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\Platform\Organization;
use App\Models\Reselling\ResalePurchaseEntry;
use App\Services\Billing\Purchase\{PurchaseDocument, PurchaseDocuments, SupplierPurchaseOriginals};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anbieterrechnungen, die nur im Buchhaltungsprogramm liegen, als Einkauf
 * übernehmen (Phase 175). Der Rechnungseingang führt: Eine Rechnung, deren
 * Nummer der Einkauf schon kennt, wird nicht noch einmal gelesen.
 *
 * @phpstan-import-type Result from IncomingProviderInvoiceImport
 */
final class MirroredProviderInvoiceImport {
    public function __construct(
        private readonly PurchaseDocuments $documents,
        private readonly ProviderSuppliers $suppliers,
        private readonly IncomingProviderInvoiceImport $import,
    ) {}

    /** @return list<Result> je gelesenem Beleg */
    public function run(Organization $organization, CarbonImmutable $from): array {
        $results = [];
        $mappings = $this->suppliers->all($organization);
        if ($mappings->isEmpty()) {
            return $results;
        }
        foreach ($this->documents->sources() as $source) {
            if (! $source instanceof SupplierPurchaseOriginals) {
                continue;
            }
            foreach ($mappings as $mapping) {
                if (! IncomingProviderInvoiceImport::readsMirrored($mapping->provider)) {
                    continue;
                }
                foreach ($source->forSupplier($organization, $mapping->supplier, $from) as $document) {
                    if ($this->known($organization, $mapping->provider->value, $document)) {
                        continue;
                    }
                    try {
                        $result = $this->import->importMirrored($organization, $mapping->provider, $document, $source->original($organization, $document));
                        if ($result !== null) {
                            $results[] = $result;
                        }
                    } catch (Throwable $e) {
                        // Ein Beleg ohne erreichbares Original hält die übrigen nicht auf.
                        Log::warning('resale: gespiegelter Einkaufsbeleg nicht übernommen', ['document' => $document->identity(), 'exception' => $e::class, 'message' => $e->getMessage()]);
                    }
                }
            }
        }

        return $results;
    }

    private function known(Organization $organization, string $provider, PurchaseDocument $document): bool {
        $number = trim((string) $document->number);

        return ResalePurchaseEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(static fn($q) => $q
                ->where(static fn($d) => $d->where('document_type', $document->morphClass)->where('document_id', $document->morphId))
                ->when($number !== '', static fn($w) => $w->orWhere(static fn($n) => $n->where('provider', $provider)->where('document_number', $number))))
            ->exists();
    }
}
