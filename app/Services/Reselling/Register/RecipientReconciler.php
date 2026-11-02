<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipientReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{PeriodStatus, SubscriptionKind};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProductNameMatcher};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use App\Services\Reselling\Plan\PlanAudit;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Abgleich je Rechnungsempfänger (Feature 152): stellt die fälligen Perioden
 * aller Abos eines Empfängers (Kunde samt seiner Endkunden) den Lizenz-
 * positionen seiner Rechnungen gegenüber — als Bilanz je Produkt in
 * Lizenzmonaten. Die Bilanz trennt „nur nicht zugeordnet" (freie Positionen
 * vorhanden) von „nie abgerechnet" (mehr Perioden als Positionen) und
 * „zu viel abgerechnet" (mehr Positionen als Perioden). Je offener Periode
 * stehen die Positionen desselben Produkts mit ihrem Verbrauch daneben, damit
 * ein hartnäckiger Fall ohne Blick ins Quellsystem auflösbar ist. Drei
 * Fallen, die je Empfänger unsichtbar wären, werden mitgezeigt: die Rechnung
 * ging an einen anderen Empfänger (Schwesterfirma), die Rechnung wurde
 * storniert, der Endkunde steht im Rechnungstext, sein Abo aber noch ohne
 * Halter im Posteingang. Positionen kommen aus allen Spiegelquellen
 * ({@see InvoiceMirror}); `contact` ist der Empfängerschlüssel der Quelle.
 *
 * Der Belegspiegel der Organisation wird je Request einmal geladen
 * ({@see ResaleRequestMemo}) und in Lizenz- und Domainzeilen geteilt; die
 * Übersicht und die Belegtexte liegen 60 s im Cache unter dem Org-Stempel
 * ({@see ResaleCacheStamp}) — Leistungsanalyse 2026-11-02, perfc-01/03/09.
 *
 * @phpstan-type LineRow array{line: MirrorLine, product: string, months: float, licences: float, per_licence: float, linked: float, free: float, periods: list<string>, recipient: string|null, recipient_id: int|null, contact: string, gap: bool}
 * @phpstan-type Candidate array{row: LineRow, distance: int, count: int, rows: list<LineRow>, group_free: float, group_licences: float}
 * @phpstan-type PeriodRow array{period: ResalePeriod, subscription: ResaleSubscription, required: float, covered: float, needed: float, product: string, candidates: list<Candidate>, taken: list<Candidate>, foreign: list<Candidate>, voided: list<Candidate>}
 * @phpstan-type CreditNoteRow array{line: MirrorLine, months: float, licences: float, per_licence: float, linked: float, periods: list<string>}
 * @phpstan-type InboxHint array{company: string, subscriptions: list<ResaleSubscription>, mentions: int}
 * @phpstan-type ProductRow array{key: string, label: string, term: int, subscriptions: int, periods: int, required: float, covered: float, invoiced: float, free: float, missing: float, surplus: float, gap_since: CarbonImmutable|null}
 * @phpstan-type OverviewRow array{customer: Customer|null, name: string, subscriptions: int, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float, lines: int, open_amount: Money|null, findings: int, done: bool}
 * @phpstan-type CachedOverviewRow array{customer_id: int|null, name: string, subscriptions: int, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float, lines: int, open_amount: array{amount: string, currency: string}|null, findings: int, done: bool}
 * @phpstan-type VoucherText array{recipient: int, date: string|null, ends: string|null, text: string}
 */
final class RecipientReconciler {
    /** Lebensdauer der Übersicht und der Belegtexte im Cache (Sekunden); der Org-Stempel verwirft früher. */
    public const CACHE_TTL = 60;

    private readonly InvoiceMirror $mirror;

    private readonly PeriodLinker $linker;

    private readonly PlanAudit $planAudit;

    private readonly ResaleRequestMemo $memo;

    public function __construct(?InvoiceMirror $mirror = null, ?PeriodLinker $linker = null, private readonly ProductNameMatcher $matcher = new ProductNameMatcher(), ?PlanAudit $planAudit = null, ?ResaleRequestMemo $memo = null) {
        $this->mirror = $mirror ?? app(InvoiceMirror::class);
        $this->linker = $linker ?? new PeriodLinker($this->mirror);
        $this->planAudit = $planAudit ?? app(PlanAudit::class);
        $this->memo = $memo ?? app(ResaleRequestMemo::class);
    }

    /**
     * Alle Positionen gültiger Rechnungen der Organisation (ohne Lizenzfilter), je Request einmal geladen.
     *
     * @return Collection<int, MirrorLine>
     */
    private function allLines(Organization $organization): Collection {
        return $this->memo->remember('mirror-lines:all:' . $organization->id, fn(): Collection => $this->mirror->linesFor($organization, null, licenceOnly: false));
    }

    /**
     * Lizenzpositionen der Organisation — aus derselben Ladung, in PHP getrennt (die Quellen
     * entscheiden die Einstufung je Zeile, {@see MirrorLine::$articleIsLicence}).
     *
     * @return Collection<int, MirrorLine>
     */
    private function licenceLines(Organization $organization): Collection {
        return $this->memo->remember('mirror-lines:licence:' . $organization->id, fn(): Collection => $this->allLines($organization)->filter(static fn(MirrorLine $line): bool => $line->articleIsLicence)->values());
    }

    /** @return Collection<int, MirrorLine> */
    private function voidedLines(Organization $organization): Collection {
        return $this->memo->remember('mirror-lines:voided:' . $organization->id, fn(): Collection => $this->mirror->voidedLines($organization, null));
    }

