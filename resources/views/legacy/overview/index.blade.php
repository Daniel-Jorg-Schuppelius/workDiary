@extends('layouts.app')
@section('title', __('Legacy Überblick') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Überblick'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <form method="GET" action="{{ route('legacy.overview.index') }}" class="flex-none rounded-box border border-base-300 bg-base-200 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex flex-col">
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Zeitraum') }}</span></label>
                <select name="zeitpunkt" class="select select-bordered select-sm">
                    <option value="1" @selected(($filters['zeitpunkt'] ?? 1) == 1)>{{ __('Daten ab heute') }}</option>
                    <option value="2" @selected(($filters['zeitpunkt'] ?? 1) == 2)>{{ __('Daten bis heute') }}</option>
                </select>
            </div>
            <div class="flex flex-col">
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                <select name="status" class="select select-bordered select-sm">
                    <option value="2" @selected(($filters['status'] ?? 2) == 2)>{{ __('Ungelesen') }}</option>
                    <option value="1" @selected(($filters['status'] ?? 2) == 1)>{{ __('In Bearbeitung') }}</option>
                    <option value="-1" @selected(($filters['status'] ?? 2) == -1)>{{ __('Erledigt') }}</option>
                    <option value="3" @selected(($filters['status'] ?? 2) == 3)>{{ __('Probleme') }}</option>
                    <option value="0" @selected(($filters['status'] ?? 2) == 0)>{{ __('Alle') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Anzeigen') }}</button>
        </div>
    </form>

    <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300">
        <div class="h-full overflow-auto">
        <table class="table table-xs table-zebra table-pin-rows">
            <thead class="bg-base-200">
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
                            <td class="text-center">{{ optional($entry->author)->uname ?? __('Unbekannt') }}</td>
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
        </table>
        </div>
    </div>

    @if ($entries->hasPages())
        <div class="flex-none rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-sm">{{ $entries->links('vendor.pagination.daisyui-simple') }}</div>
    @endif
</div>
@endsection
