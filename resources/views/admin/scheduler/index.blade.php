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
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('scheduler.field.job') }}</th>
                <th>{{ __('scheduler.field.plan') }}</th>
                <th>{{ __('scheduler.field.last_run') }}</th>
                <th>{{ __('scheduler.field.next_due') }}</th>
                <th class="text-center">{{ __('scheduler.field.failures') }}</th>
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
            <tr @class(['opacity-60' => ! $job['enabled']])>
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
                    <div>{{ $job['cadence']->type->label() }}</div>
                    <div class="text-xs font-mono text-muted">{{ $job['expression'] }}</div>
                    <x-status-badge size="xs" tone="ghost">{{ __('scheduler.source.' . $job['source']) }}</x-status-badge>
                </td>
                <td class="text-sm">
                    @if ($state?->last_started_at)
                        <div>{{ $state->last_started_at->timezone(config('app.schedule_timezone', config('app.timezone')))->format('d.m.Y H:i') }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            @if ($state->last_status === \App\Models\ScheduledJobRun::STATUS_SUCCESS)
                                <x-status-badge size="xs" tone="success">{{ __('scheduler.state.success') }}</x-status-badge>
                            @elseif ($state->last_status === \App\Models\ScheduledJobRun::STATUS_FAILED)
                                <x-status-badge size="xs" tone="error">{{ __('scheduler.state.failed') }}</x-status-badge>
                            @elseif ($state->last_status !== null)
                                <x-status-badge size="xs" tone="neutral">{{ $state->last_status }}</x-status-badge>
                            @endif
                            @if ($state->last_duration_ms !== null)
                                <span class="text-xs text-muted">{{ number_format($state->last_duration_ms / 1000, 1) }}s</span>
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
