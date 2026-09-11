<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseAllocator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Domain\DomainAccountingEntry;
use App\Models\{Organization, User};
use App\Models\Reselling\{ResalePeriod, ResalePurchaseEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProviderInvoice};
use App\Services\Reselling\Purchase\PurchaseDocument;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Einkaufsbelege auf Perioden verteilen (Feature 152, MVP-762).
 *
 * Anbieterrechnungen (z. B. Telekom) sind Monatsrechnungen über alle Abos —
 * und oft gemischt mit anderen Leistungen. Der Betreiber nennt den Anteil des
 * Anbieters (Vorgabe: Belegsumme); der Betrag wird pro rata auf die Perioden
 * verteilt, die den Rechnungsmonat berühren, gewichtet mit ihrem monatlichen
 * Soll-Einkauf. Domain-Buchungen (083) treffen ihre Domain direkt. Der
 * Eingangsbeleg ist anbieterneutral ({@see PurchaseDocument}, Review
 * 2026-09-11): Lexoffice-Spiegel, lokale Ausgabe oder Eingangs-E-Rechnung.
 */
final class PurchaseAllocator {
    /** Quelle, deren Hash-Basis aus der Zeit vor der Belegregistry nur die Beleg-ID trägt — bestehende Lexoffice-Zuteilungen bleiben Dubletten. */
    private const LEGACY_HASH_SOURCE = 'lexoffice';

    /** @var array<int, Collection<int, ResaleSubscription>> Organisation → alle Abos (Gutschrift-Fallback), je Lauf einmal geladen */
    private array $subscriptions = [];

    /** Lauf-Cache leeren (vor einem neuen Lauf oder nach Änderungen am Register). */
    public function reset(): void {
        $this->subscriptions = [];
    }

    /**
     * Eingangsbeleg pro rata auf die Perioden des Monats verteilen. Zeilen
     * tragen den Belegbezug als Morph; Lexoffice-Belege behalten ihre
     * bisherige Hash-Basis (nur die Beleg-ID), damit bestehende Zuteilungen
     * dieselbe Zuteilung bleiben — andere Quellen hashen `quelle|id`.
     *
     * @return array{entries: int, allocated: float, unallocated: float}
     */
    public function allocateVoucher(Organization $organization, PurchaseDocument $document, SubscriptionProvider $provider, Money $net, CarbonImmutable $month, ?User $user = null, string $source = ResalePurchaseEntry::SOURCE_VOUCHER): array {
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereHas('subscription', static fn($s) => $s->where('provider', $provider->value)) // eigener Bestand kostet auch Einkauf
            ->where('starts_on', '<', DateRange::dayAfter($monthEnd))
            ->where('ends_on', '>=', DateRange::day($monthStart))
            ->with('subscription')
            ->get();

        $weights = [];
        $total = 0.0;
        foreach ($periods as $period) {
            $monthly = ($period->expected_purchase?->toFloat() ?? 0.0) / max(1, $period->termMonths());
            if ($monthly <= 0.0) {
                continue;
            }
            $weights[$period->id] = $monthly;
            $total += $monthly;
        }
        $result = ['entries' => 0, 'allocated' => 0.0, 'unallocated' => $net->toFloat()];
        $documentKey = $document->sourceKey === self::LEGACY_HASH_SOURCE ? (string) $document->morphId : $document->sourceKey . '|' . $document->morphId;
        $baseHash = $documentKey . '|' . $provider->value . '|' . $month->format('Y-m');

        DB::transaction(function () use ($organization, $document, $provider, $net, $month, $user, $source, $periods, $weights, $total, $baseHash, &$result): void {
            // Alte pro-rata-Zuteilung dieses Belegs ersetzen (ein Beleg = eine Zuteilung).
            ResalePurchaseEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('source', $source)
                ->where('document_type', $document->morphClass)
                ->where('document_id', $document->morphId)
                ->delete();
            $remaining = round($net->toFloat(), 2);
            $count = count($weights);
            $index = 0;
            foreach ($periods as $period) {
                if (! isset($weights[$period->id])) {
                    continue;
                }
                $index++;
                // Letzte Periode bekommt den Rest, damit die Summe exakt stimmt.
                $share = $index === $count ? $remaining : round($net->toFloat() * $weights[$period->id] / $total, 2);
                $remaining = round($remaining - $share, 2);
                ResalePurchaseEntry::query()->create([
                    'organization_id' => $organization->id,
                    'subscription_id' => $period->subscription_id,
                    'period_id' => $period->id,
                    'provider' => $provider,
                    'source' => $source,
                    'document_type' => $document->morphClass,
                    'document_id' => $document->morphId,
                    'document_number' => $document->number !== null ? mb_substr($document->number, 0, 64) : null,
                    'entry_date' => $document->date?->toDateString() ?? $month->toDateString(),
                    'description' => (string) __('resale.purchase.pro_rata', ['month' => $month->format('m/Y')]),
                    'net_amount' => $share,
                    'currency' => $net->getCurrency()->value,
                    'raw_hash' => (string) CryptoHelper::hash($baseHash . '|' . $period->id),
                    'created_by_user_id' => $user?->id,
                ]);
                $result['entries']++;
                $result['allocated'] += $share;
            }
            $result['unallocated'] = round($net->toFloat() - $result['allocated'], 2);
        });

        return $result;
    }

