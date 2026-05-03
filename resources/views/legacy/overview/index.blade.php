@extends('layouts.app')
@section('title', __('Legacy Überblick') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Überblick'))

@section('content')
    <x-filter-bar :action="route('legacy.overview.index')" class="mb-4">
            <div>
                <label class="label text-sm font-semibold pb-1">{{ __('Zeitraum') }}</label>
                <select name="zeitpunkt" class="select select-bordered select-sm">
                    <option value="1" @selected(($filters['zeitpunkt'] ?? 1) == 1)>{{ __('Daten ab heute') }}</option>
                    <option value="2" @selected(($filters['zeitpunkt'] ?? 1) == 2)>{{ __('Daten bis heute') }}</option>
                </select>
            </div>
            <div>
                <label class="label text-sm font-semibold pb-1">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered select-sm">
                    <option value="2" @selected(($filters['status'] ?? 2) == 2)>{{ __('Ungelesen') }}</option>
                    <option value="1" @selected(($filters['status'] ?? 2) == 1)>{{ __('In Bearbeitung') }}</option>
                    <option value="-1" @selected(($filters['status'] ?? 2) == -1)>{{ __('Erledigt') }}</option>
                    <option value="3" @selected(($filters['status'] ?? 2) == 3)>{{ __('Probleme') }}</option>
                    <option value="0" @selected(($filters['status'] ?? 2) == 0)>{{ __('Alle') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Anzeigen</button>
    </x-filter-bar>

    <x-table size="xs">
            <thead>
                <tr>
                    <th>{{ __('Erstellt') }}</th>
                    @if ($isAdmin)
                        <th>{{ __('Mitarbeiter') }}</th>
                    @endif
                    <th>{{ __('Von') }}</th>
                    <th>{{ __('Bis') }}</th>
                    <th>{{ __('Inhalt') }}</th>
                    <th>{{ __('Antwort') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-center">{{ optional($entry->aktuell)?->format('d.m.y') ?? '-' }}</td>
                        @if ($isAdmin)
                            <td class="text-center">{{ optional($entry->author)->uname ?? 'Unbekannt' }}</td>
                        @endif
                        <td class="text-center">{{ optional($entry->von)?->format('d.m.y') ?? '-' }}</td>
                        <td class="text-center">{{ optional($entry->bis)?->format('d.m.y') ?? '-' }}</td>
                        <td title="{{ $entry->inhalt ?? '' }}">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 25) }}</td>
                        <td title="{{ $entry->antwort ?? '' }}">{{ \Illuminate\Support\Str::limit($entry->antwort ?? '', 25) }}</td>
                        <td class="text-center">{{ $entry->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 6 }}" class="py-6 text-center text-base-content/70">{{ __('Keine Einträge gefunden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
    </x-table>

    @if ($entries->hasPages())
        <div class="mt-6">{{ $entries->links('pagination::simple-tailwind') }}</div>
    @endif
@endsection
