{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau einer Projektzuordnung (MVP-1309): was der erste Abgleich mit den
  offenen Quellaufgaben täte — ohne Schreibwirkung. Von hier wird bestätigt
  oder eine bestehende Aufgabe ausdrücklich verknüpft. Variablen: $provider,
  $sourceNoun, $routePrefix, $link, $preview (rows, counts, truncated),
  $candidates (Aufgaben des Zielprojekts ohne Fremdreferenz).
--}}
@extends('layouts.app')
@section('title', $provider . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $provider)

@section('content')
@php
    $r = ['provider' => $provider, 'source' => $sourceNoun];
    $pending = $link->status === \App\Enums\Integration\TaskImportLinkStatus::Pending;
    $actionTones = ['create' => 'success', 'linked' => 'plain', 'mismatch' => 'warning', 'clarify' => 'warning', 'blocked' => 'error', 'skip_done' => 'ghost'];
    $creatable = array_values(array_filter($preview['rows'], static fn (array $row): bool => $row['action'] === 'create'));
@endphp
<x-index-page
    :subtitle="__('integration.task_import.preview.subtitle', ['source_project' => $link->sourceLabel(), 'project' => $link->project?->name ?? '—'])"
    :back-route="$routePrefix . '.index'"
    :back-label="$provider"
    :badge="$link->status->label()"
    :badge-tone="$link->status->tone()">

    <x-slot:actions>
        @if ($pending)
            <x-action-form :action="route($routePrefix . '.links.confirm', $link)" :confirm="__('integration.task_import.preview.confirm_question', ['count' => $preview['counts']['create']])">
                <x-button type="submit" icon="check" placement="bar">{{ __('integration.task_import.preview.confirm') }}</x-button>
            </x-action-form>
        @endif
    </x-slot:actions>

    <x-validation-errors first />

    <div role="note" class="alert text-sm">
        <x-icon name="info" />
        <span>{{ __('integration.task_import.preview.hint', $r) }}</span>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ($preview['counts'] as $action => $count)
            <x-status-badge :tone="$actionTones[$action] ?? 'ghost'" outline>{{ __('integration.task_import.preview.action.' . $action) }}: {{ $count }}</x-status-badge>
        @endforeach
    </div>

    <x-card padding="p-0">
        <x-table bare size="sm">
            <x-slot:head>
                <tr>
                    <th>{{ __('integration.task_import.preview.col.remote', $r) }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('integration.task_import.preview.col.action') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($preview['rows'] as $row)
                @php
                    $remote = $row['remote'];
                    $remoteStatus = \App\Enums\Task\TaskStatus::tryFrom((string) ($remote->fields['status'] ?? ''));
                @endphp
                <tr>
                    <td>
                        <div class="font-medium">
                            @if ($remote->snapshot['key'] ?? null)<span class="font-mono text-xs text-muted">{{ $remote->snapshot['key'] }}</span> @endif{{ $remote->snapshot['title'] !== '' ? $remote->snapshot['title'] : '—' }}
                        </div>
                        @if ($row['task'])
                            <div class="text-xs text-muted">{{ __('integration.task_import.preview.local_task', ['title' => $row['task']->title]) }}</div>
                        @endif
                    </td>
                    <td>{{ $remoteStatus?->label() ?? '—' }}</td>
                    <td><x-status-badge :tone="$actionTones[$row['action']] ?? 'ghost'" size="xs">{{ __('integration.task_import.preview.action.' . $row['action']) }}</x-status-badge></td>
                    <td class="text-right whitespace-nowrap">
                        @if ($row['candidate'])
                            <x-action-form :action="route($routePrefix . '.links.attach', $link)" :confirm="__('integration.task_import.preview.attach_question', ['title' => $row['candidate']->title])">
                                <input type="hidden" name="remote_id" value="{{ $remote->id }}">
                                <input type="hidden" name="task" value="{{ $row['candidate']->sqid }}">
                                <x-button type="submit" size="xs" tone="ghost" icon="link">{{ __('integration.task_import.preview.attach_candidate') }}</x-button>
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="checklist" :title="__('integration.task_import.preview.empty', $r)" compact />
            @endforelse
        </x-table>
        @if ($preview['truncated'])
            <p class="px-4 py-2 text-xs text-muted">{{ __('integration.task_import.preview.truncated', ['count' => \App\Plugins\Support\TaskSync\AbstractTaskImportService::PREVIEW_LIMIT]) }}</p>
        @endif
    </x-card>

    @if ($creatable !== [] && $candidates->isNotEmpty())
        <x-card :title="__('integration.task_import.preview.attach_title')">
            <p class="mb-3 text-sm text-muted">{{ __('integration.task_import.preview.attach_hint', $r) }}</p>
            <form method="POST" action="{{ route($routePrefix . '.links.attach', $link) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-select-field name="remote_id" :label="__('integration.task_import.preview.col.remote', $r)" required>
                    @foreach ($creatable as $row)
                        <option value="{{ $row['remote']->id }}">{{ trim(($row['remote']->snapshot['key'] ?? '') . ' ' . $row['remote']->snapshot['title']) }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="task" :label="__('integration.task_import.preview.local_task_label')" required>
                    @foreach ($candidates as $task)
                        <option value="{{ $task->sqid }}">{{ $task->title }}</option>
                    @endforeach
                </x-select-field>
                <x-button type="submit" icon="link">{{ __('integration.task_import.preview.attach') }}</x-button>
            </form>
        </x-card>
    @endif
</x-index-page>
@endsection
