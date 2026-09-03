<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationRunner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\{CompanyMappingMode, ReconciliationRunStatus};
use App\Models\{Customer, ExternalReference, ForeignCustomer, Organization};
use App\Models\Reselling\{CompanyMapping, ReconciliationRun};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeInvoiceLineReader, LexofficePlugin};
use App\Services\Reselling\Contracts\InvoiceLineSource;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\{Cache, Storage};
use RuntimeException;
use Throwable;

/**
 * Führt einen Lauf aus der Oberfläche aus: Dateien lesen, zusammenführen,
 * zuordnen, abgleichen, Preise prüfen, Bericht am Lauf speichern. Fehler
 * enden im Status „fehlgeschlagen" mit Klartext, nie in einem halben Bericht.
 */
final class ReconciliationRunner {
    public function __construct(
        private readonly MarketplacePurchasesReader $telekomReader,
        private readonly QualityHostingContractsReader $qualityHostingReader,
        private readonly QualityHostingPriceListReader $priceListReader,
        private readonly PurchasesImportMerger $merger,
        private readonly MarketplaceContactResolver $resolver,
        private readonly MarketplaceReconciler $reconciler,
        private readonly PriceCheckBuilder $priceCheck,
        private readonly ReconciliationReportSerializer $serializer,
        private readonly ForeignCustomerTextResolver $textResolver = new ForeignCustomerTextResolver(),
    ) {}

    public function run(ReconciliationRun $run, ?InvoiceLineSource $source = null): void {
        $organization = Organization::query()->find($run->organization_id);
        if (! $organization instanceof Organization) {
            $this->fail($run, 'Organisation nicht gefunden.');

            return;
        }

        $run->status = ReconciliationRunStatus::Running;
        $run->started_at = CarbonImmutable::now();
        $run->error = null;
        $run->save();

        $lock = Cache::lock(LexofficeConfig::apiLockKey($organization->id), 1800);
        try {
            $lock->block(600);
        } catch (LockTimeoutException) {
            $this->fail($run, 'Ein anderer Lexoffice-Lauf dieser Organisation blockiert seit 10 Minuten — bitte später erneut starten.');

            return;
        }

        try {
            $report = OrganizationContext::run($organization, fn(): array => $this->execute($run, $organization, $source));
        } catch (Throwable $e) {
            $lock->release();
            $this->fail($run, $e->getMessage());

            return;
        }

        $lock->release();

        $run->report = $report;
        $run->summary = $report['summary'];
        $run->status = ReconciliationRunStatus::Done;
        $run->finished_at = CarbonImmutable::now();
        $run->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(ReconciliationRun $run, Organization $organization, ?InvoiceLineSource $source): array {
        $config = LexofficeConfig::resolve($organization->id);
        if ($source === null) {
            if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
                throw new RuntimeException('Lexoffice-Plugin ist für diese Organisation nicht aktiv oder ohne API-Schlüssel.');
            }
            $source = new LexofficeInvoiceLineReader((string) $config['api_key'], (string) $config['base_url']);
        }

        $disk = Storage::disk(ReconciliationRun::DISK);
        $imports = [];
        foreach ($run->filesOfKind(ReconciliationRun::KIND_TELEKOM) as $file) {
            $imports[] = $this->telekomReader->read($disk->path($file['path']));
        }
        foreach ($run->filesOfKind(ReconciliationRun::KIND_QUALITYHOSTING) as $file) {
            $imports[] = $this->qualityHostingReader->read($disk->path($file['path']));
        }
        if ($imports === []) {
            throw new RuntimeException('Keine Exportdatei (Telekom oder Quality Hosting) am Lauf.');
        }
        $import = $this->merger->merge(...$imports);

        $priceList = PriceList::empty();
        foreach ($run->filesOfKind(ReconciliationRun::KIND_PRICELIST) as $file) {
            $priceList = $this->priceListReader->read($disk->path($file['path']));
        }

        $manual = [];
        foreach ($run->filesOfKind(ReconciliationRun::KIND_MAP) as $file) {
            $manual += $this->readMap($disk->path($file['path']));
        }

        $source->verifyAccess();

        $stored = CompanyMapping::targetsFor($organization);
        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $this->resolver->resolve($organization, $company, $manual, $source, $stored);
        }

