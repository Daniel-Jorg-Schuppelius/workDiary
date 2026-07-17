<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchDueCatalogsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{SupplierCatalogImport, SupplierCatalogSource};
use App\Services\Procurement\{CatalogFetchService, CatalogImportDispatcher};
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ruft fällige Remote-Katalogquellen ab und importiert sie (Feature 050,
 * MVP-091, geplanter Abruf). Fällig = aktiv, Remote-Quelltyp, Intervall gesetzt
 * und `next_fetch_at` erreicht/leer. Jeder Lauf wird protokolliert; Fehler einer
 * Quelle brechen den Gesamtlauf nicht ab.
 */
class FetchDueCatalogsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'catalog:fetch-due';

    protected $description = 'Ruft fällige Remote-Katalogquellen ab und importiert sie (Feature 050).';

    public function handle(CatalogFetchService $fetch, CatalogImportDispatcher $dispatcher): int {
        $due = SupplierCatalogSource::query()->withoutGlobalScopes()
            ->where('active', true)
            ->whereIn('source_type', ['http', 'ftp', 'sftp'])
            ->where('fetch_interval_minutes', '>', 0)
            ->where(fn ($q) => $q->whereNull('next_fetch_at')->orWhere('next_fetch_at', '<=', Carbon::now()))
            ->get();

        foreach ($due as $source) {
            $organization = $source->organization;
            if ($organization !== null) {
                $this->withOrganizationContext($organization, fn () => $this->process($source, $fetch, $dispatcher));
            } else {
                $this->process($source, $fetch, $dispatcher);
            }
        }

        $this->info(sprintf('%d Quelle(n) verarbeitet.', $due->count()));

        return self::SUCCESS;
    }

    private function process(SupplierCatalogSource $source, CatalogFetchService $fetch, CatalogImportDispatcher $dispatcher): void {
        try {
            $content = $fetch->fetch($source);
        } catch (Throwable $e) {
            $dispatcher->recordFailure($source, SupplierCatalogImport::TRIGGER_SCHEDULED, $e->getMessage());
            $this->scheduleNext($source);

            return;
        }

        try {
            $dispatcher->run($source, $content, (array) ($source->mapping ?? []), SupplierCatalogImport::TRIGGER_SCHEDULED);
        } catch (Throwable) {
            // Importfehler sind bereits im Dispatcher protokolliert.
        }

        $this->scheduleNext($source);
    }

    private function scheduleNext(SupplierCatalogSource $source): void {
        $source->forceFill([
            'next_fetch_at' => Carbon::now()->addMinutes((int) $source->fetch_interval_minutes),
        ])->save();
    }
}
