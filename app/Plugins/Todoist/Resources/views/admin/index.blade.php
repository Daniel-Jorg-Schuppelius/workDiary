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
<x-index-page :subtitle="__('todoist.subtitle')">
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('todoist.connection.title') }}</h2>

        @if (! $configured)
            <div class="alert alert-warning">
                <x-icon name="key_off" />
                <span>{{ __('todoist.flash.not_configured') }}</span>
            </div>
        @elseif ($connection === null || $connection->status === \App\Models\TodoistConnection::STATUS_DISCONNECTED)
            <p class="text-sm opacity-80 mb-3">{{ __('todoist.connection.none') }}</p>
            {{-- Datenübertragungs-Hinweis VOR OAuth (MVP-116): was an Todoist geht --}}
            <div class="alert mb-3">
                <x-icon name="privacy_tip" />
                <span class="text-sm">{{ __('todoist.connection.privacy_note') }}</span>
            </div>
            <form method="POST" action="{{ route('admin.todoist.oauth.start') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm gap-1">
                    <x-icon name="link" class="text-base" />{{ __('todoist.connection.connect') }}
                </button>
            </form>
        @else
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm mb-3">
                <div><span class="opacity-60">{{ __('Status') }}:</span>
                    <span @class(['badge badge-sm', 'badge-success' => $connection->status === 'active', 'badge-warning' => $connection->status === 'paused'])>
                        {{ __('todoist.status.' . $connection->status) }}
                    </span>
                </div>
                <div><span class="opacity-60">{{ __('todoist.connection.account') }}:</span> <strong>{{ $connection->todoist_user_email ?? '—' }}</strong></div>
                <div><span class="opacity-60">{{ __('todoist.connection.connected_at') }}:</span> {{ $connection->connected_at?->format('d.m.Y H:i') ?? '—' }}</div>
                @if ($connection->last_sync_at)
                    <div><span class="opacity-60">{{ __('todoist.connection.last_sync') }}:</span> {{ $connection->last_sync_at->format('d.m.Y H:i') }}</div>
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
                        <button type="submit" class="btn btn-sm btn-primary gap-1">
                            <x-icon name="sync" class="text-base" />{{ __('todoist.connection.sync_now') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.integration.inbox', ['plugin' => \App\Plugins\Todoist\TodoistPlugin::ID]) }}" class="btn btn-sm gap-1">
                    <x-icon name="inbox" class="text-base" />{{ __('todoist.connection.open_inbox') }}
                </a>
                <form method="POST" action="{{ route('admin.todoist.oauth.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">{{ __('todoist.connection.reconnect') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.todoist.disconnect') }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('todoist.connection.confirm_disconnect') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline btn-error">{{ __('todoist.connection.disconnect') }}</button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Projektzuordnungen (MVP-112): nur ausdrücklich Zugeordnetes wird synchronisiert --}}
    @if ($connection !== null && $connection->isActive())
        <x-card padding="p-0">
            <h2 class="font-semibold p-4 pb-0">{{ __('todoist.links.title') }}</h2>
            <x-table bare class="table-sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('todoist.links.col.todoist_project') }}</th>
                        <th>{{ __('todoist.links.col.target') }}</th>
                        <th>{{ __('todoist.links.col.mode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('todoist.links.col.last_run') }}</th>
                        <th class="text-right">{{ __('todoist.links.col.actions') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($links as $link)
                    <tr>
                        <td>{{ $link->todoist_project_name ?? $link->todoist_project_id }}</td>
                        <td class="text-sm">
                            {{ $link->target_kind === 'project' ? ($link->project?->name ?? '—') : __('todoist.links.global_kanban') }}
                        </td>
                        <td class="text-sm">{{ __('todoist.mode.' . $link->sync_mode) }}</td>
                        <td><span @class(['badge badge-sm', 'badge-success' => $link->status === 'active', 'badge-warning' => $link->status === 'paused'])>{{ __('todoist.link_status.' . $link->status) }}</span></td>
                        <td class="text-sm">
                            {{ $link->last_run_at?->format('d.m.Y H:i') ?? '—' }}
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
                                <a href="{{ route('admin.todoist.links.preflight', $link) }}" class="btn btn-xs">{{ __('todoist.links.preflight') }}</a>
                                @if ($link->status !== 'active')
                                    <form method="POST" action="{{ route('admin.todoist.links.status', $link) }}">@csrf
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-xs btn-success">{{ __('todoist.links.activate') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.todoist.links.status', $link) }}">@csrf
                                        <input type="hidden" name="status" value="paused">
                                        <button type="submit" class="btn btn-xs">{{ __('todoist.links.pause') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.todoist.links.destroy', $link) }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('todoist.links.confirm_remove') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('todoist.links.remove')" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" icon="checklist" :title="__('todoist.links.empty')" />
                @endforelse
            </x-table>

            <form method="POST" action="{{ route('admin.todoist.links.store') }}" class="p-4 border-t border-base-200 flex flex-wrap items-end gap-2"
                  x-data="{ kind: 'project' }">
                @csrf
                <div class="fieldset grow">
                    <label class="fieldset-label" for="todoist_project_id">{{ __('todoist.links.col.todoist_project') }}</label>
                    <select id="todoist_project_id" name="todoist_project_id" class="select select-sm select-bordered w-full" required
                            x-on:change="$refs.pname.value = $event.target.selectedOptions[0]?.dataset.name || ''">
                        @foreach ($remoteProjects as $remote)
                            <option value="{{ $remote['id'] ?? '' }}" data-name="{{ $remote['name'] ?? '' }}">{{ $remote['name'] ?? ($remote['id'] ?? '—') }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="todoist_project_name" x-ref="pname" value="{{ $remoteProjects[0]['name'] ?? '' }}">
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="todoist_target_kind">{{ __('todoist.links.col.target') }}</label>
                    <select id="todoist_target_kind" name="target_kind" class="select select-sm select-bordered" x-on:change="kind = $event.target.value">
                        <option value="project">{{ __('todoist.links.target_project') }}</option>
                        <option value="global_kanban">{{ __('todoist.links.global_kanban') }}</option>
                    </select>
                </div>
                <div class="fieldset" x-show="kind === 'project'">
                    <label class="fieldset-label" for="todoist_workdiary_project">{{ __('todoist.links.workdiary_project') }}</label>
                    <select id="todoist_workdiary_project" name="project" class="select select-sm select-bordered">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->sqid }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="todoist_sync_mode">{{ __('todoist.links.col.mode') }}</label>
                    <select id="todoist_sync_mode" name="sync_mode" class="select select-sm select-bordered">
                        <option value="todoist_to_workdiary">{{ __('todoist.mode.todoist_to_workdiary') }}</option>
                        <option value="workdiary_to_todoist">{{ __('todoist.mode.workdiary_to_todoist') }}</option>
                        <option value="bidirectional">{{ __('todoist.mode.bidirectional') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('todoist.links.add') }}</button>
                <p class="text-xs opacity-60 basis-full">{{ __('todoist.links.hint') }}</p>
            </form>
        </x-card>
    @endif
</x-index-page>
@endsection
