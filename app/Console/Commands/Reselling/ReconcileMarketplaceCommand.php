<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileMarketplaceCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Reselling\ReconciliationStatus;
use App\Models\Organization;
use App\Models\Reselling\CompanyMapping;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeInvoiceLineReader};
use App\Services\Reselling\Contracts\InvoiceLineSource;
use App\Services\Reselling\Marketplace\{MarketplaceContactResolver, MarketplaceEntitlement, MarketplacePurchasesReader, MarketplaceReconciler, PriceCheckBuilder, PriceCheckRow, PriceList, PurchasesImport, PurchasesImportMerger, QualityHostingContractsReader, QualityHostingPriceListReader, ReconciliationCsvBuilder, ReconciliationOptions, ReconciliationReport, ReconciliationReportSerializer};
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Feature 151: Prüft, ob jede Abrechnungsperiode der weiterverkauften
 * Marketplace-Abos mit einer Lexoffice-Ausgangsrechnung belegt ist. Quellen:
 * Telekom Cloud Marketplace („Purchases"-CSV) und Quality Hosting
 * (Vertragsexport XLSX); beide zusammen ergeben den Bestand vor und nach der
 * Migration, Ablösungen werden erkannt. Liest nur; schreibt höchstens die
 * Berichtsdatei.
 */
class ReconcileMarketplaceCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'reselling:reconcile-marketplace
        {purchases* : Exportdateien: Telekom purchases.csv und/oder Quality Hosting Export.xlsx}
        {--map= : Zuordnungsdatei (Zeile: Firma;Lexoffice-Kontakt-UUID oder customer:<Sqid>)}
        {--pricelist= : Reseller-Preisliste (XLSX) für die Preisprüfung}
        {--until= : Stichtag (Y-m-d), Standard heute}
        {--before=45 : Tage vor Periodenbeginn, in denen eine Rechnung zählt}
        {--after=90 : Tage nach Periodenbeginn, in denen eine Rechnung zählt}
        {--csv= : Bericht zusätzlich als CSV (Semikolon, UTF-8) schreiben}
        {--all : auch gedeckte Perioden auflisten}
        {--strict : nur Positionen mit erkannter Edition zählen (Standard: jede Microsoft-Position des Kontakts, wenn keine Edition passt)}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Gleicht Marketplace-Abos (M365-Reselling, Telekom + Quality Hosting) mit den Lexoffice-Ausgangsrechnungen ab: fehlende, teilweise und unter Einkauf berechnete Perioden.';

    public function handle(MarketplacePurchasesReader $telekomReader, QualityHostingContractsReader $qualityHostingReader, QualityHostingPriceListReader $priceListReader, PurchasesImportMerger $merger, MarketplaceContactResolver $resolver, MarketplaceReconciler $reconciler, PriceCheckBuilder $priceCheck, ReconciliationReportSerializer $serializer, ReconciliationCsvBuilder $csv): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->count() !== 1) {
            $this->error($organizations->isEmpty() ? 'Keine Organisation gefunden.' : 'Mehrere Organisationen — bitte --organization angeben.');

            return self::FAILURE;
        }
        /** @var Organization $organization */
        $organization = $organizations->first();

        $config = LexofficeConfig::resolve($organization->id);
        if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
            $this->error("Organisation #{$organization->id} ({$organization->name}): Lexoffice-Plugin nicht aktiv oder ohne API-Key.");

            return self::FAILURE;
        }

        $options = $this->reconciliationOptions();
        if ($options === null) {
            return self::FAILURE;
        }

        $imports = [];
        foreach ((array) $this->argument('purchases') as $file) {
            $file = (string) $file;
            try {
                $imports[] = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                    'xlsx', 'xlsm' => $qualityHostingReader->read($file),
                    default => $telekomReader->read($file),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }
        $import = $merger->merge(...$imports);

        $manual = $this->readMap();
        if ($manual === null) {
            return self::FAILURE;
        }

        $priceList = PriceList::empty();
        $priceListPath = (string) ($this->option('pricelist') ?? '');
        if ($priceListPath !== '') {
            try {
                $priceList = $priceListReader->read($priceListPath);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info(sprintf(
            'Organisation #%d (%s) · %d Positionen (%s) · %d Firmen · %d Ablösungen · Stichtag %s · Fenster -%d/+%d Tage',
            $organization->id,
            $organization->name,
            count($import->entitlements),
            $this->sourceSummary($import),
            count($import->companies()),
            count($import->links),
            $options->reference->format('d.m.Y'),
            $options->windowBefore,
            $options->windowAfter,
        ));
        foreach ($import->issues as $issue) {
            $this->warn('  ' . $issue);
        }
        if ((bool) $this->option('all')) {
            $this->renderSuccessions($import);
        }

        $source = $this->source($config);
        try {
            $source->verifyAccess();
        } catch (Throwable $e) {
            $this->error('Lexoffice nicht erreichbar — Abgleich abgebrochen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $stored = CompanyMapping::targetsFor($organization);
        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $resolver->resolve($organization, $company, $manual, $source, $stored);
        }
        foreach ($resolver->errors() as $error) {
            $this->warn('  Lexoffice-Suche: ' . $error);
        }

        $pool = new \App\Services\Reselling\Marketplace\InvoiceLinePool($source);
        [$from, $to] = \App\Services\Reselling\Marketplace\ReconciliationRunner::globalWindow($import, $options);
        $partners = \App\Services\Reselling\Marketplace\ReconciliationRunner::partnerContacts($organization, $mappings);
        $mappings = (new \App\Services\Reselling\Marketplace\ForeignCustomerTextResolver)->resolve($mappings, $import->companies(), $pool, array_keys($partners), $from, $to, $partners);

        $articles = \App\Services\Reselling\Marketplace\ArticleCatalog::forOrganization($organization->id);
        $report = $this->withOrganizationContext($organization, fn(): ReconciliationReport => $reconciler->reconcile($import->entitlements, $mappings, $source, $options, $pool, $articles));
        $priceRows = $priceCheck->build($import->entitlements, $priceList, $report, $options->reference, $articles);
        $serialized = $serializer->toArray($import, $report, $priceRows, $resolver->errors(), $priceList);

        $this->renderMappings($report);
        $this->renderFindings($report, (bool) $this->option('all'));
        $this->renderExtras($report);
        $this->renderPriceCheck($priceRows, $priceList);
        $this->renderSummary($report);

        $csvPath = (string) ($this->option('csv') ?? '');
        if ($csvPath !== '') {
            $dir = dirname($csvPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            // CsvExport: BOM + Formel-Injektions-Guard je Zelle (S-46) — Firmen-
            // und Positionsnamen sind Fremdtext aus Marketplace und Lexoffice.
            file_put_contents($csvPath, CsvExport::toString($csv->header(), $csv->rows($serialized), ';'));
            $this->info('Bericht geschrieben: ' . $csvPath);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<PriceCheckRow>  $rows
     */
    private function renderPriceCheck(array $rows, PriceList $priceList): void {
        if ($rows === []) {
            return;
        }

        $table = [];
        foreach ($rows as $row) {
            $table[] = [
                $row->product,
                $row->termMonths . ' Mon. / ' . $row->interval->label(),
                (string) $row->runningQuantity,
                $row->contractUnitMin === null ? '' : ($row->contractUnitMin->equals($row->contractUnitMax ?? $row->contractUnitMin) ? $row->contractUnitMin->format() : $row->contractUnitMin->format() . ' – ' . $row->contractUnitMax?->format()),
                $row->listPrice?->format() ?? '—',
                $row->uvp?->format() ?? '—',
                $row->salesMedian === null ? '—' : $row->salesMedian->format() . ' (' . $row->salesSamples . ')',
                $row->articlePrice?->format() ?? '—',
                $row->marginPercent === null ? '' : number_format($row->marginPercent, 1, ',', '') . ' %',
                implode(', ', array_map(static fn(string $flag): string => match ($flag) {
                    PriceCheckRow::FLAG_BELOW_LIST => 'unter Einkauf',
                    PriceCheckRow::FLAG_BELOW_UVP => 'unter UVP',
                    PriceCheckRow::FLAG_CONTRACT_ABOVE_LIST => 'Vertrag teurer als Liste',
                    PriceCheckRow::FLAG_NO_SALES => 'keine Rechnungsdaten',
                    PriceCheckRow::FLAG_NO_LIST => 'nicht in Preisliste',
                    default => $flag,
                }, $row->flags)),
            ];
        }

        $this->newLine();
        $this->line('<comment>Preisprüfung' . ($priceList->isEmpty() ? ' (ohne Preisliste)' : ($priceList->validFrom !== null ? ' (Preisliste gültig ab ' . $priceList->validFrom->format('d.m.Y') . ')' : '')) . '</comment>');
        $this->table(['Produkt', 'Laufzeit', 'Stück laufend', 'Einkauf Vertrag', 'Einkauf Liste', 'UVP', 'Verkauf Median (n)', 'Artikelpreis', 'Marge zur Liste', 'Hinweis'], $table);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function source(array $config): InvoiceLineSource {
        return new LexofficeInvoiceLineReader((string) $config['api_key'], (string) $config['base_url']);
    }

    private function reconciliationOptions(): ?ReconciliationOptions {
        $until = (string) ($this->option('until') ?? '');
        try {
            $reference = $until === '' ? CarbonImmutable::today() : CarbonImmutable::createFromFormat('!Y-m-d', $until);
        } catch (Throwable) {
            $reference = null;
        }
        if (! $reference instanceof CarbonImmutable) {
            $this->error("Stichtag nicht lesbar (Y-m-d erwartet): {$until}");

            return null;
        }

        $before = (int) $this->option('before');
        $after = (int) $this->option('after');
        if ($before < 0 || $after < 0) {
            $this->error('--before und --after müssen ≥ 0 sein.');

            return null;
        }

        return new ReconciliationOptions($reference->startOfDay(), $before, $after, (bool) $this->option('strict'));
    }

    /**
     * @return array<string, string>|null
     */
    private function readMap(): ?array {
        $path = (string) ($this->option('map') ?? '');
        if ($path === '') {
            return [];
        }
        if (! is_readable($path)) {
            $this->error("Zuordnungsdatei nicht lesbar: {$path}");

            return null;
        }

        $map = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', preg_split('/[;,\t]/', $line, 2) ?: []);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                $this->warn("  Zuordnungsdatei: Zeile ignoriert: {$line}");

                continue;
            }
            $map[$parts[0]] = $parts[1];
        }

        return $map;
    }

    private function sourceSummary(PurchasesImport $import): string {
        $parts = [];
        foreach ($import->countBySource() as $source => $count) {
            $parts[] = MarketplaceEntitlement::labelFor($source) . ' ' . $count;
        }

        return $parts === [] ? 'keine' : implode(', ', $parts);
    }

    private function renderSuccessions(PurchasesImport $import): void {
        if ($import->links === []) {
            return;
        }

        $rows = [];
        foreach ($import->links as $link) {
            $rows[] = [
                $link->predecessor->company->name,
                $link->predecessor->edition,
                $link->predecessor->startsOn->format('d.m.Y'),
                $link->predecessor->endsOn?->format('d.m.Y') ?? '',
                $link->successor->entitlementId,
                $link->successor->startsOn->format('d.m.Y'),
            ];
        }

        $this->newLine();
        $this->line('<comment>Ablösungen Telekom → Quality Hosting (Telekom-Laufzeit am Vertragsstart gekappt)</comment>');
        $this->table(['Firma', 'Produkt', 'Telekom ab', 'Telekom bis', 'QH-Vertrag', 'QH ab'], $rows);
    }

    private function renderMappings(ReconciliationReport $report): void {
        $rows = [];
        foreach ($report->companies as $company) {
            $mapping = $company->mapping;
            $rows[] = [
                $mapping->company->name,
                $mapping->customer->name ?? '—',
                $mapping->contactIds !== [] ? implode(', ', $mapping->contactIds) : '—',
                $mapping->sourceLabel(),
                $mapping->candidates !== [] ? implode(' | ', $mapping->candidates) : '',
            ];
        }

        $this->newLine();
        $this->line('<comment>Zuordnung Marketplace-Firma → Lexoffice-Kontakt</comment>');
        $this->table(['Firma', 'Kunde', 'Lexoffice-Kontakt', 'Quelle', 'Kandidaten'], $rows);

        foreach ($report->companies as $company) {
            foreach ($company->errors as $error) {
                $this->error('  ' . $company->company()->name . ': ' . $error);
            }
        }
    }

    private function renderFindings(ReconciliationReport $report, bool $all): void {
        $rows = [];
        foreach ($report->companies as $company) {
            foreach ($company->findings as $finding) {
                if (! $all && ! $finding->status->isProblem()) {
                    continue;
                }
                $rows[] = [
                    $company->company()->name,
                    $finding->period->entitlement->sourceLabel(),
                    $finding->period->entitlement->edition,
                    $finding->period->label(),
                    (string) $finding->period->quantity,
                    $finding->period->fee()->format(),
                    $finding->status->label(),
                    implode(', ', $finding->voucherNumbers()),
                    $finding->lowestUnitNet?->format() ?? '',
                    $finding->note,
                ];
            }
        }

        $this->newLine();
        $this->line('<comment>' . ($all ? 'Alle Perioden' : 'Auffällige Perioden') . '</comment>');
        if ($rows === []) {
            $this->line('  keine');

            return;
        }
        $this->table(['Firma', 'Quelle', 'Edition', 'Periode', 'Menge', 'Einkauf', 'Status', 'Rechnung(en)', 'Netto/Stück', 'Hinweis'], $rows);
    }

    private function renderExtras(ReconciliationReport $report): void {
        $rows = [];
        foreach ($report->companies as $company) {
            foreach ($company->extras as $extra) {
                $rows[] = [
                    $company->company()->name,
                    $extra->line->voucherNumber !== '' ? $extra->line->voucherNumber : $extra->line->voucherId,
                    $extra->line->voucherDate->format('d.m.Y'),
                    $extra->line->name,
                    rtrim(rtrim(number_format($extra->remainingQuantity, 2, ',', ''), '0'), ','),
                    $extra->line->unitNet->format(),
                ];
            }
        }
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->line('<comment>Microsoft-Positionen ohne fällige Periode (berechnet ohne Abo oder Edition nicht erkannt)</comment>');
        $this->table(['Firma', 'Rechnung', 'Datum', 'Position', 'Restmenge', 'Netto/Stück'], $rows);
    }

    private function renderSummary(ReconciliationReport $report): void {
        $counts = $report->countsByStatus();
        $parts = [];
        foreach (ReconciliationStatus::cases() as $status) {
            $parts[] = $status->label() . ': ' . $counts[$status->value];
        }

        $this->newLine();
        $this->line('<comment>Zusammenfassung</comment>');
        $this->line('  Perioden — ' . implode(' · ', $parts));
        $this->line('  Offene Einkaufsgebühr (fehlend/teilweise): ' . $report->openFee()->format());
        $unmapped = $report->unmappedCompanies();
        if ($unmapped !== []) {
            $this->line('  Ohne Zuordnung: ' . count($unmapped) . ' Firmen, Einkaufsgebühr ' . $report->unmappedFee()->format());
            $this->line('  → Zuordnungsdatei anlegen (--map): "Firma;Lexoffice-Kontakt-UUID" oder "Firma;customer:<Sqid>"');
        }
    }

}
