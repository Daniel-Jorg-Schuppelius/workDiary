{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('operations.title.index'))
@section('nav-title', __('operations.title.index'))

@section('content')
<x-index-page :subtitle="__('operations.title.subtitle')">
    <x-filter-bar :action="route('admin.operations.index')" :reset="route('admin.operations.index')">
        <x-filter-field :label="__('operations.field.status')" for="op-status" class="min-w-44 shrink-0">
            <select id="op-status" name="status" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('operations.filter.active') }}</option>
                @foreach (\App\Enums\Operations\OperationsTaskStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('operations.field.severity')" for="op-severity" class="min-w-44 shrink-0">
            <select id="op-severity" name="severity" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('operations.filter.all_severities') }}</option>
                @foreach (\App\Enums\Operations\OperationsTaskSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected($severityFilter === $severity)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Typ')" for="op-type" class="min-w-52 shrink-0">
            <select id="op-type" name="type" class="select select-sm select-bordered w-full" data-autosubmit>
                <option value="">{{ __('operations.filter.all_types') }}</option>
                @foreach (\App\Enums\Operations\OperationsTaskType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($typeFilter === $type)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($tasks->isEmpty())
        <x-empty-state framed icon="task_alt" :title="__('operations.empty.title')" :message="__('operations.empty.message')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('operations.field.task') }}</th>
                    <th>{{ __('operations.field.severity') }}</th>
                    <th>{{ __('operations.field.status') }}</th>
                    <th>{{ __('operations.field.last_seen') }}</th>
                    <th class="text-right">{{ __('operations.field.actions') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($tasks as $task)
                <tr>
                    <td>
                        <div class="flex items-start gap-2">
                            <x-icon :name="$task->type->icon()" class="mt-0.5 text-muted" />
                            <div>
                                <div class="font-medium">{{ $task->title() }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <x-status-badge size="xs" tone="ghost">{{ $task->type->label() }}</x-status-badge>
                                    @if ($task->is_system)
                                        <x-status-badge size="xs" tone="info">{{ __('operations.field.system_wide') }}</x-status-badge>
                                    @endif
                                    @if ($task->assignee)
                                        <x-status-badge size="xs" tone="ghost">{{ __('operations.field.assignee') }}: {{ $task->assignee->name }}</x-status-badge>
                                    @endif
                                    @if ($task->url())
                                        <a href="{{ $task->url() }}" class="link link-hover text-xs">{{ __('operations.action.open_link') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><x-status-badge size="xs" :tone="$task->severity->tone()">{{ $task->severity->label() }}</x-status-badge></td>
                    <td>
                        <x-status-badge size="xs" :tone="$task->status->tone()">{{ $task->status->label() }}</x-status-badge>
                        @if ($task->status === \App\Enums\Operations\OperationsTaskStatus::Snoozed && $task->snoozed_until)
                            <div class="text-xs text-muted">{{ __('operations.field.snooze_until') }} {{ $task->snoozed_until->format('d.m.Y') }}</div>
                        @endif
                        @if ($task->note)
                            <div class="text-xs text-muted" title="{{ $task->note }}">{{ \Illuminate\Support\Str::limit($task->note, 40) }}</div>
                        @endif
                    </td>
                    <td class="text-sm">{{ $task->last_seen_at->format('d.m.Y H:i') }}</td>
                    <td class="text-right">
                        @if ($canManage)
                            <div class="inline-flex items-center gap-1">
                                @if ($task->status->isActive())
                                    <form method="POST" action="{{ route('admin.operations.done', $task) }}">
                                        @csrf
                                        <x-icon-btn icon="check_circle" type="submit" :label="__('operations.action.done')" />
                                    </form>
                                    <form method="POST" action="{{ route('admin.operations.snooze', $task) }}">
                                        @csrf
                                        <x-icon-btn icon="snooze" type="submit" :label="__('operations.action.snooze')" />
                                    </form>
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-ghost btn-xs btn-circle" title="{{ __('operations.action.delegate') }} / {{ __('operations.action.ignore') }}">
                                            <x-icon name="more_vert" />
                                        </summary>
                                        <div class="dropdown-content z-20 w-72 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg space-y-3">
                                            <form method="POST" action="{{ route('admin.operations.delegate', $task) }}" class="space-y-2">
                                                @csrf
                                                <span class="text-xs font-medium">{{ __('operations.action.delegate') }}</span>
                                                <select name="assigned_user" class="select select-bordered select-sm w-full">
                                                    @foreach (\App\Models\User::query()->orderBy('name')->limit(100)->get() as $candidate)
                                                        <option value="{{ $candidate->sqid }}">{{ $candidate->name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-button type="submit" tone="primary" size="sm" class="w-full">{{ __('operations.action.delegate') }}</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.operations.ignore', $task) }}" class="space-y-2">
                                                @csrf
                                                <span class="text-xs font-medium">{{ __('operations.action.ignore') }}</span>
                                                <input type="text" name="note" required maxlength="500" class="input input-bordered input-sm w-full"
                                                       placeholder="{{ __('operations.field.note') }}">
                                                <x-button type="submit" tone="ghost" size="sm" class="w-full">{{ __('operations.action.ignore') }}</x-button>
                                            </form>
                                        </div>
                                    </details>
                                @else
                                    <form method="POST" action="{{ route('admin.operations.reopen', $task) }}">
                                        @csrf
                                        <x-icon-btn icon="undo" type="submit" :label="__('operations.action.reopen')" />
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-pagination :paginator="$tasks" standing />
</x-index-page>
@endsection
