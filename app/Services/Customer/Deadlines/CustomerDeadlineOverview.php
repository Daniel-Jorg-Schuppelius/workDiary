<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineOverview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Enums\Customer\CustomerDeadlineStatus;
use App\Models\Customer\CustomerDeadline;
use App\Models\Platform\{Organization, User};
use App\Services\Delegation\ActsForResolver;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Übersicht aller Kundenfristen (Feature 197, MVP-1633): Filter nach
 * Regelsatz, Fristart, zuständiger Person („Meine“ schließt die heute
 * Vertretenen ein), Fälligkeitsmonat, Status und Kunde; Zählkacheln und
 * Bezeichnungen. Liste, Export und Cockpit lesen dieselbe Abfrage.
 *
 * @phpstan-type Filters array{rule_set: string|null, kind: string|null, responsible: string|null, responsible_id: int|null, month: string|null, status: string, customer_id: int|null}
 */
final class CustomerDeadlineOverview {
    /** Statusfilter: offen (offen + verlängert), überfällig, in der Vorwarnzeit, je Status, alle. */
    public const array STATUS_FILTERS = ['open', 'overdue', 'due_soon', 'completed', 'extended', 'waived', 'all'];

    public function __construct(
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly ActsForResolver $actsFor,
    ) {}

    /**
     * @param  Filters  $filters
     * @return Builder<CustomerDeadline>
     */
    public function query(Organization $organization, User $viewer, array $filters, ?CarbonImmutable $today = null): Builder {
        $today ??= CarbonImmutable::today();
        $query = CustomerDeadline::query()->where('customer_deadlines.organization_id', $organization->id);

        if ($filters['rule_set'] !== null) {
            $query->where('rule_set', $filters['rule_set']);
        }
        if ($filters['kind'] !== null) {
            // Jahreserklärungen tragen die Steuerart hinter dem Doppelpunkt.
            $query->where(static fn (Builder $q) => $q->where('kind', $filters['kind'])->orWhereLikeEscaped('kind', $filters['kind'] . ':', 'prefix'));
        }
        if ($filters['responsible'] === 'mine') {
            $query->whereIn('responsible_user_id', $this->actsFor->assigneeIds($viewer));
        } elseif ($filters['responsible'] === 'none') {
            $query->whereNull('responsible_user_id');
        } elseif ($filters['responsible_id'] !== null) {
            $query->where('responsible_user_id', $filters['responsible_id']);
        }
        if ($filters['month'] !== null && preg_match('/^\d{4}-\d{2}$/', $filters['month']) === 1) {
            $start = CarbonImmutable::parse($filters['month'] . '-01');
            $query->whereBetween('due_on', DateRange::days($start, $start->endOfMonth()));
        }
        if ($filters['customer_id'] !== null) {
            $query->where('customer_id', $filters['customer_id']);
        }

        match ($filters['status']) {
            'overdue' => $query->open()->where('due_on', '<', DateRange::day($today)),
            'due_soon' => $this->whereDueSoon($query, $organization, $today),
            'completed' => $query->where('status', CustomerDeadlineStatus::Completed->value),
            'extended' => $query->where('status', CustomerDeadlineStatus::Extended->value),
            'waived' => $query->where('status', CustomerDeadlineStatus::Waived->value),
            'all' => null,
            default => $query->open(),
        };

        return $query;
    }

    /**
     * Zählkacheln: überfällig, in der Vorwarnzeit fällig, offen — innerhalb der
     * übrigen Filter, unabhängig vom Statusfilter.
     *
     * @param  Filters  $filters
     * @return array{overdue: int, due_soon: int, open: int}
     */
    public function counts(Organization $organization, User $viewer, array $filters, ?CarbonImmutable $today = null): array {
        $today ??= CarbonImmutable::today();
        $base = ['status' => 'open'] + $filters;
        $open = $this->query($organization, $viewer, $base, $today)->get(['id', 'rule_set', 'due_on']);
        $overdue = 0;
        $soon = 0;
        foreach ($open as $deadline) {
            $due = $deadline->due_on->toDateString();
            if ($due < $today->toDateString()) {
                $overdue++;
            } elseif ($due <= $today->addDays($this->ruleSets->warnDays($organization, $deadline->rule_set))->toDateString()) {
                $soon++;
            }
        }

        return ['overdue' => $overdue, 'due_soon' => $soon, 'open' => $open->count()];
    }

    /**
     * Offen und in der Vorwarnzeit des eigenen Regelsatzes fällig (ab heute).
     *
     * @param  Builder<CustomerDeadline>  $query
     */
    private function whereDueSoon(Builder $query, Organization $organization, CarbonImmutable $today): void {
        $limits = [];
        foreach (array_keys($this->ruleSets->available($organization)) as $key) {
            $limits[$key] = DateRange::dayAfter($today->addDays($this->ruleSets->warnDays($organization, $key)));
        }
        $fallback = DateRange::dayAfter($today->addDays($this->ruleSets->defaultWarnDays($organization)));
        $query->open()
            ->where('due_on', '>=', DateRange::day($today))
            ->where(static function (Builder $outer) use ($limits, $fallback): void {
                foreach ($limits as $key => $limit) {
                    $outer->orWhere(static fn (Builder $q) => $q->where('rule_set', $key)->where('due_on', '<', $limit));
                }
                $outer->orWhere(static fn (Builder $q) => $q->whereNotIn('rule_set', array_keys($limits))->where('due_on', '<', $fallback));
            });
    }

    /** Bezeichnung der Fristart über den Regelsatz; ohne aktives Modul der Schlüssel. */
    public function kindLabel(Organization $organization, CustomerDeadline $deadline): string {
        return $this->ruleSets->get($organization, $deadline->rule_set)?->kindLabel($deadline->kind) ?? $deadline->kind;
    }

    public function ruleSetLabel(Organization $organization, string $ruleSet): string {
        return $this->ruleSets->get($organization, $ruleSet)?->label() ?? $ruleSet;
    }
}
