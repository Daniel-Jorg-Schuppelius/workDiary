@extends('layouts.app')
@section('title', __('Qualifikationen'))
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <header class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">{{ __('Qualifikationen') }}</h1>
            <p class="text-sm text-base-content/70">{{ __('Anforderungsprofile und Zertifikate.') }}</p>
        </div>
        @can('create', \App\Models\Qualification::class)
        <a href="{{ route('qualifications.create') }}" class="btn btn-primary btn-sm">
            + {{ __('Qualifikation anlegen') }}
        </a>
        @endcan
    </header>

    <x-table>
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Kürzel') }}</th>
                <th>{{ __('Beschreibung') }}</th>
                <th class="text-center">{{ __('Mitarbeiter') }}</th>
                <th class="text-center">{{ __('Aktiv') }}</th>
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
                            <a href="{{ route('qualifications.edit', $qual) }}" class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            @endcan
                            @can('delete', $qual)
                            <form method="POST" action="{{ route('qualifications.destroy', $qual) }}"
                                  onsubmit="return confirm('{{ __('Qualifikation wirklich löschen?') }}')">
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
