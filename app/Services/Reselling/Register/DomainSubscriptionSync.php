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
use App\Enums\Reselling\{BillingFrequency, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Domain\DomainProjection;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePriceEntry, ResaleSubscription};
use App\Services\Platform\Catalog\CatalogArticle;
use App\Settings\SettingsRegistry;
use App\Support\DomainNames;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper, StringHelper};

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

    /**
     * Preisstaffel (Benutzerklasse des Domainportals) für Domains im eigenen Konto, die der Inhaber selbst
     * berechnet (Entscheidung 2026-10-30: eigene Klasse im Portal, hier nur benannt); Subuser-Domains nehmen die
     * Klasse ihres Subusers.
     */
    public const SETTING_DIRECT_PRICE_CLASS = 'resale.domain_direct_price_class';

    public static function directPriceClass(Organization $organization): ?string {
        $value = app(SettingsRegistry::class)->effective(self::SETTING_DIRECT_PRICE_CLASS, $organization)->value;
        $value = is_string($value) ? strtoupper(trim($value)) : '';

        return $value === '' ? null : $value;
    }

    /**
     * Preisstaffeln, die der Katalog für den Domainanbieter führt (Auswahlhilfe).
     *
     * @return list<string>
     */
    public static function knownPriceClasses(Organization $organization): array {
        $classes = ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->where('price_class', '!=', '')
            ->whereNull('valid_to')
            ->distinct()
            ->orderBy('price_class')
            ->pluck('price_class')
            ->all();

        return array_values(array_map(static fn ($class): string => (string) $class, $classes));
    }

    /**
     * Spielraum zwischen Registrierung und erstem Jahrestag des Laufzeitendes: DENIC-Drift
     * von ein bis drei Tagen (muster-gmbh.de 25.01./23.01.) und Registry-Zyklen, die bei einem
     * Transfer kurz vor dem Transfertag liegen.
     */
    public const START_TOLERANCE_DAYS = 45;

    /**
     * Abstand, ab dem der gemeldete Ablauf kein Laufzeitende ist: der Live-Gateway meldet für
     * .de-Domains im ersten Jahr Registrierung + 1 Monat als Ablauf, finalisiert aber am
     * Registrierungs-Jahrestag (Prod 2026-10-29: 7 von 7, Abstand 304–335 Tage; die
     * Gnadenfristen der gTLD-Registries liegen bei 41–44 Tagen).
     */
    public const STALE_EXPIRATION_GAP_DAYS = 90;

    /**
     * Laufzeitende der Domain: der Ablauf — oder die Finalisierung, wenn sie erst lange nach
     * dem Ablauf folgt (dann ist der Ablauf kein Laufzeitende, siehe {@see STALE_EXPIRATION_GAP_DAYS}).
     */
    public static function termEndOf(DomainProjection $projection): ?CarbonImmutable {
        if ($projection->expiration_at === null) {
            return null;
        }
        $expiration = CarbonImmutable::instance($projection->expiration_at)->startOfDay();
        if ($projection->finalization_at === null) {
            return $expiration;
        }
        $finalization = CarbonImmutable::instance($projection->finalization_at)->startOfDay();

        return $finalization->greaterThan($expiration->addDays(self::STALE_EXPIRATION_GAP_DAYS)) ? $finalization : $expiration;
    }

    /**
     * Beginn des Domain-Abos (Feature 185, MVP-1407): das Lizenzjahr läuft von Jahrestag
     * zu Jahrestag des Laufzeitendes ({@see termEndOf()}) — dort bucht das Portal die
     * Verlängerung —, der Beginn ist der erste Jahrestag ab der Registrierung. Bei einem
     * Transfer ist die Registrierung der Transfertag und die Jahre davor gehören dem alten
     * Anbieter (Befund 2026-10-29: zahlenwerk.eu seit 28.02.2018 im Portal, verlängert jeden
     * Oktober; optikwerk.one Transfer im April, Zyklus 25.11.). Ohne Laufzeitende gilt
     * die Registrierung; fehlt auch sie (der Listen-Sync liefert sie nicht, nur der
     * Detailabgleich), bleibt ein gespeicherter Beginn stehen, sonst wird aus dem
     * Laufzeitende rückgerechnet — ein Jahr vor dem laufenden Lizenzjahr.
     */
    public static function startOf(DomainProjection $projection, ?ResaleSubscription $existing, CarbonImmutable $reference): ?CarbonImmutable {
        $end = self::termEndOf($projection);
        if ($projection->registration_at !== null) {
            $registration = CarbonImmutable::instance($projection->registration_at)->startOfDay();
            if ($end === null) {
                return $registration;
            }
            $floor = $registration->subDays(self::START_TOLERANCE_DAYS);
            $start = $end;
            if ($start->lessThan($floor)) {
                return $registration; // Laufzeitende vor der Registrierung: unplausibel, Registrierung zählt
            }
            while ($start->subYearNoOverflow()->greaterThanOrEqualTo($floor)) {
                $start = $start->subYearNoOverflow();
            }

            return $start;
        }
        if ($existing !== null) {
            return $existing->starts_on;
        }
        if ($end === null) {
            return null;
        }
        $start = $end;
        while ($start->greaterThan($reference)) {
            $start = $start->subYearNoOverflow();
        }

        return $start->subYearNoOverflow();
    }

    public function __construct(
        private readonly PeriodPlanner $planner,
        private readonly ResaleArticleCandidates $candidates,
    ) {}

    /**
     * @return array{domains: int, created: int, updated: int, unchanged: int, ended: int, skipped_gone: bool}
     */
    public function sync(Organization|int $organization, ?CarbonImmutable $reference = null): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $reference ??= ResalePeriod::today();
        $result = ['domains' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'ended' => 0, 'skipped_gone' => false];
        $seen = [];
        $organizationModel = $organization instanceof Organization ? $organization : Organization::query()->withoutGlobalScopes()->find($organizationId);
        $directClass = $organizationModel !== null ? self::directPriceClass($organizationModel) : null;
        $articles = $this->candidates->for((int) $organizationId);

        $projections = DomainProjection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with('resellerAccount:id,user_class,depth')
            ->orderBy('external_domain')
            ->get();
        $classes = ['', $directClass];
        foreach ($projections as $projection) {
            $classes[] = self::priceClassFor($projection, $directClass);
        }
        $catalog = $this->catalog($organizationId, $reference, array_values(array_unique(array_filter($classes, static fn (?string $c): bool => $c !== null))));
        foreach ($projections as $projection) {
            $result['domains']++;
            $externalId = mb_strtolower($projection->external_domain);
            $seen[] = $externalId;
            $existing = ResaleSubscription::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('provider', SubscriptionProvider::DomainReselling->value)
                ->where('external_id', $externalId)
                ->first();
            $startsOn = self::startOf($projection, $existing, $reference);
            if ($startsOn === null) {
                continue; // weder Registrierungs- noch Ablaufdatum: keine Perioden
            }
            $status = $this->status($projection, $reference);
            $expiresOn = $projection->expiration_at !== null ? CarbonImmutable::instance($projection->expiration_at)->startOfDay()->toDateString() : null;
            $attributes = [
                'kind' => SubscriptionKind::Domain,
                // Lesbare Form als Bezeichnung (Pilot 2026-10-31): „onlinehändler-…“ statt „xn--…“ auf Seiten und Rechnungen; die Kennung bleibt Punycode.
                'label' => DomainNames::display($projection->external_domain),
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
            $hash = (string) CryptoHelper::hash(JsonHelper::encode($attributes));

            $subscription = $existing;
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
            $this->applyPricing($subscription, $externalId, $catalog, $articles, self::priceClassFor($projection, $directClass));
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
     * Verkaufspreis und Artikel je TLD. Der Preis folgt dem Katalog (Entscheidung 2026-10-30):
     * zuerst die Zeile der Preisstaffel des Abos (Klasse des Subusers bzw. die für direkte
     * Kunden benannte), sonst eine Zeile ohne Staffel (Importliste), dort UVP vor Katalogpreis;
     * ohne Katalogzeile bleibt ein gepflegter Preis stehen, sonst gilt der Katalogartikel
     * (Monatspreis × 12). Fehlt dem Abo der Einkauf, kommt er aus der Katalogzeile.
     *
     * @param  array<string, array<string, ResalePriceEntry>>  $catalog  Staffel → TLD-Schlüssel → gültige Katalogzeile
     * @param  list<CatalogArticle>  $articles  {@see ResaleArticleCandidates}
     */
    private function applyPricing(ResaleSubscription $subscription, string $domain, array $catalog, array $articles, ?string $priceClass): void {
        $tlds = self::tldCandidates($domain);
        if ($tlds === []) {
            return;
        }
        if ($subscription->article_ref === null) {
            $article = $this->matchArticle($tlds, $articles);
            if ($article !== null) {
                $subscription->article_ref = $article->key;
            }
        }
        $entry = self::catalogEntry($catalog, $priceClass, $tlds) ?? self::catalogEntry($catalog, '', $tlds);
        if ($entry !== null) {
            $subscription->sale_unit_price = ($entry->list_unit_price ?? $entry->purchase_unit_price)->withScale(4);
            if ($subscription->purchase_unit_price === null) {
                $subscription->purchase_unit_price = $entry->purchase_unit_price->withScale(4);
            }

            return;
        }
        if ($subscription->sale_unit_price !== null) {
            return;
        }
        $article = $subscription->catalogArticle();
        $price = $article?->netPrice;
        if ($article !== null && $price !== null) {
            $subscription->sale_unit_price = LicenseMonths::isMonthUnit($article->unitName) ? $price->times(12)->withScale(4) : $price->withScale(4);
        }
    }

    /**
     * Preisstaffel eines Abos: Klasse des Subusers, im eigenen Konto die für direkte Kunden benannte.
     */
    public static function priceClassFor(DomainProjection $projection, ?string $directClass): ?string {
        $reseller = $projection->resellerAccount;
        if ($reseller !== null && (int) $reseller->depth > 0) {
            $class = strtoupper(trim((string) $reseller->user_class));

            return $class === '' ? $directClass : $class;
        }

        return $directClass;
    }

    /**
     * Katalogzeile der Staffel zum längsten passenden TLD-Suffix (`.co.uk` vor `.uk`).
     *
     * @param  array<string, array<string, ResalePriceEntry>>  $catalog
     * @param  list<string>  $tlds
     */
    private static function catalogEntry(array $catalog, ?string $priceClass, array $tlds): ?ResalePriceEntry {
        $byTld = $catalog[$priceClass ?? ''] ?? [];
        foreach ($tlds as $tld) {
            $entry = $byTld[ResalePriceEntry::domainProductKey($tld)] ?? null;
            if ($entry !== null) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Katalogzeilen des Domain-Anbieters am Stichtag je Staffel, Produkt = TLD (`.de`, `.co.uk`, `.couk`).
     *
     * @param  list<string>  $classes  Staffeln ('' = ohne Staffel)
     * @return array<string, array<string, ResalePriceEntry>>
     */
    private function catalog(int $organizationId, CarbonImmutable $reference, array $classes): array {
        $catalog = [];
        $entries = ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->whereIn('price_class', $classes)
            ->validOn($reference)
            ->orderByDesc('valid_from')
            ->get();
        foreach ($entries as $entry) {
            $catalog[(string) $entry->price_class][ResalePriceEntry::domainProductKey((string) $entry->product)] ??= $entry;
        }

        return $catalog;
    }

    /**
     * Genau ein Katalogartikel „Domain .de"/„.de-Domain" (auch ohne Punkt);
     * mehrdeutig = keiner.
     *
     * @param  list<string>  $tlds
     * @param  list<CatalogArticle>  $articles
     */
    private function matchArticle(array $tlds, array $articles): ?CatalogArticle {
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
            $name = mb_strtolower(StringHelper::normalizeWhitespace($article->name, unicode: true));
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

    /** Kurzschlüssel eines Halters (`c<id>`, `f<id>`, `own`, `none`) — passt in `sync_status` (16 Zeichen). */
    public static function holderKey(?int $customerId, ?int $foreignCustomerId, bool $own): string {
        if ($foreignCustomerId !== null) {
            return 'f' . $foreignCustomerId;
        }
        if ($customerId !== null) {
            return 'c' . $customerId;
        }

        return $own ? 'own' : 'none';
    }
}
