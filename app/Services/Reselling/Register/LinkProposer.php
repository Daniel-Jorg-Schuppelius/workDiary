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

use App\Enums\Reselling\{LinkOrigin, PeriodStatus, SubscriptionKind};
use App\Models\Customer\ForeignCustomer;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, NameTokenMatcher, ProductNameMatcher};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use App\Settings\SettingScope;
use App\Support\Query\DateRange;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB};
use RuntimeException;

/**
 * Vorschlagslauf (Feature 152, MVP-761): ordnet gespiegelte Rechnungs-
 * positionen den offenen Abrechnungsperioden zu — als Vorschlag, den der
 * Nutzer bestätigt. Regeln aus 151, aber gegen den Bestand statt gegen
 * Heuristik: Positionen des Rechnungsempfängers (Kunde bzw. Partner des
 * Fremdkunden) aus allen Spiegelquellen ({@see InvoiceMirror}), Produkt über
 * Artikel oder Namen, Verbrauch in
 * Lizenzmonaten (`LicenseMonths`), Bezugsdatum = Beginn des Leistungszeitraums
 * der Rechnung, sonst Rechnungsdatum; Periode mit dem nächsten Beginn zuerst,
 * dann Reste im Fenster. Bei Partnerkontakten mit
 * mehreren Endkunden desselben Produkts zählt nur eine Position, die den
 * Endkunden nennt. Bestätigte und manuelle Bezüge werden nie angefasst;
 * alte Vorschläge werden ersetzt. Stornierte Rechnungen decken nichts mehr:
 * ihre bestätigten Bezüge bleiben als Spur (0 Monate, Hinweis), die Periode
 * wird aus der Restdeckung neu bewertet.
 */
final class LinkProposer {
    public const WINDOW_BEFORE = 90;
    public const WINDOW_AFTER = 730;

    /** Sperre je Organisation: Scheduler und Buttons aus vier Views dürfen nicht gleichzeitig laufen. */
    private const LOCK_SECONDS = 300;

    /**
     * Partnerkontakt mit mehreren Endkunden desselben Produkts: ohne Nennung
     * zählt eine Position nur im Nächste-Periode-Pass und nur, wenn ihr
     * Belegdatum dicht am Periodenbeginn liegt (Reseller rechnet je Lizenz
     * eine Position „12 Monat" zum Jahrestag ab).
     */
    public const SHARED_NEAREST_DAYS = 45;

    /** Sicherer Treffer (Feature 185, MVP-1406): Bezugsdatum höchstens so viele Tage vor bzw. nach dem Periodenbeginn. */
    public const SURE_BEFORE_DAYS = 14;

    public const SURE_AFTER_DAYS = 45;

    /** Abstand Periodenbeginn ↔ Zyklusbeginn der Position, den Anbieter- gegen Rechnungsdatum erklären. */
    public const SERVICE_TOLERANCE_DAYS = 14;

    /** Org-Einstellung: Zeitpunkt des letzten Laufs (Übersicht „zuletzt abgeglichen"). */
    public const SETTING_LAST_RUN = 'resale.last_proposal_run_at';

    public const AUDIT_AUTO_CONFIRMED = 'resale_period.auto_confirmed';

    private const PASS_NEAREST = 'nearest';

    private const PASS_RESERVED = 'reserved';

    private const PASS_FREE = 'free';

    /** @var array<string, array<int, true>> Position → Abos, an die sie in diesem Lauf schon hängt (Mehrjahres-Positionen bleiben beim Abo). */
    private array $linkedSubscriptions = [];

    /** @var array<int, array<string, ResalePeriodLink>> Periode → Position → Vorschlag dieses Laufs (zweiter Pass erhöht statt zu doppeln). */
    private array $proposed = [];

    /** @var list<array{0: ResalePeriod, 1: array<string, mixed>}> Audit der automatischen Bestätigungen, geschrieben nach dem Block ohne Modell-Events */
    private array $autoConfirmedAudits = [];

    /** @var array<int, string> Abo-ID → Produktschlüssel des laufenden Laufs */
    private array $productKeys = [];

    /** @var array<int, int> Abo → Zahl der Abos desselben Empfängers und Produkts (Geschwister, Mayershofer-Regel) */
    private array $siblings = [];

    /** @var array<string, list<int>> Position → Indizes der Perioden, deren Laufzeit das Bezugsdatum enthält. */
    private array $containing = [];

    /** @var array<int, float> Periodenindex → noch offene Lizenzmonate (für die Reservierung im Fenster-Pass). */
    private array $needed = [];

    private readonly InvoiceMirror $mirror;

    public function __construct(private readonly ProductNameMatcher $matcher = new ProductNameMatcher(), ?InvoiceMirror $mirror = null) {
        $this->mirror = $mirror ?? app(InvoiceMirror::class);
    }

