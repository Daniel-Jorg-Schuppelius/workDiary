@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('nav-title', __('Qualifikationen'))
@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                @can('create', \App\Models\Qualification::class)
                    <a href="{{ route('qualifications.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                        + {{ __('Qualifikation anlegen') }}
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table table-sort="server"
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
                            <span class="badge badge-success badge-sm">{{ __('Ja') }}</span>
                        @else
                            <span class="badge badge-ghost badge-sm">{{ __('Nein') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            @can('update', $qual)
                            <a href="{{ route('qualifications.edit', $qual) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            @endcan
                            @can('delete', $qual)
                            <form method="POST" action="{{ route('qualifications.destroy', $qual) }}"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Qualifikation wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>' :colspan="6" :title="__('Noch keine Qualifikationen vorhanden')" compact />
            @endforelse
    </x-table>
    @if ($qualifications->hasPages())
        <div>{{ $qualifications->links() }}</div>
    @endif
</x-page-shell>
@endsection
