{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _filters.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Filterleiste des Leistungsplans (Feature 199): Jahr, zuständige Person, Stand, Kundengruppe, Suche, Leistungen (als gespeicherte Ansicht). Variablen: $route, $filters, $years, $members, $customerGroups, $result, $ruleSets (nur Periodensicht). --}}
<x-filter-bar :action="route($route)" :reset="route($route)">
    <select name="year" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('service_plan.filter.year') }}">
        @foreach ($years as $value => $label)
            <option value="{{ $value }}" @selected($filters['year_param'] === (string) $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="responsible" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('service_plan.filter.responsible') }}">
        <option value="">{{ __('service_plan.filter.all_responsible') }}</option>
        <option value="mine" @selected($filters['responsible'] === 'mine')>{{ __('service_plan.filter.mine') }}</option>
        <option value="none" @selected($filters['responsible'] === 'none')>{{ __('service_plan.filter.unassigned') }}</option>
        @foreach ($members as $member)
            @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
            <option value="{{ $memberSqid }}" @selected($filters['responsible'] === $memberSqid)>{{ $member->name }}</option>
        @endforeach
    </select>
    <select name="state" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('service_plan.filter.state') }}">
        <option value="">{{ __('service_plan.filter.all_states') }}</option>
        @foreach (\App\Enums\ServicePlan\ServicePlanCellState::FILTERS as $state)
            <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ __('service_plan.count.' . $state) }}</option>
        @endforeach
    </select>
    @if ($customerGroups->isNotEmpty())
        <select name="group" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('service_plan.filter.group') }}">
            <option value="">{{ __('service_plan.filter.all_groups') }}</option>
            @foreach ($customerGroups as $group)
                <option value="{{ $group->sqid }}" @selected($filters['group'] === $group->sqid)>{{ $group->displayLabel() }}</option>
            @endforeach
        </select>
    @endif
    @if (($ruleSets ?? []) !== [])
        <select name="rule_set" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('service_plan.filter.rule_set') }}">
            @foreach ($ruleSets as $key => $label)
                <option value="{{ $key }}" @selected($filters['rule_set'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    @endif
    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="input input-sm input-bordered w-48 shrink-0"
           placeholder="{{ __('service_plan.filter.search') }}" aria-label="{{ __('service_plan.filter.search') }}">
    @if ($result->available !== [])
        <details class="dropdown shrink-0">
            <summary class="btn btn-sm btn-outline">
                <x-icon name="view_column" />
                {{ __('service_plan.filter.services') }}@if ($filters['services'] !== []) ({{ count($filters['services']) }})@endif
            </summary>
            <fieldset class="dropdown-content z-20 mt-1 max-h-72 w-72 space-y-1 overflow-y-auto rounded-box border border-base-300 bg-base-100 p-3 shadow">
                <legend class="sr-only">{{ __('service_plan.filter.services') }}</legend>
                @foreach ($result->available as $column)
                    @php($serviceParam = $column->service->param())
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="services[]" value="{{ $serviceParam }}" class="checkbox checkbox-sm" @checked(in_array($serviceParam, $filters['services'], true))>
                        {{ $column->label }}
                    </label>
                @endforeach
                <p class="pt-1 text-xs text-muted">{{ __('service_plan.filter.services_hint') }}</p>
            </fieldset>
        </details>
    @endif
    @if ($filters['customer'] !== null)
        <input type="hidden" name="customer" value="{{ $filters['customer'] }}">
    @endif
</x-filter-bar>
