<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanAudit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\Reselling\{BillingFrequency, PeriodStatus, SubscriptionStatus};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Register\{LicenseAccounts, LicenseMonths, LinkDeviation, ResaleCacheStamp, ResaleDraftRun};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Prüfregeln Bestand ↔ Plan (Feature 185, MVP-1412): vor der Rechnung
 * sichtbar, was die Serienvorlagen des Buchhaltungsprogramms nicht abbilden —
 * Abo ohne Vorlage, Vorlage ohne Abo, abweichende Menge, Vorlage läuft nach
 * dem Ende weiter, pausiert oder beendet bei laufendem Abo, nächste Ausführung
 * neben dem Periodenbeginn, letzte Ausführung fehlgeschlagen.
 *
 * @phpstan-type Finding array{kind: string, subscription: ResaleSubscription|null, template: PlanTemplate|null, line: PlanLine|null, params: array<string, string|int>, due: CarbonImmutable|null, amount: float|null}
 */
final class PlanAudit {
    public const string SUBSCRIPTION_WITHOUT_PLAN = 'subscription_without_plan';

    public const string PLAN_WITHOUT_SUBSCRIPTION = 'plan_without_subscription';

    public const string QUANTITY_MISMATCH = 'quantity_mismatch';

    public const string PLAN_AFTER_END = 'plan_after_end';

    public const string PLAN_PAUSED = 'plan_paused';

    public const string PLAN_MISSING = 'plan_missing';

    public const string NEXT_EXECUTION_OFF = 'next_execution_off';

    public const string LAST_FAILED = 'last_failed';

    /** Vorlagenposition mehr Lizenzen zugeordnet, als sie berechnet (Phase 167, MVP-1454). */
    public const string LINE_OVERBOOKED = 'line_overbooked';

    /** Vorlage endet bald, das zugeordnete Abo läuft weiter (MVP-1760): das nächste Lizenzjahr bliebe ohne Rechnung. */
    public const string PLAN_ENDS = 'plan_ends';

    /** Vorlagenposition ohne Abo, deren Artikel beim Empfänger nur noch ein beendetes Abo hat (Phase 187, MVP-1774/1775). */
    public const string PLAN_ENDED_PRODUCT = 'plan_ended_product';

    /** Preis der Vorlagenposition je Lizenz weicht vom Verkaufspreis des Abos ab (MVP-1774). */
    public const string PRICE_OFF = 'price_off';

    public const array KINDS = [
        self::SUBSCRIPTION_WITHOUT_PLAN, self::PLAN_WITHOUT_SUBSCRIPTION, self::QUANTITY_MISMATCH, self::PLAN_AFTER_END,
        self::PLAN_PAUSED, self::PLAN_MISSING, self::NEXT_EXECUTION_OFF, self::LAST_FAILED, self::LINE_OVERBOOKED, self::PLAN_ENDS, self::PLAN_ENDED_PRODUCT, self::PRICE_OFF,
    ];

    /** Toleranz zwischen nächster Ausführung der Vorlage und Periodenbeginn. */
    private const int EXECUTION_TOLERANCE_DAYS = 7;

    /** Vorwarnzeit vor dem Enddatum einer Vorlage; früher gemeldet bliebe der Befund monatelang stehen. */
    public const int PLAN_END_WARN_DAYS = 90;

    /** So weit darf das Abo-Ende hinter dem Vorlagenende liegen, ohne dass etwas fehlt. */
    private const int PLAN_END_GRACE_DAYS = 45;

    /** Vorlauf der Aufgabe „Vorlagen vor dem nächsten Lauf prüfen“ in Tagen. */
    public const int DUE_DAYS = 30;

    /** So lange nach dem Ende eines Abos gilt ein neues Abo mit anderem Artikel als Produktwechsel. */
    private const int SWITCH_DAYS = 120;

    /** Lebensdauer der Befundzähler im Cache (Sekunden). */
    private const int COUNT_TTL = 60;

    public function __construct(
        private readonly RecurringPlans $plans,
        private readonly LicenseAccounts $accounts,
        private readonly ResaleDraftRun $drafts,
        private readonly TemplateExecutions $executions,
    ) {}

