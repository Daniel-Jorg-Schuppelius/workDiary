<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleImportIncomingCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Reselling\Register\IncomingProviderInvoiceImport;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Console\Command;

/**
 * Rückwirkend: Eingangsrechnungen zugeordneter Anbieter-Lieferanten als
 * Einkauf übernehmen (Feature 185, MVP-1420). Schon übernommene Zeilen
 * erkennt der Import am Hash.
 */
class ResaleImportIncomingCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'resale:import-incoming {--since= : Belegdatum ab (YYYY-MM-DD), Vorgabe 365 Tage} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Übernimmt Eingangsrechnungen der zugeordneten Anbieter-Lieferanten rückwirkend als Einkauf des Reselling-Registers.';

    public function handle(IncomingProviderInvoiceImport $import): int {
        $sinceOption = (string) ($this->option('since') ?? '');
        $since = $sinceOption !== '' ? DateHelper::normalizeToIso($sinceOption) : null;
        if ($sinceOption !== '' && $since === null) {
            $this->error('Ungültiges Datum für --since (erwartet YYYY-MM-DD).');

            return self::INVALID;
        }
        $from = $since !== null ? CarbonImmutable::parse($since) : CarbonImmutable::now()->subDays(365);

        $this->forEachOrganization(function (Organization $org) use ($import, $from): void {
            $incomings = IncomingEInvoice::query()->withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->purchases()
                ->whereNotNull('supplier_id')
                ->where(static fn($q) => $q->where('issue_date', '>=', DateRange::day($from))->orWhere(static fn($n) => $n->whereNull('issue_date')->where('received_at', '>=', DateRange::dayStart($from))))
                ->orderBy('issue_date')
                ->get();
            $done = 0;
            $skipped = 0;
            foreach ($incomings as $incoming) {
                $result = OrganizationContext::run($org, static fn(): ?array => $import->import($incoming));
                if ($result === null) {
                    $skipped++;

                    continue;
                }
                $done++;
                $this->line(sprintf('  %s: %s — %d Zeilen, %.2f netto%s%s', $result['number'] !== '' ? $result['number'] : '#' . $incoming->id, $result['kind'], $result['entries'], $result['net'], $result['unmatched'] !== [] ? ', ohne Abo: ' . count($result['unmatched']) : '', $result['error'] !== null ? ' — ' . $result['error'] : ($result['skipped'] !== null ? ' — ' . $result['skipped'] : '')));
            }
            $this->line("Organisation #{$org->id} ({$org->name}): {$done} Eingangsrechnungen übernommen, {$skipped} ohne Anbieter-Zuordnung.");
        });

        return self::SUCCESS;
    }
}
