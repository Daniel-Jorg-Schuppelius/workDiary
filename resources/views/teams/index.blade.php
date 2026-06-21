@extends('layouts.app')

@section('title', __('Teams'))
@section('nav-title', __('Teams'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Operative Arbeits-Teams verwalten – Mitglieder, Teamleiter und zugewiesene Aufträge.')">
    <x-slot:actions>
        @can('create', \App\Models\Team::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('teams.create')"
                        show-label>{{ __('Team anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('teams.index')" :reset="route('teams.index')">
        <input type="text" name="q" value="{{ $search ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Teamname') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Teamleiter') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Mitglieder') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($teams as $team)
            <tr>
                <td class="font-medium">
                    @if ($team->color)
                        <span class="mr-2 inline-block h-2 w-2 rounded-full" style="background-color: {{ $team->color }}"></span>
                    @endif
                    <a href="{{ route('teams.show', $team) }}" class="link link-hover">{{ $team->name }}</a>
                </td>
                <td class="text-sm text-base-content/70">{{ $team->lead?->name ?? '—' }}</td>
                <td>{{ $team->members_count }}</td>
                <td class="text-right">
                    <x-icon-btn icon="visibility" size="xs" :href="route('teams.show', $team)" :title="__('Ansehen')" />
                    @can('update', $team)
                        <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                    :href="route('teams.edit', $team)" :title="__('Bearbeiten')" />
                    @endcan
                    @can('delete', $team)
                        <x-action-form :action="route('teams.destroy', $team)" method="DELETE"
                              :confirm="__('Team wirklich löschen?')">
                            <x-icon-btn type="submit" icon="delete" size="xs" tone="error" :title="__('Löschen')" />
                        </x-action-form>
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4"
                icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>'
                :title="__('Noch keine Teams angelegt.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$teams" />
</x-index-page>
@endsection
