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
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorVoucher};
use App\Services\Reselling\Register\LinkProposer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Vorlagenblock einer Seite (Phase 165, MVP-1444): die Serienvorlagen, die
 * zu einer Gruppe von Abos gehören — adressiert an einen ihrer Empfänger oder
 * über eine Zuordnung verbunden —, je Lizenzposition mit Zuordnungen, der
 * Ausführungsstand fehlgeschlagener Vorlagen und die Befunde des
 * Plan-Abgleichs. Lizenzkonto und Arbeitsmappe je Rechnungsempfänger teilen
 * sich Daten und Teilstück (`finance.resale._plan_section`).
 *
 * Dazu der Abrechnungslauf (Phase 183, MVP-1742): je Vorlage der Beleg der
 * letzten Ausführung und je zugeordnetem Abo die Periode dieses Laufs — die
 * Kette Abo → Vorlagenposition → Rechnung → Periode steht in einer Zeile, das
 * fehlende Glied ist markiert.
 *
 * @phpstan-type PlanRun array{state: string, period: ResalePeriod|null, voucher: string|null}
 * @phpstan-type PlanEntry array{template: PlanTemplate, lines: list<array{line: PlanLine, links: list<ResalePlanLink>}>, execution: TemplateExecution|null, runs: array<int, PlanRun>}
 * @phpstan-type PlanBlock array{available: bool, templates: list<PlanEntry>, findings: list<array<string, mixed>>, vouchers: array<string, MirrorVoucher>}
 */
final class PlanSection {
    /** Die Periode des Laufs hängt am Beleg der Ausführung. */
    public const string RUN_BILLED = 'billed';

    /** Die Periode ist anders entschieden (anderer Beleg, Verzicht, strittig). */
    public const string RUN_COVERED = 'covered';

    /** Der Beleg ist da, die Periode aber nicht gedeckt — das fehlende Glied. */
    public const string RUN_UNLINKED = 'unlinked';

    /** Kein Lizenzjahr des Abos (oder eines Vorgängers) zum Lauf. */
    public const string RUN_NO_PERIOD = 'no_period';

    /** Noch kein Beleg aus der Vorlage. */
    public const string RUN_PENDING = 'pending';

    public function __construct(
        private readonly RecurringPlans $plans,
        private readonly PlanAudit $audit,
        private readonly TemplateExecutions $executions,
        private readonly InvoiceMirror $mirror,
        private readonly PlanAnchors $anchors,
    ) {}

    /**
     * @param  Collection<int, ResaleSubscription>  $subscriptions  Abos der Seite
     * @param  list<int>  $recipientIds  Empfänger, deren Vorlagen auch ohne Zuordnung gezeigt werden
     * @return PlanBlock
     */
    public function build(Organization $organization, Collection $subscriptions, array $recipientIds): array {
        if (! $this->plans->availableFor($organization)) {
            return ['available' => false, 'templates' => [], 'findings' => [], 'vouchers' => []];
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
            $templates[] = ['template' => $template, 'lines' => $lines, 'execution' => null, 'runs' => []];
            $shown[] = $template;
        }
        // Stand der letzten Ausführung aus dem Spiegel (MVP-1441) — für alle Vorlagen, er trägt den Abrechnungslauf.
        $executions = $this->executions->statesFor($organization, $shown);
        $shownLinks = [];
        foreach ($templates as &$entry) {
            $entry['execution'] = $executions[$entry['template']->key] ?? null;
            foreach ($entry['lines'] as $row) {
                foreach ($row['links'] as $link) {
                    $shownLinks[] = $link;
                }
            }
        }
        unset($entry);
        $periods = $this->chainPeriods($organization, $shownLinks);
        foreach ($templates as &$entry) {
            foreach ($entry['lines'] as $row) {
                foreach ($row['links'] as $link) {
                    $entry['runs'][(int) $link->id] = self::run($entry['execution'], $periods[(int) $link->subscription_id] ?? []);
                }
            }
        }
        unset($entry);

        return ['available' => true, 'templates' => $templates, 'findings' => $this->audit->findings($organization, $subscriptionIds, null, $subscriptions), 'vouchers' => $this->noteVouchers($organization, $links, $shown, $recipientIds)];
    }