    /**
     * Alle Rechnungsempfänger mit Abos oder Lizenzpositionen, Probleme zuerst.
     * 60 s im Cache je Organisation und Stichtag (nur Arrays); Liste, „Nächster"
     * und die Position in der Mappe teilen sich das Ergebnis.
     *
     * @return list<OverviewRow>
     */
    public function overview(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $key = ResaleCacheStamp::key('reconcile-overview', 1, (int) $organization->id, $reference->toDateString());
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            $cached = $this->overviewRows($organization, $reference);
            Cache::put($key, $cached, self::CACHE_TTL);
        }
        $customerIds = [];
        foreach ($cached as $row) {
            if (is_array($row) && is_int($row['customer_id'] ?? null)) {
                $customerIds[] = $row['customer_id'];
            }
        }
        $customers = $customerIds === [] ? collect() : Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');
        $rows = [];
        foreach ($cached as $row) {
            if (! is_array($row) || ! array_key_exists('customer_id', $row)) {
                continue;
            }
            /** @var CachedOverviewRow $row */
            $amount = $row['open_amount'];
            $rows[] = [
                'customer' => $row['customer_id'] !== null ? $customers->get($row['customer_id']) : null,
                'name' => $row['name'],
                'subscriptions' => $row['subscriptions'],
                'open' => $row['open'],
                'partial' => $row['partial'],
                'proposed' => $row['proposed'],
                'free' => $row['free'],
                'missing' => $row['missing'],
                'surplus' => $row['surplus'],
                'lines' => $row['lines'],
                'open_amount' => $amount !== null ? Money::of($amount['amount'], CurrencyCode::tryFrom($amount['currency']) ?? CurrencyCode::Euro) : null,
                'findings' => $row['findings'],
                'done' => $row['done'],
            ];
        }