    /**
     * Fensterende für eine Position: 730 Tage nach Periodenbeginn — bei
     * Mehrperioden-Positionen („48 Monat" für vier Jahre, rückwirkend
     * berechnet) um die zusätzlichen Monate je Lizenz verlängert, sonst
     * fände die erste Periode ihre Rechnung nie.
     */
    public static function windowEnd(CarbonImmutable $periodStart, int $termMonths, MirrorLine $line): CarbonImmutable {
        $extra = (int) round(LicenseMonths::split($line)['months']) - $termMonths;

        return $periodStart->addDays(self::WINDOW_AFTER)->addMonthsNoOverflow(max(0, $extra));
    }

    /**
     * Mit Leistungszeitraum zählt nur er (Phase 163, Pilot Mayershofer): der
     * Periodenbeginn liegt (±14 Tage) auf einem Zyklusbeginn der Position —
     * Leistungsbeginn plus k × Periodenlänge, nicht nach dem Leistungsende.
     * Sonst deckte eine 24-Monats-Position „ab 26.10." Perioden, die im August
     * begannen, nur weil das Datumsfenster weit genug war; „irgendwo im
     * Leistungszeitraum" reichte nicht, weil dann das zweite Jahr der falschen
     * Abos drankam. Der Lauf wendet die Regel nur bei Geschwister-Abos an
     * (gleicher Empfänger, gleiches Produkt) — bei einem einzelnen Abo gibt es
     * nichts zu unterscheiden, und Sammelrechnungen tragen oft nur die Spanne
     * aller Positionen (Befund 2026-10-29); für alle gilt {@see serviceOverlaps()}.
     * Ohne Leistungszeitraum gilt das Fenster um das Rechnungsdatum ({@see inWindow()}).
     */
    public static function serviceFits(ResalePeriod $period, MirrorLine $line): bool {
        if ($line->serviceFrom === null || $line->serviceTo === null) {
            return true;
        }
        $start = $period->starts_on;
        $term = max(1, $period->termMonths());
        $limit = $start->addDays(self::SERVICE_TOLERANCE_DAYS);
        for ($cycle = $line->serviceFrom; ! $cycle->greaterThan($line->serviceTo) && ! $cycle->greaterThan($limit); $cycle = $cycle->addMonthsNoOverflow($term)) {
            if (abs($cycle->diffInDays($start)) <= self::SERVICE_TOLERANCE_DAYS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liegt die Position im Fenster der Periode? Bezugsdatum (Leistungsbeginn,
     * sonst Rechnungsdatum) zwischen −90 Tagen und dem Fensterende — oder der
     * Leistungszeitraum deckt den Periodenbeginn (Mehrjahres-Position).
     */
    /**
     * Überschneidet der Leistungszeitraum der Position die Periode? Ohne
     * Zeitraum ja. Eine Position „2 Jahr“ ab 27.03.2016 deckt keine Periode,
     * die 2014 begann — das Datumsfenster (730 Tage plus Mehrjahresanteil vor
     * dem Bezugsdatum) ist für Nachberechnungen ohne Zeitraum gedacht.
     */
    public static function serviceOverlaps(ResalePeriod $period, MirrorLine $line): bool {
        if ($line->serviceFrom === null || $line->serviceTo === null) {
            return true;
        }

        return ! $line->serviceTo->lessThan($period->starts_on) && ! $line->serviceFrom->greaterThan($period->ends_on);
    }

    public static function inWindow(ResalePeriod $period, MirrorLine $line): bool {
        $date = LicenseMonths::referenceDate($line);
        if ($date === null) {
            return false;
        }
        $start = $period->starts_on;
        if (! $date->lessThan($start->subDays(self::WINDOW_BEFORE)) && ! $date->greaterThan(self::windowEnd($start, $period->termMonths(), $line))) {
            return true;
        }

        return LicenseMonths::serviceCovers($line, $start);
    }

    /**
     * @return array{periods: int, linked: int, partial: int, links: int, lines_without_subscription: int, auto_confirmed: int}
     *
     * @throws RuntimeException wenn für die Organisation schon ein Lauf läuft
     */
    public function propose(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $result = ['periods' => 0, 'linked' => 0, 'partial' => 0, 'links' => 0, 'lines_without_subscription' => 0, 'auto_confirmed' => 0];
        $lock = Cache::lock('resale:propose:' . $organization->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new RuntimeException((string) __('resale.propose.locked'));
        }

        try {
            // Massenlauf ohne Modell-Events (gilt für alle Modelle im Lauf): Vorschläge
            // sind keine Entscheidung und gehören nicht ins Audit-Protokoll (Review
            // 2026-09-10, A2) — Bestätigen, Verzichten und manuelle Bezüge schreiben
            // ihre Events selbst. organization_id wird hier immer explizit gesetzt.
            // Transaktion außen, Events innen: das Audit der automatischen Bestätigung
            // entsteht nach dem Block mit Modell-Events, sonst bekäme die Audit-Zeile
            // keinen Ketten-Hash (HashChained hängt am creating-Event).
            $this->autoConfirmedAudits = [];
            DB::transaction(function () use ($organization, $reference, &$result): void {
                Model::withoutEvents(function () use ($organization, $reference, &$result): void {
                    // Alte Vorschläge weg — bestätigte und manuelle Bezüge bleiben und zählen als
                    // Verbrauch; Bezüge auf Entwurfspositionen (lokaler Rechnungsentwurf) bleiben,
                    // die Quelle grenzt sie aus. Die betroffenen Perioden merken: wer nicht mehr
                    // bewertet wird (Halter weg, eigener Bestand), bekommt am Ende den Status aus
                    // der Restdeckung statt „berechnet" ohne Bezug.
                    $proposals = $this->mirror->scopeProposalLinks($organization, ResalePeriodLink::query()->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->where('origin', LinkOrigin::Proposed->value));
                    $affected = array_map('intval', $proposals->clone()->distinct()->pluck('period_id')->all());
                    $proposals->delete();
                    $affected = array_values(array_unique(array_merge($affected, $this->neutralizeVoidedLinks($organization))));

                    $subscriptions = ResaleSubscription::query()->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->where('is_own_holding', false)
                        ->where(static fn($q) => $q->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
                        ->with(['customer', 'foreignCustomer.customer'])
                        ->orderBy('id')
                        ->get();
                    ResaleSubscription::withCatalogArticles($subscriptions);
                    $evaluated = $subscriptions->isEmpty() ? [] : $this->evaluate($organization, $subscriptions, $reference, $result);
                    $this->resettle($organization, array_values(array_diff($affected, $evaluated)));
                });
                foreach ($this->autoConfirmedAudits as [$period, $payload]) {
                    $period->audit(self::AUDIT_AUTO_CONFIRMED, $payload);
                }
            });
            // Laufstempel ist Systemzustand, keine Einstellung des Nutzers: ohne Modell-Events,
            // sonst stünde stündlich eine „settings geändert“-Zeile im Audit der Organisation.
            Organization::withoutEvents(static fn() => Setting::set(self::SETTING_LAST_RUN, CarbonImmutable::now()->toIso8601String(), SettingScope::Organization, $organization, null));
        } finally {
            $lock->release();
        }

        return $result;
    }

    /** Zeitpunkt des letzten Laufs der Organisation (null: noch nie). */
    public static function lastRunAt(Organization $organization): ?CarbonImmutable {
        $value = app(\App\Settings\SettingsRegistry::class)->effective(self::SETTING_LAST_RUN, $organization)->value;

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    /**
     * Storno: Bezüge jeder Herkunft auf Positionen stornierter Rechnungen
     * decken nichts mehr. Vorschläge sind schon weg; bestätigte/manuelle
     * bleiben als Spur mit 0 Monaten, 0 Betrag und Hinweis (Original-Monate
     * in der Bemerkung). Die Periode wird aus der Restdeckung neu bewertet;
     * ist sie nicht mehr voll gedeckt, gilt die Entscheidung als aufgehoben
     * (`decided_at` null) — der Lauf darf die Ersatzrechnung vorschlagen.
     *
     * @return list<int> IDs der betroffenen Perioden
     */
    private function neutralizeVoidedLinks(Organization $organization): array {
        /** @var array<string, MirrorLine> $lines */
        $lines = [];
        /** @var array<string, list<int>> $idsByMorph */
        $idsByMorph = [];
        foreach ($this->mirror->voidedLines($organization, null) as $line) {
            $lines[$line->identity()] = $line;
            $idsByMorph[$line->morphClass][] = $line->morphId;
        }
        if ($lines === []) {
            return [];
        }
        $links = ResalePeriodLink::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('months', '>', 0)
            ->where(static function (Builder $outer) use ($idsByMorph): void {
                foreach ($idsByMorph as $morphClass => $ids) {
                    $outer->orWhere(static fn(Builder $q) => $q->where('linkable_type', $morphClass)->whereIn('linkable_id', $ids));
                }
            })
            ->get();
        if ($links->isEmpty()) {
            return [];
        }
        $periodIds = [];
        foreach ($links as $link) {
            $line = $lines[MirrorLine::identityOf((string) $link->linkable_type, (int) $link->linkable_id)] ?? null;
            $months = (float) $link->months;
            $label = $line !== null ? LicenseMonths::label($months, LicenseMonths::split($line)['months']) : LicenseMonths::label($months, 0.0);
            $link->appendNote((string) __('resale.link.note_voided', ['months' => $label]));
            $link->forceFill(['months' => 0, 'quantity' => 0, 'amount' => 0])->save();
            $periodIds[(int) $link->period_id] = true;
        }
        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', array_keys($periodIds))
            ->whereIn('status', [PeriodStatus::Billed->value, PeriodStatus::Partial->value])
            ->with('links')
            ->get();
        foreach ($periods as $period) {
            $status = $period->statusFromCoverage($period->coveredMonths());
            $attributes = ['status' => $status];
            if ($status !== PeriodStatus::Billed) {
                $attributes += ['decided_at' => null, 'decided_by_user_id' => null];
            }
            $period->forceFill($attributes)->save();
        }

        return array_keys($periodIds);
    }

    /**
     * Perioden, deren Vorschläge gelöscht wurden, die der Lauf aber nicht
     * bewertet hat: Status aus der Restdeckung (bestätigte/manuelle Bezüge).
     *
     * @param  list<int>  $periodIds
     */
    private function resettle(Organization $organization, array $periodIds): void {
        if ($periodIds === []) {
            return;
        }
        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $periodIds)
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value, PeriodStatus::Billed->value])
            ->with('links')
            ->get();
        foreach ($periods as $period) {
            $status = $period->statusFromCoverage($period->coveredMonths());
            if ($period->status !== $status) {
                $period->forceFill(['status' => $status])->save();
            }
        }
    }

    /**
     * Der eigentliche Lauf über die Perioden der Abos mit Halter.
     *
     * @param  Collection<int, ResaleSubscription>  $subscriptions
     * @param  array{periods: int, linked: int, partial: int, links: int, lines_without_subscription: int, auto_confirmed: int}  $result
     * @return list<int> IDs der bewerteten Perioden
     */
    private function evaluate(Organization $organization, Collection $subscriptions, CarbonImmutable $reference, array &$result): array {
        /** @var array<int, int|null> $recipientBySubscription Abo → Rechnungsempfänger (Kunde) */
        $recipientBySubscription = [];
        /** @var array<int, array<string, array<string, true>>> $holdersByProduct Empfänger → Produktschlüssel → Halter */
        $holdersByProduct = [];
        foreach ($subscriptions as $subscription) {
            $recipientBySubscription[$subscription->id] = $subscription->billedTo()?->id;
        }

        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('subscription_id', $subscriptions->pluck('id')->all())
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value, PeriodStatus::Billed->value])
            ->whereNull('decided_at')
            ->where('starts_on', '<', DateRange::dayAfter($reference))
            ->with('links')
            ->orderBy('starts_on')
            // Gleicher Beginn: feste Reihenfolge, damit wiederholte Läufe gleich ausfallen.
            ->orderBy('id')
            ->get();
        if ($periods->isEmpty()) {
            return [];
        }
        $subscriptionsById = $subscriptions->keyBy('id');

        // Fenster: Belegdatum ODER Leistungsende ab 90 Tage vor der ältesten Periode
        // (36-Monats-Rechnung von 2024 deckt die 2026er-Periode), bis morgen.
        $from = $periods->min('starts_on')->subDays(self::WINDOW_BEFORE);
        $to = $reference->addDay();
        $recipientIds = array_values(array_unique(array_filter($recipientBySubscription, static fn(?int $id): bool => $id !== null)));
        $lines = $this->mirror->linesFor($organization, $recipientIds, $from, $to);
        // Domain-Abos: ihre Rechnungen laufen über Artikel ohne Lizenz-Einstufung
        // („Top-Level-Domain“), der Domainname steht im Positionstext — dazu die
        // Positionen der Empfänger ohne Lizenzfilter, die eine ihrer Domains nennen.
        $domainRecipients = [];
        $domains = [];
        foreach ($subscriptions as $subscription) {
            $recipient = $recipientBySubscription[$subscription->id] ?? null;
            if ($subscription->kind === SubscriptionKind::Domain && $recipient !== null) {
                $domainRecipients[$recipient] = $recipient;
                $domains[] = $subscription->label;
            }
        }
        if ($domains !== []) {
            $known = $lines->map(static fn(MirrorLine $line): string => $line->identity())->flip();
            $lines = $lines->concat(
                $this->mirror->linesMentioningDomains($organization, array_values($domainRecipients), $domains, $from, $to)
                    ->reject(static fn(MirrorLine $line): bool => $known->has($line->identity()))
            )->values();
        }
        /** @var array<string, float> $remaining Position → restliche Lizenzmonate */
        $remaining = [];
        /** @var array<string, string> $articleNames Katalogschlüssel → Name: Abos ohne Artikel bekommen denselben Produktschlüssel wie die Positionen */
        $articleNames = [];
        /** @var array<string, MirrorLine> $linesByIdentity */
        $linesByIdentity = [];
        foreach ($lines as $line) {
            $remaining[$line->identity()] = LicenseMonths::ofLine($line);
            $linesByIdentity[$line->identity()] = $line;
            if ($line->articleKey !== null && $line->articleName !== null) {
                $articleNames[$line->articleKey] = $line->articleName;
            }
        }
        // Produktschlüssel einmal je Abo — sonst zählt die Sharing-Regel „art:…" und
        // „name:…" desselben Produkts als zwei Produkte und die Nennungspflicht entfällt.
        $this->productKeys = [];
        $siblingsByProduct = [];
        foreach ($subscriptions as $subscription) {
            $this->productKeys[$subscription->id] = $subscription->productKey($articleNames);
            $holder = $subscription->foreign_customer_id !== null ? 'f' . $subscription->foreign_customer_id : 'c' . $subscription->customer_id;
            $recipient = $recipientBySubscription[$subscription->id];
            if ($recipient !== null) {
                $holdersByProduct[$recipient][$this->productKeys[$subscription->id]][$holder] = true;
                $siblingsByProduct[$recipient][$this->productKeys[$subscription->id]] = ($siblingsByProduct[$recipient][$this->productKeys[$subscription->id]] ?? 0) + 1;
            }
        }
        $this->siblings = [];
        foreach ($subscriptions as $subscription) {
            $recipient = $recipientBySubscription[$subscription->id];
            $this->siblings[$subscription->id] = $recipient === null ? 1 : ($siblingsByProduct[$recipient][$this->productKeys[$subscription->id]] ?? 1);
        }
        // Mehrere Abos desselben Halters teilen sich die Positionen; erst
        // verschiedene Halter (Endkunden eines Partners) brauchen die Nennung.
        /** @var array<int, array<string, int>> $productOwners */
        $productOwners = [];
        foreach ($holdersByProduct as $recipient => $products) {
            foreach ($products as $product => $holders) {
                $productOwners[$recipient][$product] = count($holders);
            }
        }
        $this->linkedSubscriptions = [];
        $this->proposed = [];
        foreach (ResalePeriodLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('linkable_type', $this->mirror->morphClasses())->get(['linkable_type', 'linkable_id', 'subscription_id', 'months']) as $existing) {
            $identity = MirrorLine::identityOf((string) $existing->linkable_type, (int) $existing->linkable_id);
            if (isset($remaining[$identity])) {
                $remaining[$identity] = max(0.0, $remaining[$identity] - (float) $existing->months);
                $this->linkedSubscriptions[$identity][(int) $existing->subscription_id] = true;
            }
        }

        // Zustand je Periode: benötigte Monate abzüglich des Verbrauchs, der bleibt —
        // alle noch vorhandenen Bezüge (entschiedene, Entwurfsbezüge, Nicht-Spiegel-
        // Bezüge); die Vorschläge des letzten Laufs sind schon gelöscht.
        $states = [];
        $evaluated = [];
        foreach ($periods as $period) {
            $subscription = $subscriptionsById->get($period->subscription_id);
            if ($subscription === null) {
                continue;
            }
            $evaluated[] = $period->id;
            $confirmed = (float) $period->links->sum(static fn(ResalePeriodLink $l): float => (float) $l->months);
            $states[] = ['period' => $period, 'subscription' => $subscription, 'needed' => max(0.0, $period->requiredMonths() - $confirmed), 'covered' => $confirmed];
        }
        $nearest = $this->nearestPeriods($states, $lines, $recipientBySubscription);
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
                $states[$index] = $this->allocate($index, $state, $lines, $remaining, $recipientBySubscription, $productOwners, $nearest, $pass, $result);
            }
        }

        foreach ($states as $state) {
            $result['periods']++;
            $period = $state['period'];
            $status = $period->statusFromCoverage($state['covered']);
            if ($status === PeriodStatus::Billed) {
                $result['linked']++;
            } elseif ($status === PeriodStatus::Partial) {
                $result['partial']++;
            }
            if ($period->status !== $status) {
                $period->forceFill(['status' => $status])->save();
            }
            if ($status === PeriodStatus::Billed && $this->autoConfirm($state, $linesByIdentity)) {
                $result['auto_confirmed']++;
            }
        }

        foreach ($lines as $line) {
            if (($remaining[$line->identity()] ?? 0.0) >= LicenseMonths::ofLine($line) - 0.001 && $line->articleIsLicence) {
                $result['lines_without_subscription']++;
            }
        }

        return $evaluated;
    }

    /**
     * @param  array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}  $state
     * @param  Collection<int, MirrorLine>  $lines
     * @param  array<string, float>  $remaining
     * @param  array<int, int|null>  $recipientBySubscription
     * @param  array<int, array<string, int>>  $productOwners
     * @param  array<string, int|null>  $nearest
     * @param  array{periods: int, linked: int, partial: int, links: int, lines_without_subscription: int, auto_confirmed: int}  $result
     * @return array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}
     */
    private function allocate(int $index, array $state, Collection $lines, array &$remaining, array $recipientBySubscription, array $productOwners, array $nearest, string $pass, array &$result): array {
        $nearestOnly = $pass === self::PASS_NEAREST;
        $period = $state['period'];
        $subscription = $state['subscription'];
        $recipient = $recipientBySubscription[$subscription->id] ?? null;
        if ($recipient === null) {
            return $state;
        }
        $termMonths = $period->termMonths();
        $product = $this->productKeys[$subscription->id] ?? $subscription->productKey();
        $mentionKeys = $subscription->foreignCustomer !== null ? $this->mentionKeys($subscription->foreignCustomer) : null;

        $candidates = [];
        foreach ($lines as $line) {
            $identity = $line->identity();
            if ($line->recipientCustomerId !== $recipient || ($remaining[$identity] ?? 0.0) <= 0.001) {
                continue;
            }
            $date = LicenseMonths::referenceDate($line);
            if ($date === null || ! self::inWindow($period, $line) || ! self::serviceOverlaps($period, $line)) {
                continue;
            }
            // Zyklusregel nur, wo sie etwas entscheidet: mehrere Abos desselben Produkts beim
            // Empfänger. Ein einzelnes Abo nimmt auch Sammelrechnungen, deren Zeitraum nur
            // umspannt, und Rechnungen mit verschobenem Jahrestag (cfl-world.de: „8 Jahr“ ab
            // 08.09.2016 für ein Abo ab 27.01.2017).
            if (($this->siblings[$subscription->id] ?? 1) > 1 && ! self::serviceFits($period, $line)) {
                continue;
            }
            if (! $this->matchesProduct($subscription, $line)) {
                continue;
            }
            $mentions = $mentionKeys !== null && $this->mentions($mentionKeys, $line->text() . ' ' . (string) $line->voucherText);
            $distance = abs($date->diffInDays($period->starts_on));
            if ($nearestOnly && ($nearest[$identity] ?? null) !== $index) {
                continue;
            }
            if ($pass === self::PASS_RESERVED && $this->reservedElsewhere($identity, $index)) {
                continue;
            }
            // Mehrere Endkunden desselben Produkts beim selben Empfänger: ohne Nennung
            // nur als nächste Periode und dicht am Periodenbeginn.
            if (($productOwners[$recipient][$product] ?? 1) > 1 && ! $mentions && (! $nearestOnly || $distance > self::SHARED_NEAREST_DAYS)) {
                continue;
            }
            // Eine Position, die schon an einer anderen Periode DIESES Abos hängt
            // („24 Monat" = eine Lizenz über zwei Jahre), bleibt beim Abo.
            // Umgekehrt: eine Position, die schon an einem ANDEREN Abo hängt, kommt erst nach den freien.
            $continuity = isset($this->linkedSubscriptions[$identity][$subscription->id]) ? 0 : (isset($this->linkedSubscriptions[$identity]) ? 2 : 1);
            // Passgenau zuerst: eine Jahresperiode nimmt Positionen mit 12 Monaten je Lizenz
            // („12 Monat", „1 Jahr") vor Mehrjahres-Positionen („48 Monat" = vier Jahre eines
            // anderen Vertrags) — die kommen erst dran, wenn nichts Passendes mehr frei ist.
            $fit = abs(LicenseMonths::split($line)['months'] - $termMonths);
            // Der Beleg, der aus dem Rechnungsvorschlag dieser Periode entstand, kommt vor allem anderen (Feature 185, MVP-1408).
            $draft = self::matchesDraft($period, $line) ? 0 : 1;
            $candidates[] = ['line' => $line, 'draft' => $draft, 'mentions' => $mentions ? 0 : 1, 'continuity' => $continuity, 'fit' => $fit, 'distance' => $distance];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['draft'] <=> $b['draft'] ?: $a['mentions'] <=> $b['mentions'] ?: $a['continuity'] <=> $b['continuity'] ?: $a['fit'] <=> $b['fit'] ?: $a['distance'] <=> $b['distance'] ?: strcmp($a['line']->sourceKey, $b['line']->sourceKey) ?: $a['line']->morphId <=> $b['line']->morphId);

        foreach ($candidates as $candidate) {
            if ($state['needed'] <= 0.001) {
                break;
            }
            /** @var MirrorLine $line */
            $line = $candidate['line'];
            $identity = $line->identity();
            // Je Periode höchstens Lizenzen × Periodenlänge, wenn die Lizenzzahl sicher ist
            // („5 Jahr", Leistungszeitraum): eine Lizenz über zwei Jahre deckt zwölf Monate
            // dieser Periode, der Rest gehört der Folgeperiode. „24 Monat" ohne Zeitraum
            // bleibt offen — zwei Lizenzen für ein Jahr sind ebenso möglich.
            $existing = $this->proposed[$period->id][$identity] ?? null;
            $cap = LicenseMonths::isLicenceCountCertain($line)
                ? LicenseMonths::split($line)['licences'] * $termMonths - ($existing === null ? 0.0 : (float) $existing->months)
                : PHP_FLOAT_MAX;
            $take = min($state['needed'], $remaining[$identity], $cap);
            if ($take <= 0.001) {
                continue;
            }
            $months = $take + ($existing === null ? 0.0 : (float) $existing->months);
            $units = LicenseMonths::unitsFor($line, $months, $termMonths);
            $attributes = [
                'quantity' => round($months / $termMonths, 3),
                'months' => round($months, 2),
                'amount' => $line->unitNet->times($units)->withScale(2),
            ];
            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
            } else {
                $this->proposed[$period->id][$identity] = ResalePeriodLink::query()->create($attributes + [
                    'organization_id' => $period->organization_id,
                    'period_id' => $period->id,
                    'subscription_id' => $subscription->id,
                    'linkable_type' => $line->morphClass,
                    'linkable_id' => $line->morphId,
                    'voucher_number' => $line->voucherNumber,
                    'voucher_date' => $line->voucherDate,
                    'currency' => $line->currency->value,
                    'origin' => LinkOrigin::Proposed,
                ]);
                $result['links']++;
            }
            $remaining[$identity] -= $take;
            $state['needed'] -= $take;
            $state['covered'] += $take;
            $this->needed[$index] = $state['needed'];
            $this->linkedSubscriptions[$identity][$subscription->id] = true;
        }

        return $state;
    }

    /** Entstand der Beleg aus dem Rechnungsvorschlag dieser Periode (Stempel `draft_reference`)? */
    public static function matchesDraft(ResalePeriod $period, MirrorLine $line): bool {
        return $period->draft_reference !== null && $period->draft_reference !== '' && $line->voucherExternalId !== null && $line->voucherExternalId === $period->draft_reference;
    }

    /**
     * Sicherer Treffer (Feature 185, MVP-1406): gleicher Artikelschlüssel
     * (keine Namensheuristik), Bezugsdatum dicht am Periodenbeginn — oder der
     * Beleg stammt aus dem Rechnungsvorschlag der Periode.
     */
    public static function isSure(ResalePeriod $period, ResaleSubscription $subscription, MirrorLine $line): bool {
        if (self::matchesDraft($period, $line)) {
            return true;
        }
        // Domain-Abo: statt des Artikelschlüssels zählt, dass die Position die Domain nennt.
        if ($subscription->kind === SubscriptionKind::Domain) {
            if (! $line->mentionsDomain($subscription->label)) {
                return false;
            }
        } elseif ($line->articleKey === null || $subscription->article_ref !== $line->articleKey) {
            return false;
        }
        $date = LicenseMonths::referenceDate($line);
        if ($date === null) {
            return false;
        }

        return ! $date->lessThan($period->starts_on->subDays(self::SURE_BEFORE_DAYS)) && ! $date->greaterThan($period->starts_on->addDays(self::SURE_AFTER_DAYS));
    }

    /**
     * Vollständig von EINEM Beleg dieses Laufs gedeckte Periode mit lauter
     * sicheren Positionen, deren Lizenzen die Periodenmenge treffen: die
     * Vorschläge werden „automatisch bestätigt", die Periode entschieden
     * (ohne Nutzer) — nachvollziehbar im Audit, zurücknehmbar wie jede
     * Entscheidung. Deckung von früher (entschiedene Bezüge) macht den Fall
     * unsicher.
     *
     * @param  array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}  $state
     * @param  array<string, MirrorLine>  $linesByIdentity
     */
    private function autoConfirm(array $state, array $linesByIdentity): bool {
        $period = $state['period'];
        $proposals = $this->proposed[$period->id] ?? [];
        if ($proposals === []) {
            return false;
        }
        $runMonths = 0.0;
        $licences = 0.0;
        $vouchers = [];
        foreach ($proposals as $identity => $link) {
            $line = $linesByIdentity[$identity] ?? null;
            if ($line === null || ! self::isSure($period, $state['subscription'], $line)) {
                return false;
            }
            $runMonths += (float) $link->months;
            $licences += LicenseMonths::split($line)['licences'];
            $vouchers[$line->voucherKey] = $line->voucherNumber;
        }
        if ($state['covered'] - $runMonths > 0.001 || count($vouchers) !== 1 || abs($licences - $period->quantity) > 0.001) {
            return false;
        }
        foreach ($proposals as $link) {
            $link->forceFill(['origin' => LinkOrigin::Auto, 'confirmed_at' => now()])->save();
        }
        $attributes = ['decided_at' => now(), 'decided_by_user_id' => null];
        if ($period->draftIsInvoiced()) {
            $attributes += ['draft_reference' => null, 'draft_created_at' => null]; // der Entwurf wurde Rechnung
        }
        $period->forceFill($attributes)->save();
        $this->autoConfirmedAudits[] = [$period, ['vouchers' => array_values(array_filter($vouchers)), 'months' => round($runMonths, 2)]];

        return true;
    }

    /**
     * Liegt das Bezugsdatum der Position in der Laufzeit einer ANDEREN Periode,
     * die noch Lizenzmonate braucht — während die aktuelle es nicht enthält?
     */
    private function reservedElsewhere(string $identity, int $index): bool {
        $containing = $this->containing[$identity] ?? [];
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
     * Je Position: Index der Periode (passendes Produkt, gleicher Empfänger), deren Beginn dem Belegdatum am nächsten liegt.
     * Nennt die Position Endkunden, zählen nur deren Perioden; liegen ohne Nennung Perioden
     * verschiedener Endkunden gleich nah, ist die Position mehrdeutig (null) — sonst
     * entschiede die Reihenfolge der Perioden über den Endkunden.
     *
     * @param  list<array{period: ResalePeriod, subscription: ResaleSubscription, needed: float, covered: float}>  $states
     * @param  Collection<int, MirrorLine>  $lines
     * @param  array<int, int|null>  $recipientBySubscription
     * @return array<string, int|null>
     */
    private function nearestPeriods(array $states, Collection $lines, array $recipientBySubscription): array {
        /** @var array<int, array{tokens: list<string>, squashed: list<string>}|null> $mentionKeys */
        $mentionKeys = [];
        foreach ($states as $state) {
            $subscription = $state['subscription'];
            $mentionKeys[$subscription->id] ??= $subscription->foreignCustomer !== null ? $this->mentionKeys($subscription->foreignCustomer) : null;
        }

        $nearest = [];
        foreach ($lines as $line) {
            $identity = $line->identity();
            $date = LicenseMonths::referenceDate($line);
            if ($date === null) {
                $nearest[$identity] = null;

                continue;
            }
            $text = $line->text() . ' ' . (string) $line->voucherText;
            $matching = [];
            $containing = [];
            foreach ($states as $index => $state) {
                if ($line->recipientCustomerId === null || ($recipientBySubscription[$state['subscription']->id] ?? null) !== $line->recipientCustomerId || ! $this->matchesProduct($state['subscription'], $line)) {
                    continue;
                }
                $period = $state['period'];
                $keys = $mentionKeys[$state['subscription']->id] ?? null;
                $matching[$index] = [
                    'distance' => (int) abs($date->diffInDays($period->starts_on)),
                    'mentioned' => $keys !== null && $this->mentions($keys, $text),
                    'holder' => $state['subscription']->foreign_customer_id !== null ? 'f' . $state['subscription']->foreign_customer_id : 'c' . $state['subscription']->customer_id,
                ];
                if (! $date->lessThan($period->starts_on) && ! $date->greaterThan($period->ends_on)) {
                    $containing[] = $index;
                }
            }
            $this->containing[$identity] = $containing;

            $mentioned = array_filter($matching, static fn (array $m): bool => $m['mentioned']);
            $pool = $mentioned !== [] ? $mentioned : $matching;
            $best = null;
            $holders = [];
            foreach ($pool as $index => $m) {
                if ($best === null || $m['distance'] < $pool[$best]['distance']) {
                    $best = $index;
                    $holders = [$m['holder'] => true];
                } elseif ($m['distance'] === $pool[$best]['distance']) {
                    $holders[$m['holder']] = true;
                }
            }
            $nearest[$identity] = count($holders) > 1 ? null : $best;
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
        $core = self::squash(implode(' ', NameTokenMatcher::withoutLegalForm($foreign->name)));
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

    /**
     * Produkt der Position = Produkt des Abos? Tragen beide einen Artikel
     * derselben Katalogquelle, entscheidet der Schlüssel; sonst muss die
     * Position laut Quelle eine Lizenzposition sein und ihr Text zum
     * Abo-Namen bzw. Artikelnamen passen.
     */
    public static function matchesProductOf(ResaleSubscription $subscription, MirrorLine $line, ProductNameMatcher $matcher): bool {
        // Domain-Abo: die Position muss die Domain nennen; Lizenz-Einstufung und Artikel spielen keine Rolle.
        if ($subscription->kind === SubscriptionKind::Domain) {
            return $line->mentionsDomain($subscription->label);
        }
        $own = ArticleCatalog::parse($subscription->article_ref);
        $theirs = ArticleCatalog::parse($line->articleKey);
        if ($own !== null && $theirs !== null && $own[0] === $theirs[0]) {
            return $own[1] === $theirs[1];
        }
        if (! $line->articleIsLicence) {
            return false;
        }
        $text = trim((string) $line->articleName . ' ' . $line->text());
        $article = $subscription->catalogArticle();

        return $matcher->matches($subscription->label, $text)
            || ($article !== null && $matcher->matches($article->name, $text));
    }

    private function matchesProduct(ResaleSubscription $subscription, MirrorLine $line): bool {
        return self::matchesProductOf($subscription, $line, $this->matcher);
    }
}