    /**
     * Anbieterrechnung positionsgenau (Feature 152, MVP-762): jede Position
     * trägt den Vertrag (= Abo-Kennung bei Quality Hosting) und die Laufzeit —
     * der Betrag geht exakt an die Periode, deren Beginn die Laufzeit nennt.
     * Gutschrift-Positionen ohne Vertrag (Umzugsbonus je Endkunde) gelten der
     * Firma: erstes Abo dieser Firma beim Anbieter, Periode am Belegdatum.
     * Weicht die Positionssumme von der Belegsumme ab (Seite nicht gelesen),
     * wird importiert und in `issues` gewarnt — nie still.
     *
     * @return array{lines: int, matched: int, unmatched: list<string>, duplicates: int, net: float, issues: list<string>}
     */
    public function importProviderInvoice(Organization $organization, ProviderInvoice $invoice, SubscriptionProvider $provider, ?User $user = null, ?string $fileName = null, CurrencyCode $currency = CurrencyCode::Euro): array {
        $result = ['lines' => count($invoice->lines), 'matched' => 0, 'unmatched' => [], 'duplicates' => 0, 'net' => 0.0, 'issues' => array_map('strval', $invoice->issues)];
        $consistency = $invoice->consistencyIssue();
        if ($consistency !== null) {
            $result['issues'][] = $consistency;
        }
        $all = $this->subscriptions($organization);
        $subscriptions = $all->filter(static fn(ResaleSubscription $s): bool => $s->provider === $provider)->values();
        $byContract = $subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->external_id !== null)->keyBy(static fn(ResaleSubscription $s): string => mb_strtoupper((string) $s->external_id));
        $entryDate = $invoice->date ?? ResalePeriod::today();