    /**
     * Perioden der Kette (Abo und Vorgänger) je zugeordnetem Abo, mit Bezügen.
     *
     * @param  list<ResalePlanLink>  $links
     * @return array<int, list<ResalePeriod>>
     */
    private function chainPeriods(Organization $organization, array $links): array {
        $chains = $this->anchors->chains($organization, array_values(array_unique(array_map(static fn(ResalePlanLink $l): int => (int) $l->subscription_id, $links))));
        if ($chains === []) {
            return [];
        }
        $byChainMember = ResalePeriod::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('subscription_id', array_values(array_unique(array_merge(...array_values($chains)))))
            ->with('links')
            ->orderBy('starts_on')
            ->get()
            ->groupBy('subscription_id');
        $periods = [];
        foreach ($chains as $subscriptionId => $chain) {
            $periods[$subscriptionId] = [];
            foreach ($chain as $member) {
                foreach ($byChainMember->get($member, []) as $period) {
                    $periods[$subscriptionId][] = $period;
                }
            }
        }

        return $periods;
    }

    /**
     * Abrechnungslauf einer Zuordnung: die Periode, in deren Laufzeit der Beleg der letzten
     * Ausführung liegt oder die höchstens {@see LinkProposer::WINDOW_BEFORE} Tage danach beginnt
     * (dieselbe Regel wie der Vorlagenanker), und ob sie an diesem Beleg hängt.
     *
     * @param  list<ResalePeriod>  $periods
     * @return PlanRun
     */
    private static function run(?TemplateExecution $execution, array $periods): array {
        $date = $execution?->voucherDate;
        if ($execution === null || $date === null) {
            return ['state' => self::RUN_PENDING, 'period' => null, 'voucher' => null];
        }
        $best = null;
        $bestDistance = null;
        foreach ($periods as $period) {
            if ($date->lessThan($period->starts_on->subDays(LinkProposer::WINDOW_BEFORE)) || $date->greaterThan($period->ends_on)) {
                continue;
            }
            $distance = (int) abs($date->diffInDays($period->starts_on));
            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $period;
                $bestDistance = $distance;
            }
        }
        if ($best === null) {
            return ['state' => self::RUN_NO_PERIOD, 'period' => null, 'voucher' => $execution->voucherNumber];
        }
        $linked = $execution->voucherNumber !== null && $best->links->contains(static fn(ResalePeriodLink $l): bool => $l->voucher_number === $execution->voucherNumber && (float) $l->months > 0.0);
        $state = match (true) {
            $linked => self::RUN_BILLED,
            in_array($best->status, ResalePeriod::dueStatuses(), true) => self::RUN_UNLINKED,
            default => self::RUN_COVERED,
        };

        return ['state' => $state, 'period' => $best, 'voucher' => $execution->voucherNumber];
    }

    /**
     * Belege hinter den Bezugsnotizen (Pilot 2026-11-01): die Nummer am Bezug öffnet den Belegdialog.
     * Gesucht wird bei den Empfängern der Seite und der Vorlagen, so weit zurück, wie der Vorschlag
     * beim ältesten Bezug sah.
     *
     * @param  Collection<int, ResalePlanLink>  $links
     * @param  list<PlanTemplate>  $templates
     * @param  list<int>  $recipientIds
     * @return array<string, MirrorVoucher> Belegnummer → Beleg
     */
    private function noteVouchers(Organization $organization, Collection $links, array $templates, array $recipientIds): array {
        $noted = $links->filter(static fn(ResalePlanLink $link): bool => (string) $link->note !== '');
        if ($noted->isEmpty()) {
            return [];
        }
        $numbers = array_values(array_unique(array_map(static fn(ResalePlanLink $link): string => (string) $link->note, $noted->all())));
        $oldest = $noted->min('created_at');
        $from = ($oldest !== null ? CarbonImmutable::parse($oldest) : ResalePeriod::today())->subMonths(PlanLinkProposer::INVOICE_LOOKBACK_MONTHS);
        $recipients = $recipientIds;
        foreach ($templates as $template) {
            if ($template->recipientCustomerId !== null) {
                $recipients[] = $template->recipientCustomerId;
            }
        }
        $vouchers = [];
        foreach ($this->mirror->vouchersFor($organization, array_values(array_unique($recipients)), $from, 500) as $voucher) {
            if ($voucher->voucherNumber !== null && in_array($voucher->voucherNumber, $numbers, true)) {
                $vouchers[$voucher->voucherNumber] ??= $voucher;
            }
        }

        return $vouchers;
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
            if ($subscription->hasHolder() && ! $subscription->is_own_holding && $subscription->status->isPlanning() && ! $subscription->isBilledByProvider()) {
                $candidates[] = $subscription;
            }
        }

        return $candidates;
    }
}