        return $rows;
    }

    /**
     * Übersicht ohne Cache: Zeilen in Cache-Form (Kunden-ID statt Modell, Betrag als Zahl und Währung).
     *
     * @return list<CachedOverviewRow>
     */
    private function overviewRows(Organization $organization, CarbonImmutable $reference): array {
        $subscriptions = ResaleSubscription::withCatalogArticles($this->subscriptions($organization)->with('periods.links')->get());
        /** @var array<int, Collection<int, ResaleSubscription>> $byCustomer */
        $byCustomer = [];
        foreach ($subscriptions as $subscription) {
            $billedTo = $subscription->billedTo();
            if ($billedTo !== null) {
                $byCustomer[$billedTo->id] ??= collect();
                $byCustomer[$billedTo->id]->push($subscription);
            }
        }
        $lines = $this->licenceLines($organization);
        /** @var array<int|string, Collection<int, MirrorLine>> $linesByRecipient Kunden-ID oder Empfängerschlüssel der Quelle */
        $linesByRecipient = [];
        foreach ($lines as $line) {
            $key = $line->recipientCustomerId ?? $line->recipientKey;
            $linesByRecipient[$key] ??= collect();
            $linesByRecipient[$key]->push($line);
        }
        $linkedMonths = $this->linker->consumed($lines);

        $customerIds = array_values(array_unique(array_merge(array_keys($byCustomer), array_filter(array_keys($linesByRecipient), 'is_int'))));
        $customers = Customer::query()->whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');

        // Vorlagenbefunde je Empfänger (Phase 165, MVP-1445): zählen zum Fertig-Kriterium.
        /** @var array<int, int> $findingsByRecipient */
        $findingsByRecipient = [];
        foreach ($this->planAudit->findings($organization, null, $reference) as $finding) {
            $recipientId = $finding['subscription']?->billedTo()->id ?? $finding['template']?->recipientCustomerId;
            if ($recipientId !== null) {
                $findingsByRecipient[(int) $recipientId] = ($findingsByRecipient[(int) $recipientId] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach (array_unique(array_merge(array_keys($byCustomer), array_keys($linesByRecipient))) as $key) {
            $customer = is_int($key) ? $customers->get($key) : null;
            $recipientLines = $linesByRecipient[$key] ?? collect();
            $analysis = $this->analyze($byCustomer[$key] ?? collect(), $recipientLines, $linkedMonths, $reference);
            $firstLine = $recipientLines->first();
            $name = $customer !== null ? $customer->name : ($firstLine !== null ? (string) $firstLine->recipientName : '');
            $findings = is_int($key) ? ($findingsByRecipient[$key] ?? 0) : 0;
            $openAmount = self::openAmount($byCustomer[$key] ?? collect(), $reference);
            $rows[] = [
                'customer_id' => $customer?->id,
                'name' => $name !== '' ? $name : '—',
                'subscriptions' => ($byCustomer[$key] ?? collect())->count(),
                'open' => $analysis['open'],
                'partial' => $analysis['partial'],
                'proposed' => $analysis['proposed'],
                'free' => $analysis['free'],
                'missing' => $analysis['missing'],
                'surplus' => $analysis['surplus'],
                'lines' => $recipientLines->count(),
                'open_amount' => $openAmount !== null ? ['amount' => $openAmount->getAmount(), 'currency' => $openAmount->getCurrency()->value] : null,
                'findings' => $findings,
                // Fertig = keine fällige Periode ohne Entscheidung und kein Vorlagenbefund (Entscheidung des Inhabers 2026-10-31).
                'done' => $analysis['open'] + $analysis['partial'] === 0 && $findings === 0,
            ];
        }
        // Offener Betrag zuerst (Entscheidung 2026-10-31), dann offene Perioden, Befunde, freie Positionen, Name.
        usort($rows, static fn(array $a, array $b): int => (((float) ($b['open_amount']['amount'] ?? 0.0)) <=> ((float) ($a['open_amount']['amount'] ?? 0.0)))
            ?: (($b['open'] + $b['partial']) <=> ($a['open'] + $a['partial']))
            ?: ($b['findings'] <=> $a['findings'])
            ?: ($b['free'] <=> $a['free'])
            ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Arbeitsliste (Phase 165, MVP-1445): Empfänger mit Kunde, die noch nicht fertig sind, in Arbeitsreihenfolge.
     *
     * @return list<OverviewRow>
     */
    public function worklist(Organization $organization, ?CarbonImmutable $reference = null): array {
        return array_values(array_filter($this->overview($organization, $reference), static fn(array $row): bool => $row['customer'] !== null && ! $row['done']));
    }

    /**
     * Offenes Soll der fälligen, nicht entschiedenen Perioden (offen/teilweise) — in der Währung der ersten Periode.
     *
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     */
    private static function openAmount(Collection $subscriptions, CarbonImmutable $reference): ?Money {
        $sum = null;
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->duePeriods($reference) as $period) {
                $amount = $period->openAmount();
                if ($amount === null) {
                    continue;
                }
                $sum = $sum === null ? $amount : ($sum->isSameCurrency($amount) ? $sum->plus($amount) : $sum);
            }
        }

        return $sum;
    }

    /**
     * Abgleich eines Empfängers: Bilanz je Produkt, fällige Perioden mit
     * Kandidaten, alle Lizenzpositionen mit Verbrauch.
     *
     * Gutschrift-Positionen des Empfängers (`credit_notes`) stehen daneben —
     * als negative Bezüge nur von Hand, nie heuristisch (Review 2026-09-10, A3).
     *
     * @return array{subscriptions: Collection<int, ResaleSubscription>, has_source: bool, pending: int, inbox: list<InboxHint>, credit_notes: list<CreditNoteRow>, products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, product_by_subscription: array<int, string>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    public function forCustomer(Organization $organization, Customer $customer, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $all = ResaleSubscription::withCatalogArticles($this->subscriptions($organization)->with(['periods.links', 'periods.subscription'])->get());
        $subscriptions = $all->filter(static fn(ResaleSubscription $s): bool => $s->billedTo()?->id === $customer->id)->values();
        // Domain-Abos: ihre Rechnungen laufen über Artikel ohne Lizenz-Einstufung, der Domainname
        // steht im Positionstext — dazu die Positionen aller Empfänger, die eine Domain des Kunden
        // nennen. Fremde Empfänger darunter sind der häufigste Grund für offene Domain-Perioden
        // (Befund 2026-10-30: Domains beim Steuerbüro, Rechnungen seit 2023 an den Inhaber privat).
        $domains = array_values(array_unique(array_filter(array_map(
            static fn(ResaleSubscription $s): string => mb_strtolower(trim($s->label)),
            $subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->kind === SubscriptionKind::Domain)->all(),
        ))));
        // Eine Ladung aller Positionen der Organisation (perfc-01): Lizenzpositionen tragen die Bilanz —
        // eigene, fremde (Rechnungen an Schwesterfirmen) —, die übrigen zählen nur, wenn sie eine Domain nennen.
        $lines = collect();
        $others = collect();
        foreach ($this->allLines($organization) as $line) {
            if (! $line->articleIsLicence && ! self::mentionsAnyDomain($line, $domains)) {
                continue;
            }
            if ($line->recipientCustomerId === $customer->id) {
                $lines->push($line);
            } else {
                $others->push($line);
            }
        }
        // Verwandte Empfänger: Name teilt ein Kern-Token mit dem Empfänger, den
        // Firmennamen seiner Abos oder seinen Endkunden („Ökotherm Service" ↔
        // „Ökotherm - HLSK"). Ihre stornierten Rechnungen werden mitgezeigt.
        $baseTokens = self::tokens($customer->name);
        $relatedKeys = $baseTokens;
        foreach ($subscriptions as $subscription) {
            $relatedKeys += self::holderTokens($subscription);
        }
        $allVoided = $this->voidedLines($organization);
        $recipientTokens = $this->recipientTokens($others->merge($allVoided), $customer->id);
        $voided = $allVoided->filter(static fn(MirrorLine $line): bool => $line->recipientCustomerId === $customer->id
            || array_intersect_key($recipientTokens[$line->recipientKey] ?? [], $relatedKeys) !== [])->values();
        $pending = $this->mirror->pendingCount($organization, [$customer->id]);
        $linkedMonths = $this->linker->consumed($lines->merge($others));
        $inbox = $this->inboxHints($organization, $lines);

        return ['subscriptions' => $subscriptions, 'has_source' => $this->mirror->coversRecipient($organization, $customer), 'pending' => $pending, 'inbox' => $inbox, 'credit_notes' => $this->creditNoteRows($organization, $customer)]
            + $this->analyze($subscriptions, $lines, $linkedMonths, $reference, $others, $voided, $recipientTokens, $baseTokens, $customer->id);
    }

    /**
     * Nennt die Position eine der Domains — exakt oder mit Tippfehler (wie {@see InvoiceMirror::linesMentioningDomains()})?
     *
     * @param  list<string>  $domains  klein geschrieben
     */
    private static function mentionsAnyDomain(MirrorLine $line, array $domains): bool {
        foreach ($domains as $domain) {
            if ($line->mentionsDomain($domain) || $line->mentionsDomainLoosely($domain, $domains)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gutschrift-Lizenzpositionen des Empfängers mit Lizenzmonaten (Betrag)
     * und dem, was davon schon als negativer Bezug hängt.
     *
     * @return list<CreditNoteRow>
     */
    private function creditNoteRows(Organization $organization, Customer $customer): array {
        $creditLines = $this->mirror->creditNoteLines($organization, [$customer->id]);
        $consumed = $this->linker->consumed($creditLines);
        $rows = [];
        foreach ($creditLines as $line) {
            $split = LicenseMonths::split($line);
            $rows[] = [
                'line' => $line,
                'months' => $split['licences'] * $split['months'],
                'licences' => $split['licences'],
                'per_licence' => $split['months'],
                'linked' => abs($consumed[$line->identity()]['months'] ?? 0.0),
                'periods' => $consumed[$line->identity()]['periods'] ?? [],
            ];
        }
        usort($rows, static fn(array $a, array $b): int => ($b['line']->voucherDate <=> $a['line']->voucherDate) ?: ($a['line']->position <=> $b['line']->position));

        return $rows;
    }

    /**
     * Kern-Tokens (≥ 4 Zeichen) des Empfängernamens je fremdem Empfängerschlüssel.
     *
     * @param  Collection<int, MirrorLine>  $lines
     * @return array<string, array<string, true>>
     */
    private function recipientTokens(Collection $lines, int $ownCustomerId): array {
        $tokens = [];
        foreach ($lines as $line) {
            if ($line->recipientCustomerId === $ownCustomerId || isset($tokens[$line->recipientKey])) {
                continue;
            }
            $set = self::tokens((string) $line->recipientName);
            if ($set !== []) {
                $tokens[$line->recipientKey] = $set;
            }
        }

        return $tokens;
    }

    /** @return array<string, true> */
    private static function holderTokens(ResaleSubscription $subscription): array {
        $tokens = self::tokens((string) $subscription->company_name);
        if ($subscription->foreignCustomer !== null) {
            $tokens += self::tokens($subscription->foreignCustomer->name);
        }

        return $tokens;
    }

    /** @return array<string, true> */
    private static function tokens(string $name): array {
        $tokens = [];
        foreach (NameTokenMatcher::significantTokens($name) as $token) {
            if (mb_strlen($token) >= 4) {
                $tokens[$token] = true;
            }
        }

        return $tokens;
    }

    /**
     * Umgekehrte Richtung für den Posteingang (Pilot 2026-11-01): je Firma ohne Halter die Rechnungsempfänger,
     * in deren Rechnungstexten sie steht — meist der Partner, der den Endkunden abrechnet. Meiste Belege zuerst.
     * Die Belegtexte (je Beleg: Empfänger, Datum, Leistungsende, Text) liegen 60 s im Cache (perfc-09);
     * das Fenster ab `$from` (Belegdatum oder Leistungsende) greift auf dem Cache-Stand.
     *
     * @param  list<string>  $companies
     * @return array<string, list<array{customer: Customer, vouchers: int}>> Firma → Empfänger
     */
    public function mentioningRecipients(Organization $organization, array $companies, CarbonImmutable $from): array {
        if ($companies === []) {
            return [];
        }
        $fromDay = $from->toDateString();
        $tokens = [];
        foreach ($this->voucherTexts($organization) as $voucher => $entry) {
            if (($entry['date'] === null || $entry['date'] < $fromDay) && ($entry['ends'] === null || $entry['ends'] < $fromDay)) {
                continue;
            }
            $tokens[$voucher] = ['recipient' => $entry['recipient'], 'tokens' => NameTokenMatcher::significantTokens($entry['text'])];
        }
        /** @var array<string, array<int, int>> $counts */
        $counts = [];
        /** @var array<int, int> $ids */
        $ids = [];
        foreach ($companies as $company) {
            $companyTokens = NameTokenMatcher::significantTokens($company);
            foreach ($tokens as $entry) {
                if (NameTokenMatcher::tokensMatch($companyTokens, $entry['tokens'])) {
                    $counts[$company][$entry['recipient']] = ($counts[$company][$entry['recipient']] ?? 0) + 1;
                    $ids[$entry['recipient']] = $entry['recipient'];
                }
            }
        }
        $customers = Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey(array_values($ids))->get(['id', 'name'])->keyBy('id');
        $result = [];
        foreach ($counts as $company => $byRecipient) {
            arsort($byRecipient);
            foreach ($byRecipient as $id => $vouchers) {
                $customer = $customers->get($id);
                if ($customer instanceof Customer) {
                    $result[$company][] = ['customer' => $customer, 'vouchers' => $vouchers];
                }
            }
        }

        return $result;
    }

    /**
     * Rechnungstexte je Beleg (Belegtext + Positionstexte) der Lizenzrechnungen mit bekanntem
     * Empfänger — nur Skalare, 60 s je Organisation unter dem Org-Stempel.
     *
     * @return array<string, VoucherText> Quelle:Beleg → Eintrag
     */
    private function voucherTexts(Organization $organization): array {
        $key = ResaleCacheStamp::key('voucher-texts', 1, (int) $organization->id);
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            $cached = [];
            foreach ($this->licenceLines($organization) as $line) {
                if ($line->recipientCustomerId === null) {
                    continue;
                }
                $voucher = $line->sourceKey . ':' . $line->voucherKey;
                $cached[$voucher] ??= ['recipient' => $line->recipientCustomerId, 'date' => $line->voucherDate?->toDateString(), 'ends' => null, 'text' => trim((string) $line->voucherText)];
                $cached[$voucher]['text'] .= ' ' . $line->text();
                $ends = $line->serviceTo?->toDateString();
                if ($ends !== null && ($cached[$voucher]['ends'] === null || $ends > $cached[$voucher]['ends'])) {
                    $cached[$voucher]['ends'] = $ends;
                }
            }
            Cache::put($key, $cached, self::CACHE_TTL);
        }
        $texts = [];
        foreach ($cached as $voucher => $entry) {
            if (is_array($entry) && is_int($entry['recipient'] ?? null) && is_string($entry['text'] ?? null)) {
                $texts[(string) $voucher] = ['recipient' => $entry['recipient'], 'date' => is_string($entry['date'] ?? null) ? $entry['date'] : null, 'ends' => is_string($entry['ends'] ?? null) ? $entry['ends'] : null, 'text' => $entry['text']];
            }
        }

        return $texts;
    }

    /**
     * Abos ohne Halter, deren Firmenname in den Rechnungstexten dieses
     * Empfängers vorkommt — der Partner rechnet den Endkunden ab, das Abo
     * wartet aber noch im Posteingang.
     *
     * @param  Collection<int, MirrorLine>  $lines
     * @return list<InboxHint>
     */
    private function inboxHints(Organization $organization, Collection $lines): array {
        $texts = [];
        foreach ($lines as $line) {
            $voucher = $line->sourceKey . ':' . $line->voucherKey;
            $texts[$voucher] ??= trim((string) $line->voucherText);
            $texts[$voucher] .= ' ' . $line->text();
        }
        if ($texts === []) {
            return [];
        }
        /** @var array<string, InboxHint> $hints */
        $hints = [];
        $unassigned = ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->awaitingHolder()->orderBy('label')->get();
        foreach ($unassigned as $subscription) {
            $name = trim((string) $subscription->company_name);
            if ($name === '') {
                continue;
            }
            $key = MarketplaceCompany::normalizeName($name);
            if (! isset($hints[$key])) {
                $mentions = 0;
                foreach ($texts as $text) {
                    if (NameTokenMatcher::matches($name, $text)) {
                        $mentions++;
                    }
                }
                if ($mentions === 0) {
                    continue;
                }
                $hints[$key] = ['company' => $name, 'subscriptions' => [], 'mentions' => $mentions];
            }
            $hints[$key]['subscriptions'][] = $subscription;
        }
        $rows = array_values($hints);
        usort($rows, static fn(array $a, array $b): int => ($b['mentions'] <=> $a['mentions']) ?: strcmp($a['company'], $b['company']));

        return $rows;
    }

    /**
     * Produktschlüssel einer Position — dieselbe Regel wie
     * {@see ResaleSubscription::productKey()}: Lexoffice-Artikel `art:<id>`;
     * lokale Artikel und Positionen ohne Artikel laufen über das Abo, dessen
     * Artikel bzw. Name zur Position passt, sonst über den normalisierten Namen.
     *
     * @param  array<int, string>  $productBySubscription
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  list<string>  $domains  Domains der Abos (Tippfehler-Erkennung), einmal je Analyse berechnet
     */
    private function lineProductKey(MirrorLine $line, array $productBySubscription, Collection $subscriptions, array $domains): string {
        foreach ($subscriptions as $subscription) {
            if (isset($productBySubscription[$subscription->id]) && LinkProposer::matchesProductOf($subscription, $line, $this->matcher, $domains)) {
                return $productBySubscription[$subscription->id];
            }
        }

        return $line->articleKey ?? 'name:' . ProductNameMatcher::normalize($line->label());
    }

    /**
     * Gleiche Positionen desselben Belegs — drei Zeilen „12 Monat × 3,95“ für drei
     * Lizenzen — werden eine Zeile mit Anzahl; sonst stehen drei identische
     * Vorschläge mit je eigenem Knopf untereinander (Pilot 2026-10-31). Zwilling =
     * gleicher Beleg, Artikel, Text, Menge, Einheit, Preis, Leistungszeitraum,
     * Empfänger und gleicher Rest.
     *
     * @param  list<array{row: LineRow, distance: int}>  $entries
     * @return list<Candidate>
     */
    private static function groupTwins(array $entries): array {
        /** @var array<string, Candidate> $grouped */
        $grouped = [];
        foreach ($entries as $entry) {
            $line = $entry['row']['line'];
            $key = implode('|', [
                $line->sourceKey, $line->voucherKey, (string) $line->articleKey, $line->name, (string) $line->description, (string) $line->quantity,
                (string) $line->unitName, $line->unitNet->getAmount(), $line->serviceFrom?->toDateString() ?? '', $line->serviceTo?->toDateString() ?? '',
                (string) $line->recipientCustomerId, sprintf('%.2f', $entry['row']['free']), sprintf('%.2f', $entry['row']['linked']),
            ]);
            if (isset($grouped[$key])) {
                $grouped[$key]['count']++;
                $grouped[$key]['rows'][] = $entry['row'];
                $grouped[$key]['group_free'] += $entry['row']['free'];
                $grouped[$key]['group_licences'] += $entry['row']['licences'];

                continue;
            }
            $grouped[$key] = $entry + ['count' => 1, 'rows' => [$entry['row']], 'group_free' => $entry['row']['free'], 'group_licences' => $entry['row']['licences']];
        }

        return array_values($grouped);
    }

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  Collection<int, MirrorLine>  $lines
     * @param  array<string, array{months: float, periods: list<string>}>  $linkedMonths
     * @param  Collection<int, MirrorLine>  $others  Lizenzpositionen anderer Empfänger
     * @param  Collection<int, MirrorLine>  $voided  stornierte Lizenzpositionen dieses Empfängers und verwandter Empfänger
     * @param  array<string, array<string, true>>  $recipientTokens  Empfängerschlüssel → Kern-Tokens des Empfängernamens
     * @param  array<string, true>  $baseTokens  Kern-Tokens des Empfängers selbst
     * @return array{products: list<ProductRow>, periods: list<PeriodRow>, lines: list<LineRow>, product_by_subscription: array<int, string>, open: int, partial: int, proposed: int, free: float, missing: float, surplus: float}
     */
    private function analyze(Collection $subscriptions, Collection $lines, array $linkedMonths, CarbonImmutable $reference, ?Collection $others = null, ?Collection $voided = null, array $recipientTokens = [], array $baseTokens = [], ?int $ownCustomerId = null): array {
        /** @var array<string, string> $articleNames Katalogschlüssel → Name */
        $articleNames = [];
        foreach ($lines as $line) {
            if ($line->articleKey !== null && $line->articleName !== null) {
                $articleNames[$line->articleKey] = $line->articleName;
            }
        }
        /** @var array<int, string> $productBySubscription */
        $productBySubscription = [];
        /** @var array<string, string> $labels */
        $labels = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->productKey($articleNames);
            $productBySubscription[$subscription->id] = $key;
            $labels[$key] ??= $subscription->productName();
        }
        // Domains einmal je Analyse; gleiche Positionen (Artikel, Text, Einstufung) bekommen den Schlüssel
        // nur einmal berechnet — der Abgleich je Abo (Namensmatch) ist der teure Teil (perfc-03).
        $domains = array_values($subscriptions->filter(static fn(ResaleSubscription $s): bool => $s->kind === SubscriptionKind::Domain)->map(static fn(ResaleSubscription $s): string => $s->label)->all());
        /** @var array<string, string> $productKeys Zeilensignatur → Produktschlüssel */
        $productKeys = [];
        $productOf = function (MirrorLine $line) use (&$productKeys, $productBySubscription, $subscriptions, $domains): string {
            $signature = implode("\x1f", [(string) $line->articleKey, $line->articleIsLicence ? '1' : '0', (string) $line->articleName, $line->name, (string) $line->description]);

            return $productKeys[$signature] ??= $this->lineProductKey($line, $productBySubscription, $subscriptions, $domains);
        };

        /** @var list<LineRow> $lineRows */
        $lineRows = [];
        /** @var array<string, float> $invoiced */
        $invoiced = [];
        /** @var array<string, float> $freeByProduct */
        $freeByProduct = [];
        foreach ($lines as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $identity = $line->identity();
            $linked = $linkedMonths[$identity]['months'] ?? 0.0;
            $key = $productOf($line);
            $labels[$key] ??= $line->label();
            $lineRow = ['line' => $line, 'product' => $key, 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => max(0.0, $months - $linked), 'periods' => $linkedMonths[$identity]['periods'] ?? [], 'recipient' => null, 'recipient_id' => null, 'contact' => $line->recipientKey, 'gap' => false];
            $lineRows[] = $lineRow;
            $invoiced[$key] = ($invoiced[$key] ?? 0.0) + $months;
            $freeByProduct[$key] = ($freeByProduct[$key] ?? 0.0) + $lineRow['free'];
        }
        usort($lineRows, static fn(array $a, array $b): int => ($b['line']->voucherDate <=> $a['line']->voucherDate) ?: strcmp($a['line']->sourceKey, $b['line']->sourceKey) ?: ($a['line']->morphId <=> $b['line']->morphId));
        /** @var list<LineRow> $otherRows Freie Positionen anderer Empfänger */
        $otherRows = [];
        foreach ($others ?? collect() as $line) {
            $split = LicenseMonths::split($line);
            $months = $split['licences'] * $split['months'];
            $identity = $line->identity();
            $linked = $linkedMonths[$identity]['months'] ?? 0.0;
            if ($months - $linked <= 0.001) {
                continue;
            }
            $recipient = trim((string) $line->recipientName);
            $otherRows[] = ['line' => $line, 'product' => $productOf($line), 'months' => $months, 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => $linked, 'free' => $months - $linked, 'periods' => $linkedMonths[$identity]['periods'] ?? [], 'recipient' => $recipient !== '' ? $recipient : null, 'recipient_id' => $line->recipientCustomerId, 'contact' => $line->recipientKey, 'gap' => false];
        }
        /** @var list<LineRow> $voidedRows */
        $voidedRows = [];
        foreach ($voided ?? collect() as $line) {
            $split = LicenseMonths::split($line);
            $own = $ownCustomerId !== null && $line->recipientCustomerId === $ownCustomerId;
            $recipient = trim((string) $line->recipientName);
            $voidedRows[] = ['line' => $line, 'product' => $productOf($line), 'months' => $split['licences'] * $split['months'], 'licences' => $split['licences'], 'per_licence' => $split['months'], 'linked' => 0.0, 'free' => 0.0, 'periods' => [], 'recipient' => $own || $recipient === '' ? null : $recipient, 'recipient_id' => null, 'contact' => $line->recipientKey, 'gap' => false];
        }
        // Je Produkt gruppiert: die Periodenschleife sieht nur die Zeilen ihres Produkts (perfc-03).
        /** @var array<string, list<LineRow>> $lineRowsByProduct */
        $lineRowsByProduct = [];
        foreach ($lineRows as $lineRow) {
            $lineRowsByProduct[$lineRow['product']][] = $lineRow;
        }
        /** @var array<string, list<LineRow>> $otherRowsByProduct */
        $otherRowsByProduct = [];
        foreach ($otherRows as $lineRow) {
            $otherRowsByProduct[$lineRow['product']][] = $lineRow;
        }
        /** @var array<string, list<LineRow>> $voidedRowsByProduct */
        $voidedRowsByProduct = [];
        foreach ($voidedRows as $lineRow) {
            $voidedRowsByProduct[$lineRow['product']][] = $lineRow;
        }

        /** @var array<string, int> $subscriptionCount */
        $subscriptionCount = [];
        /** @var array<string, int> $termByProduct Monate je Lizenz und Periode (Intervall des Abos) */
        $termByProduct = [];
        /** @var array<string, int> $periodCount */
        $periodCount = [];
        /** @var array<string, float> $requiredByProduct */
        $requiredByProduct = [];
        /** @var array<string, float> $coveredByProduct */
        $coveredByProduct = [];
        /** @var list<PeriodRow> $periodRows */
        $periodRows = [];
        $open = $partial = $proposed = 0;
        foreach ($subscriptions as $subscription) {
            $key = $productBySubscription[$subscription->id];
            $subscriptionCount[$key] = ($subscriptionCount[$key] ?? 0) + 1;
            foreach ($subscription->periods as $period) {
                if ($period->starts_on->greaterThan($reference) || in_array($period->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
                    continue;
                }
                $required = $period->requiredMonths();
                $covered = $period->coveredMonths();
                $termByProduct[$key] ??= $period->termMonths();
                $periodCount[$key] = ($periodCount[$key] ?? 0) + 1;
                $requiredByProduct[$key] = ($requiredByProduct[$key] ?? 0.0) + $required;
                $coveredByProduct[$key] = ($coveredByProduct[$key] ?? 0.0) + $covered;
                if ($period->status === PeriodStatus::Billed && $period->isProposedOnly()) {
                    $proposed++;
                }
                $needed = max(0.0, $required - $covered);
                if ($needed <= 0.001) {
                    continue;
                }
                if ($period->status === PeriodStatus::Partial) {
                    $partial++;
                } else {
                    $open++;
                }
                $periodRows[] = ['period' => $period, 'subscription' => $subscription, 'required' => $required, 'covered' => $covered, 'needed' => $needed, 'product' => $key, 'candidates' => [], 'taken' => [], 'foreign' => [], 'voided' => []];
            }
        }
        usort($periodRows, static fn(array $a, array $b): int => ($a['period']->starts_on <=> $b['period']->starts_on) ?: ($a['subscription']->id <=> $b['subscription']->id));

        foreach ($periodRows as $index => $row) {
            $start = $row['period']->starts_on;
            foreach ($lineRowsByProduct[$row['product']] ?? [] as $lineRow) {
                // Bezug = Beginn des Leistungszeitraums, sonst Rechnungsdatum; nur im Fenster der
                // Periode (bei Mehrperioden-Positionen verlängert) — oder der Leistungszeitraum
                // deckt den Periodenbeginn.
                $date = LicenseMonths::referenceDate($lineRow['line']);
                if ($date === null || ! LinkProposer::inWindow($row['period'], $lineRow['line'])) {
                    continue;
                }
                $distance = (int) abs($date->diffInDays($start));
                $periodRows[$index][$lineRow['free'] > 0.001 ? 'candidates' : 'taken'][] = ['row' => $lineRow, 'distance' => $distance];
            }
            // Verwandt je Periode: Empfänger, Firmenname des Abos oder sein Endkunde
            // teilen ein Kern-Token mit dem Namen des anderen Empfängers.
            $periodTokens = $baseTokens + self::holderTokens($row['subscription']);
            // Unverwandter Empfänger, dessen freie Positionen in der Laufzeit der Periode
            // zusammen genau die Periode ergeben (Omega Rundum: 3 × 12 für den 3er-Vertrag der Inhaberin):
            // ein früherer Kontoinhaber. Nur bei mehr als einer Lizenz — „12 Monat" passt überall.
            /** @var array<string, float> $sumsByContact */
            $sumsByContact = [];
            if ($row['required'] > 12.001) {
                foreach ($otherRowsByProduct[$row['product']] ?? [] as $lineRow) {
                    $date = LicenseMonths::referenceDate($lineRow['line']);
                    if ($date === null || abs($date->diffInDays($start)) > LinkProposer::WINDOW_BEFORE) {
                        continue;
                    }
                    $sumsByContact[$lineRow['contact']] = ($sumsByContact[$lineRow['contact']] ?? 0.0) + $lineRow['free'];
                }
            }
            // Summe = ganze Periode (nicht nur der Rest), dicht am Periodenbeginn — sonst
            // trifft jede Firma mit zufällig gleich vielen Lizenzen.
            $formerHolders = array_keys(array_filter($sumsByContact, static fn(float $sum): bool => abs($sum - $row['required']) < 0.001));
            foreach ([['foreign', $otherRowsByProduct[$row['product']] ?? []], ['voided', $voidedRowsByProduct[$row['product']] ?? []]] as [$bucket, $rows]) {
                foreach ($rows as $lineRow) {
                    $date = LicenseMonths::referenceDate($lineRow['line']);
                    if ($date === null || ! LinkProposer::inWindow($row['period'], $lineRow['line'])) {
                        continue;
                    }
                    $distance = (int) abs($date->diffInDays($start));
                    $related = isset($recipientTokens[$lineRow['contact']]) && array_intersect_key($recipientTokens[$lineRow['contact']], $periodTokens) !== [];
                    // Eine Position, die die Domain des Abos nennt, ist eindeutig — gleich, an wen die Rechnung ging.
                    if (! $related && $row['subscription']->kind === SubscriptionKind::Domain && $lineRow['line']->mentionsDomain($row['subscription']->label)) {
                        $related = true;
                    }
                    $ownLine = $ownCustomerId !== null && $lineRow['line']->recipientCustomerId === $ownCustomerId;
                    // Stornos nur dicht am Periodenbeginn — ein Storno aus dem übernächsten Jahr erklärt nichts.
                    if ($bucket === 'voided' && ((! $related && ! $ownLine) || $distance > LinkProposer::WINDOW_BEFORE)) {
                        continue;
                    }
                    // Fremde Positionen nur von verwandten Empfängern — oder von einem
                    // früheren Kontoinhaber (Summe seiner Positionen = Periode) — oder, wenn
                    // der Empfänger selbst nichts Freies hat, bei exakt passender ungewöhnlicher
                    // Menge (mehr als eine Lizenz) dicht am Periodenbeginn: Rechnung an die
                    // falsche Firma. „12 Monat" passt sonst überall.
                    $former = in_array($lineRow['contact'], $formerHolders, true) && $distance <= LinkProposer::WINDOW_BEFORE;
                    if ($bucket === 'foreign' && ! $related && ! $former && ($periodRows[$index]['candidates'] !== [] || $row['needed'] <= 12.001 || abs($lineRow['free'] - $row['needed']) > 0.001 || $distance > LinkProposer::SHARED_NEAREST_DAYS)) {
                        continue;
                    }
                    $periodRows[$index][$bucket][] = ['row' => $lineRow, 'distance' => $distance];
                }
            }
            foreach (['candidates', 'taken', 'foreign', 'voided'] as $bucket) {
                usort($periodRows[$index][$bucket], static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
                $periodRows[$index][$bucket] = self::groupTwins($periodRows[$index][$bucket]);
            }
        }

        // Freie Position, deren Bezugsdatum keine Periode des Produkts (welchen Status auch immer)
        // enthält: ab dort fehlt ein Abo im Register — typisch ein Vertrag, den der Anbieter-Export
        // nicht mehr liefert (nur gekündigte Verträge exportiert, laufende fehlen).
        /** @var array<string, list<array{0: CarbonImmutable, 1: CarbonImmutable}>> $ranges */
        $ranges = [];
        foreach ($subscriptions as $subscription) {
            $key = $productBySubscription[$subscription->id];
            foreach ($subscription->periods as $period) {
                $ranges[$key][] = [$period->starts_on->subDays(LinkProposer::WINDOW_BEFORE), $period->ends_on];
            }
        }
        /** @var array<string, CarbonImmutable> $gapSince */
        $gapSince = [];
        foreach ($lineRows as $index => $lineRow) {
            if ($lineRow['free'] <= 0.001) {
                continue;
            }
            $date = LicenseMonths::referenceDate($lineRow['line']);
            if ($date === null) {
                continue;
            }
            $covered = false;
            foreach ($ranges[$lineRow['product']] ?? [] as [$from, $to]) {
                if (! $date->lessThan($from) && ! $date->greaterThan($to)) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                continue;
            }
            $lineRows[$index]['gap'] = true;
            if (! isset($gapSince[$lineRow['product']]) || $date->lessThan($gapSince[$lineRow['product']])) {
                $gapSince[$lineRow['product']] = $date;
            }
        }

        /** @var list<ProductRow> $productRows */
        $productRows = [];
        $free = $missing = $surplus = 0.0;
        foreach ($labels as $key => $label) {
            $productRequired = $requiredByProduct[$key] ?? 0.0;
            $productCovered = $coveredByProduct[$key] ?? 0.0;
            $productFree = $freeByProduct[$key] ?? 0.0;
            $openMonths = max(0.0, $productRequired - $productCovered);
            $productMissing = max(0.0, $openMonths - $productFree);
            $productSurplus = max(0.0, $productFree - $openMonths);
            $productRows[] = [
                'key' => $key,
                'label' => $label,
                'term' => $termByProduct[$key] ?? 12,
                'subscriptions' => $subscriptionCount[$key] ?? 0,
                'periods' => $periodCount[$key] ?? 0,
                'required' => $productRequired,
                'covered' => $productCovered,
                'invoiced' => $invoiced[$key] ?? 0.0,
                'free' => $productFree,
                'missing' => $productMissing,
                'surplus' => $productSurplus,
                'gap_since' => $gapSince[$key] ?? null,
            ];
            $free += $productFree;
            $missing += $productMissing;
            $surplus += $productSurplus;
        }
        usort($productRows, static fn(array $a, array $b): int => (($b['missing'] + $b['surplus']) <=> ($a['missing'] + $a['surplus'])) ?: strcmp($a['label'], $b['label']));

        return ['products' => $productRows, 'periods' => $periodRows, 'lines' => $lineRows, 'product_by_subscription' => $productBySubscription, 'open' => $open, 'partial' => $partial, 'proposed' => $proposed, 'free' => $free, 'missing' => $missing, 'surplus' => $surplus];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ResaleSubscription> */
    private function subscriptions(Organization $organization): \Illuminate\Database\Eloquent\Builder {
        return ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_own_holding', false)
            ->where(static fn($q) => $q->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name'])
            ->with(ResaleSubscription::providerBillingRelations())
            ->orderBy('label')->orderBy('starts_on');
    }
}
