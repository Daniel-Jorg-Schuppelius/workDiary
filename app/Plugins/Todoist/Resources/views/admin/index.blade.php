{{--
  Created on   : Sat Jul 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', 'Todoist — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Todoist')

@section('content')
<x-index-page :subtitle="__('todoist::todoist.subtitle')">
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('todoist::todoist.connection.title') }}</h2>

        @if (! $configured)
            <div role="alert" class="alert alert-warning">
                <x-icon name="key_off" />
                <span>{{ __('todoist::todoist.flash.not_configured') }}</span>
            </div>
        @elseif ($connection === null || $connection->status === \App\Plugins\Todoist\Enums\TodoistConnectionStatus::Disconnected)
            {{-- raw-markup-ok: Hinweis vor dem Verbinden, kein Leerzustand einer Liste --}}
            <p class="text-sm opacity-80 mb-3">{{ __('todoist::todoist.connection.none') }}</p>
            {{-- Datenübertragungs-Hinweis VOR OAuth (MVP-116): was an Todoist geht --}}
            <div role="status" class="alert mb-3">
                <x-icon name="privacy_tip" />
                <span class="text-sm">{{ __('todoist::todoist.connection.privacy_note') }}</span>
            </div>
            <form method="POST" action="{{ route('admin.todoist.oauth.start') }}">
                @csrf
                <x-button type="submit" icon="link" icon-size="1rem">
                    {{ __('todoist::todoist.connection.connect') }}
                </x-button>
            </form>
        @else
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm mb-3">
                <div><span class="opacity-60">{{ __('Status') }}:</span>
                    <x-status-badge :tone="$connection->status === \App\Plugins\Todoist\Enums\TodoistConnectionStatus::Active ? 'success' : ($connection->status === \App\Plugins\Todoist\Enums\TodoistConnectionStatus::Paused ? 'warning' : 'plain')">
                        {{ $connection->status->label() }}
                    </x-status-badge>
                </div>
                <div><span class="opacity-60">{{ __('todoist::todoist.connection.account') }}:</span> <strong>{{ $connection->todoist_user_email ?? '—' }}</strong></div>
                <div><span class="opacity-60">{{ __('todoist::todoist.connection.connected_at') }}:</span> {{ $connection->connected_at?->fdatetime() ?? '—' }}</div>
                @if ($connection->last_sync_at)
                    <div><span class="opacity-60">{{ __('todoist::todoist.connection.last_sync') }}:</span> {{ $connection->last_sync_at->fdatetime() }}</div>
                @endif
                @if ($connection->last_error)
                    <div class="text-error text-xs">{{ $connection->last_error }}</div>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                {{-- Manueller Vollabgleich (MVP-116): auditierter Admin-Vorgang --}}
                @if ($connection->isActive())
                    <form method="POST" action="{{ route('admin.todoist.sync') }}">
                        @csrf
                        <x-button type="submit" icon="sync" icon-size="1rem">
                            {{ __('todoist::todoist.connection.sync_now') }}
                        </x-button>
                    </form>
                @endif
                <x-button :href="route('admin.integration.inbox', ['plugin' => \App\Plugins\Todoist\TodoistPlugin::ID])" tone="plain" icon="inbox" icon-size="1rem">
                    {{ __('todoist::todoist.connection.open_inbox') }}
                </x-button>
                <form method="POST" action="{{ route('admin.todoist.oauth.start') }}">
                    @csrf
                    <x-button type="submit" tone="plain">{{ __('todoist::todoist.connection.reconnect') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.todoist.disconnect') }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('todoist::todoist.connection.confirm_disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="error" class="btn-outline">{{ __('todoist::todoist.connection.disconnect') }}</x-button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Projektzuordnungen (MVP-112): nur ausdrücklich Zugeordnetes wird synchronisiert --}}
    @if ($connection !== null && $connection->isActive())
        <x-card padding="p-0">
            <h2 class="font-semibold p-4 pb-0">{{ __('todoist::todoist.links.title') }}</h2>
            <x-table bare class="table-sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('todoist::todoist.links.col.todoist_project') }}</th>
                        <th>{{ __('todoist::todoist.links.col.target') }}</th>
                        <th>{{ __('todoist::todoist.links.col.mode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('todoist::todoist.links.col.last_run') }}</th>
                        <th class="text-right">{{ __('todoist::todoist.links.col.actions') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($links as $link)
                    <tr>
                        <td>{{ $link->todoist_project_name ?? $link->todoist_project_id }}</td>
                        <td class="text-sm">
                            {{ $link->target_kind === 'project' ? ($link->project?->name ?? '—') : __('todoist::todoist.links.global_kanban') }}
                        </td>
                        <td class="text-sm">{{ __('todoist::todoist.mode.' . $link->sync_mode) }}</td>
                        <td><x-status-badge :tone="$link->status === \App\Plugins\Todoist\Enums\TodoistProjectLinkStatus::Active ? 'success' : ($link->status === \App\Plugins\Todoist\Enums\TodoistProjectLinkStatus::Paused ? 'warning' : 'plain')">{{ $link->status->label() }}</x-status-badge></td>
                        <td class="text-sm">
                            {{ $link->last_run_at?->fdatetime() ?? '—' }}
                            @if (is_array($link->last_run_counters))
                                <div class="text-xs opacity-60">
                                    +{{ $link->last_run_counters['created'] ?? 0 }}
                                    ~{{ $link->last_run_counters['updated'] ?? 0 }}
                                    ={{ $link->last_run_counters['unchanged'] ?? 0 }}
                                    ⚠{{ $link->last_run_counters['conflicts'] ?? 0 }}
                                </div>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <x-button :href="route('admin.todoist.links.preflight', $link)" tone="plain" size="xs">{{ __('todoist::todoist.links.preflight') }}</x-button>
                                @if ($link->status !== \App\Plugins\Todoist\Enums\TodoistProjectLinkStatus::Active)
                                    <form method="POST" action="{{ route('admin.todoist.links.status', $link) }}">@csrf
                                        <input type="hidden" name="status" value="active">
                                        <x-button type="submit" tone="success" size="xs">{{ __('todoist::todoist.links.activate') }}</x-button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.todoist.links.status', $link) }}">@csrf
                                        <input type="hidden" name="status" value="paused">
                                        <x-button type="submit" tone="plain" size="xs">{{ __('todoist::todoist.links.pause') }}</x-button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.todoist.links.destroy', $link) }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('todoist::todoist.links.confirm_remove') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('todoist::todoist.links.remove')" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" icon="checklist" :title="__('todoist::todoist.links.empty')" />
                @endforelse
            </x-table>
            <x-pagination :paginator="$links" standing />

            <form method="POST" action="{{ route('admin.todoist.links.store') }}" class="p-4 border-t border-base-200 flex flex-wrap items-end gap-2"
                  x-data="{ kind: 'project' }">
                @csrf
                <div class="fieldset grow">
                    <label class="fieldset-label" for="todoist_project_id">{{ __('todoist::todoist.links.col.todoist_project') }}</label>
                    <select id="todoist_project_id" name="todoist_project_id" class="select select-sm select-bordered w-full" required>
                        @foreach ($remoteProjects as $remote)
                            <option value="{{ $remote['id'] ?? '' }}">{{ $remote['name'] ?? ($remote['id'] ?? '—') }}</option>
                        @endforeach
                    </select>
                    {{-- Name je Projekt ohne Skript mitgeben; der Controller nimmt den zum gewählten Projekt. --}}
                    @foreach ($remoteProjects as $remote)
                        @if (($remote['id'] ?? '') !== '')
                            <input type="hidden" name="todoist_project_names[{{ $remote['id'] }}]" value="{{ $remote['name'] ?? '' }}">
                        @endif
                    @endforeach
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="todoist_target_kind">{{ __('todoist::todoist.links.col.target') }}</label>
                    <select id="todoist_target_kind" name="target_kind" class="select select-sm select-bordered" x-on:change="kind = $event.target.value">
                        <option value="project">{{ __('todoist::todoist.links.target_project') }}</option>
                        <option value="global_kanban">{{ __('todoist::todoist.links.global_kanban') }}</option>
                    </select>
                </div>
                <div class="fieldset" x-show="kind === 'project'">
                    <label class="fieldset-label" for="todoist_workdiary_project">{{ __('todoist::todoist.links.workdiary_project') }}</label>
                    <select id="todoist_workdiary_project" name="project" class="select select-sm select-bordered">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->sqid }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="todoist_sync_mode">{{ __('todoist::todoist.links.col.mode') }}</label>
                    <select id="todoist_sync_mode" name="sync_mode" class="select select-sm select-bordered">
                        <option value="todoist_to_workdiary">{{ __('todoist::todoist.mode.todoist_to_workdiary') }}</option>
                        <option value="workdiary_to_todoist">{{ __('todoist::todoist.mode.workdiary_to_todoist') }}</option>
                        <option value="bidirectional">{{ __('todoist::todoist.mode.bidirectional') }}</option>
                    </select>
                </div>
                <x-button type="submit">{{ __('todoist::todoist.links.add') }}</x-button>
                <p class="text-xs opacity-60 basis-full">{{ __('todoist::todoist.links.hint') }}</p>
            </form>
        </x-card>
    @endif
</x-index-page>
@endsection
