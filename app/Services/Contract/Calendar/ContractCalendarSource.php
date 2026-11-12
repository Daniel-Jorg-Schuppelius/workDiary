<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractCalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Calendar;

use App\Enums\Contract\{ContractObligationStatus, ContractStatus, ContractTermKind};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Platform\User;
use App\Services\Calendar\Contracts\CalendarEventSource;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Query\DateRange;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Vertragsfristen im Kalender (MVP-1617, H2-03): offene und versäumte Pflichten
 * (darunter die Kündigungsfrist), Laufzeitende bzw. Verlängerung befristeter
 * Verträge und die Indexprüfung. Im eigenen Kalender die, für die man
 * verantwortlich ist, in der Teamansicht alle, die man sehen darf.
 */
final class ContractCalendarSource implements CalendarEventSource {
    private const string COLOR = '#b45309';

    /** Obergrenze der Verlängerungsperioden je Vertrag im Zeitraum. */
    private const int MAX_PERIODS = 60;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function events(CarbonInterface $start, CarbonInterface $end, User $user, bool $teamScope, ?int $filterUserId): array {
        if (! $this->features->isEnabled('module.contracts') || ! $user->can('viewAny', Contract::class)) {
            return [];
        }
        $gate = Gate::forUser($user);
        $from = Carbon::instance($start)->startOfDay();
        $to = Carbon::instance($end)->endOfDay();
        $responsible = $teamScope ? $filterUserId : (int) $user->id;

        $events = [];
        $obligations = ContractObligation::query()
            ->whereIn('status', [ContractObligationStatus::Open->value, ContractObligationStatus::Missed->value])
            ->whereBetween('due_on', DateRange::days($from, $to))
            ->whereHas('contract', fn (Builder $q) => $q->open())
            ->when($responsible !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('responsible_user_id', $responsible)
                ->orWhere(fn (Builder $c) => $c->whereNull('responsible_user_id')->whereHas('contract', fn (Builder $k) => $k->where('responsible_user_id', $responsible)))))
            ->with('contract')
            ->get();
        foreach ($obligations as $obligation) {
            $contract = $obligation->contract;
            if ($contract === null || ! $gate->allows('view', $contract)) {
                continue;
            }
            $events[] = $this->event('contract-obligation-' . $obligation->id, $obligation->title . ' · ' . $contract->title, $obligation->due_on, $contract);
        }

        $contracts = Contract::query()
            ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Terminated->value])
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $f) => $f->where('term_kind', ContractTermKind::Fixed->value)->whereNotNull('ends_on')->where('ends_on', '<', DateRange::dayAfter($to)))
                ->orWhereBetween('indexation_review_on', DateRange::days($from, $to)))
            ->when($responsible !== null, fn (Builder $q) => $q->where('responsible_user_id', $responsible))
            ->get();
        foreach ($contracts as $contract) {
            if (! $gate->allows('view', $contract)) {
                continue;
            }
            foreach ($this->periodEnds($contract, $from, $to) as $i => [$date, $renews]) {
                $label = (string) ($renews ? __('contract.calendar.renewal') : __('contract.calendar.end'));
                $events[] = $this->event('contract-end-' . $contract->id . '-' . $i, $label . ' · ' . $contract->title, $date, $contract);
            }
            if ($contract->indexation_review_on !== null && $contract->indexation_review_on->betweenIncluded($from, $to)) {
                $events[] = $this->event('contract-indexation-' . $contract->id, (string) __('contract.calendar.indexation_review') . ' · ' . $contract->title, $contract->indexation_review_on, $contract);
            }
        }

        return $events;
    }

    /**
     * Laufzeitenden im Zeitraum; ein gekündigter Vertrag verlängert sich nicht mehr.
     *
     * @return list<array{0: Carbon, 1: bool}> Datum, Verlängerung statt Ende
     */
    private function periodEnds(Contract $contract, Carbon $from, Carbon $to): array {
        if ($contract->term_kind !== ContractTermKind::Fixed || $contract->ends_on === null) {
            return [];
        }
        $renews = $contract->auto_renew && $contract->status !== ContractStatus::Terminated;
        if (! $renews) {
            return $contract->ends_on->betweenIncluded($from, $to) ? [[$contract->ends_on->copy(), false]] : [];
        }
        $step = max(1, (int) ($contract->renew_period_months ?? 12));
        $ends = [];
        $period = $contract->ends_on->copy();
        for ($i = 0; $i < 1200 && $period->lte($to) && count($ends) < self::MAX_PERIODS; $i++) {
            if ($period->gte($from)) {
                $ends[] = [$period->copy(), true];
            }
            $period = $period->copy()->addMonthsNoOverflow($step);
        }

        return $ends;
    }

    /** @return array{id: string, title: string, start: string, end: null, color: string, url: string} */
    private function event(string $id, string $title, CarbonInterface $date, Contract $contract): array {
        return [
            'id' => $id,
            'title' => $title,
            'start' => $date->toDateString(),
            'end' => null,
            'color' => self::COLOR,
            'url' => route('contracts.show', $contract),
        ];
    }
}
