@extends('layouts.app')
@section('title', __('Legacy Bereitschaft') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Bereitschaft'))

@section('content')
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Legacy\LegacyUser> $users */
    /** @var array<string, mixed> $filters */
@endphp
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <div role="tablist" class="tabs tabs-box flex-none self-start">
        <a role="tab" href="{{ route('legacy.oncall.index') }}" class="tab tab-active">{{ __('Bereitschaft') }}</a>
        <a role="tab" href="{{ route('legacy.notdienst.index') }}" class="tab">{{ __('Notdienst') }}</a>
    </div>
    <x-filter-bar :action="route('legacy.oncall.index')">
            @if ($isAdmin)
                <div class="flex flex-col min-w-48">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                    <select name="user" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($users as $legacyUser)
                            <option value="{{ $legacyUser->id }}" @selected(($filters['user'] ?? '') == $legacyUser->id)>{{ $legacyUser->uname }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('legacy.oncall.index') }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
            @endif
            @if ($isAdmin)
                <a href="{{ route('legacy.oncall.create') }}" data-entry-modal-trigger class="btn btn-sm btn-outline">{{ __('Neue Bereitschaft') }}</a>
            @endif
    </x-filter-bar>

    @php
        $kpiTiles = [
            ['all',      __('Gesamt'),       'border-base-300'],
            ['today',    __('Heute aktiv'),  'border-primary/40'],
            ['upcoming', __('Kommend'),      'border-info/40'],
            ['past',     __('Vergangen'),    'border-neutral/40'],
        ];
    @endphp
    <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpiTiles as [$key, $label, $borderClass])
            <div class="rounded-box border bg-base-100 px-4 py-3 shadow-xs {{ $borderClass }}">
                <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($counts[$key] ?? 0, 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    <x-table size="xs" :pin-rows="true" scroll="flex">
                <thead class="bg-base-200">
                <tr>
                    <th class="w-16 text-center">ID</th>
                    <th>{{ __('Mitarbeiter') }}</th>
                    <th class="w-32 text-center">{{ __('Von') }}</th>
                    <th class="w-32 text-center">{{ __('Bis') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="text-center">{{ $item->id }}</td>
                        <td>{{ optional($item->mitarbeiter)->uname ?? __('Unbekannt') }}</td>
                        <td class="bg-info/10 text-center">{{ $item->von?->format('d.m.Y') ?? '-' }}</td>
                        <td class="bg-info/10 text-center">{{ $item->bis?->format('d.m.Y') ?? '-' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($isAdmin)
                                <div class="inline-flex items-center justify-end gap-1 whitespace-nowrap">
                                    <a href="{{ route('legacy.oncall.edit', $item) }}" data-entry-modal-trigger class="btn btn-sm btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('legacy.oncall.destroy', $item) }}" class="inline" onsubmit="return confirm('{{ __('Eintrag wirklich löschen?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-base-content/70">{{ __('Keine Bereitschaftseinträge gefunden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
    </x-table>

    <div class="flex-none rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-base-content/60">
                {{ __('Seite') }} {{ $items->currentPage() }} / {{ $items->lastPage() }}
                ({{ $items->total() }} {{ __('Einträge') }})
            </span>
            @if ($items->hasPages())
                {{ $items->links('vendor.pagination.daisyui-simple') }}
            @endif
        </div>
    </div>
</div>
@endsection
