@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('nav-title', __('Qualifikationen'))
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex justify-end">
        @can('create', \App\Models\Qualification::class)
            <a href="{{ route('qualifications.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                + {{ __('Qualifikation anlegen') }}
            </a>
        @endcan
    </div>

    <x-table>
        <thead>
            <tr>
                <th><x-sort-th column="name" :route="route('qualifications.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="name">{{ __('Name') }}</x-sort-th></th>
                <th><x-sort-th column="abbreviation" :route="route('qualifications.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Kürzel') }}</x-sort-th></th>
                <th>{{ __('Beschreibung') }}</th>
                <th class="text-center"><x-sort-th column="users" :route="route('qualifications.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Mitarbeiter') }}</x-sort-th></th>
                <th class="text-center"><x-sort-th column="is_active" :route="route('qualifications.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Aktiv') }}</x-sort-th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
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
                <tr>
                    <td colspan="6" class="py-8 text-center text-base-content/50">{{ __('Noch keine Qualifikationen vorhanden.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
    <div>{{ $qualifications->links() }}</div>
</div>
@endsection
