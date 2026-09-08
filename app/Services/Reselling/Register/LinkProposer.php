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
use App\Models\{Customer, ExternalReference, ForeignCustomer, LexofficeVoucherLine, Organization};
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
 * Lizenzmonaten (`LicenseMonths`), Bezugsdatum = Beginn des Leistungszeitraums
 * der Rechnung, sonst Rechnungsdatum; Periode mit dem nächsten Beginn zuerst,
 * dann Reste im Fenster. Bei Partnerkontakten mit
 * mehreren Endkunden desselben Produkts zählt nur eine Position, die den
 * Endkunden nennt. Bestätigte und manuelle Bezüge werden nie angefasst;
 * alte Vorschläge werden ersetzt.
 */
final class LinkProposer {
    public const WINDOW_BEFORE = 90;
    public const WINDOW_AFTER = 730;

    /**
     * Partnerkontakt mit mehreren Endkunden desselben Produkts: ohne Nennung
     * zählt eine Position nur im Nächste-Periode-Pass und nur, wenn ihr
     * Belegdatum dicht am Periodenbeginn liegt (Reseller rechnet je Lizenz
     * eine Position „12 Monat" zum Jahrestag ab).
     */
    public const SHARED_NEAREST_DAYS = 45;

    private const PASS_NEAREST = 'nearest';

    private const PASS_RESERVED = 'reserved';

    private const PASS_FREE = 'free';

    /** @var array<int, array<int, true>> Position → Abos, an die sie in diesem Lauf schon hängt (Mehrjahres-Positionen bleiben beim Abo). */
    private array $linkedSubscriptions = [];

    /** @var array<int, array<int, ResalePeriodLink>> Periode → Position → Vorschlag dieses Laufs (zweiter Pass erhöht statt zu doppeln). */
    private array $proposed = [];

    /** @var array<int, list<int>> Position → Indizes der Perioden, deren Laufzeit das Bezugsdatum enthält. */
    private array $containing = [];