    /**
     * Entwirft der Serienlauf des Registers die Rechnungen des Empfängers? Je Empfänger einmal geprüft.
     *
     * @param  array<int, bool>  $cache
     */
    private function draftedByRegister(Organization $organization, ?Customer $recipient, array &$cache): bool {
        if ($recipient === null) {
            return false;
        }

        return $cache[(int) $recipient->id] ??= $this->drafts->coversRecipient($organization, $recipient);
    }

    /**
     * Alle Befunde der Organisation; `$subscriptionIds` grenzt auf ein Konto ein
     * (Vorlagen zählen dann nur, wenn sie dessen Empfänger betreffen). Eine Seite,
     * die ihre Abos schon geladen hat (Lizenzkonto, Arbeitsmappe), reicht sie als
     * `$loaded` herein — sonst lädt das Register sie (Leistungsanalyse 2026-11-02, perfc-04).
     *
     * @param  list<int>|null  $subscriptionIds
     * @param  Collection<int, ResaleSubscription>|null  $loaded  vorgeladene Abos (mit Halter, Katalogartikel, Anbieterabrechnung)
     * @return list<Finding>
     */
    public function findings(Organization $organization, ?array $subscriptionIds = null, ?CarbonImmutable $reference = null, ?Collection $loaded = null): array {
        if (! $this->plans->availableFor($organization)) {
            return [];
        }
        $reference ??= ResalePeriod::today();
        $templates = $this->plans->templatesFor($organization)->keyBy(static fn(PlanTemplate $t): string => $t->key);
        $subscriptions = ($loaded ?? $this->accounts->subscriptions($organization))
            ->filter(static fn(ResaleSubscription $s): bool => $s->hasHolder() && ! $s->is_own_holding && $s->status !== SubscriptionStatus::Superseded)
            ->when($subscriptionIds !== null, static fn(Collection $c) => $c->whereIn('id', $subscriptionIds ?? []))
            ->values();
        // Je Konto zählen Vorlagen ohne Abo nur für Empfänger, die selbst Halter sind — Partner-Vorlagen
        // gehören zu keinem bestimmten Endkunden-Konto (wie {@see \App\Services\Reselling\Register\LicenseAccountOverview}).
        $recipientIds = $subscriptions
            ->filter(static fn(ResaleSubscription $s): bool => $s->foreign_customer_id === null && $s->status->isPlanning())
            ->map(static fn(ResaleSubscription $s): ?int => $s->billedTo()?->id)->filter()->unique()->values()->all();
        $links = ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get();
        /** @var array<int, list<ResalePlanLink>> $linksBySubscription */
        $linksBySubscription = [];
        /** @var array<string, true> $linkedLines */
        $linkedLines = [];
        /** @var array<string, int> $linkedByLine Vorlagenposition → zugeordnete Lizenzen über alle Abos */
        $linkedByLine = [];
        foreach ($links as $link) {
            $linksBySubscription[(int) $link->subscription_id][] = $link;
            $linkedLines[$link->lineKey()] = true;
            $linkedByLine[$link->lineKey()] = ($linkedByLine[$link->lineKey()] ?? 0) + (int) $link->quantity;
        }
        $nextStarts = $this->nextPeriodStarts($organization, array_values(array_map('intval', $subscriptions->pluck('id')->all())), $reference);
        $executions = $this->executions->statesFor($organization, $templates->filter(static fn(PlanTemplate $t): bool => $t->lastFailed));

        $products = $this->productsByRecipient($organization);

        $findings = [];
        /** @var array<int, bool> $drafted Empfänger → der Serienlauf des Registers entwirft seine Rechnungen */
        $drafted = [];
        foreach ($subscriptions as $subscription) {
            $own = $linksBySubscription[$subscription->id] ?? [];
            if ($own === []) {
                // Ohne Vorlage fehlt nur dann etwas, wenn auch das Register nicht entwirft (MVP-1439): bei aktivem
                // Serienlauf für die Hoheit des Empfängers entsteht die Rechnung als Übergabe bzw. lokaler Entwurf.
                // Domains im Subuser-Konto rechnet das Domainportal selbst ab (MVP-1446) — dort fehlt keine Vorlage.
                if ($subscription->status->isPlanning() && ! $subscription->isBilledByProvider() && ! $this->draftedByRegister($organization, $subscription->billedTo(), $drafted)) {
                    $findings[] = $this->finding(self::SUBSCRIPTION_WITHOUT_PLAN, $subscription, null, null);
                }

                continue;
            }
            $linked = 0;
            /** @var array<string, true> $seenTemplates Vorlagen, die für dieses Abo schon geprüft sind — ein Befund je Abo und Vorlage, nicht je Position */
            $seenTemplates = [];
            foreach ($own as $link) {
                $linked += $link->quantity;
                if (isset($seenTemplates[$link->plan_key])) {
                    continue;
                }
                $seenTemplates[$link->plan_key] = true;
                $template = $templates->get($link->plan_key);
                if ($template === null) {
                    $findings[] = $this->finding(self::PLAN_MISSING, $subscription, null, null, ['plan' => $link->plan_key]);

                    continue;
                }
                $line = $template->line($link->line_position);
                if ($template->lastFailed) {
                    $findings[] = $this->finding(self::LAST_FAILED, $subscription, $template, $line, ['error' => (string) $template->lastError, 'state' => isset($executions[$template->key]) ? $executions[$template->key]->label() : '']);
                }
                if (! $template->isActive() && $subscription->status === SubscriptionStatus::Active) {
                    $findings[] = $this->finding(self::PLAN_PAUSED, $subscription, $template, $line, ['status' => $template->status]);
                }
                if ($template->isActive() && $subscription->ends_on !== null && $template->nextExecutionOn !== null && $template->nextExecutionOn->greaterThan($subscription->ends_on)) {
                    $findings[] = $this->finding(self::PLAN_AFTER_END, $subscription, $template, $line, ['date' => $template->nextExecutionOn->format('d.m.Y'), 'end' => $subscription->ends_on->format('d.m.Y')]);
                }
                if ($template->isActive() && $template->endsOn !== null && $subscription->status->isPlanning()
                    && ! $template->endsOn->lessThan($reference) && ! $template->endsOn->greaterThan($reference->addDays(self::PLAN_END_WARN_DAYS))
                    && ($subscription->ends_on === null || $subscription->ends_on->greaterThan($template->endsOn->addDays(self::PLAN_END_GRACE_DAYS)))) {
                    $findings[] = $this->finding(self::PLAN_ENDS, $subscription, $template, $line, ['date' => $template->endsOn->format('d.m.Y')]);
                }
                $next = $nextStarts[$subscription->id] ?? null;
                if ($template->isActive() && $template->nextExecutionOn !== null && $next !== null && abs($template->nextExecutionOn->diffInDays($next)) > self::EXECUTION_TOLERANCE_DAYS) {
                    $findings[] = $this->finding(self::NEXT_EXECUTION_OFF, $subscription, $template, $line, ['next' => $template->nextExecutionOn->format('d.m.Y'), 'period' => $next->format('d.m.Y')]);
                }
            }
            foreach ($own as $link) {
                $template = $templates->get($link->plan_key);
                $line = $template?->line($link->line_position);
                $prices = $template !== null && $line !== null && $template->isActive() && $subscription->status->isPlanning() ? self::priceDeviation($subscription, $template, $line) : null;
                if ($template !== null && $line !== null && $prices !== null) {
                    $findings[] = $this->finding(self::PRICE_OFF, $subscription, $template, $line, [
                        'plan' => Money::ofFloat($prices[0], $line->currency, 2)->format(),
                        'subscription' => Money::ofFloat($prices[1], $line->currency, 2)->format(),
                    ]);
                    break; // ein Befund je Abo, nicht je Position
                }
            }
            if ($subscription->status->isPlanning() && $linked !== $subscription->quantity) {
                $findings[] = $this->finding(self::QUANTITY_MISMATCH, $subscription, null, null, ['linked' => $linked, 'quantity' => $subscription->quantity]);
            }
        }
        foreach ($templates as $template) {
            if (! $template->isActive() || ($subscriptionIds !== null && ! in_array($template->recipientCustomerId, $recipientIds, true))) {
                continue;
            }
            foreach ($template->licenceLines() as $line) {
                $lineKey = ResalePlanLink::lineKeyOf($template->key, $line->position);
                if (! isset($linkedLines[$lineKey])) {
                    $ended = $this->endedProduct($products, $template->recipientCustomerId, $line->articleKey);
                    $findings[] = $ended !== null
                        ? $this->finding(self::PLAN_ENDED_PRODUCT, null, $template, $line, $ended + ['product' => $line->label()])
                        : $this->finding(self::PLAN_WITHOUT_SUBSCRIPTION, null, $template, $line, ['quantity' => (string) round($line->licences($template->intervalMonths()), 2)]);

                    continue;
                }
                // Mehr zugeordnet, als die Position berechnet: die übrigen Abos gelten als gedeckt, werden aber nie berechnet.
                $licences = (int) round($line->licences($template->intervalMonths()));
                if (($linkedByLine[$lineKey] ?? 0) > $licences) {
                    $findings[] = $this->finding(self::LINE_OVERBOOKED, null, $template, $line, ['linked' => $linkedByLine[$lineKey], 'quantity' => $licences]);
                }
            }
        }

        return $findings;
    }

