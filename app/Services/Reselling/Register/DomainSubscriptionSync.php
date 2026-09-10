<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSubscriptionSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Domain\DomainRenewalMode;
use App\Enums\Reselling\{BillingFrequency, RenewalMode, ResaleArticleRole, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Domain\DomainProjection;
use App\Models\{LexofficeArticle, Organization};
use App\Models\Reselling\{ResalePeriod, ResalePriceEntry, ResaleSubscription};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Collection;

/**
 * Domains als Abo-Art (Feature 152, MVP-763): jede Domain-Projektion (083)
 * mit Halter wird ein Abo „Domain" — Anbieter DomainReselling, Kennung =
 * Domainname, Jahresintervall ab Registrierung, Einkauf = Verlängerungspreis
 * der Projektion. Die Projektion bleibt die technische Wahrheit; das Abo
 * spiegelt nur Halter, Laufzeit und Preis. Verkaufspreis je TLD aus dem
 * Katalog (`provider = domainreselling`, Produkt `.de`), Artikelbezug aus dem
 * Lexoffice-Artikel „Domain .de"/„.de-Domain". Manuelle Verkaufspreise,
 * Artikel und Halterentscheidungen im Register überlebt jeder Lauf.
 */
final class DomainSubscriptionSync {
    /** Präfix in `resale_subscriptions.sync_status`: zuletzt aus der Projektion gespiegelter Halter. */
    public const MIRRORED_HOLDER_PREFIX = 'p:';

    public function __construct(private readonly PeriodPlanner $planner) {}

    /**
     * @return array{domains: int, created: int, updated: int, unchanged: int, ended: int, skipped_gone: bool}
     */
    public function sync(Organization|int $organization, ?CarbonImmutable $reference = null): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $reference ??= ResalePeriod::today();
        $result = ['domains' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'ended' => 0, 'skipped_gone' => false];
        $seen = [];
        $catalog = $this->catalog($organizationId, $reference);
        $articles = LexofficeArticle::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->active()->get();

        $projections = DomainProjection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('external_domain')
            ->get();
        foreach ($projections as $projection) {
            $result['domains']++;
            $externalId = mb_strtolower($projection->external_domain);
            $seen[] = $externalId;
            $startsOn = $projection->registration_at !== null ? CarbonImmutable::instance($projection->registration_at)->startOfDay() : null;
            if ($startsOn === null) {
                continue; // ohne Registrierungsdatum keine Perioden
            }
            $status = $this->status($projection, $reference);
            $expiresOn = $projection->expiration_at !== null ? CarbonImmutable::instance($projection->expiration_at)->startOfDay()->toDateString() : null;
            $attributes = [
                'kind' => SubscriptionKind::Domain,
                'label' => $projection->external_domain,
                'company_name' => null,
                'quantity' => 1,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $status === SubscriptionStatus::Active ? null : $expiresOn,
                'term_months' => 12,
                'interval' => BillingFrequency::Yearly,
                'renewal' => $projection->renewal_mode === DomainRenewalMode::Autorenew || $projection->renewal_mode === null ? RenewalMode::Auto : RenewalMode::Cancel,
                'purchase_unit_price' => $projection->renewal_price !== null ? (string) $projection->renewal_price : null,
                'currency' => $projection->renewal_currency !== null ? $projection->renewal_currency->value : 'EUR',
                'status' => $status,
                'domain_projection_id' => $projection->id,
            ];
            $hash = (string) CryptoHelper::hash(json_encode($attributes, JSON_THROW_ON_ERROR));

            $subscription = ResaleSubscription::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('provider', SubscriptionProvider::DomainReselling->value)
                ->where('external_id', $externalId)
                ->first();
            $isNew = $subscription === null;
            $subscription ??= new ResaleSubscription(['organization_id' => $organizationId, 'provider' => SubscriptionProvider::DomainReselling, 'external_id' => $externalId]);
            $unchanged = ! $isNew && $subscription->raw_hash === $hash;
            if (! $unchanged) {
                if ($status !== SubscriptionStatus::Active && $expiresOn === null) {
                    // Ende ohne Ablaufdatum: einmal am Stichtag, danach fest — sonst wandert es täglich (und der Hash mit).
                    $attributes['ends_on'] = $subscription->ends_on?->toDateString() ?? $reference->toDateString();
                }
                $subscription->fill($attributes);
                $subscription->raw_hash = $hash;
            }
            // Halter aus der Projektion, solange das Register keinen entschieden hat. `sync_status`
            // merkt sich den zuletzt gespiegelten Projektions-Halter: stimmt er mit dem aktuellen
            // Halter überein, stammt dieser aus der Projektion und folgt ihrem Wechsel; weicht er
            // ab, hat das Register entschieden und der Sync fasst ihn nicht an.
            $projectionHolder = self::holderKey($projection->customer_id, $projection->foreign_customer_id, (bool) $projection->is_own_holding);
            $currentHolder = self::holderKey($subscription->customer_id, $subscription->foreign_customer_id, (bool) $subscription->is_own_holding);
            $mirrored = $subscription->sync_status === self::MIRRORED_HOLDER_PREFIX . $currentHolder
                || ($subscription->sync_status === null && $currentHolder === $projectionHolder);
            if (! $subscription->hasHolder() || $mirrored) {
                $subscription->customer_id = $projection->customer_id;
                $subscription->foreign_customer_id = $projection->foreign_customer_id;
                $subscription->is_own_holding = (bool) $projection->is_own_holding;
                $subscription->sync_status = self::MIRRORED_HOLDER_PREFIX . $projectionHolder;
            }
            $this->applyPricing($subscription, $externalId, $catalog, $articles);
            $subscription->last_seen_at = CarbonImmutable::now();
            $subscription->save();
            $this->planner->sync($subscription, $reference);
            $result[$isNew ? 'created' : ($unchanged ? 'unchanged' : 'updated')]++;
        }

        // Domains, die aus der Projektion verschwunden sind, enden am Stichtag.
        // Ohne eine einzige Projektion (Plugin aus, Sync-Fehler, Tabelle neu) ist
        // „verschwunden" nicht belegt — dann nichts beenden.
        if ($seen === []) {
            $result['skipped_gone'] = true;

            return $result;
        }
        $gone = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value])
            ->whereNotIn('external_id', $seen)
            ->get();
        foreach ($gone as $subscription) {
            $subscription->forceFill(['status' => SubscriptionStatus::Ended, 'ends_on' => $subscription->ends_on ?? $reference->toDateString()])->save();
            $this->planner->sync($subscription, $reference);
            $result['ended']++;
        }

        return $result;
    }

    private function status(DomainProjection $projection, CarbonImmutable $reference): SubscriptionStatus {
        $status = mb_strtoupper((string) $projection->status);
        if (in_array($status, ['DELETED', 'EXPIRED', 'TRANSFERRED_OUT', 'FAILED'], true)) {
            return SubscriptionStatus::Ended;
        }
        if ($projection->renewal_mode === DomainRenewalMode::Autoexpire || $projection->renewal_mode === DomainRenewalMode::Autodelete) {
            return $projection->expiration_at !== null && CarbonImmutable::instance($projection->expiration_at)->lessThan($reference) ? SubscriptionStatus::Ended : SubscriptionStatus::Cancelled;
        }

        return SubscriptionStatus::Active;
    }

    /**
     * Verkaufspreis und Artikel je TLD — nur, solange im Register nichts
     * gepflegt ist. Katalogpreis = UVP der Zeile (sonst ihr Katalogpreis),
     * danach der Preis des Lexoffice-Artikels (Monatspreis × 12).
     *
     * @param  array<string, ResalePriceEntry>  $catalog  TLD → gültige Katalogzeile
     * @param  Collection<int, LexofficeArticle>  $articles
     */
    private function applyPricing(ResaleSubscription $subscription, string $domain, array $catalog, Collection $articles): void {
        $tlds = self::tldCandidates($domain);
        if ($tlds === []) {
            return;
        }
        if ($subscription->lexoffice_article_id === null && $subscription->article_id === null) {
            $article = $this->matchArticle($tlds, $articles);
            if ($article !== null) {
                $subscription->lexoffice_article_id = $article->id;
            }
        }
        if ($subscription->sale_unit_price !== null) {
            return;
        }
        foreach ($tlds as $tld) {
            $entry = $catalog[$tld] ?? null;
            if ($entry !== null) {
                $subscription->sale_unit_price = ($entry->list_unit_price ?? $entry->purchase_unit_price)->withScale(4);

                return;
            }
        }
        $article = $subscription->lexoffice_article_id !== null ? $articles->firstWhere('id', $subscription->lexoffice_article_id) : null;
        $price = $article?->net_unit_price;
        if ($article !== null && $price !== null) {
            $subscription->sale_unit_price = LicenseMonths::isMonthUnit($article->unit_name) ? $price->times(12)->withScale(4) : $price->withScale(4);
        }
    }

    /**
     * Katalogzeilen des Domain-Anbieters am Stichtag, Produkt = TLD (`.de`, `.co.uk`).
     *
     * @return array<string, ResalePriceEntry>
     */
    private function catalog(int $organizationId, CarbonImmutable $reference): array {
        $catalog = [];
        $entries = ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->validOn($reference)
            ->orderByDesc('valid_from')
            ->get();
        foreach ($entries as $entry) {
            $catalog[self::normalizeTld($entry->product)] ??= $entry;
        }

        return $catalog;
    }

    /**
     * Genau ein Lexoffice-Artikel „Domain .de"/„.de-Domain" (auch ohne Punkt);
     * mehrdeutig = keiner.
     *
     * @param  list<string>  $tlds
     * @param  Collection<int, LexofficeArticle>  $articles
     */
    private function matchArticle(array $tlds, Collection $articles): ?LexofficeArticle {
        $wanted = [];
        foreach ($tlds as $tld) {
            $bare = ltrim($tld, '.');
            $wanted[] = 'domain ' . $tld;
            $wanted[] = 'domain ' . $bare;
            $wanted[] = $tld . '-domain';
            $wanted[] = $bare . '-domain';
            $wanted[] = $tld . ' domain';
            $wanted[] = $bare . ' domain';
        }
        $hits = [];
        foreach ($articles as $article) {
            if ($article->resale_role === ResaleArticleRole::Excluded) {
                continue;
            }
            $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $article->name) ?? ''));
            if (in_array($name, $wanted, true)) {
                $hits[] = $article;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * TLD-Kandidaten vom längsten Suffix zum kürzesten: `shop.example.co.uk` →
     * `.example.co.uk`, `.co.uk`, `.uk` — der Katalog kann `.co.uk` oder `.uk` führen.
     *
     * @return list<string>
     */
    public static function tldCandidates(string $domain): array {
        $labels = explode('.', mb_strtolower(trim($domain)));
        $candidates = [];
        for ($i = 1, $n = count($labels); $i < $n; $i++) {
            $candidates[] = '.' . implode('.', array_slice($labels, $i));
        }

        return $candidates;
    }

    private static function normalizeTld(string $product): string {
        return '.' . ltrim(mb_strtolower(trim($product)), '.');
    }

    /** Kurzschlüssel eines Halters (`c<id>`, `f<id>`, `own`, `none`) — passt in `sync_status` (16 Zeichen). */
    private static function holderKey(?int $customerId, ?int $foreignCustomerId, bool $own): string {
        if ($foreignCustomerId !== null) {
            return 'f' . $foreignCustomerId;
        }
        if ($customerId !== null) {
            return 'c' . $customerId;
        }

        return $own ? 'own' : 'none';
    }
}
