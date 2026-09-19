{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('scheduler.title.index'))
@section('nav-title', __('scheduler.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('scheduler.title.subtitle')">
    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('scheduler.title.help') }}</h3>
            <div class="text-sm">{{ __('scheduler.title.help_text') }}</div>
            <div class="mt-1 text-sm">
                @if ($operatingWindow)
                    {{ __('scheduler.window.active', ['window' => $operatingWindow->label(), 'timezone' => config('app.schedule_timezone', config('app.timezone'))]) }}
                @else
                    {{ __('scheduler.window.none') }}
                @endif
                @can(\App\Enums\User\Permission::PlatformSettingsManage->value)
                    <a href="{{ route('admin.settings.index', ['q' => 'scheduler.operating_window']) }}" class="link">{{ __('scheduler.window.configure') }}</a>
                @endcan
            </div>
        </div>
    </div>

    <x-filter-bar :action="route('admin.scheduler.index')" :reset="route('admin.scheduler.index')">
        <x-filter-field :label="__('Suche')" for="sch-q" class="flex-1 min-w-60">
            <input id="sch-q" type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ __('scheduler.filter.search_placeholder') }}"
                   class="input input-sm input-bordered w-full">
        </x-filter-field>
        <x-filter-field :label="__('scheduler.field.criticality')" for="sch-criticality" class="min-w-44 shrink-0">
            <select id="sch-criticality" name="criticality" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('scheduler.filter.all_criticalities') }}</option>
                @foreach (\App\Scheduling\JobCriticality::cases() as $criticality)
                    <option value="{{ $criticality->value }}" @selected($filters['criticality'] === $criticality)>{{ $criticality->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('scheduler.field.last_status')" for="sch-status" class="min-w-44 shrink-0">
            <select id="sch-status" name="status" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('scheduler.filter.all_statuses') }}</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('scheduler.field.source')" for="sch-source" class="min-w-52 shrink-0">
            <select id="sch-source" name="source" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('scheduler.filter.all_sources') }}</option>
                @foreach ($sourceOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-toggle name="paused" id="sch-paused"
                         :label="__('scheduler.filter.only_paused')"
                         :checked="$filters['paused']" data-autosubmit />
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.scheduler.index')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="request()->except(['sort', 'dir', 'page'])"
             :empty-title="__('scheduler.empty.title')" :empty-message="__('scheduler.empty.message')">
        <x-slot:head>
            <tr>
                <x-table.th sort="job" default="asc">{{ __('scheduler.field.job') }}</x-table.th>
                <th>{{ __('scheduler.field.plan') }}</th>
                <x-table.th sort="last_run">{{ __('scheduler.field.last_run') }}</x-table.th>
                <x-table.th sort="next_due">{{ __('scheduler.field.next_due') }}</x-table.th>
                <x-table.th sort="failures" align="center">{{ __('scheduler.field.failures') }}</x-table.th>
                <th class="text-right">{{ __('scheduler.field.actions') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($jobs as $job)
            @php
                /** @var \App\Scheduling\JobDefinition $definition */
                $definition = $job['definition'];
                /** @var \App\Models\ScheduledJobState|null $state */
                $state = $job['state'];
            @endphp
            <tr @class(['hover', 'opacity-60' => ! $job['enabled']])>
                <td>
                    <div class="font-medium">{{ $definition->label() }}</div>
                    <div class="text-xs font-mono text-muted">{{ $definition->key }} · {{ $definition->command }}</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <x-status-badge size="xs" :tone="$definition->criticality->tone()">{{ $definition->criticality->label() }}</x-status-badge>
                        @unless ($job['enabled'])
                            <x-status-badge size="xs" tone="warning">{{ __('scheduler.state.paused') }}</x-status-badge>
                        @endunless
                    </div>
                </td>
                <td>
                    <div>{{ $job['cadence']->label() }}</div>
                    <div class="text-xs font-mono text-muted">{{ $job['expression'] }}</div>
                    <x-status-badge size="xs" tone="ghost">{{ __('scheduler.source.' . $job['source']) }}</x-status-badge>
                    @if ($job['shifted_from'])
                        <x-status-badge size="xs" tone="info">{{ __('scheduler.source.shifted', ['time' => $job['shifted_from']->time ?? $job['shifted_from']->cronExpression()]) }}</x-status-badge>
                    @endif
                </td>
                <td class="text-sm">
                    @if ($state?->last_started_at)
                        <div>{{ $state->last_started_at->timezone(config('app.schedule_timezone', config('app.timezone')))->format('d.m.Y H:i') }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            @if ($state->last_status !== null)
                                <x-status-badge size="xs" :tone="$state->last_status->tone()">{{ $state->last_status->label() }}</x-status-badge>
                            @endif
                            @if ($state->last_duration_ms !== null)
                                <span class="text-xs text-muted">{{ \App\Support\DocumentNumber::decimal($state->last_duration_ms / 1000, 1) }} s</span>
                            @endif
                        </div>
                    @else
                        <span class="opacity-50">{{ __('scheduler.state.never_ran') }}</span>
                    @endif
                </td>
                <td class="text-sm">
                    @if ($job['next_due_at'])
                        {{ $job['next_due_at']->timezone(config('app.schedule_timezone', config('app.timezone')))->format('d.m.Y H:i') }}
                    @else
                        <span class="opacity-50">–</span>
                    @endif
                </td>
                <td class="text-center">
                    @if (($state?->consecutive_failures ?? 0) > 0)
                        <x-status-badge size="xs" tone="error">{{ $state->consecutive_failures }}</x-status-badge>
                    @else
                        <span class="opacity-50">0</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="inline-flex items-center gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.scheduler.edit', ['job' => $definition->key])"
                                    :label="__('scheduler.action.reschedule')" />
                        @if ($job['enabled'])
                            <form method="POST" action="{{ route('admin.scheduler.pause', ['job' => $definition->key]) }}">
                                @csrf
                                <x-icon-btn icon="pause_circle" type="submit" :label="__('scheduler.action.pause')" />
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.scheduler.resume', ['job' => $definition->key]) }}">
                                @csrf
                                <x-icon-btn icon="play_circle" type="submit" :label="__('scheduler.action.resume')" />
                            </form>
                        @endif
                        @if ($job['source'] === 'override' || ! $job['enabled'])
                            <form method="POST" action="{{ route('admin.scheduler.reset', ['job' => $definition->key]) }}">
                                @csrf
                                <x-icon-btn icon="restart_alt" type="submit" :label="__('scheduler.action.reset')" />
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.scheduler.test-run', ['job' => $definition->key]) }}">
                            @csrf
                            <x-icon-btn icon="play_arrow" type="submit" :label="__('scheduler.action.test_run')" />
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