        $options = new ReconciliationOptions($run->reference_date->startOfDay(), $run->window_before, $run->window_after, (bool) $run->strict_products);

        // Fremdkunden über Rechnungstexte der Partner: Der Endkunde steht bei
        // Partnerrechnungen im Titel/Einleitung/Schlusstext, nicht in den Positionen.
        $pool = new InvoiceLinePool($source);
        [$from, $to] = self::globalWindow($import, $options);
        $partners = self::partnerContacts($organization, $mappings);
        $mappings = $this->textResolver->resolve($mappings, $import->companies(), $pool, array_keys($partners), $from, $to, $partners);

        $articles = ArticleCatalog::forOrganization($organization->id);
        $report = $this->reconciler->reconcile($import->entitlements, $mappings, $source, $options, $pool, $articles);
        $priceRows = $this->priceCheck->build($import->entitlements, $priceList, $report, $options->reference, $articles);

        return $this->serializer->toArray($import, $report, $priceRows, $this->resolver->errors(), $priceList);
    }

    /**
     * Zeitraum, in dem Partnerrechnungen nach Endkunden-Nennungen durchsucht werden.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function globalWindow(PurchasesImport $import, ReconciliationOptions $options): array {
        $from = null;
        foreach ($import->entitlements as $entitlement) {
            $from = $from === null || $entitlement->startsOn->lessThan($from) ? $entitlement->startsOn : $from;
        }
        $from ??= $options->reference;

        return [$from->subDays($options->windowBefore), $options->reference->addDays($options->windowAfter)];
    }

    /**
     * Partner-Pool: Kontakte der bereits zugeordneten Firmen, Kunden mit
     * Fremdkunden und Kunden aus gespeicherten Partner-Zuordnungen.
     *
     * @param  array<string, ContactMapping>  $mappings
     * @return array<string, Customer|null> Kontakt-ID → Kunde (falls bekannt)
     */
    public static function partnerContacts(Organization $organization, array $mappings): array {
        $partners = [];
        foreach ($mappings as $mapping) {
            foreach ($mapping->contactIds as $contactId) {
                $partners[$contactId] = $mapping->customer;
            }
        }

        $customerIds = ForeignCustomer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')->distinct()->pluck('customer_id')->all();
        $storedPartnerIds = CompanyMapping::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('mode', CompanyMappingMode::Partner->value)->whereNotNull('customer_id')->pluck('customer_id')->all();
        $customerIds = array_values(array_unique(array_merge(array_map('intval', $customerIds), array_map('intval', $storedPartnerIds))));
        if ($customerIds !== []) {
            $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', $customerIds)->get()->keyBy('id');
            $refs = ExternalReference::query()
                ->forPlugin($organization, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
                ->where('referenceable_type', (new Customer)->getMorphClass())
                ->whereIn('referenceable_id', $customerIds)
                ->get(['external_id', 'referenceable_id']);
            foreach ($refs as $ref) {
                $contactId = (string) $ref->external_id;
                if ($contactId !== '' && ! isset($partners[$contactId])) {
                    $partners[$contactId] = $customers->get((int) $ref->referenceable_id);
                }
            }
        }

        return $partners;
    }

    /**
     * Zuordnungsdatei: `Firma;Lexoffice-Kontakt-UUID` oder `Firma;customer:<Sqid>`.
     *
     * @return array<string, string>
     */
    public function readMap(string $path): array {
        $map = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', preg_split('/[;,\t]/', $line, 2) ?: []);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $map[$parts[0]] = $parts[1];
            }
        }

        return $map;
    }

    private function fail(ReconciliationRun $run, string $message): void {
        $run->status = ReconciliationRunStatus::Failed;
        $run->error = mb_substr($message, 0, 2000);
        $run->finished_at = CarbonImmutable::now();
        $run->save();
    }
}
