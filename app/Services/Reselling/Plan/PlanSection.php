<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};
use Illuminate\Support\Collection;

/**
 * Vorlagenblock einer Seite (Phase 165, MVP-1444): die Serienvorlagen, die
 * zu einer Gruppe von Abos gehören — adressiert an einen ihrer Empfänger oder
 * über eine Zuordnung verbunden —, je Lizenzposition mit Zuordnungen, der
 * Ausführungsstand fehlgeschlagener Vorlagen und die Befunde des
 * Plan-Abgleichs. Lizenzkonto und Arbeitsmappe je Rechnungsempfänger teilen
 * sich Daten und Teilstück (`finance.resale._plan_section`).
 *
 * @phpstan-type PlanEntry array{template: PlanTemplate, lines: list<array{line: PlanLine, links: list<ResalePlanLink>}>, execution: TemplateExecution|null}
 * @phpstan-type PlanBlock array{available: bool, templates: list<PlanEntry>, findings: list<array<string, mixed>>}
 */
final class PlanSection {
    public function __construct(
        private readonly RecurringPlans $plans,
        private readonly PlanAudit $audit,
        private readonly TemplateExecutions $executions,
    ) {}

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions  Abos der Seite
     * @param  list<int>  $recipientIds  Empfänger, deren Vorlagen auch ohne Zuordnung gezeigt werden
     * @return PlanBlock
     */
    public function build(Organization $organization, Collection $subscriptions, array $recipientIds): array {
        if (! $this->plans->availableFor($organization)) {
            return ['available' => false, 'templates' => [], 'findings' => []];
        }
        $subscriptionIds = array_values(array_map('intval', $subscriptions->pluck('id')->all()));
        $links = ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('subscription_id', $subscriptionIds)
            ->with('subscription.customer', 'subscription.foreignCustomer')->get();
        $linkedKeys = $links->pluck('plan_key')->unique()->all();
        /** @var array<string, list<ResalePlanLink>> $linksByLine */
        $linksByLine = [];
        foreach ($links as $link) {
            $linksByLine[$link->lineKey()][] = $link;
        }
        /** @var list<PlanEntry> $templates */
        $templates = [];
        /** @var list<PlanTemplate> $shown */
        $shown = [];
        foreach ($this->plans->templatesFor($organization) as $template) {
            if (! in_array($template->recipientCustomerId, $recipientIds, true) && ! in_array($template->key, $linkedKeys, true)) {
                continue;
            }
            $lines = [];
            foreach ($template->licenceLines() as $line) {
                $lines[] = ['line' => $line, 'links' => $linksByLine[ResalePlanLink::lineKeyOf($template->key, $line->position)] ?? []];
            }
            $templates[] = ['template' => $template, 'lines' => $lines, 'execution' => null];
            $shown[] = $template;
        }
        // Fehlgeschlagene Ausführung: ob die Rechnung dabei entstand, sagt der Spiegel (MVP-1441).
        $executions = $this->executions->statesFor($organization, array_values(array_filter($shown, static fn(PlanTemplate $t): bool => $t->lastFailed)));
        foreach ($templates as &$entry) {
            $entry['execution'] = $executions[$entry['template']->key] ?? null;
        }
        unset($entry);

        return ['available' => true, 'templates' => $templates, 'findings' => $this->audit->findings($organization, $subscriptionIds)];
    }

    /**
     * Abos, denen eine Vorlagenposition zugeordnet werden kann: mit Halter, fremd, planbar.
     *
     * @param  iterable<ResaleSubscription>  $subscriptions
     * @return list<ResaleSubscription>
     */
    public static function candidates(iterable $subscriptions): array {
        $candidates = [];
        foreach ($subscriptions as $subscription) {
            if ($subscription->hasHolder() && ! $subscription->is_own_holding && $subscription->status->isPlanning()) {
                $candidates[] = $subscription;
            }
        }

        return $candidates;
    }
}
