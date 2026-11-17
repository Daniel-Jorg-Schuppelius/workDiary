{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenfristen (Feature 197, MVP-1633). Variablen: $deadlines (Paginator), $counts, $filters, $ruleSets (Schlüssel → Bezeichnung), $enabledRuleSets, $kinds, $warnDays, $members, $overview, $organization, $today, $sort, $dir, $canManage. --}}

@extends('layouts.app')

@section('title', __('customer_deadline.title'))
@section('nav-title', __('customer_deadline.title'))
@include('partials.page-fill')

@php
    $linkParams = array_filter([
        'rule_set' => $filters['rule_set'],
        'kind' => $filters['kind'],
        'responsible' => $filters['responsible'],
        'month' => $filters['month'],
        'status' => $filters['status'] !== 'open' ? $filters['status'] : null,
        'customer' => $filters['customer_id'] !== null ? \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $filters['customer_id']) : null,
    ], static fn ($value) => $value !== null && $value !== '');
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('customer_deadline.subtitle')">
    <x-slot:actions>
        <x-report-export :formats="['csv', 'xlsx']" :url="fn (string $format) => route('customer-deadlines.index', array_merge($linkParams, ['export' => $format]))" />
        @if ($canManage)
            <x-icon-btn icon="tune" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('customer-deadlines.settings.edit') . '?dialog=1'"
                        show-label>{{ __('customer_deadline.settings.title') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($enabledRuleSets === [])
        <div role="note" class="alert alert-info text-sm">
            <x-icon name="info" />
            <span>{{ __('customer_deadline.hint.no_rule_sets') }}</span>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('customer_deadline.count.overdue')" :value="$counts['overdue']" tone="error"
                    :href="route('customer-deadlines.index', array_merge($linkParams, ['status' => 'overdue']))" :active="$filters['status'] === 'overdue'" />
        <x-kpi-tile :label="__('customer_deadline.count.due_soon')" :value="$counts['due_soon']" tone="warning"
                    :href="route('customer-deadlines.index', array_merge($linkParams, ['status' => 'due_soon']))" :active="$filters['status'] === 'due_soon'" />
        <x-kpi-tile :label="__('customer_deadline.count.open')" :value="$counts['open']"
                    :href="route('customer-deadlines.index', array_merge($linkParams, ['status' => null]))" :active="$filters['status'] === 'open'" />
    </div>

    <x-filter-bar :action="route('customer-deadlines.index')" :reset="route('customer-deadlines.index')">
        <select name="rule_set" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('customer_deadline.filter.rule_set') }}">
            <option value="">{{ __('customer_deadline.filter.all_rule_sets') }}</option>
            @foreach ($ruleSets as $key => $label)
                <option value="{{ $key }}" @selected($filters['rule_set'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="kind" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('customer_deadline.filter.kind') }}">
            <option value="">{{ __('customer_deadline.filter.all_kinds') }}</option>
            @foreach ($kinds as $key => $label)
                <option value="{{ $key }}" @selected($filters['kind'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="responsible" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('customer_deadline.filter.responsible') }}">
            <option value="">{{ __('customer_deadline.filter.all_responsible') }}</option>
            <option value="mine" @selected($filters['responsible'] === 'mine')>{{ __('customer_deadline.filter.mine') }}</option>
            <option value="none" @selected($filters['responsible'] === 'none')>{{ __('customer_deadline.filter.unassigned') }}</option>
            @foreach ($members as $member)
                @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
                <option value="{{ $memberSqid }}" @selected($filters['responsible'] === $memberSqid)>{{ $member->name }}</option>
            @endforeach
        </select>
        <input type="month" name="month" value="{{ $filters['month'] }}" class="input input-sm input-bordered w-40 shrink-0" aria-label="{{ __('customer_deadline.filter.month') }}">
        <select name="status" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('customer_deadline.filter.status') }}">
            @foreach (\App\Services\Customer\Deadlines\CustomerDeadlineOverview::STATUS_FILTERS as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('customer_deadline.filter.status_' . $status) }}</option>
            @endforeach
        </select>
        @if ($filters['customer_id'] !== null)
            <input type="hidden" name="customer" value="{{ $linkParams['customer'] ?? '' }}">
        @endif
    </x-filter-bar>

    @if ($deadlines->isEmpty())
        <x-empty-state framed icon="event_available" :title="__('customer_deadline.empty.title')" :message="__('customer_deadline.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th class="w-px"><span class="sr-only">{{ __('customer_deadline.column.traffic_light') }}</span></th>
                    <x-sort-th column="due_on" :route="route('customer-deadlines.index')" :params="$linkParams" :sort="$sort" :dir="$dir" default="due_on">{{ __('customer_deadline.column.due_on') }}</x-sort-th>
                    <th>{{ __('customer_deadline.column.customer') }}</th>
                    <x-sort-th column="kind" :route="route('customer-deadlines.index')" :params="$linkParams" :sort="$sort" :dir="$dir">{{ __('customer_deadline.column.kind') }}</x-sort-th>
                    <x-sort-th column="period" :route="route('customer-deadlines.index')" :params="$linkParams" :sort="$sort" :dir="$dir">{{ __('customer_deadline.column.period') }}</x-sort-th>
                    <th>{{ __('customer_deadline.column.responsible') }}</th>
                    <x-sort-th column="status" :route="route('customer-deadlines.index')" :params="$linkParams" :sort="$sort" :dir="$dir">{{ __('customer_deadline.column.status') }}</x-sort-th>
                </tr>
            </x-slot:head>
            @foreach ($deadlines as $deadline)
                @php($light = $deadline->trafficLight($today, $warnDays[$deadline->rule_set] ?? \App\Services\Customer\Deadlines\CustomerDeadlineRuleSets::DEFAULT_WARN_DAYS))
                <tr class="hover" data-deadline-row="{{ $deadline->sqid }}">
                    <td>
                        @if ($light !== null)
                            <span class="inline-block size-3 rounded-full {{ ['error' => 'bg-error', 'warning' => 'bg-warning', 'success' => 'bg-success'][$light] }}"
                                  title="{{ __('customer_deadline.light.' . $light) }}" data-traffic-light="{{ $light }}"></span>
                            <span class="sr-only">{{ __('customer_deadline.light.' . $light) }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap tabular-nums">
                        <a class="link link-hover font-medium" data-entry-modal-trigger href="{{ route('customer-deadlines.edit', $deadline) }}?dialog=1">{{ $deadline->due_on->fdate() }}</a>
                        @if ($deadline->due_on->toDateString() !== $deadline->original_due_on->toDateString())
                            <div class="text-xs text-muted">{{ __('customer_deadline.original', ['date' => $deadline->original_due_on->fdate()]) }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($deadline->customer !== null)
                            <a class="link link-hover" href="{{ route('customers.show', $deadline->customer) }}">{{ $deadline->customer->name }}</a>
                            @if ($deadline->customer->number)
                                <span class="text-xs text-muted">{{ $deadline->customer->number }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        {{ $overview->kindLabel($organization, $deadline) }}
                        @if ($deadline->is_region_assumed)
                            <x-icon name="location_off" class="align-middle text-warning" :title="__('customer_deadline.region_assumed')" />
                            <span class="sr-only">{{ __('customer_deadline.region_assumed') }}</span>
                        @endif
                        <div class="text-xs text-muted">{{ $ruleSets[$deadline->rule_set] ?? $deadline->rule_set }}</div>
                    </td>
                    <td class="whitespace-nowrap">{{ $deadline->period_key->label() }}</td>
                    <td>{{ $deadline->responsible?->name ?? '—' }}</td>
                    <td><x-status-badge size="sm" :tone="$deadline->status->tone()">{{ $deadline->status->label() }}</x-status-badge></td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$deadlines" standing />
    @endif
</x-index-page>
@endsection
