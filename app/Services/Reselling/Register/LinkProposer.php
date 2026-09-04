<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkProposer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, ExternalReference, LexofficeVoucherLine, Organization};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProductNameMatcher};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vorschlagslauf (Feature 152, MVP-761): ordnet gespiegelte Rechnungs-
 * positionen den offenen Abrechnungsperioden zu — als Vorschlag, den der
 * Nutzer bestätigt. Regeln aus 151, aber gegen den Bestand statt gegen
 * Heuristik: Positionen des Rechnungsempfängers (Kunde bzw. Partner des
 * Fremdkunden), Produkt über Lexoffice-Artikel oder Namen, Verbrauch in
 * Lizenzmonaten (Monatspreis-Positionen: Menge = Monate), Periode mit dem
 * nächsten Beginn zuerst, dann Reste im Fenster. Bei Partnerkontakten mit
 * mehreren Endkunden desselben Produkts zählt nur eine Position, die den
 * Endkunden nennt. Bestätigte und manuelle Bezüge werden nie angefasst;
 * alte Vorschläge werden ersetzt.
 */
final class LinkProposer {
    public const WINDOW_BEFORE = 90;
    public const WINDOW_AFTER = 730;

    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {}

    /**
     * @return array{periods: int, linked: int, partial: int, links: int, lines_without_subscription: int}
     */
    public function propose(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= CarbonImmutable::today();
        $result = ['periods' => 0, 'linked' => 0, 'partial' => 0, 'links' => 0, 'lines_without_subscription' => 0];

        $subscriptions = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_own_holding', false)
            ->where(static fn($q) => $q->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
            ->with(['customer', 'foreignCustomer.customer', 'lexofficeArticle'])
            ->get();
        if ($subscriptions->isEmpty()) {
            return $result;
        }

        DB::transaction(function () use ($organization, $subscriptions, $reference, &$result): void {
            // Alte Vorschläge weg — bestätigte und manuelle Bezüge bleiben und zählen als Verbrauch.
            ResalePeriodLink::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('origin', LinkOrigin::Proposed->value)
                ->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass()) // lokale Rechnungsentwürfe bleiben
                ->delete();

            $contactsByCustomer = $this->contactsByCustomer($organization, $subscriptions);
            /** @var array<int, list<string>> $contactsBySubscription */
            $contactsBySubscription = [];
            /** @var array<string, array<string, array<string, true>>> $holdersByProduct Kontakt → Produktschlüssel → Halter */
            $holdersByProduct = [];
            foreach ($subscriptions as $subscription) {
                $billedTo = $subscription->billedTo();
                $contacts = $billedTo === null ? [] : ($contactsByCustomer[$billedTo->id] ?? []);
                $contactsBySubscription[$subscription->id] = $contacts;
                $product = $this->productKey($subscription);
                $holder = $subscription->foreign_customer_id !== null ? 'f' . $subscription->foreign_customer_id : 'c' . $subscription->customer_id;
                foreach ($contacts as $contact) {
                    $holdersByProduct[$contact][$product][$holder] = true;
                }
            }
            // Mehrere Abos desselben Halters teilen sich die Positionen; erst
            // verschiedene Halter (Endkunden eines Partners) brauchen die Nennung.
            /** @var array<string, array<string, int>> $productOwners */
            $productOwners = [];
            foreach ($holdersByProduct as $contact => $products) {
                foreach ($products as $product => $holders) {
                    $productOwners[$contact][$product] = count($holders);
                }
            }

            $periods = ResalePeriod::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('subscription_id', $subscriptions->pluck('id')->all())
                ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value, PeriodStatus::Billed->value])
                ->whereNull('decided_at')
                ->where('starts_on', '<', DateRange::dayAfter($reference))
                ->with('links')
                ->orderBy('starts_on')
                ->get();
            if ($periods->isEmpty()) {
                return;
            }
            $subscriptionsById = $subscriptions->keyBy('id');

            $from = $periods->min('starts_on')->subDays(self::WINDOW_BEFORE);
            $to = $reference->addDay();
            $contactIds = array_values(array_unique(array_merge(...array_values($contactsBySubscription) ?: [[]])));
            $lines = $this->lines($organization, $contactIds, $from, $to);
            /** @var array<int, float> $remaining Positions-ID → restliche Lizenzmonate */
            $remaining = [];
            foreach ($lines as $line) {
                $remaining[$line->id] = $this->lineMonths($line, null);
            }
            foreach (ResalePeriodLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass())->get(['linkable_id', 'months']) as $existing) {
                if (isset($remaining[(int) $existing->linkable_id])) {
                    $remaining[(int) $existing->linkable_id] = max(0.0, $remaining[(int) $existing->linkable_id] - (float) $existing->months);
                }
            }

