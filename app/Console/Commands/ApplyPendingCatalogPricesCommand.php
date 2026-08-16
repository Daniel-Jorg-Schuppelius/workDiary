<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplyPendingCatalogPricesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\{CatalogItemUpserter, DatanormImportService};
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Wendet vorgemerkte zukünftige DATANORM-Preisstände an (Feature 107,
 * W3-Rest): DATPREIS-Sätze mit Gültigkeitsdatum werden beim Import nur in
 * `extra_attributes` vorgemerkt; dieser Lauf aktiviert fällige Preise über
 * den Delta-Upsert (inklusive Preishistorie und Margen-Warnungen) und räumt
 * die Vormerkung ab.
 */
class ApplyPendingCatalogPricesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'catalog:apply-pending-prices';

    protected $description = 'Aktiviert fällige, vorgemerkte DATANORM-Preisstände (Feature 107).';

    public function handle(CatalogItemUpserter $upserter): int {
        $today = Carbon::now()->toDateString();
        $applied = 0;

        $pending = SupplierCatalogItem::query()->withoutGlobalScopes()
            ->where('extra_attributes', 'like', '%' . DatanormImportService::PENDING_PRICE_KEY . '%')
            ->with('source.organization')
            ->get()
            ->groupBy('supplier_catalog_source_id');

        foreach ($pending as $items) {
            /** @var SupplierCatalogSource|null $source */
            $source = $items->first()?->source;
            if ($source === null) {
                continue;
            }

            $records = [];
            foreach ($items as $item) {
                $extra = (array) ($item->extra_attributes ?? []);
                $price = $extra[DatanormImportService::PENDING_PRICE_KEY] ?? null;
                if (! is_array($price) || (string) ($price['valid_from'] ?? '') > $today) {
                    continue;
                }
                unset($extra[DatanormImportService::PENDING_PRICE_KEY], $price['valid_from']);
                $records[] = ['external_no' => $item->external_no, 'extra_attributes' => $extra] + $price;
            }
            if ($records === []) {
                continue;
            }

            $organization = $source->organization;
            $run = function () use ($upserter, $source, $records): array {
                return $upserter->persist($source, $records, 'datanorm:pending-prices', snapshot: false);
            };
            $summary = $organization !== null
                ? $this->withOrganizationContext($organization, $run)
                : $run();
            $applied += count($records);
            $this->info(sprintf(
                'Quelle #%d: %d fällige Preisstände angewendet (%d Preisänderungen).',
                $source->id,
                count($records),
                (int) ($summary['price_changed'] ?? 0)
            ));
        }

        $this->info(sprintf('%d Preisstand/-stände angewendet.', $applied));

        return self::SUCCESS;
    }
}
