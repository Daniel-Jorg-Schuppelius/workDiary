@extends('layouts.app')

@section('title', $team->name)
@section('nav-title', $team->name)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <x-icon-btn icon="timeline" size="sm" :href="route('teams.workload', $team)" show-label>{{ __('Auslastung') }}</x-icon-btn>
                @can('update', $team)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                :href="route('teams.edit', $team)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h3 class="card-title">{{ __('Stammdaten') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-base-content/60">{{ __('Teamname') }}</dt>
                        <dd>@if ($team->color)<span class="mr-2 inline-block h-2 w-2 rounded-full" style="background-color: {{ $team->color }}"></span>@endif{{ $team->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-base-content/60">{{ __('Teamleiter') }}</dt><dd>{{ $team->lead?->name ?? '—' }}</dd></div>
                    @if ($team->description)
                        <div><dt class="text-base-content/60">{{ __('Beschreibung') }}</dt><dd>{{ $team->description }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-3">
                <h3 class="card-title">{{ __('Zugewiesene Aufträge') }} ({{ $team->projects->count() }})</h3>
                @forelse ($team->projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="link link-hover block text-sm">{{ $project->name }}</a>
                @empty
                    <x-empty-state compact
                        icon='<span class="material-symbols-outlined" aria-hidden="true">folder_open</span>'
                        :title="__('Diesem Team sind noch keine Aufträge zugewiesen.')" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="card-title">{{ __('Mitglieder') }} ({{ $team->members->count() }})</h3>
                @can('manageMembers', $team)
                    @if ($addableUsers->isNotEmpty())
                        <x-icon-btn icon="person_add" tone="primary" size="sm" data-entry-modal-trigger
                                    :href="route('teams.members.attach.form', $team)"
                                    show-label>{{ __('Mitglied hinzufügen') }}</x-icon-btn>
                    @endif
                @endcan
            </div>

            <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitglied') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('E-Mail') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Rolle im Team') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($team->members as $member)
                    <tr>
                        <td>{{ $member->name }}</td>
                        <td class="text-sm text-base-content/70">{{ $member->email }}</td>
                        <td class="text-sm">
                            @if ((int) $member->id === (int) $team->lead_user_id)
                                <x-status-badge tone="primary" size="xs">{{ __('Teamleiter') }}</x-status-badge>
                            @else
                                {{ __('Mitglied') }}
                            @endif
                        </td>
                        <td class="text-right">
                            @can('manageMembers', $team)
                                <form method="POST" action="{{ route('teams.members.detach', [$team, $member]) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <x-icon-btn type="submit" icon="person_remove" size="xs" tone="error" :title="__('Entfernen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">group</span>'
                        :title="__('Noch keine Mitglieder.')" compact />
                @endforelse
            </x-table>
        </div>
    </div>
</x-page-shell>
@endsection
