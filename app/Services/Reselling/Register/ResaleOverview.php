<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleOverview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{ImportStatus, LinkOrigin, PeriodStatus, SubscriptionProvider};
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResaleImport, ResalePeriod, ResaleSubscription};
use App\Services\Reselling\License\LicenseInvoiceLinks;
use App\Services\Reselling\Mirror\InvoiceMirror;
use App\Services\Reselling\Plan\{PlanAudit, RecurringPlans};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Überblick des Reselling-Registers als Aufgabenliste (Feature 185, MVP-1414):
 * was wartet — fällige Perioden, unbestätigte Vorschläge, Prüffälle an
 * Bezügen, Serienvorlagen und Aufteilungen, Abos ohne Halter, Verlängerungen —
 * dazu der Stand je Anbieter (letzter Import) und der letzte Vorschlagslauf.
 * Nur Zähler und Verweise; entschieden wird auf den Fachseiten.
 *
 * @phpstan-type Task array{group: string, key: string, tone: string, icon: string, title: string, detail: string|null, count: int, url: string, dialog: bool, manage: bool}
 * @phpstan-type ImportState array{provider: SubscriptionProvider, last: ResaleImport|null, age: int|null, stale: bool}
 */
final class ResaleOverview {
    /** Ab diesem Alter gilt der Anbieterstand als veraltet (Übersicht und Digest). */
    public const IMPORT_STALE_DAYS = 30;

    public const RENEWAL_DAYS = 30;

    /** Karenz (Phase 163, MVP-1430): eine fällige Periode wird erst nach so vielen Tagen zur Aufgabe. */
    public const GRACE_DAYS = 21;

    /** Toleranz vor dem Periodenbeginn, in der die nächste Ausführung der Serienvorlage noch „für diese Periode“ zählt. */
    public const PLAN_TOLERANCE_DAYS = 7;

    /** Aufgabengruppen des Überblicks in Anzeigereihenfolge (Phase 163, MVP-1433). */
    public const GROUPS = ['berechnen', 'zuordnen', 'plan', 'anbieter'];

    public function __construct(
        private readonly LicenseAccountOverview $accounts,
        private readonly PlanAudit $planAudit,
        private readonly RecurringPlans $plans,
        private readonly ResaleRenewalReport $renewals,
        private readonly LicenseInvoiceLinks $licenseLinks,
        private readonly InvoiceMirror $mirror,
    ) {}

