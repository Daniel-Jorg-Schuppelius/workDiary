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

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Reselling\Register\{IncomingProviderInvoiceImport, MirroredProviderInvoiceImport};
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper};
use Illuminate\Console\Command;

/**
 * Eingangsrechnungen zugeordneter Anbieter-Lieferanten als Einkauf übernehmen
 * (Feature 185, MVP-1420), danach Belege, die nur im Buchhaltungsprogramm
 * liegen (Phase 175). Täglich; schon übernommene Zeilen erkennt der Import am Hash.
 */
class ResaleImportIncomingCommand extends Command {
    use IteratesModuleOrganizations;

    protected $signature = 'resale:import-incoming {--since= : Belegdatum ab (YYYY-MM-DD), Vorgabe 365 Tage} {--days= : Belegdatum der letzten N Tage (geplanter Lauf)} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Übernimmt Rechnungen der zugeordneten Anbieter-Lieferanten aus Rechnungseingang und Buchhaltungsprogramm als Einkauf des Reselling-Registers.';

    public function handle(IncomingProviderInvoiceImport $import, MirroredProviderInvoiceImport $mirrored): int {
        $sinceOption = (string) ($this->option('since') ?? '');
        $since = $sinceOption !== '' ? DateHelper::normalizeToIso($sinceOption) : null;
        if ($sinceOption !== '' && $since === null) {
            $this->error('Ungültiges Datum für --since (erwartet YYYY-MM-DD).');

            return self::INVALID;
        }
        $days = NumberHelper::toIntOrNull($this->option('days'));
        $from = $since !== null ? CarbonImmutable::parse($since) : CarbonImmutable::now()->subDays($days !== null && $days > 0 ? $days : 365);

        $this->forEachOrganization(function (Organization $org) use ($import, $mirrored, $from): void {
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
                $this->report($result, '#' . $incoming->id);
            }
            $fromMirror = OrganizationContext::run($org, static fn(): array => $mirrored->run($org, $from));
            foreach ($fromMirror as $result) {
                $this->report($result, '?');
            }
            if ($done === 0 && $fromMirror === []) {
                return;
            }
            $this->line(sprintf('Organisation #%d (%s): %d Eingangsrechnungen übernommen, %d ohne Anbieter-Zuordnung, %d Belege aus dem Buchhaltungsprogramm.', $org->id, $org->name, $done, $skipped, count($fromMirror)));
        });

        return self::SUCCESS;
    }

    /** @param array{kind: string, number: string, entries: int, net: float, unmatched: list<string>, duplicates: int, issues: list<string>, error: string|null, skipped: string|null} $result */
    private function report(array $result, string $fallback): void {
        $this->line(sprintf('  %s: %s — %d Zeilen, %.2f netto%s%s', $result['number'] !== '' ? $result['number'] : $fallback, $result['kind'], $result['entries'], $result['net'], $result['unmatched'] !== [] ? ', ohne Abo: ' . count($result['unmatched']) : '', $result['error'] !== null ? ' — ' . $result['error'] : ($result['skipped'] !== null ? ' — ' . $result['skipped'] : '')));
    }
}
