@extends('layouts.app')
@section('title', __('Legacy Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Archiv'))

@section('content')
    @php
        $legacyUsers = collect($users ?? []);
        $tabFilters = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
        $tabs = [
            'auftraege' => ['label' => __('Aufträge'), 'count' => $counts['auftraege'] ?? 0],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $counts['bereitschaft'] ?? 0],
            'notdienst' => ['label' => __('Notdienst'), 'count' => $counts['notdienst'] ?? 0],
        ];
    @endphp

    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        {{-- Kopfzeile mit Tabs + Wochenansicht --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div role="tablist" class="tabs tabs-box">
                @foreach ($tabs as $key => $info)
                    <a role="tab"
                       href="{{ route('legacy.archive.index', array_merge($tabFilters, ['tab' => $key])) }}"
                       class="tab {{ ($tab ?? 'auftraege') === $key ? 'tab-active' : '' }}">
                        {{ $info['label'] }}
                        <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                    </a>
                @endforeach
            </div>
            @if ($isAdmin)
                <a href="{{ route('legacy.archive.week') }}" class="btn btn-sm btn-outline">
                    {{ __('Archiv-Wochenansicht') }}
                </a>
            @endif
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('legacy.archive.index') }}" class="rounded-box border border-base-300 bg-base-200 p-3">
            <input type="hidden" name="tab" value="{{ $tab ?? 'auftraege' }}">
            <div class="flex flex-wrap items-end gap-3">
                @if ($isAdmin)
                    <div class="min-w-44">
                        <label class="label py-0 text-xs font-semibold">{{ __('Mitarbeiter') }}</label>
                        <select name="user" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach ($legacyUsers as $legacyUser)
                                <option value="{{ $legacyUser->id }}" @selected(($filters['user'] ?? '') == $legacyUser->id)>{{ $legacyUser->uname }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="label py-0 text-xs font-semibold">{{ __('Von') }}</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input input-bordered input-sm">
                </div>
                <div>
                    <label class="label py-0 text-xs font-semibold">{{ __('Bis') }}</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input input-bordered input-sm">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
                @if (! empty($tabFilters))
                    <a href="{{ route('legacy.archive.index', ['tab' => $tab ?? 'auftraege']) }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
                @endif
            </div>
        </form>

        {{-- Tabellenbereich (scrollbar, sticky header) --}}
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            @if (($tab ?? 'auftraege') === 'auftraege')
                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th>{{ __('Inhalt') }}</th>
                            <th class="whitespace-nowrap">{{ __('Bis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diaryEntries as $entry)
                            <tr>
                                <td class="whitespace-nowrap">{{ optional($entry->mitarbeiter)->uname ?? '—' }}</td>
                                <td class="text-sm">{{ truncate($entry->inhalt ?? '', 160) }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->bis?->format('d.m.Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif (($tab ?? '') === 'bereitschaft')
                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="whitespace-nowrap">{{ __('Bis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($onCallEntries as $entry)
                            <tr>
                                <td class="whitespace-nowrap">{{ optional($entry->mitarbeiter)->uname ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->von?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->bis?->format('d.m.Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="whitespace-nowrap">{{ __('Bis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notdienstEntries as $entry)
                            <tr>
                                <td class="whitespace-nowrap">{{ optional($entry->mitarbeiter)->uname ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->von?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->bis?->format('d.m.Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Paginierung des aktiven Tabs --}}
        <div class="flex items-center justify-between gap-3">
            <div class="text-xs text-base-content/60">
                @php
                    $activePaginator = match ($tab ?? 'auftraege') {
                        'bereitschaft' => $onCallEntries,
                        'notdienst'    => $notdienstEntries,
                        default        => $diaryEntries,
                    };
                @endphp
                @if ($activePaginator->total() > 0)
                    {{ __('Seite') }} {{ $activePaginator->currentPage() }} / {{ $activePaginator->lastPage() }}
                    · {{ $activePaginator->total() }} {{ __('Einträge') }}
                @endif
            </div>
            <div>
                @if ($activePaginator->hasPages())
                    {{ $activePaginator->links('vendor.pagination.daisyui-simple') }}
                @endif
            </div>
        </div>

        {{-- Admin: Archivierung starten --}}
        @if ($isAdmin)
            <details class="rounded-box border border-base-300 bg-base-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                    ⚙ {{ __('Archivierung starten') }}
                </summary>
                <form method="POST" action="{{ route('legacy.archive.run') }}" class="px-4 pb-4 pt-2">
                    @csrf
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="label py-0 text-xs font-semibold">{{ __('Archiv bis') }}</label>
                            <select name="months" class="select select-bordered select-sm">
                                <option value="3">3 {{ __('Monate') }}</option>
                                <option value="6">6 {{ __('Monate') }}</option>
                                <option value="9">9 {{ __('Monate') }}</option>
                                <option value="12">12 {{ __('Monate') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label py-0 text-xs font-semibold">{{ __('Mitarbeiter') }} ({{ __('Optional') }})</label>
                            <select name="user" class="select select-bordered select-sm">
                                <option value="">{{ __('Alle') }}</option>
                                @foreach ($legacyUsers as $legacyUser)
                                    <option value="{{ $legacyUser->id }}">{{ $legacyUser->uname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm"
                                data-confirm-dialog
                                data-confirm-title="{{ __('Archivierung starten') }}"
                                data-confirm-message="{{ __('Archivierung wirklich starten?') }}"
                                data-confirm-label="{{ __('Starten') }}">
                            {{ __('Archivierung starten') }}
                        </button>
                    </div>
                </form>
            </details>
        @endif
    </div>
@endsection