    /**
     * @return array{summary: array{active: int, open: int, open_amount: string, grace: int, planned: int, proposed: int, unassigned: int, unlinked: int, findings: int}, groups: list<string>, tasks: list<Task>, imports: list<ImportState>, last_run: CarbonImmutable|null}
     */
    public function build(Organization $organization, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $due = ResalePeriod::query()->where('starts_on', '<', DateRange::dayAfter($reference))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
        $openAll = (clone $due)->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])->with('links')->orderBy('starts_on')->get();
        // Ausnahmen statt Bestätigungen (Phase 163): Aufgabe erst nach der Karenz und nicht, solange eine
        // aktive Serienvorlage ihre Ausführung für diese Periode noch vor sich hat.
        $nextExecutions = $this->plans->nextExecutions($organization);
        $graceLimit = $reference->subDays(self::GRACE_DAYS);
        $open = collect();
        $grace = 0;
        $planned = 0;
        foreach ($openAll as $period) {
            if (self::isPlanned($period, $nextExecutions[(int) $period->subscription_id] ?? null)) {
                $planned++;

                continue;
            }
            if ($period->starts_on->greaterThan($graceLimit)) {
                $grace++;

                continue;
            }
            $open->push($period);
        }
        /** @var array<string, Money> $amounts */
        $amounts = [];
        foreach ($open as $period) {
            $amount = $period->openAmount();
            if ($amount === null) {
                continue;
            }
            $code = $amount->getCurrency()->value;
            $amounts[$code] = isset($amounts[$code]) ? $amounts[$code]->plus($amount) : $amount;
        }
        $openAmount = implode(' · ', array_map(static fn(Money $m): string => $m->withScale(2)->format(), array_values($amounts)));
        // Vorschläge gelten (Phase 163, MVP-1429): unbestätigte sind Stichprobe, keine Aufgabe.
        $proposed = (clone $due)
            ->whereIn('status', [PeriodStatus::Billed->value, PeriodStatus::Partial->value])
            ->whereNull('decided_at')
            ->whereHas('links', static fn(Builder $l) => $l->where('origin', LinkOrigin::Proposed->value))
            ->count();
        $unassigned = ResaleSubscription::query()->planning()->unassigned()->count();
        $unlinked = $this->mirror->unlinkedLines($organization, 1)['total'];

        $accountRows = $this->accounts->overview($organization, $reference);
        $allocationConflicts = (int) array_sum(array_column($accountRows, 'conflicts'));
        $linkFindings = $this->linkFindings($organization);
        $planFindings = $this->plans->availableFor($organization) ? $this->planAudit->countByKind($organization) : [];
        $renewals = (int) ($this->renewals->build($reference, $reference->addDays(self::RENEWAL_DAYS), $reference)['buckets'][self::RENEWAL_DAYS] ?? 0);
        $imports = $this->imports($organization, $reference);
        $stale = array_values(array_filter($imports, static fn(array $i): bool => $i['stale']));

        $tasks = [];
        // Berechnen — was Geld kostet
        // Fällige Perioden führen in die Arbeitsmappe des nächsten offenen Empfängers (Phase 165, MVP-1445), nicht in die Liste.
        $this->task($tasks, 'berechnen', 'open', 'error', 'event_busy', $open->count(), (string) __($openAmount !== '' ? 'resale.overview.task.open_detail' : 'resale.overview.task.open_detail_grace', ['amount' => $openAmount, 'days' => self::GRACE_DAYS]), route('finance.resale.reconcile.next'));
        $this->task($tasks, 'berechnen', 'link_findings', 'warning', 'link_off', (int) array_sum($linkFindings), $this->kindDetail($linkFindings, 'resale.account.finding.'), route('finance.resale.accounts.index'));
        $this->task($tasks, 'berechnen', 'allocation_conflicts', 'warning', 'call_split', $allocationConflicts, null, route('finance.resale.accounts.index'));
        $this->task($tasks, 'berechnen', 'unbilled_licenses', 'warning', 'key_off', $this->licenseLinks->unbilledCount($organization, $reference), (string) __('resale.overview.task.unbilled_licenses_detail', ['days' => LicenseInvoiceLinks::UNBILLED_DAYS]), route('finance.resale.licenses.index', ['status' => 'sold', 'unbilled' => 1]));
        // Zuordnen — was das Register noch nicht erklären kann
        $this->task($tasks, 'zuordnen', 'unassigned', 'warning', 'inbox', $unassigned, null, route('finance.resale.inbox'), manage: true);
        $this->task($tasks, 'zuordnen', 'unlinked', 'warning', 'playlist_add', $unlinked, (string) __('resale.overview.task.unlinked_detail'), route('finance.resale.periods.index', ['unlinked' => 1]), manage: true);
        // Plan prüfen — was die nächste Rechnung falsch macht
        $this->task($tasks, 'plan', 'plan_findings', 'warning', 'event_repeat', (int) array_sum($planFindings), $this->kindDetail($planFindings, 'resale.plan.finding.', '_short'), route('finance.resale.accounts.index'));
        // Anbieterstand und Termine
        $this->task($tasks, 'anbieter', 'stale_imports', 'neutral', 'upload', count($stale), $stale === [] ? null : implode(' · ', array_map(static fn(array $i): string => $i['provider']->label() . ' (' . __('resale.overview.state.age', ['days' => $i['age']]) . ')', $stale)), route('finance.resale.import.create'), dialog: true, manage: true);
        $this->task($tasks, 'anbieter', 'renewals', 'neutral', 'autorenew', $renewals, (string) __('resale.overview.task.renewals_detail', ['days' => self::RENEWAL_DAYS]), route('finance.resale.report.renewals'));

        return [
            'summary' => [
                'active' => ResaleSubscription::query()->planning()->count(),
                'open' => $open->count(),
                'open_amount' => $openAmount,
                'grace' => $grace,
                'planned' => $planned,
                'proposed' => $proposed,
                'unassigned' => $unassigned,
                'unlinked' => $unlinked,
                'findings' => (int) array_sum($linkFindings) + (int) array_sum($planFindings) + $allocationConflicts,
            ],
            'groups' => self::GROUPS,
            'tasks' => $tasks,
            'imports' => $imports,
            'last_run' => LinkProposer::lastRunAt($organization),
        ];
    }

    /** Periode, deren Serienvorlage ihre Ausführung für sie noch vor sich hat (Toleranz vor dem Beginn): geplant, nicht offen. */
    public static function isPlanned(ResalePeriod $period, ?CarbonImmutable $nextExecution): bool {
        return $nextExecution !== null
            && ! $nextExecution->lessThan($period->starts_on->subDays(self::PLAN_TOLERANCE_DAYS))
            && ! $nextExecution->greaterThan($period->ends_on);
    }

    /**
     * Anbieter, deren letzter Import älter als {@see IMPORT_STALE_DAYS} Tage ist.
     * Ohne jeden Import zählt ein Anbieter nicht — Bestand von Hand hat keinen Export.
     *
     * @return list<SubscriptionProvider>
     */
    public function staleProviders(Organization $organization, ?CarbonImmutable $reference = null): array {
        return array_values(array_map(
            static fn(array $i): SubscriptionProvider => $i['provider'],
            array_filter($this->imports($organization, $reference ?? ResalePeriod::today()), static fn(array $i): bool => $i['stale']),
        ));
    }

    /**
     * Stand je Anbieter mit Datei-Import: der letzte gelungene Lauf (ohne Preisliste) und sein Alter.
     *
     * @return list<ImportState>
     */
    private function imports(Organization $organization, CarbonImmutable $reference): array {
        $rows = [];
        $latest = ResaleImport::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', ImportStatus::Done->value)
            ->where('kind', '<>', ResaleImport::KIND_PRICELIST)
            ->orderByDesc('id')
            ->get()
            ->unique(static fn(ResaleImport $i): string => $i->provider->value)
            ->keyBy(static fn(ResaleImport $i): string => $i->provider->value);
        $providers = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->planning()
            ->whereNotIn('provider', [SubscriptionProvider::DomainReselling->value, SubscriptionProvider::Manual->value])
            ->distinct()
            ->pluck('provider')
            ->map(static fn($p): string => $p instanceof SubscriptionProvider ? $p->value : (string) $p)
            ->merge($latest->keys())
            ->unique()
            ->values();
        foreach ($providers as $value) {
            $provider = SubscriptionProvider::tryFrom($value);
            if ($provider === null) {
                continue;
            }
            /** @var ResaleImport|null $last */
            $last = $latest->get($value);
            $age = $last === null ? null : (int) $last->created_at->startOfDay()->diffInDays($reference->startOfDay());
            $rows[] = ['provider' => $provider, 'last' => $last, 'age' => $age, 'stale' => $age !== null && $age > self::IMPORT_STALE_DAYS];
        }
        usort($rows, static fn(array $a, array $b): int => ($b['stale'] <=> $a['stale']) ?: strcmp($a['provider']->label(), $b['provider']->label()));

        return $rows;
    }

    /**
     * Prüffälle an Bezügen (überbucht, zeitlich unpassend) über alle planbaren Abos.
     *
     * @return array<string, int>
     */
    private function linkFindings(Organization $organization): array {
        $ids = ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->replannable()->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        $counts = [];
        foreach ((new LinkAudit)->forSubscriptions($organization, LinkAudit::loadedWithLinks($organization, $ids)) as $finding) {
            $counts[$finding['kind']] = ($counts[$finding['kind']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<Task>  $tasks
     */
    private function task(array &$tasks, string $group, string $key, string $tone, string $icon, int $count, ?string $detail, string $url, bool $dialog = false, bool $manage = false): void {
        if ($count <= 0) {
            return;
        }
        $tasks[] = [
            'group' => $group,
            'key' => $key,
            'tone' => $tone,
            'icon' => $icon,
            'title' => (string) __('resale.overview.task.' . $key),
            'detail' => $detail !== null && $detail !== '' ? $detail : null,
            'count' => $count,
            'url' => $url,
            'dialog' => $dialog,
            'manage' => $manage,
        ];
    }

    /** @param  array<string, int>  $counts */
    private function kindDetail(array $counts, string $prefix, string $suffix = ''): ?string {
        if ($counts === []) {
            return null;
        }
        arsort($counts);

        return implode(' · ', array_map(static fn(string $kind, int $n): string => $n . ' × ' . __($prefix . $kind . $suffix), array_keys($counts), $counts));
    }
}