            // Zustand je Periode: benötigte Monate abzüglich bestätigter/manueller Bezüge.
            $states = [];
            foreach ($periods as $period) {
                $subscription = $subscriptionsById->get($period->subscription_id);
                if ($subscription === null) {
                    continue;
                }
                $confirmed = (float) $period->links->sum(static fn(ResalePeriodLink $l): float => $l->origin->isDecided() ? (float) $l->months : 0.0);
                $states[] = ['period' => $period, 'subscription' => $subscription, 'needed' => max(0.0, $period->requiredMonths() - $confirmed), 'covered' => $confirmed];
            }
            $nearest = $this->nearestPeriods($states, $lines, $contactsBySubscription);

            foreach ([true, false] as $nearestOnly) {
                foreach ($states as $index => $state) {
                    if ($state['needed'] <= 0.001) {
                        continue;
                    }
                    $states[$index] = $this->allocate($index, $state, $lines, $remaining, $contactsBySubscription, $productOwners, $nearest, $nearestOnly, $result);
                }
            }

            foreach ($states as $state) {
                $result['periods']++;
                $period = $state['period'];
                $status = $state['needed'] <= 0.001 ? PeriodStatus::Billed : ($state['covered'] > 0.001 ? PeriodStatus::Partial : PeriodStatus::Open);
                if ($status === PeriodStatus::Billed) {
                    $result['linked']++;
                } elseif ($status === PeriodStatus::Partial) {
                    $result['partial']++;
                }
                if ($period->status !== $status) {
                    $period->forceFill(['status' => $status])->save();
                }
            }