    /**
     * Befunde je Art — 60 s im Cache je Organisation (nur Zahlen), weil Dashboard-Kachel,
     * Arbeitsvorrat und Überblick sie auf jeder Seite fragen (perfc-05); der Org-Stempel
     * verwirft sie bei Zuordnung, Vorlagen-Sync und jeder Registeränderung.
     *
     * @return array<string, int> Art → Anzahl
     */
    public function countByKind(Organization $organization): array {
        $key = ResaleCacheStamp::key('plan-findings', 1, (int) $organization->id, ResalePeriod::today()->toDateString());
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            $cached = [];
            foreach ($this->findings($organization) as $finding) {
                $cached[$finding['kind']] = ($cached[$finding['kind']] ?? 0) + 1;
            }
            Cache::put($key, $cached, self::COUNT_TTL);
        }
        $counts = [];
        foreach ($cached as $kind => $count) {
            if (is_string($kind) && is_int($count)) {
                $counts[$kind] = $count;
            }
        }

        return $counts;
    }

    /**
     * Beginn der nächsten Periode je Abo (erste Periode nach dem Stichtag).
     *
     * @param  list<int>  $subscriptionIds
     * @return array<int, CarbonImmutable>
     */
    private function nextPeriodStarts(Organization $organization, array $subscriptionIds, CarbonImmutable $reference): array {
        if ($subscriptionIds === []) {
            return [];
        }
        $starts = [];
        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('subscription_id', $subscriptionIds)
            ->where('starts_on', '>=', DateRange::dayAfter($reference))
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value, PeriodStatus::Billed->value])
            ->orderBy('starts_on')
            ->get(['subscription_id', 'starts_on']);
        foreach ($periods as $period) {
            $starts[(int) $period->subscription_id] ??= $period->starts_on;
        }

        return $starts;
    }

    /**
     * @param  array<string, string|int>  $params
     * @return Finding
     */
    private function finding(string $kind, ?ResaleSubscription $subscription, ?PlanTemplate $template, ?PlanLine $line, array $params = []): array {
        return [
            'kind' => $kind, 'subscription' => $subscription, 'template' => $template, 'line' => $line, 'params' => $params,
            // Wann die Vorlage das nächste Mal berechnet und wie viel die Position dann kostet (MVP-1774).
            'due' => $template !== null && $template->isActive() ? $template->nextExecutionOn : null,
            'amount' => $line !== null ? round($line->quantity * $line->unitNet->toFloat(), 2) : null,
        ];
    }

    /**
     * Befunde an Vorlagen, die in den nächsten {@see DUE_DAYS} Tagen ausführen: was vor dem Lauf zu prüfen ist.
     *
     * @return array{count: int, amount: float, customer_id: int|null}
     */
    public function dueSoon(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $until = $reference->addDays(self::DUE_DAYS);
        $result = ['count' => 0, 'amount' => 0.0, 'customer_id' => null];
        /** @var array<string, true> $seen Vorlagenposition zählt mit ihrem Betrag nur einmal */
        $seen = [];
        foreach ($this->findings($organization, null, $reference) as $finding) {
            $due = $finding['due'];
            if ($finding['template'] === null || $due === null || $due->lessThan($reference) || $due->greaterThan($until)) {
                continue;
            }
            $result['count']++;
            $result['customer_id'] ??= $finding['template']->recipientCustomerId;
            $key = $finding['template']->key . ':' . ($finding['line']->position ?? 0);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result['amount'] += $finding['amount'] ?? 0.0;
            }
        }
        $result['amount'] = round($result['amount'], 2);

        return $result;
    }

    /**
     * Abos je Rechnungsempfänger und Artikel: läuft eines, wann endete das letzte, welche beginnen wann.
     *
     * @return array<int, array{articles: array<string, array{running: bool, end: CarbonImmutable|null}>, starts: list<array{on: CarbonImmutable, article: string, label: string}>}>
     */
    private function productsByRecipient(Organization $organization): array {
        $products = [];
        foreach ($this->accounts->subscriptions($organization) as $subscription) {
            $recipient = $subscription->billedTo()?->id;
            $article = $subscription->article_ref;
            if ($recipient === null || $article === null || $subscription->is_own_holding) {
                continue;
            }
            $products[$recipient] ??= ['articles' => [], 'starts' => []];
            $state = $products[$recipient]['articles'][$article] ?? ['running' => false, 'end' => null];
            if ($subscription->status->isPlanning()) {
                $state['running'] = true;
            } elseif ($subscription->ends_on !== null && ($state['end'] === null || $subscription->ends_on->greaterThan($state['end']))) {
                $state['end'] = $subscription->ends_on;
            }
            $products[$recipient]['articles'][$article] = $state;
            $products[$recipient]['starts'][] = ['on' => $subscription->starts_on, 'article' => $article, 'label' => $subscription->productName()];
        }

        return $products;
    }

    /**
     * Berechnet die Vorlagenposition ein Produkt, dessen Abo beim Empfänger beendet ist? Dann das Ende
     * und, bei einem Produktwechsel, das Abo, das danach begann (MVP-1775).
     *
     * @param  array<int, array{articles: array<string, array{running: bool, end: CarbonImmutable|null}>, starts: list<array{on: CarbonImmutable, article: string, label: string}>}>  $products
     * @return array{end: string, hint: string}|null
     */
    private function endedProduct(array $products, ?int $recipientId, ?string $article): ?array {
        $state = $recipientId !== null && $article !== null ? ($products[$recipientId]['articles'][$article] ?? null) : null;
        if ($recipientId === null || $state === null || $state['running'] || $state['end'] === null) {
            return null;
        }
        $end = $state['end'];
        $hint = '';
        foreach ($products[$recipientId]['starts'] as $start) {
            if ($start['article'] !== $article && ! $start['on']->lessThan($end->subDays(self::SWITCH_DAYS)) && ! $start['on']->greaterThan($end->addDays(self::SWITCH_DAYS))) {
                $hint = ' ' . __('resale.plan.finding.plan_ended_product_switch', ['product' => $start['label'], 'date' => $start['on']->format('d.m.Y')]);
                break;
            }
        }

        return ['end' => $end->format('d.m.Y'), 'hint' => $hint];
    }

    /**
     * Preis der Vorlagenposition je Lizenz und Ausführung gegen den Verkaufspreis des Abos — nur bei
     * gleichem Rhythmus und ohne Rumpflaufzeit vergleichbar.
     *
     * @return array{0: float, 1: float}|null  Vorlage, Abo — null, wenn es passt oder nicht vergleichbar ist
     */
    private static function priceDeviation(ResaleSubscription $subscription, PlanTemplate $template, PlanLine $line): ?array {
        $sale = $subscription->sale_unit_price;
        $interval = $template->intervalMonths();
        if ($sale === null || $interval !== ($subscription->interval === BillingFrequency::Yearly ? 12 : 1)) {
            return null;
        }
        if (LicenseMonths::isMonthUnit($line->unitName) && abs(fmod($line->quantity, $interval)) > 0.001) {
            return null;
        }
        $licences = $line->licences($interval);
        if ($licences <= 0.0) {
            return null;
        }
        $perLicence = round($line->quantity * $line->unitNet->toFloat() / $licences, 2);

        return LinkDeviation::amountDiffers($sale->toFloat(), $perLicence) ? [$perLicence, round($sale->toFloat(), 2)] : null;
    }
}
