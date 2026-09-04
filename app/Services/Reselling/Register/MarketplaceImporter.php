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
use App\Models\{LexofficeArticle, Organization, User};
use App\Models\Reselling\{CompanyMapping, ResaleImport, ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\{MarketplaceEntitlement, MarketplacePurchasesReader, ProductNameMatcher, PurchasesImport, PurchasesImportMerger, QualityHostingContractsReader, QualityHostingPriceListReader, UnitPriceCatalog};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bringt die Anbieter-Exporte (Feature 152, MVP-759) ins Register: je
 * Position ein Abo, Upsert über Anbieter und Kennung, Ablösung Telekom →
 * Quality Hosting als Nachfolger, Halter nur aus dem Datenbestand (sonst
 * Inbox), Verkaufspreis aus dem passenden Lexoffice-Artikel — nie über eine
 * manuelle Pflege hinweg. Die Preisliste füllt den Einkaufskatalog.
 */
final class MarketplaceImporter {
    public function __construct(
        private readonly MarketplacePurchasesReader $telekomReader,
        private readonly QualityHostingContractsReader $qualityHostingReader,
        private readonly QualityHostingPriceListReader $priceListReader,
        private readonly PurchasesImportMerger $merger,
        private readonly HolderResolver $holders,
        private readonly PeriodPlanner $planner,
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @param  array<string, array{name: string, path: string, stored?: string|null}>  $files  Schlüssel: ResaleImport::KIND_*
     * @return list<ResaleImport>
     */
    public function import(Organization $organization, ?User $user, array $files, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $records = [];
        $imports = [];

        foreach ([ResaleImport::KIND_PURCHASES => SubscriptionProvider::TelekomMarketplace, ResaleImport::KIND_CONTRACTS => SubscriptionProvider::QualityHosting] as $kind => $provider) {
            if (! isset($files[$kind])) {
                continue;
            }
            $record = $this->record($organization, $user, $provider, $kind, $files[$kind]);
            try {
                $parsed = $kind === ResaleImport::KIND_PURCHASES
                    ? $this->telekomReader->read($files[$kind]['path'])
                    : $this->qualityHostingReader->read($files[$kind]['path']);
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
                $list = $this->priceListReader->read($files[ResaleImport::KIND_PRICELIST]['path']);
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
            'file_name' => $file['name'],
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
        $articles = LexofficeArticle::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->active()->get();
        $successorOf = [];
        foreach ($import->links as $link) {
            $successorOf[$this->externalKey($link->predecessor)] = $this->externalKey($link->successor);
        }

        /** @var array<string, array<string, int>> $counters */
        $counters = [];
        /** @var array<string, ResaleSubscription> $byKey */
        $byKey = [];
        foreach ($import->entitlements as $entitlement) {
            $kind = $entitlement->source === MarketplaceEntitlement::SOURCE_TELEKOM ? ResaleImport::KIND_PURCHASES : ResaleImport::KIND_CONTRACTS;
            $counters[$kind] ??= ['rows_created' => 0, 'rows_updated' => 0, 'rows_unchanged' => 0, 'rows_unassigned' => 0];
            $provider = $this->provider($entitlement);
            $externalId = $entitlement->entitlementId;
            $quantity = $entitlement->quantity ?? $catalog->quantityOf($entitlement);
            $unitFee = $entitlement->unitFee ?? $catalog->unitPriceOf($entitlement);
            $hasSuccessor = isset($successorOf[$this->externalKey($entitlement)]);
            $status = $this->status($entitlement, $hasSuccessor, $reference);

            $attributes = [
                'kind' => SubscriptionKind::License,
                'label' => $entitlement->edition,
                'company_name' => $entitlement->company->name,
                'external_order_id' => $entitlement->orderId !== '' ? $entitlement->orderId : null,
                'quantity' => $quantity,
                'starts_on' => $entitlement->startsOn->toDateString(),
                'ends_on' => $entitlement->endsOn?->toDateString(),
                'term_months' => $entitlement->termMonths(),
                'interval' => $entitlement->frequency,
                'renewal' => $entitlement->endsOn === null ? RenewalMode::Auto : RenewalMode::Cancel,
                'purchase_unit_price' => $unitFee->withScale(4)->getAmount(),
                'currency' => $unitFee->getCurrency()->value,
                'status' => $status,
            ];
            $hash = (string) CryptoHelper::hash(json_encode($attributes, JSON_THROW_ON_ERROR));

            $subscription = ResaleSubscription::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->where('external_id', $externalId)
                ->first();

            DB::transaction(function () use (&$subscription, &$counters, $kind, $organization, $provider, $externalId, $attributes, $hash, $entitlement, $stored, $articles, $records, $reference): void {
                $isNew = $subscription === null;
                $subscription ??= new ResaleSubscription(['organization_id' => $organization->id, 'provider' => $provider, 'external_id' => $externalId]);
                $unchanged = ! $isNew && $subscription->raw_hash === $hash;
                if (! $unchanged) {
                    $subscription->fill($attributes);
                    $subscription->raw_hash = $hash;
                }
                $subscription->import_id = $records[$kind]->id ?? $subscription->import_id;
                $subscription->last_seen_at = CarbonImmutable::now();

                // Halter nur setzen, wenn noch keiner entschieden ist.
                if (! $subscription->hasHolder()) {
                    $holder = $this->holders->resolve($organization, $entitlement->company, $stored);
                    if ($holder !== null) {
                        $subscription->customer_id = $holder['customer_id'];
                        $subscription->foreign_customer_id = $holder['foreign_customer_id'];
                    }
                }
                // Produkt und Verkaufspreis aus dem Lexoffice-Artikel, solange nichts gepflegt ist.
                if ($subscription->lexoffice_article_id === null && $subscription->article_id === null) {
                    $article = $this->matchArticle($entitlement->edition, $articles);
                    if ($article !== null) {
                        $subscription->lexoffice_article_id = $article->id;
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

        // Ablösung: Nachfolger verknüpfen, sobald beide Seiten gespeichert sind.
        foreach ($successorOf as $predecessorKey => $successorKey) {
            $predecessor = $byKey[$predecessorKey] ?? null;
            $successor = $byKey[$successorKey] ?? null;
            if ($predecessor === null || $successor === null || $predecessor->successor_id === $successor->id) {
                continue;
            }
            $predecessor->forceFill(['successor_id' => $successor->id, 'status' => SubscriptionStatus::Superseded])->save();
            // Nachfolger erbt den Halter, wenn er selbst noch keinen hat (und umgekehrt).
            if (! $successor->hasHolder() && $predecessor->hasHolder()) {
                $successor->forceFill(['customer_id' => $predecessor->customer_id, 'foreign_customer_id' => $predecessor->foreign_customer_id, 'is_own_holding' => $predecessor->is_own_holding])->save();
            } elseif ($successor->hasHolder() && ! $predecessor->hasHolder()) {
                $predecessor->forceFill(['customer_id' => $successor->customer_id, 'foreign_customer_id' => $successor->foreign_customer_id, 'is_own_holding' => $successor->is_own_holding])->save();
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
     * @param  list<\App\Services\Reselling\Marketplace\PriceListEntry>  $entries
     * @return array{rows_total: int, rows_created: int, rows_updated: int, rows_unchanged: int}
     */
    private function upsertPriceList(Organization $organization, ResaleImport $record, array $entries, CarbonImmutable $validFrom): array {
        $counters = ['rows_total' => count($entries), 'rows_created' => 0, 'rows_updated' => 0, 'rows_unchanged' => 0];
        foreach ($entries as $entry) {
            $existing = ResalePriceEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('provider', SubscriptionProvider::QualityHosting->value)
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
                    'provider' => SubscriptionProvider::QualityHosting,
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
        // Ältere Gültigkeiten dieses Anbieters enden am Vortag der neuen Liste.
        ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', SubscriptionProvider::QualityHosting->value)
            ->where('valid_from', '<', $validFrom->toDateString())
            ->whereNull('valid_to')
            ->update(['valid_to' => $validFrom->subDay()->toDateString()]);

        return $counters;
    }

    private function provider(MarketplaceEntitlement $entitlement): SubscriptionProvider {
        return $entitlement->source === MarketplaceEntitlement::SOURCE_TELEKOM ? SubscriptionProvider::TelekomMarketplace : SubscriptionProvider::QualityHosting;
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
     * Lexoffice-Artikel zur Edition: exakter Name zuerst, sonst der einzige
     * Artikel, dessen Name die Edition trifft.
     *
     * @param  \Illuminate\Support\Collection<int, LexofficeArticle>  $articles
     */
    private function matchArticle(string $edition, $articles): ?LexofficeArticle {
        $wanted = ProductNameMatcher::normalize($edition);
        $hits = [];
        foreach ($articles as $article) {
            if (ProductNameMatcher::normalize((string) $article->name) === $wanted) {
                return $article;
            }
            if ($this->matcher->matches($edition, (string) $article->name)) {
                $hits[] = $article;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /** Artikelpreis je Stück und Intervall: Einheit „Monat" × 12 bei Jahresintervall. */
    private function salePrice(LexofficeArticle $article, BillingFrequency $interval): ?Money {
        $price = $article->net_unit_price;
        if ($price === null) {
            return null;
        }
        $monthly = mb_strtolower(trim((string) $article->unit_name)) === 'monat';
        if ($monthly && $interval === BillingFrequency::Yearly) {
            return $price->times(12);
        }

        return $price;
    }
}