        DB::transaction(function () use ($organization, $invoice, $provider, $user, $fileName, $currency, $all, $subscriptions, $byContract, $entryDate, &$result): void {
            foreach ($invoice->lines as $line) {
                $subscription = null;
                if ($line->contract !== null) {
                    $subscription = $byContract->get(mb_strtoupper($line->contract));
                }
                if ($subscription === null && $line->companyName !== null) {
                    $wanted = MarketplaceCompany::normalizeName($line->companyName);
                    $companyName = $line->companyName;
                    $exact = $subscriptions->filter(static fn(ResaleSubscription $s): bool => MarketplaceCompany::normalizeName((string) $s->company_name) === $wanted);
                    if ($exact->isEmpty()) {
                        // Abgeschnittene oder abweichende Firmennamen (Gutschriften): Kern-Tokens, aber nur bei genau einer Firma.
                        $fuzzy = $subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->company_name !== null && NameTokenMatcher::matches((string) $s->company_name, $companyName));
                        $exact = $fuzzy->pluck('company_name')->map(static fn($n): string => MarketplaceCompany::normalizeName((string) $n))->unique()->count() === 1 ? $fuzzy : collect();
                    }
                    $subscription = $exact->sortBy('starts_on')->first();
                    // Gutschrift für eine Firma, die beim Anbieter kein Abo (mehr) hat: jüngstes Abo der Firma egal welchen Anbieters.
                    if ($subscription === null) {
                        $subscription = $all
                            ->filter(static fn(ResaleSubscription $s): bool => $s->company_name !== null && MarketplaceCompany::normalizeName((string) $s->company_name) === $wanted)
                            ->sortByDesc('starts_on')
                            ->first();
                    }
                }
                if ($subscription === null) {
                    $result['unmatched'][] = sprintf('#%d %s (%s)', $line->position, $line->description, $line->contract ?? $line->companyName ?? '?');

                    continue;
                }
                $lineKey = '|' . ($line->contract ?? $line->companyKey ?? '');
                $hash = (string) CryptoHelper::hash('provider|' . $provider->value . '|' . $invoice->number . '|' . $line->position . $lineKey);
                // Vor dem Review 2026-09-10 trugen Gutschrift-Positionen alle die Nummer 0;
                // der alte Hash gilt weiter als Dublette, sonst käme die Zeile beim Re-Import doppelt.
                $legacyHash = (string) CryptoHelper::hash('provider|' . $provider->value . '|' . $invoice->number . '|0' . $lineKey);
                $duplicate = ResalePurchaseEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)
                    ->where(static fn($q) => $q->where('raw_hash', $hash)
                        ->orWhere(static fn($legacy) => $legacy->where('raw_hash', $legacyHash)->where('net_amount', number_format($line->total, 2, '.', ''))))
                    ->exists();
                if ($duplicate) {
                    $result['duplicates']++;

                    continue;
                }
                $anchor = $line->periodStart ?? $entryDate;
                $period = ResalePeriod::query()->withoutGlobalScopes()
                    ->where('subscription_id', $subscription->id)
                    ->where('starts_on', '<', DateRange::dayAfter($anchor))
                    ->where('ends_on', '>=', DateRange::day($anchor))
                    ->first()
                    ?? ResalePeriod::query()->withoutGlobalScopes()->where('subscription_id', $subscription->id)->orderByDesc('starts_on')->first();
                ResalePurchaseEntry::query()->create([
                    'organization_id' => $organization->id,
                    'subscription_id' => $subscription->id,
                    'period_id' => $period?->id,
                    'provider' => $provider,
                    'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE,
                    'document_number' => $invoice->number,
                    'entry_date' => $entryDate->toDateString(),
                    'description' => mb_substr(trim($line->description . ($line->periodStart !== null ? ' · ' . $line->periodStart->format('d.m.Y') . ' – ' . ($line->periodEnd?->format('d.m.Y') ?? '') : '') . ($fileName !== null ? ' · ' . $fileName : '')), 0, 255),
                    'net_amount' => $line->total,
                    'currency' => $currency->value,
                    'raw_hash' => $hash,
                    'created_by_user_id' => $user?->id,
                ]);
                $result['matched']++;
                $result['net'] += $line->total;
            }
            $result['net'] = round($result['net'], 2);
        });

        return $result;
    }

    /** @return Collection<int, ResaleSubscription> */
    private function subscriptions(Organization $organization): Collection {
        return $this->subscriptions[$organization->id] ??= ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->get();
    }

    /**
     * Domain-Buchungsjournal (083) → Einkaufsbeleg je Domain-Abo und Periode.
     *
     * @return array{entries: int, skipped: int}
     */
    public function syncDomainAccounting(Organization|int $organization): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $result = ['entries' => 0, 'skipped' => 0];
        $subscriptions = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->whereNotNull('domain_projection_id')
            ->get()
            ->keyBy('domain_projection_id');
        if ($subscriptions->isEmpty()) {
            return $result;
        }
        $entries = DomainAccountingEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('domain_projection_id', $subscriptions->keys()->all())
            ->whereNotNull('net_amount')
            ->whereNotNull('entry_date')
            ->orderBy('entry_date')
            ->get();
        foreach ($entries as $entry) {
            /** @var ResaleSubscription|null $subscription */
            $subscription = $subscriptions->get($entry->domain_projection_id);
            if ($subscription === null || $entry->entry_date === null) {
                $result['skipped']++;

                continue;
            }
            $hash = (string) CryptoHelper::hash('domain|' . $entry->id . '|' . $entry->raw_hash);
            if (ResalePurchaseEntry::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('raw_hash', $hash)->exists()) {
                $result['skipped']++;

                continue;
            }
            $date = CarbonImmutable::instance($entry->entry_date);
            $period = ResalePeriod::query()->withoutGlobalScopes()
                ->where('subscription_id', $subscription->id)
                ->where('starts_on', '<', DateRange::dayAfter($date))
                ->where('ends_on', '>=', DateRange::day($date))
                ->first();
            ResalePurchaseEntry::query()->create([
                'organization_id' => $organizationId,
                'subscription_id' => $subscription->id,
                'period_id' => $period?->id,
                'provider' => SubscriptionProvider::DomainReselling,
                'source' => ResalePurchaseEntry::SOURCE_DOMAIN,
                'domain_accounting_entry_id' => $entry->id,
                'document_number' => $entry->reference,
                'entry_date' => $date->toDateString(),
                'description' => mb_substr(trim((string) $entry->type . ' ' . (string) $entry->description), 0, 255),
                'net_amount' => (string) $entry->net_amount,
                'currency' => $entry->currency !== null ? $entry->currency->value : 'EUR',
                'raw_hash' => $hash,
            ]);
            $result['entries']++;
        }

        return $result;
    }
}