    /** @var array<int, float> Periodenindex → noch offene Lizenzmonate (für die Reservierung im Fenster-Pass). */
    private array $needed = [];

    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher(), private readonly LicenseArticleClassifier $classifier = new LicenseArticleClassifier()) {}

    /**
     * Fensterende für eine Position: 730 Tage nach Periodenbeginn — bei
     * Mehrperioden-Positionen („48 Monat" für vier Jahre, rückwirkend
     * berechnet) um die zusätzlichen Monate je Lizenz verlängert, sonst
     * fände die erste Periode ihre Rechnung nie.
     */
    public static function windowEnd(CarbonImmutable $periodStart, int $termMonths, LexofficeVoucherLine $line): CarbonImmutable {
        $extra = (int) round(LicenseMonths::split($line)['months']) - $termMonths;

        return $periodStart->addDays(self::WINDOW_AFTER)->addMonthsNoOverflow(max(0, $extra));
    }

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
                $remaining[$line->id] = LicenseMonths::ofLine($line);
            }
            $this->linkedSubscriptions = [];
            $this->proposed = [];
            foreach (ResalePeriodLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('linkable_type', (new LexofficeVoucherLine)->getMorphClass())->get(['linkable_id', 'subscription_id', 'months']) as $existing) {
                if (isset($remaining[(int) $existing->linkable_id])) {
                    $remaining[(int) $existing->linkable_id] = max(0.0, $remaining[(int) $existing->linkable_id] - (float) $existing->months);
                    $this->linkedSubscriptions[(int) $existing->linkable_id][(int) $existing->subscription_id] = true;
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
            $this->needed = array_map(static fn(array $state): float => $state['needed'], $states);

            // Drei Pässe: nächste Periode je Position; dann chronologisch, aber eine Position,
            // deren Bezugsdatum in der Laufzeit einer anderen noch offenen Periode liegt, bleibt
            // für diese reserviert (Rechnung vom Februar 2026 gehört dem Nachfolger, nicht
            // dem alten Vertrag von 2024); zuletzt frei — Nachberechnungen über mehrere Jahre
            // („48 Monat" im August 2025) füllen die ältesten offenen Perioden.
            foreach ([self::PASS_NEAREST, self::PASS_RESERVED, self::PASS_FREE] as $pass) {
                foreach ($states as $index => $state) {
                    if ($state['needed'] <= 0.001) {
                        continue;
                    }
                    $states[$index] = $this->allocate($index, $state, $lines, $remaining, $contactsBySubscription, $productOwners, $nearest, $pass, $result);
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
                if (($remaining[$line->id] ?? 0.0) >= LicenseMonths::ofLine($line) - 0.001 && $this->looksLikeLicense($line)) {
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
    private function allocate(int $index, array $state, Collection $lines, array &$remaining, array $contactsBySubscription, array $productOwners, array $nearest, string $pass, array &$result): array {
        $nearestOnly = $pass === self::PASS_NEAREST;
        $period = $state['period'];
        $subscription = $state['subscription'];
        $contacts = array_flip($contactsBySubscription[$subscription->id] ?? []);
        if ($contacts === []) {
            return $state;
        }
        $windowStart = $period->starts_on->subDays(self::WINDOW_BEFORE);
        $termMonths = $period->termMonths();
        $product = $this->productKey($subscription);
        $mentionKeys = $subscription->foreignCustomer !== null ? $this->mentionKeys($subscription->foreignCustomer) : null;

        $candidates = [];
        foreach ($lines as $line) {
            $contact = (string) $line->voucher->contact_external_id;
            if (! isset($contacts[$contact]) || ($remaining[$line->id] ?? 0.0) <= 0.001) {
                continue;
            }
            $date = LicenseMonths::referenceDate($line);
            if ($date === null) {
                continue;
            }
            if (($date->lessThan($windowStart) || $date->greaterThan(self::windowEnd($period->starts_on, $termMonths, $line))) && ! LicenseMonths::serviceCovers($line, $period->starts_on)) {
                continue;
            }
            if (! $this->matchesProduct($subscription, $line)) {
                continue;
            }
            $mentions = $mentionKeys !== null && $this->mentions($mentionKeys, $line->text() . ' ' . (string) $line->voucher->voucher_text);
            $distance = abs($date->diffInDays($period->starts_on));
            if ($nearestOnly && ($nearest[$line->id] ?? null) !== $index) {
                continue;
            }
            if ($pass === self::PASS_RESERVED && $this->reservedElsewhere($line->id, $index)) {
                continue;
            }
            // Mehrere Endkunden desselben Produkts am selben Kontakt: ohne Nennung
            // nur als nächste Periode und dicht am Periodenbeginn.
            if (($productOwners[$contact][$product] ?? 1) > 1 && ! $mentions && (! $nearestOnly || $distance > self::SHARED_NEAREST_DAYS)) {
                continue;
            }
            // Eine Position, die schon an einer anderen Periode DIESES Abos hängt
            // („24 Monat" = eine Lizenz über zwei Jahre), bleibt beim Abo.
            // Umgekehrt: eine Position, die schon an einem ANDEREN Abo hängt, kommt erst nach den freien.
            $continuity = isset($this->linkedSubscriptions[$line->id][$subscription->id]) ? 0 : (isset($this->linkedSubscriptions[$line->id]) ? 2 : 1);
            // Passgenau zuerst: eine Jahresperiode nimmt Positionen mit 12 Monaten je Lizenz
            // („12 Monat", „1 Jahr") vor Mehrjahres-Positionen („48 Monat" = vier Jahre eines
            // anderen Vertrags) — die kommen erst dran, wenn nichts Passendes mehr frei ist.
            $fit = abs(LicenseMonths::split($line)['months'] - $termMonths);
            $candidates[] = ['line' => $line, 'mentions' => $mentions ? 0 : 1, 'continuity' => $continuity, 'fit' => $fit, 'distance' => $distance];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['mentions'] <=> $b['mentions'] ?: $a['continuity'] <=> $b['continuity'] ?: $a['fit'] <=> $b['fit'] ?: $a['distance'] <=> $b['distance'] ?: $a['line']->id <=> $b['line']->id);

        foreach ($candidates as $candidate) {
            if ($state['needed'] <= 0.001) {
                break;
            }
            /** @var LexofficeVoucherLine $line */
            $line = $candidate['line'];
            // Je Periode höchstens Lizenzen × Periodenlänge, wenn die Lizenzzahl sicher ist
            // („5 Jahr", Leistungszeitraum): eine Lizenz über zwei Jahre deckt zwölf Monate
            // dieser Periode, der Rest gehört der Folgeperiode. „24 Monat" ohne Zeitraum
            // bleibt offen — zwei Lizenzen für ein Jahr sind ebenso möglich.
            $existing = $this->proposed[$period->id][$line->id] ?? null;
            $cap = LicenseMonths::isLicenceCountCertain($line)
                ? LicenseMonths::split($line)['licences'] * $termMonths - ($existing === null ? 0.0 : (float) $existing->months)
                : PHP_FLOAT_MAX;
            $take = min($state['needed'], $remaining[$line->id], $cap);
            if ($take <= 0.001) {
                continue;
            }
            $months = $take + ($existing === null ? 0.0 : (float) $existing->months);
            $units = LicenseMonths::unitsFor($line, $months, $termMonths);
            $attributes = [
                'quantity' => round($months / $termMonths, 3),
                'months' => round($months, 2),
                'amount' => $line->unit_net->times($units)->withScale(2),
            ];
            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
            } else {
                $this->proposed[$period->id][$line->id] = ResalePeriodLink::query()->create($attributes + [
                    'organization_id' => $period->organization_id,
                    'period_id' => $period->id,
                    'subscription_id' => $subscription->id,
                    'linkable_type' => $line->getMorphClass(),
                    'linkable_id' => $line->id,
                    'voucher_number' => $line->voucher->voucher_number,
                    'voucher_date' => $line->voucher->voucher_date,
                    'currency' => $line->currency->value,
                    'origin' => LinkOrigin::Proposed,
                ]);
                $result['links']++;
            }
            $remaining[$line->id] -= $take;
            $state['needed'] -= $take;
            $state['covered'] += $take;
            $this->needed[$index] = $state['needed'];
            $this->linkedSubscriptions[$line->id][$subscription->id] = true;
        }

        return $state;
    }

    /**
     * Liegt das Bezugsdatum der Position in der Laufzeit einer ANDEREN Periode,
     * die noch Lizenzmonate braucht — während die aktuelle es nicht enthält?
     */
    private function reservedElsewhere(int $lineId, int $index): bool {
        $containing = $this->containing[$lineId] ?? [];
        if (in_array($index, $containing, true)) {
            return false;
        }
        foreach ($containing as $other) {
            if (($this->needed[$other] ?? 0.0) > 0.001) {
                return true;
            }
        }

        return false;
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
            $date = LicenseMonths::referenceDate($line);
            if ($date === null) {
                $nearest[$line->id] = null;

                continue;
            }
            $best = null;
            $containing = [];
            foreach ($states as $index => $state) {
                if (! in_array($contact, $contactsBySubscription[$state['subscription']->id] ?? [], true) || ! $this->matchesProduct($state['subscription'], $line)) {
                    continue;
                }
                $period = $state['period'];
                $distance = (int) abs($date->diffInDays($period->starts_on));
                if ($best === null || $distance < $best[1]) {
                    $best = [$index, $distance];
                }
                if (! $date->lessThan($period->starts_on) && ! $date->greaterThan($period->ends_on)) {
                    $containing[] = $index;
                }
            }
            $this->containing[$line->id] = $containing;
            $nearest[$line->id] = $best[0] ?? null;
        }

        return $nearest;
    }

    /**
     * Suchschlüssel eines Endkunden für die Nennung im Belegtext: Kern-Tokens
     * des Namens, der Name ohne Leerzeichen („Haus 24" ↔ „Haus24") und der
     * Matchcode des Fremdkunden. Der Reseller schreibt den Endkunden meist
     * verkürzt in den Schlusstext („Vielen Dank … M365 Haus24").
     *
     * @return array{tokens: list<string>, squashed: list<string>}
     */
    private function mentionKeys(ForeignCustomer $foreign): array {
        $squashed = [];
        foreach ([$foreign->name, $foreign->company, $foreign->matchcode] as $candidate) {
            $key = self::squash((string) $candidate);
            if (mb_strlen($key) >= 4) {
                $squashed[] = $key;
            }
        }
        // Auch der Name ohne Rechtsform („Haus 24 GmbH" → „haus24").
        $core = self::squash(implode(' ', NameTokenMatcher::significantTokens($foreign->name)));
        if (mb_strlen($core) >= 4) {
            $squashed[] = $core;
        }

        return ['tokens' => NameTokenMatcher::significantTokens($foreign->name), 'squashed' => array_values(array_unique($squashed))];
    }

    /**
     * Nennt der Text den Endkunden? Alle Kern-Tokens des Namens als Wörter
     * (mindestens eines mit ≥ 4 Zeichen) — oder die leerzeichenfreie Form von
     * Name, Firma oder Matchcode als Teil des leerzeichenfreien Textes.
     *
     * @param  array{tokens: list<string>, squashed: list<string>}  $keys
     */
    private function mentions(array $keys, string $text): bool {
        $words = array_flip(explode(' ', MarketplaceCompany::normalizeName($text)));
        if ($keys['tokens'] !== []) {
            $long = false;
            $all = true;
            foreach ($keys['tokens'] as $token) {
                if (! isset($words[$token])) {
                    $all = false;
                    break;
                }
                $long = $long || mb_strlen($token) >= 4;
            }
            if ($all && $long) {
                return true;
            }
        }
        $flat = self::squash($text);
        foreach ($keys['squashed'] as $key) {
            if (str_contains($flat, $key)) {
                return true;
            }
        }

        return false;
    }

    /** Normalisiert und entfernt alles außer Buchstaben und Ziffern. */
    private static function squash(string $value): string {
        return str_replace(' ', '', MarketplaceCompany::normalizeName($value));
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

    /**
     * Lizenzposition = Position eines Microsoft-Artikels. Verkaufte Lizenzen
     * sind Artikel der Rechnungsverwaltung; freie Positionen (Support, Stunden,
     * Texte) sind nie Lizenzen — der Artikel entscheidet, sonst nichts.
     */
    private function looksLikeLicense(LexofficeVoucherLine $line): bool {
        return $this->classifier->isLicense($line->article);
    }

    private function productKey(ResaleSubscription $subscription): string {
        return $subscription->lexoffice_article_id !== null ? 'art:' . $subscription->lexoffice_article_id : 'name:' . ProductNameMatcher::normalize($subscription->label);
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
            ->with(['voucher:id,external_id,contact_external_id,voucher_number,voucher_date,service_starts_on,service_ends_on,voucher_text,recipient_name', 'article:id,name,unit_name,resale_role'])
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
