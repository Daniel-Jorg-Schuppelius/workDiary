<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{BillingFrequency, ImportStatus, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{CompanyMapping, ResaleImport, ResalePeriod, ResalePriceEntry, ResaleSubscription};
use App\Services\Platform\Catalog\CatalogArticle;
use App\Services\Reselling\Marketplace\{GenericSubscriptionReader, MarketplaceEntitlement, MarketplacePurchasesReader, PriceListEntry, ProductNameMatcher, PurchasesImport, PurchasesImportMerger, QualityHostingContractsReader, QualityHostingPriceListReader, UnitPriceCatalog};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, DateHelper, JsonHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

/**
 * Bringt die Anbieter-Exporte (Feature 152, MVP-759) ins Register: je
 * Position ein Abo, Upsert über Anbieter und Kennung, Ablösung Telekom →
 * Quality Hosting als Nachfolger, Halter nur aus dem Datenbestand (sonst
 * Inbox), Verkaufspreis aus dem passenden Lexoffice-Artikel — nie über eine
 * manuelle Pflege hinweg (auch Einkaufspreis und Artikel). Domains kommen
 * nur über den Domain-Sync, nie aus einer Datei. Die Preisliste füllt den
 * Einkaufskatalog; Gültigkeiten schließen sich je Produkt lückenlos an.
 */
final class MarketplaceImporter {
    public const CHANGE_NEW = 'new';

    public const CHANGE_CHANGED = 'changed';

    public const CHANGE_UNCHANGED = 'unchanged';

    /** Vergleichsfelder der Vorschau in Anzeigereihenfolge; `other` = nur Hash-Felder (Bezeichnung, Intervall, Laufzeit). */
    public const CHANGE_FIELDS = ['quantity', 'price', 'status', 'starts_on', 'ends_on', 'other'];

    public function __construct(
        private readonly MarketplacePurchasesReader $telekomReader,
        private readonly QualityHostingContractsReader $qualityHostingReader,
        private readonly QualityHostingPriceListReader $priceListReader,
        private readonly GenericSubscriptionReader $genericReader,
        private readonly PurchasesImportMerger $merger,
        private readonly HolderResolver $holders,
        private readonly PeriodPlanner $planner,
        private readonly ResaleArticleCandidates $candidates,
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
        private readonly ?AllocationDeriver $allocations = null,
        private readonly ?LinkProposer $proposer = null,
    ) {}

    /**
     * Vorschau ohne Schreiben (Feature 185, MVP-1418): liest die Dateien wie der
     * Import und stellt jede Position dem Bestand gegenüber — neu, geändert
     * (Menge, Preis, Status, Beginn, Ende) oder unverändert; dazu je Anbieter
     * die planbaren Abos, die die Datei nicht mehr nennt. Halter werden hier
     * nicht aufgelöst (das legt Endkunden an), nur der vorhandene gezeigt.
     *
     * @param  array<string, array{name: string, path: string, stored?: string|null}>  $files  Schlüssel: ResaleImport::KIND_*
     * @return array{kinds: array<string, array{kind: string, label: string, file: string, export_date: string|null, error: string|null, issues: list<string>, counts: array{new: int, changed: int, unchanged: int, missing: int}, rows: list<array<string, mixed>>, missing: list<array<string, mixed>>}>, pricelist: array{file: string, rows: int, error: string|null}|null, total: array{new: int, changed: int, unchanged: int, missing: int}}
     */
    public function preview(Organization $organization, array $files, ?CarbonImmutable $reference = null, SubscriptionProvider $genericProvider = SubscriptionProvider::Other): array {
        $reference ??= ResalePeriod::today();
        /** @var array<string, array{file: string, export_date: string|null, error: string|null}> $meta */
        $meta = [];
        /** @var array<string, list<string>> $issues */
        $issues = [];
        /** @var array<string, array<string, int>> $counts Art → Änderung → Zähler */
        $counts = [];
        /** @var array<string, list<array<string, mixed>>> $rows */
        $rows = [];
        /** @var array<string, list<array<string, mixed>>> $missing */
        $missing = [];
        $imports = [];
        foreach ([ResaleImport::KIND_PURCHASES, ResaleImport::KIND_CONTRACTS, ResaleImport::KIND_GENERIC] as $kind) {
            if (! isset($files[$kind])) {
                continue;
            }
            $meta[$kind] = ['file' => $files[$kind]['name'], 'export_date' => self::exportDateOf($files[$kind]['name'])?->toDateString(), 'error' => null];
            $issues[$kind] = [];
            $counts[$kind] = [];
            $rows[$kind] = [];
            $missing[$kind] = [];
            try {
                $parsed = match ($kind) {
                    ResaleImport::KIND_PURCHASES => $this->telekomReader->read($files[$kind]['path'], $files[$kind]['name']),
                    ResaleImport::KIND_CONTRACTS => $this->qualityHostingReader->read($files[$kind]['path'], $files[$kind]['name']),
                    default => $this->genericReader->read($files[$kind]['path'], $genericProvider, $files[$kind]['name']),
                };
                $imports[$kind] = $parsed;
                $issues[$kind] = array_map('strval', $parsed->issues);
            } catch (Throwable $e) {
                $meta[$kind]['error'] = $e->getMessage();
            }
        }

        if ($imports !== []) {
            $merged = $this->merger->merge(...array_values($imports));
            $catalog = UnitPriceCatalog::fromEntitlements($merged->entitlements);
            $successorOf = $this->successorsOf($merged);
            /** @var array<string, array<string, true>> $seen Anbieter → Kennungen der Datei */
            $seen = [];
            /** @var array<string, array<string, true>> $providersByKind */
            $providersByKind = [];
            foreach ($merged->entitlements as $entitlement) {
                $kind = self::kindOf($entitlement);
                if (! isset($counts[$kind])) {
                    continue;
                }
                $provider = $this->provider($entitlement);
                if ($provider === SubscriptionProvider::DomainReselling) {
                    $issues[$kind][] = (string) __('resale.import_issues.domain_provider', ['line' => $entitlement->sourceLine, 'company' => $entitlement->company->name, 'product' => $entitlement->edition]);

                    continue;
                }
                $plan = $this->plan($entitlement, $catalog, $successorOf, $reference);
                $seen[$provider->value][$plan['external_id']] = true;
                $providersByKind[$kind][$provider->value] = true;
                $existing = ResaleSubscription::query()->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('provider', $provider->value)
                    ->where('external_id', $plan['external_id'])
                    ->first();
                $changes = $existing === null ? [] : self::changesOf($existing, $plan);
                $change = $existing === null ? self::CHANGE_NEW : ($changes === [] ? self::CHANGE_UNCHANGED : self::CHANGE_CHANGED);
                $counts[$kind][$change] = ($counts[$kind][$change] ?? 0) + 1;
                $rows[$kind][] = [
                    'line' => $entitlement->sourceLine,
                    'external_id' => $plan['external_id'],
                    'company' => $entitlement->company->name,
                    'product' => $entitlement->edition,
                    'quantity' => $plan['quantity'],
                    'starts_on' => $plan['attributes']['starts_on'],
                    'ends_on' => $plan['attributes']['ends_on'],
                    'status' => $plan['status'],
                    'unit_fee' => $plan['unit_fee']->withScale(4),
                    'change' => $change,
                    'changes' => $changes,
                    'previous' => $existing === null ? null : [
                        'quantity' => (int) $existing->quantity,
                        'starts_on' => $existing->starts_on->toDateString(),
                        'ends_on' => $existing->ends_on?->toDateString(),
                        'status' => $existing->status,
                        'unit_fee' => $existing->provider_unit_price ?? $existing->purchase_unit_price,
                    ],
                    'price_follows' => $existing !== null && in_array('price', $changes, true) && self::purchaseFollowsProvider($existing),
                    'holder' => $existing?->hasHolder() === true ? $existing->holderLabel() : null,
                ];
            }
            foreach ($providersByKind as $kind => $providers) {
                foreach (array_keys($providers) as $providerValue) {
                    $gone = ResaleSubscription::query()->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->where('provider', $providerValue)
                        ->whereNotNull('external_id')
                        ->planning()
                        ->whereNotIn('external_id', array_keys($seen[$providerValue] ?? []))
                        ->orderBy('label')
                        ->get();
                    foreach ($gone as $subscription) {
                        $missing[$kind][] = [
                            'subscription' => $subscription,
                            'label' => $subscription->label,
                            'external_id' => (string) $subscription->external_id,
                            'holder' => $subscription->hasHolder() ? $subscription->holderLabel() : null,
                            'status' => $subscription->status,
                            'ends_on' => $subscription->ends_on?->toDateString(),
                        ];
                    }
                }
            }
        }

        $pricelist = null;
        if (isset($files[ResaleImport::KIND_PRICELIST])) {
            $pricelist = ['file' => $files[ResaleImport::KIND_PRICELIST]['name'], 'rows' => 0, 'error' => null];
            try {
                $pricelist['rows'] = count($this->priceListReader->read($files[ResaleImport::KIND_PRICELIST]['path'], $files[ResaleImport::KIND_PRICELIST]['name'])->entries);
            } catch (Throwable $e) {
                $pricelist['error'] = $e->getMessage();
            }
        }

        $kinds = [];
        $total = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'missing' => 0];
        foreach ($meta as $kind => $info) {
            $kindCounts = [
                'new' => $counts[$kind][self::CHANGE_NEW] ?? 0,
                'changed' => $counts[$kind][self::CHANGE_CHANGED] ?? 0,
                'unchanged' => $counts[$kind][self::CHANGE_UNCHANGED] ?? 0,
                'missing' => count($missing[$kind]),
            ];
            $kinds[$kind] = [
                'kind' => $kind,
                'label' => (string) __('resale.import.kind.' . $kind),
                'file' => $info['file'],
                'export_date' => $info['export_date'],
                'error' => $info['error'],
                'issues' => $issues[$kind],
                'counts' => $kindCounts,
                'rows' => $rows[$kind],
                'missing' => $missing[$kind],
            ];
            $total = ['new' => $total['new'] + $kindCounts['new'], 'changed' => $total['changed'] + $kindCounts['changed'], 'unchanged' => $total['unchanged'] + $kindCounts['unchanged'], 'missing' => $total['missing'] + $kindCounts['missing']];
        }

        return ['kinds' => $kinds, 'pricelist' => $pricelist, 'total' => $total];
    }

    /**
     * @param  array<string, array{name: string, path: string, stored?: string|null}>  $files  Schlüssel: ResaleImport::KIND_*
     * @param  SubscriptionProvider  $genericProvider  Anbieter der generischen Liste, wenn die Datei keine Spalte „Anbieter" hat
     * @return list<ResaleImport>
     */
    public function import(Organization $organization, ?User $user, array $files, ?CarbonImmutable $reference = null, SubscriptionProvider $genericProvider = SubscriptionProvider::Other): array {
        $reference ??= ResalePeriod::today();
        $this->holders->reset();
        $records = [];
        $imports = [];

        foreach ([ResaleImport::KIND_PURCHASES => SubscriptionProvider::TelekomMarketplace, ResaleImport::KIND_CONTRACTS => SubscriptionProvider::QualityHosting, ResaleImport::KIND_GENERIC => $genericProvider] as $kind => $provider) {
            if (! isset($files[$kind])) {
                continue;
            }
            $record = $this->record($organization, $user, $provider, $kind, $files[$kind]);
            try {
                $parsed = match ($kind) {
                    ResaleImport::KIND_PURCHASES => $this->telekomReader->read($files[$kind]['path'], $files[$kind]['name']),
                    ResaleImport::KIND_CONTRACTS => $this->qualityHostingReader->read($files[$kind]['path'], $files[$kind]['name']),
                    default => $this->genericReader->read($files[$kind]['path'], $genericProvider, $files[$kind]['name']),
                };
                $imports[$kind] = ['record' => $record, 'import' => $parsed];
                $record->rows_total = count($parsed->entitlements);
                $record->issues = array_map('strval', $parsed->issues);
            } catch (Throwable $e) {
                $record->forceFill(['status' => ImportStatus::Failed, 'error' => $e->getMessage()])->save();
            }
            $records[] = $record;
        }

        if ($imports !== []) {
            $merged = $this->merger->merge(...array_map(static fn(array $i): PurchasesImport => $i['import'], array_values($imports)));
            $counters = $this->upsertEntitlements($organization, $merged, $reference, array_map(static fn(array $i): ResaleImport => $i['record'], $imports));
            foreach ($imports as $kind => $item) {
                $item['record']->forceFill($counters[$kind] ?? [])->save();
            }
        }

        if (isset($files[ResaleImport::KIND_PRICELIST])) {
            $record = $this->record($organization, $user, SubscriptionProvider::QualityHosting, ResaleImport::KIND_PRICELIST, $files[ResaleImport::KIND_PRICELIST]);
            try {
                $list = $this->priceListReader->read($files[ResaleImport::KIND_PRICELIST]['path'], $files[ResaleImport::KIND_PRICELIST]['name']);
                $record->forceFill($this->upsertPriceList($organization, $record, $list->entries, $list->validFrom ?? $reference))->save();
            } catch (Throwable $e) {
                $record->forceFill(['status' => ImportStatus::Failed, 'error' => $e->getMessage()])->save();
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  array{name: string, path: string, stored?: string|null}  $file
     */
    private function record(Organization $organization, ?User $user, SubscriptionProvider $provider, string $kind, array $file): ResaleImport {
        return ResaleImport::query()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user?->id,
            'provider' => $provider,
            'kind' => $kind,
            'file_name' => mb_substr((string) $file['name'], 0, 190),
            'file_path' => $file['stored'] ?? null,
            'status' => ImportStatus::Done,
        ]);
    }

    /**
     * @param  array<string, ResaleImport>  $records  Kind → Lauf
     * @return array<string, array{rows_created: int, rows_updated: int, rows_unchanged: int, rows_unassigned: int}>
     */
    private function upsertEntitlements(Organization $organization, PurchasesImport $import, CarbonImmutable $reference, array $records): array {
        $catalog = UnitPriceCatalog::fromEntitlements($import->entitlements);
        $stored = CompanyMapping::targetsFor($organization);
        $articles = $this->candidates->for((int) $organization->id);
        $successorOf = $this->successorsOf($import);
        /** @var array<string, array{provider: SubscriptionProvider, record: ResaleImport|null, entries: list<PriceListEntry>}> $priceChanges Anbieterpreis geändert → Katalogzeile */
        $priceChanges = [];

        /** @var array<string, array<string, int>> $counters */
        $counters = [];
        /** @var array<string, ResaleSubscription> $byKey */
        $byKey = [];
        foreach ($import->entitlements as $entitlement) {
            $kind = self::kindOf($entitlement);
            $counters[$kind] ??= ['rows_created' => 0, 'rows_updated' => 0, 'rows_unchanged' => 0, 'rows_unassigned' => 0];
            $provider = $this->provider($entitlement);
            if ($provider === SubscriptionProvider::DomainReselling) {
                // Domains spiegelt nur der Domain-Sync — ein Datei-Abo würde er nachts beenden.
                $this->addIssue($records[$kind] ?? null, (string) __('resale.import_issues.domain_provider', ['line' => $entitlement->sourceLine, 'company' => $entitlement->company->name, 'product' => $entitlement->edition]));

                continue;
            }
            $plan = $this->plan($entitlement, $catalog, $successorOf, $reference);
            $externalId = $plan['external_id'];
            $attributes = $plan['attributes'];
            $hash = $plan['hash'];
            $status = $plan['status'];
            $unitFee = $plan['unit_fee'];

            $subscription = ResaleSubscription::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->where('external_id', $externalId)
                ->first();

            DB::transaction(function () use (&$subscription, &$counters, &$priceChanges, $kind, $organization, $provider, $externalId, $attributes, $hash, $status, $unitFee, $entitlement, $stored, $articles, $records, $reference): void {
                $isNew = $subscription === null;
                $subscription ??= new ResaleSubscription(['organization_id' => $organization->id, 'provider' => $provider, 'external_id' => $externalId]);
                $unchanged = ! $isNew && $subscription->raw_hash === $hash && $subscription->status === $status;
                if (! $unchanged) {
                    $subscription->fill($attributes);
                    $subscription->raw_hash = $hash;
                    $subscription->status = $status;
                }
                // Einkaufspreis laut Anbieter (Feature 185, MVP-1418): der Abo-Preis folgt, solange er dem
                // letzten Anbieterpreis entspricht (nicht von Hand gepflegt); eine Preisänderung ist eine
                // Katalogzeile ab Exportdatum. Beides steht nicht im Hash.
                $providerPrice = $unitFee->withScale(4);
                $priceChanged = ! $isNew && $subscription->provider_unit_price !== null && ! $subscription->provider_unit_price->withScale(4)->equals($providerPrice);
                if ($subscription->purchase_unit_price === null || ($priceChanged && self::purchaseFollowsProvider($subscription))) {
                    $subscription->purchase_unit_price = $providerPrice;
                }
                if ($priceChanged) {
                    $unchanged = false;
                    $priceChanges[$provider->value] ??= ['provider' => $provider, 'record' => $records[$kind] ?? null, 'entries' => []];
                    $priceChanges[$provider->value]['entries'][] = new PriceListEntry(
                        product: $entitlement->edition,
                        termMonths: $entitlement->termMonths(),
                        interval: $entitlement->frequency,
                        pricePerMonth: $providerPrice->dividedBy(max(1, self::intervalMonths($entitlement->frequency))),
                        uvpPerMonth: null,
                        pricePerInterval: $providerPrice,
                        uvpPerInterval: null,
                        offerKey: '',
                        sourceLine: $entitlement->sourceLine,
                    );
                }
                $subscription->provider_unit_price = $providerPrice;
                $subscription->import_id = $records[$kind]->id ?? $subscription->import_id;
                $subscription->last_seen_at = CarbonImmutable::now();

                // Halter nur setzen, wenn noch keiner entschieden ist.
                if (! $subscription->hasHolder()) {
                    $holder = $this->holders->resolve($organization, $entitlement->company, $stored);
                    if ($holder !== null) {
                        $subscription->customer_id = $holder['customer_id'];
                        $subscription->foreign_customer_id = $holder['foreign_customer_id'];
                        $subscription->is_own_holding = $holder['is_own_holding'];
                    }
                }
                // Verkaufspreis aus der Liste, solange keiner gepflegt ist (generischer Import).
                if ($subscription->sale_unit_price === null && $entitlement->salePrice !== null) {
                    $subscription->sale_unit_price = $entitlement->salePrice->withScale(4);
                }
                // Produkt und Verkaufspreis aus dem Katalogartikel, solange nichts gepflegt ist.
                if ($subscription->article_ref === null) {
                    $article = $this->matchArticle($entitlement->edition, $articles);
                    if ($article !== null) {
                        $subscription->article_ref = $article->key;
                        if ($subscription->sale_unit_price === null) {
                            $subscription->sale_unit_price = $this->salePrice($article, $subscription->interval);
                        }
                    }
                }
                $subscription->save();
                $this->planner->sync($subscription, $reference);

                $bucket = $isNew ? 'rows_created' : ($unchanged ? 'rows_unchanged' : 'rows_updated');
                $counters[$kind][$bucket] = ($counters[$kind][$bucket] ?? 0) + 1;
                if (! $subscription->hasHolder()) {
                    $counters[$kind]['rows_unassigned'] = ($counters[$kind]['rows_unassigned'] ?? 0) + 1;
                }
            });
            $byKey[$this->externalKey($entitlement)] = $subscription;
        }

        // Preisänderungen des Anbieters als Katalogzeilen ab Exportdatum (aus dem Dateinamen), sonst Stichtag.
        foreach ($priceChanges as $change) {
            if ($change['record'] !== null) {
                $validFrom = self::exportDateOf($change['record']->file_name) ?? $reference;
                $this->upsertPriceList($organization, $change['record'], $change['entries'], $validFrom, $change['provider']);
            }
        }

        // Ablösung: Nachfolger verknüpfen, sobald beide Seiten gespeichert sind.
        foreach ($successorOf as $predecessorKey => $successorKey) {
            $predecessor = $byKey[$predecessorKey] ?? null;
            $successor = $byKey[$successorKey] ?? null;
            if ($predecessor === null || $successor === null || $predecessor->successor_id === $successor->id) {
                continue;
            }
            DB::transaction(function () use ($predecessor, $successor, $reference): void {
                $predecessor->forceFill(['successor_id' => $successor->id, 'status' => SubscriptionStatus::Superseded])->save();
                // Nachfolger erbt den Halter, wenn er selbst noch keinen hat (und umgekehrt).
                if (! $successor->hasHolder() && $predecessor->hasHolder()) {
                    $successor->forceFill(['customer_id' => $predecessor->customer_id, 'foreign_customer_id' => $predecessor->foreign_customer_id, 'is_own_holding' => $predecessor->is_own_holding])->save();
                } elseif ($successor->hasHolder() && ! $predecessor->hasHolder()) {
                    $predecessor->forceFill(['customer_id' => $successor->customer_id, 'foreign_customer_id' => $successor->foreign_customer_id, 'is_own_holding' => $successor->is_own_holding])->save();
                }
                $this->carryAssignments($predecessor, $successor, $reference);
                $this->allocations?->carry($predecessor, $successor);
            });
        }
        // Aufteilungen der Lizenzkonten auf den neuen Bestand anwenden (neue Verträge, geänderte Mengen).
        if ($this->allocations !== null && $byKey !== []) {
            foreach (app(LicenseAccounts::class)->groups($organization) as $group) {
                $this->allocations->apply($organization, $group, $reference);
            }
        }
        // Neue Verträge und Perioden sofort gegen den Spiegel abgleichen (Feature 185, MVP-1406); ein laufender Lauf blockiert den Import nicht.
        if ($this->proposer !== null && $byKey !== []) {
            try {
                $this->proposer->propose($organization, $reference);
            } catch (\RuntimeException $e) {
                Log::info('MarketplaceImporter: Vorschlagslauf übersprungen — ' . $e->getMessage(), ['organization_id' => $organization->id]);
            }
        }

        $result = [];
        foreach ($counters as $kind => $values) {
            $result[$kind] = [
                'rows_created' => $values['rows_created'] ?? 0,
                'rows_updated' => $values['rows_updated'] ?? 0,
                'rows_unchanged' => $values['rows_unchanged'] ?? 0,
                'rows_unassigned' => $values['rows_unassigned'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Lizenzabtretungen laufen beim Nachfolger weiter: jede offene Abtretung
     * des Vorgängers bekommt ein Gegenstück am Nachfolger (gleicher Halter,
     * gleiche Menge, ab dessen Beginn) und endet selbst mit dem Vorgänger.
     * Hat der Nachfolger schon eine Abtretung an diesen Halter, wächst sie um
     * die Menge — nichts geht still verloren.
     */
    private function carryAssignments(ResaleSubscription $predecessor, ResaleSubscription $successor, CarbonImmutable $reference): void {
        $predecessor->load('assignments');
        if ($predecessor->assignments->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($predecessor, $successor, $reference): void {
            $successor->load('assignments');
            // Nur die vorab vorhandenen Abtretungen zählen als „gibt es schon";
            // was dieser Lauf anlegt, wird nicht wieder zusammengefasst.
            $existing = $successor->assignments->all();
            $handover = $successor->starts_on;
            foreach ($predecessor->assignments as $assignment) {
                if ($assignment->ends_on !== null && $assignment->ends_on->lessThan($handover)) {
                    continue;
                }
                $match = null;
                foreach ($existing as $candidate) {
                    if ($candidate->customer_id === $assignment->customer_id && $candidate->foreign_customer_id === $assignment->foreign_customer_id) {
                        $match = $candidate;
                        break;
                    }
                }
                if ($match !== null) {
                    $match->forceFill(['quantity' => $match->quantity + $assignment->quantity])->save();
                    Log::info('MarketplaceImporter: Abtretung am Nachfolger um die Menge des Vorgängers erhöht.', [
                        'organization_id' => $successor->organization_id,
                        'predecessor' => $predecessor->external_id,
                        'successor' => $successor->external_id,
                        'assignment' => $match->external_id,
                        'added' => $assignment->quantity,
                        'quantity' => $match->quantity,
                    ]);
                    $this->planner->sync($match, $reference);
                } else {
                    $suffix = $successor->nextAssignmentSuffix();
                    $carried = ResaleSubscription::query()->create([
                        'organization_id' => $successor->organization_id,
                        'parent_id' => $successor->id,
                        'kind' => $successor->kind,
                        'provider' => $successor->provider,
                        'external_id' => $successor->external_id !== null ? $successor->external_id . '#' . $suffix : null,
                        'label' => $successor->label,
                        'company_name' => $assignment->company_name,
                        'customer_id' => $assignment->customer_id,
                        'foreign_customer_id' => $assignment->foreign_customer_id,
                        'is_own_holding' => false,
                        'article_ref' => $successor->article_ref,
                        'quantity' => $assignment->quantity,
                        'starts_on' => $handover->toDateString(),
                        'ends_on' => $successor->ends_on?->toDateString(),
                        'term_months' => $successor->term_months,
                        'interval' => $successor->interval,
                        'renewal' => $successor->renewal,
                        'purchase_unit_price' => $successor->purchase_unit_price?->getAmount(),
                        'sale_unit_price' => $assignment->sale_unit_price?->getAmount() ?? $successor->sale_unit_price?->getAmount(),
                        'currency' => $successor->currency,
                        'status' => $successor->status,
                        'notes' => $assignment->notes,
                    ]);
                    $successor->assignments->push($carried); // Suffix des nächsten Gegenstücks
                    $this->planner->sync($carried, $reference);
                }
                if ($assignment->ends_on === null || $assignment->ends_on->greaterThan($handover->subDay())) {
                    $assignment->forceFill(['ends_on' => $handover->subDay()->toDateString(), 'status' => SubscriptionStatus::Superseded])->save();
                    $this->planner->sync($assignment, $reference);
                }
            }
            $successor->unsetRelation('assignments');
            $this->planner->sync($successor, $reference);
        });
    }

    private function addIssue(?ResaleImport $record, string $issue): void {
        if ($record === null) {
            return;
        }
        $issues = $record->issues ?? [];
        $issues[] = $issue;
        $record->issues = $issues;
    }

    /**
     * @param  list<\App\Services\Reselling\Marketplace\PriceListEntry>  $entries
     * @param  CarbonImmutable  $listValidFrom  Gültigkeit der Liste — Zeilen mit eigenem „Gültig ab" gehen vor
     * @return array{rows_total: int, rows_created: int, rows_updated: int, rows_unchanged: int}
     */
    private function upsertPriceList(Organization $organization, ResaleImport $record, array $entries, CarbonImmutable $listValidFrom, SubscriptionProvider $provider = SubscriptionProvider::QualityHosting): array {
        $counters = ['rows_total' => count($entries), 'rows_created' => 0, 'rows_updated' => 0, 'rows_unchanged' => 0];
        foreach ($entries as $entry) {
            $validFrom = $entry->validFrom ?? $listValidFrom;
            $existing = ResalePriceEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->where('product', $entry->product)
                ->where('term_months', $entry->termMonths)
                ->where('interval', $entry->interval->value)
                ->where('valid_from', '>=', DateRange::day($validFrom))
                ->where('valid_from', '<', DateRange::dayAfter($validFrom))
                ->first();
            $values = [
                'purchase_unit_price' => $entry->pricePerInterval->withScale(4)->getAmount(),
                'list_unit_price' => $entry->uvpPerInterval?->withScale(4)->getAmount(),
                'currency' => $entry->pricePerInterval->getCurrency()->value,
                'import_id' => $record->id,
            ];
            if ($existing === null) {
                ResalePriceEntry::query()->create($values + [
                    'organization_id' => $organization->id,
                    'provider' => $provider,
                    'product' => $entry->product,
                    'term_months' => $entry->termMonths,
                    'interval' => $entry->interval,
                    'valid_from' => $validFrom->toDateString(),
                ]);
                $counters['rows_created']++;

                continue;
            }
            $existing->fill($values);
            if ($existing->isDirty(['purchase_unit_price', 'list_unit_price', 'currency'])) {
                $existing->save();
                $counters['rows_updated']++;
            } else {
                $counters['rows_unchanged']++;
            }
        }
        // Gültigkeiten je (Produkt, Laufzeit, Intervall) lückenlos aneinanderreihen:
        // die ältere Zeile endet am Vortag der neuen, die neue am Vortag einer
        // jüngeren (nachgereichte Liste). Produkte, die die Liste nicht nennt,
        // bleiben unberührt.
        foreach ($entries as $entry) {
            $validFrom = $entry->validFrom ?? $listValidFrom;
            $siblings = ResalePriceEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->where('product', $entry->product)
                ->where('term_months', $entry->termMonths)
                ->where('interval', $entry->interval->value);
            (clone $siblings)
                ->where('valid_from', '<', DateRange::day($validFrom))
                ->where(static fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($validFrom)))
                ->update(['valid_to' => $validFrom->subDay()->toDateString()]);
            $younger = (clone $siblings)->where('valid_from', '>=', DateRange::dayAfter($validFrom))->orderBy('valid_from')->first();
            (clone $siblings)
                ->where('valid_from', '>=', DateRange::day($validFrom))
                ->where('valid_from', '<', DateRange::dayAfter($validFrom))
                ->update(['valid_to' => $younger?->valid_from->subDay()->toDateString()]);
        }

        return $counters;
    }

    /**
     * Was aus einer Position wird — gemeinsam für Import und Vorschau. Einkaufspreis
     * und Status stehen nicht im Hash: der Status hängt am Stichtag.
     *
     * @param  array<string, string>  $successorOf
     * @return array{external_id: string, attributes: array<string, mixed>, hash: string, status: SubscriptionStatus, unit_fee: Money, quantity: int}
     */
    private function plan(MarketplaceEntitlement $entitlement, UnitPriceCatalog $catalog, array $successorOf, CarbonImmutable $reference): array {
        $quantity = $entitlement->quantity ?? $catalog->quantityOf($entitlement);
        $unitFee = $entitlement->unitFee ?? $catalog->unitPriceOf($entitlement);
        $attributes = [
            'kind' => SubscriptionKind::License,
            'label' => $entitlement->edition,
            'company_name' => $entitlement->company->name,
            'external_order_id' => $entitlement->orderId !== '' ? $entitlement->orderId : null,
            // Kundennummer beim Anbieter (Lizenzkonto, Feature 185); Telekom: Owner-Company-ID.
            'provider_account' => mb_substr($entitlement->company->key, 0, 64),
            'quantity' => $quantity,
            'starts_on' => $entitlement->startsOn->toDateString(),
            'ends_on' => $entitlement->endsOn?->toDateString(),
            'term_months' => $entitlement->termMonths(),
            'interval' => $entitlement->frequency,
            'renewal' => $entitlement->endsOn === null ? RenewalMode::Auto : RenewalMode::Cancel,
            'currency' => $unitFee->getCurrency()->value,
        ];

        return [
            'external_id' => $entitlement->entitlementId,
            'attributes' => $attributes,
            'hash' => (string) CryptoHelper::hash(JsonHelper::encode($attributes)),
            'status' => $this->status($entitlement, isset($successorOf[$this->externalKey($entitlement)]), $reference),
            'unit_fee' => $unitFee,
            'quantity' => (int) $quantity,
        ];
    }

    /**
     * Geänderte Felder gegenüber dem Bestand (Vorschau): Menge, Preis, Status, Beginn, Ende; `other` nur Hash-Felder.
     *
     * @param  array{external_id: string, attributes: array<string, mixed>, hash: string, status: SubscriptionStatus, unit_fee: Money, quantity: int}  $plan
     * @return list<string>
     */
    private static function changesOf(ResaleSubscription $existing, array $plan): array {
        $changes = [];
        if ((int) $existing->quantity !== $plan['quantity']) {
            $changes[] = 'quantity';
        }
        $known = $existing->provider_unit_price ?? $existing->purchase_unit_price;
        if ($known === null || ! $known->withScale(4)->equals($plan['unit_fee']->withScale(4))) {
            $changes[] = 'price';
        }
        if ($existing->status !== $plan['status']) {
            $changes[] = 'status';
        }
        if ($existing->starts_on->toDateString() !== $plan['attributes']['starts_on']) {
            $changes[] = 'starts_on';
        }
        if ($existing->ends_on?->toDateString() !== $plan['attributes']['ends_on']) {
            $changes[] = 'ends_on';
        }
        if ($changes === [] && $existing->raw_hash !== $plan['hash']) {
            $changes[] = 'other';
        }

        return $changes;
    }

    /** Folgt der Einkaufspreis dem Anbieter (nicht von Hand gepflegt)? Ohne bekannten Anbieterpreis nur, wenn keiner gesetzt ist. */
    public static function purchaseFollowsProvider(ResaleSubscription $subscription): bool {
        if ($subscription->purchase_unit_price === null) {
            return true;
        }

        return $subscription->provider_unit_price !== null && $subscription->purchase_unit_price->withScale(4)->equals($subscription->provider_unit_price->withScale(4));
    }

    /** Stand der Datei aus dem Dateinamen (`2026-10-26_Export.xlsx`, `Export 26.10.2026.csv`), sonst null. */
    public static function exportDateOf(string $fileName): ?CarbonImmutable {
        foreach (['/(\d{4}-\d{2}-\d{2})/', '/(\d{2}\.\d{2}\.\d{4})/'] as $pattern) {
            if (preg_match($pattern, $fileName, $m) !== 1) {
                continue;
            }
            $iso = DateHelper::normalizeToIso($m[1]);
            if ($iso !== null) {
                return CarbonImmutable::parse($iso)->startOfDay();
            }
        }

        return null;
    }

    private static function intervalMonths(BillingFrequency $frequency): int {
        return $frequency === BillingFrequency::Yearly ? 12 : 1;
    }

    private static function kindOf(MarketplaceEntitlement $entitlement): string {
        return match ($entitlement->source) {
            MarketplaceEntitlement::SOURCE_TELEKOM => ResaleImport::KIND_PURCHASES,
            MarketplaceEntitlement::SOURCE_QUALITYHOSTING => ResaleImport::KIND_CONTRACTS,
            default => ResaleImport::KIND_GENERIC,
        };
    }

    /** @return array<string, string> Vorgänger-Schlüssel → Nachfolger-Schlüssel */
    private function successorsOf(PurchasesImport $import): array {
        $successorOf = [];
        foreach ($import->links as $link) {
            $successorOf[$this->externalKey($link->predecessor)] = $this->externalKey($link->successor);
        }

        return $successorOf;
    }

    private function provider(MarketplaceEntitlement $entitlement): SubscriptionProvider {
        return match ($entitlement->source) {
            MarketplaceEntitlement::SOURCE_TELEKOM => SubscriptionProvider::TelekomMarketplace,
            MarketplaceEntitlement::SOURCE_QUALITYHOSTING => SubscriptionProvider::QualityHosting,
            default => SubscriptionProvider::tryFrom((string) $entitlement->provider) ?? SubscriptionProvider::Other,
        };
    }

    private function externalKey(MarketplaceEntitlement $entitlement): string {
        return $this->provider($entitlement)->value . ':' . $entitlement->entitlementId;
    }

    private function status(MarketplaceEntitlement $entitlement, bool $hasSuccessor, CarbonImmutable $reference): SubscriptionStatus {
        if ($hasSuccessor) {
            return SubscriptionStatus::Superseded;
        }
        if ($entitlement->endsOn === null) {
            return SubscriptionStatus::Active;
        }

        return $entitlement->endsOn->lessThan($reference) ? SubscriptionStatus::Ended : SubscriptionStatus::Cancelled;
    }

    /**
     * Katalogartikel zur Edition: exakter Name zuerst, sonst der einzige
     * Artikel, dessen Name die Edition trifft.
     *
     * @param  list<CatalogArticle>  $articles  {@see ResaleArticleCandidates}
     */
    private function matchArticle(string $edition, array $articles): ?CatalogArticle {
        $wanted = ProductNameMatcher::normalize($edition);
        $hits = [];
        foreach ($articles as $article) {
            if (ProductNameMatcher::normalize($article->name) === $wanted) {
                return $article;
            }
            if ($this->matcher->matches($edition, $article->name)) {
                $hits[] = $article;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /** Artikelpreis je Stück und Intervall: Einheit „Monat" × 12 bei Jahresintervall. */
    private function salePrice(CatalogArticle $article, BillingFrequency $interval): ?Money {
        $price = $article->netPrice;
        if ($price === null) {
            return null;
        }
        if (LicenseMonths::isMonthUnit($article->unitName) && $interval === BillingFrequency::Yearly) {
            return $price->times(12);
        }

        return $price;
    }
}
