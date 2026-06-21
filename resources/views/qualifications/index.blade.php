@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('nav-title', __('Qualifikationen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('Qualifikationen und Zertifikate der Mitarbeiter verwalten.')">
    <x-slot:actions>
        @can('create', \App\Models\Qualification::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('qualifications.create')"
                        show-label>{{ __('Qualifikation anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('qualifications.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                <x-table.th sort="abbreviation">{{ __('Kürzel') }}</x-table.th>
                <th>{{ __('Beschreibung') }}</th>
                <x-table.th sort="users" align="center">{{ __('Mitarbeiter') }}</x-table.th>
                <x-table.th sort="is_active" align="center">{{ __('Aktiv') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
            @forelse ($qualifications as $qual)
                <tr class="{{ $qual->is_active ? '' : 'opacity-50' }}">
                    <td class="font-medium">{{ $qual->name }}</td>
                    <td>{{ $qual->abbreviation ?? '–' }}</td>
                    <td class="text-sm text-base-content/70 max-w-xs truncate">{{ $qual->description ?? '–' }}</td>
                    <td class="text-center">{{ $qual->users_count }}</td>
                    <td class="text-center">
                        @if ($qual->is_active)
                            <x-status-badge tone="success" size="sm">{{ __('Ja') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('Nein') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $qual)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('qualifications.edit', $qual)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $qual)
                                <x-action-form :action="route('qualifications.destroy', $qual)" method="DELETE"
                                      :confirm="__('Qualifikation wirklich löschen?')"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>' :colspan="6" :title="__('Noch keine Qualifikationen vorhanden')" compact />
            @endforelse
    </x-table>
    <x-pagination :paginator="$qualifications" />
</x-index-page>
@endsection
