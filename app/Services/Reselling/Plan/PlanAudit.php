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

use App\Enums\Reselling\{PeriodStatus, SubscriptionStatus};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Register\{LicenseAccounts, ResaleDraftRun};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prüfregeln Bestand ↔ Plan (Feature 185, MVP-1412): vor der Rechnung
 * sichtbar, was die Serienvorlagen des Buchhaltungsprogramms nicht abbilden —
 * Abo ohne Vorlage, Vorlage ohne Abo, abweichende Menge, Vorlage läuft nach
 * dem Ende weiter, pausiert oder beendet bei laufendem Abo, nächste Ausführung
 * neben dem Periodenbeginn, letzte Ausführung fehlgeschlagen.
 *
 * @phpstan-type Finding array{kind: string, subscription: ResaleSubscription|null, template: PlanTemplate|null, line: PlanLine|null, params: array<string, string|int>}
 */
final class PlanAudit {
    public const SUBSCRIPTION_WITHOUT_PLAN = 'subscription_without_plan';

    public const PLAN_WITHOUT_SUBSCRIPTION = 'plan_without_subscription';

    public const QUANTITY_MISMATCH = 'quantity_mismatch';

    public const PLAN_AFTER_END = 'plan_after_end';

    public const PLAN_PAUSED = 'plan_paused';

    public const PLAN_MISSING = 'plan_missing';

    public const NEXT_EXECUTION_OFF = 'next_execution_off';

    public const LAST_FAILED = 'last_failed';

    public const KINDS = [
        self::SUBSCRIPTION_WITHOUT_PLAN, self::PLAN_WITHOUT_SUBSCRIPTION, self::QUANTITY_MISMATCH, self::PLAN_AFTER_END,
        self::PLAN_PAUSED, self::PLAN_MISSING, self::NEXT_EXECUTION_OFF, self::LAST_FAILED,
    ];

    /** Toleranz zwischen nächster Ausführung der Vorlage und Periodenbeginn. */
    private const EXECUTION_TOLERANCE_DAYS = 7;

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
     * (Vorlagen zählen dann nur, wenn sie dessen Empfänger betreffen).
     *
     * @param  list<int>|null  $subscriptionIds
     * @return list<Finding>
     */
    public function findings(Organization $organization, ?array $subscriptionIds = null, ?CarbonImmutable $reference = null): array {
        if (! $this->plans->availableFor($organization)) {
            return [];
        }
        $reference ??= ResalePeriod::today();
        $templates = $this->plans->templatesFor($organization)->keyBy(static fn(PlanTemplate $t): string => $t->key);
        $subscriptions = $this->accounts->subscriptions($organization)
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
        foreach ($links as $link) {
            $linksBySubscription[(int) $link->subscription_id][] = $link;
            $linkedLines[$link->lineKey()] = true;
        }
        $nextStarts = $this->nextPeriodStarts($organization, array_values(array_map('intval', $subscriptions->pluck('id')->all())), $reference);
        $executions = $this->executions->statesFor($organization, $templates->filter(static fn(PlanTemplate $t): bool => $t->lastFailed));

        $findings = [];
        /** @var array<int, bool> $drafted Empfänger → der Serienlauf des Registers entwirft seine Rechnungen */
        $drafted = [];
        foreach ($subscriptions as $subscription) {
            $own = $linksBySubscription[$subscription->id] ?? [];
            if ($own === []) {
                // Ohne Vorlage fehlt nur dann etwas, wenn auch das Register nicht entwirft (MVP-1439): bei aktivem
                // Serienlauf für die Hoheit des Empfängers entsteht die Rechnung als Übergabe bzw. lokaler Entwurf.
                if ($subscription->status->isPlanning() && ! $this->draftedByRegister($organization, $subscription->billedTo(), $drafted)) {
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
                $next = $nextStarts[$subscription->id] ?? null;
                if ($template->isActive() && $template->nextExecutionOn !== null && $next !== null && abs($template->nextExecutionOn->diffInDays($next)) > self::EXECUTION_TOLERANCE_DAYS) {
                    $findings[] = $this->finding(self::NEXT_EXECUTION_OFF, $subscription, $template, $line, ['next' => $template->nextExecutionOn->format('d.m.Y'), 'period' => $next->format('d.m.Y')]);
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
                if (! isset($linkedLines[ResalePlanLink::lineKeyOf($template->key, $line->position)])) {
                    $findings[] = $this->finding(self::PLAN_WITHOUT_SUBSCRIPTION, null, $template, $line, ['quantity' => (string) round($line->licences($template->intervalMonths()), 2)]);
                }
            }
        }

        return $findings;
    }

    /** @return array<string, int> Art → Anzahl */
    public function countByKind(Organization $organization): array {
        $counts = [];
        foreach ($this->findings($organization) as $finding) {
            $counts[$finding['kind']] = ($counts[$finding['kind']] ?? 0) + 1;
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
        return ['kind' => $kind, 'subscription' => $subscription, 'template' => $template, 'line' => $line, 'params' => $params];
    }
}