            foreach ($lines as $line) {
                if (($remaining[$line->id] ?? 0.0) >= $this->lineMonths($line, null) - 0.001 && $this->looksLikeLicense($line)) {
                    $result['lines_without_subscription']++;
                }
            }
        });

        return $result;
    }

    /**
     * @param  array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}  $state
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @param  array<int, float>  $remaining
     * @param  array<int, list<string>>  $contactsBySubscription
     * @param  array<string, array<string, int>>  $productOwners
     * @param  array<int, int|null>  $nearest
     * @param  array{periods: int, linked: int, partial: int, links: int, lines_without_subscription: int}  $result
     * @return array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}
     */
    private function allocate(int $index, array $state, Collection $lines, array &$remaining, array $contactsBySubscription, array $productOwners, array $nearest, bool $nearestOnly, array &$result): array {
        $period = $state['period'];
        $subscription = $state['subscription'];
        $contacts = array_flip($contactsBySubscription[$subscription->id] ?? []);
        if ($contacts === []) {
            return $state;
        }
        $windowStart = $period->starts_on->subDays(self::WINDOW_BEFORE);
        $windowEnd = $period->starts_on->addDays(self::WINDOW_AFTER);
        $product = $this->productKey($subscription);
        $mentionTokens = $subscription->foreignCustomer !== null ? NameTokenMatcher::significantTokens($subscription->foreignCustomer->name) : [];

        $candidates = [];
        foreach ($lines as $line) {
            $contact = (string) $line->voucher->contact_external_id;
            if (! isset($contacts[$contact]) || ($remaining[$line->id] ?? 0.0) <= 0.001) {
                continue;
            }
            $voucherDate = $line->voucher->voucher_date;
            if ($voucherDate === null) {
                continue;
            }
            $date = CarbonImmutable::instance($voucherDate);
            if ($date->lessThan($windowStart) || $date->greaterThan($windowEnd)) {
                continue;
            }
            if (! $this->matchesProduct($subscription, $line)) {
                continue;
            }
            $mentions = $mentionTokens !== [] && $this->mentions($mentionTokens, $line->text() . ' ' . (string) $line->voucher->voucher_text);
            // Mehrere Endkunden desselben Produkts am selben Kontakt: nur mit Nennung.
            if (($productOwners[$contact][$product] ?? 1) > 1 && ! $mentions) {
                continue;
            }
            if ($nearestOnly && ($nearest[$line->id] ?? null) !== $index) {
                continue;
            }
            $candidates[] = ['line' => $line, 'mentions' => $mentions ? 0 : 1, 'distance' => abs($date->diffInDays($period->starts_on))];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['mentions'] <=> $b['mentions'] ?: $a['distance'] <=> $b['distance'] ?: $a['line']->id <=> $b['line']->id);

        $termMonths = $period->termMonths();
        foreach ($candidates as $candidate) {
            if ($state['needed'] <= 0.001) {
                break;
            }
            /** @var LexofficeVoucherLine $line */
            $line = $candidate['line'];
            $monthsPerUnit = $this->monthsPerUnit($line, $subscription, $termMonths);
            $take = min($state['needed'], $remaining[$line->id]);
            $units = $monthsPerUnit > 0 ? $take / $monthsPerUnit : 0.0;
            ResalePeriodLink::query()->create([
                'organization_id' => $period->organization_id,
                'period_id' => $period->id,
                'subscription_id' => $subscription->id,
                'linkable_type' => $line->getMorphClass(),
                'linkable_id' => $line->id,
                'voucher_number' => $line->voucher->voucher_number,
                'voucher_date' => $line->voucher->voucher_date,
                'quantity' => round($take / $termMonths, 3),
                'months' => round($take, 2),
                'amount' => $line->unit_net->times($units)->withScale(2),
                'currency' => $line->currency->value,
                'origin' => LinkOrigin::Proposed,
            ]);
            $remaining[$line->id] -= $take;
            $state['needed'] -= $take;
            $state['covered'] += $take;
            $result['links']++;
        }

        return $state;
    }

    /**
     * Je Position: Index der Periode (passendes Produkt, gleicher Kontakt), deren Beginn dem Belegdatum am nächsten liegt.
     *
     * @param  list<array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}>  $states
     * @param  Collection<int, LexofficeVoucherLine>  $lines
     * @param  array<int, list<string>>  $contactsBySubscription
     * @return array<int, int|null>
     */
    private function nearestPeriods(array $states, Collection $lines, array $contactsBySubscription): array {
        $nearest = [];
        foreach ($lines as $line) {
            $contact = (string) $line->voucher->contact_external_id;
            $voucherDate = $line->voucher->voucher_date;
            if ($voucherDate === null) {
                $nearest[$line->id] = null;

                continue;
            }
            $date = CarbonImmutable::instance($voucherDate);
            $best = null;
            foreach ($states as $index => $state) {
                if (! in_array($contact, $contactsBySubscription[$state['subscription']->id] ?? [], true) || ! $this->matchesProduct($state['subscription'], $line)) {
                    continue;
                }
                $distance = abs($date->diffInDays($state['period']->starts_on));
                if ($best === null || $distance < $best[1]) {
                    $best = [$index, $distance];
                }
            }
            $nearest[$line->id] = $best[0] ?? null;
        }

        return $nearest;
    }

    /**
     * Nennt der Text den Endkunden? Alle Kern-Tokens des Namens (ohne Rechtsform
     * und Füllwörter) kommen als Wörter vor, mindestens eines mit ≥ 4 Zeichen —
     * „Klimpel Bäder GmbH" trifft „Lizenzen Klimpel Bäder", nicht „Bäder Berlin".
     *
     * @param  list<string>  $tokens
     */
    private function mentions(array $tokens, string $text): bool {
        $words = array_flip(explode(' ', MarketplaceCompany::normalizeName($text)));
        $long = false;
        foreach ($tokens as $token) {
            if (! isset($words[$token])) {
                return false;
            }
            $long = $long || mb_strlen($token) >= 4;
        }

        return $long;
    }

    private function matchesProduct(ResaleSubscription $subscription, LexofficeVoucherLine $line): bool {
        if ($subscription->lexoffice_article_id !== null && $line->lexoffice_article_id !== null) {
            return $subscription->lexoffice_article_id === $line->lexoffice_article_id;
        }
        if (! $this->looksLikeLicense($line)) {
            return false;
        }
        $text = $line->text();
        if ($line->article !== null) {
            $text = $line->article->name . ' ' . $text;
        }

        return $this->matcher->matches($subscription->label, $text)
            || ($subscription->lexofficeArticle !== null && $this->matcher->matches($subscription->lexofficeArticle->name, $text));
    }

    /** Eigene Leistungen (Support, Stunden) sind nie Lizenzpositionen; der Artikel entscheidet verbindlich. */
    private function looksLikeLicense(LexofficeVoucherLine $line): bool {
        if ($line->article !== null) {
            return $this->matcher->looksLikeMicrosoftProduct($line->article->name);
        }

        return $this->matcher->looksLikeMicrosoftProduct($line->text());
    }

    private function productKey(ResaleSubscription $subscription): string {
        return $subscription->lexoffice_article_id !== null ? 'art:' . $subscription->lexoffice_article_id : 'name:' . ProductNameMatcher::normalize($subscription->label);
    }

    /** Lizenzmonate, die eine Position insgesamt liefert. */
    private function lineMonths(LexofficeVoucherLine $line, ?ResaleSubscription $subscription): float {
        $quantity = (float) $line->quantity;
        if ($this->isMonthly($line, $subscription)) {
            return $quantity;
        }

        return $quantity * 12.0;
    }

    private function monthsPerUnit(LexofficeVoucherLine $line, ResaleSubscription $subscription, int $termMonths): float {
        return $this->isMonthly($line, $subscription) ? 1.0 : (float) $termMonths;
    }

    /**
     * Monatspreis-Position: Einheit „Monat" oder Stückpreis deutlich unter dem
     * Jahres-Verkaufspreis des Abos (Reseller rechnet „12 × 20,60 €" je Lizenz).
     */
    private function isMonthly(LexofficeVoucherLine $line, ?ResaleSubscription $subscription): bool {
        $unit = mb_strtolower(trim((string) $line->unit_name));
        if ($unit === 'monat' || $unit === 'monate' || $unit === 'month') {
            return true;
        }
        if ($unit !== '') {
            return false;
        }
        $yearly = $subscription === null ? null : ($subscription->sale_unit_price ?? $subscription->purchase_unit_price);
        if ($yearly === null) {
            return $line->unit_net->toFloat() < 30.0; // Microsoft-Monatspreise liegen unter 30 €
        }

        return $line->unit_net->toFloat() < $yearly->toFloat() * 0.5;
    }

    /**
     * @param  list<string>  $contactIds
     * @return Collection<int, LexofficeVoucherLine>
     */
    private function lines(Organization $organization, array $contactIds, CarbonImmutable $from, CarbonImmutable $to): Collection {
        if ($contactIds === []) {
            return collect();
        }

        return LexofficeVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereHas('voucher', static fn($q) => $q->whereIn('contact_external_id', $contactIds)
                ->where('voucher_type', 'invoice')->where('archived', false)->whereNotIn('voucher_status', ['draft', 'voided'])
                ->where('voucher_date', '>=', DateRange::day($from))->where('voucher_date', '<', DateRange::dayAfter($to)))
            ->with(['voucher:id,external_id,contact_external_id,voucher_number,voucher_date,voucher_text,recipient_name', 'article:id,name,unit_name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Lexoffice-Kontakte des Rechnungsempfängers EINES Abos (Dialog).
     *
     * @return list<string>
     */
    public function contactsFor(ResaleSubscription $subscription): array {
        $billedTo = $subscription->billedTo();

        return $billedTo === null ? [] : $this->contactsForCustomer($billedTo);
    }

    /**
     * @return list<string>
     */
    public function contactsForCustomer(Customer $billedTo): array {
        $ids = [];
        foreach (ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $billedTo->organization_id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->where('referenceable_id', $billedTo->id)
            ->pluck('external_id') as $id) {
            $ids[] = (string) $id;
        }

        return $ids;
    }

    /**
     * Lexoffice-Kontakte je Rechnungsempfänger.
     *
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @return array<int, list<string>>
     */
    private function contactsByCustomer(Organization $organization, Collection $subscriptions): array {
        $customerIds = $subscriptions->map(static fn(ResaleSubscription $s): ?int => $s->billedTo()?->id)->filter()->unique()->values()->all();
        if ($customerIds === []) {
            return [];
        }
        $map = [];
        $references = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->whereIn('referenceable_id', $customerIds)
            ->get(['referenceable_id', 'external_id']);
        foreach ($references as $reference) {
            $map[(int) $reference->referenceable_id][] = (string) $reference->external_id;
        }

        return $map;
    }
}
