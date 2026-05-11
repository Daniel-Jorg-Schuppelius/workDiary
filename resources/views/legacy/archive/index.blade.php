@extends('layouts.app')
@section('title', __('Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Archiv'))

@section('content')
    @php
        $legacyUsers = collect($users ?? []);
        $tabFilters = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
        $tabs = [
            'auftraege' => ['label' => __('Aufträge'), 'count' => $counts['auftraege'] ?? 0],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $counts['bereitschaft'] ?? 0],
            'notdienst' => ['label' => __('Notdienst'), 'count' => $counts['notdienst'] ?? 0],
        ];
        $activeTab = $tab ?? 'auftraege';
        $today = \Carbon\Carbon::today();
        $from7 = $today->copy()->subDays(7)->format('Y-m-d');
        $from30 = $today->copy()->subDays(30)->format('Y-m-d');
        $currentFrom = (string) ($filters['from'] ?? '');
        $currentTo = (string) ($filters['to'] ?? '');
        $currentStatus = (string) ($statusFilter ?? 'all');
        $baseFilters = $tabFilters; // bestehender Mitarbeiterfilter etc.
        unset($baseFilters['from'], $baseFilters['to'], $baseFilters['status']);

        $tileLink = function (array $delta) use ($baseFilters, $activeTab) {
            return route('legacy.archive.index', array_merge($baseFilters, ['tab' => $activeTab], $delta));
        };

        $kpiTiles = $activeTab === 'auftraege'
            ? [
                [
                    'label' => __('Gesamt'),
                    'value' => $tabKpis['total']   ?? 0,
                    'border' => 'border-base-300',
                    'href' => $tileLink([]),
                    'active' => $currentStatus === 'all' && $currentFrom === '' && $currentTo === '',
                ],
                [
                    'label' => __('Letzte 7d'),
                    'value' => $tabKpis['last7']   ?? 0,
                    'border' => 'border-success/40',
                    'href' => $tileLink(['from' => $from7]),
                    'active' => $currentFrom === $from7 && $currentTo === '',
                ],
                [
                    'label' => __('Letzte 30d'),
                    'value' => $tabKpis['last30']  ?? 0,
                    'border' => 'border-warning/40',
                    'href' => $tileLink(['from' => $from30]),
                    'active' => $currentFrom === $from30 && $currentTo === '',
                ],
                [
                    'label' => __('Mit Eskalation'),
                    'value' => $tabKpis['alert']   ?? 0,
                    'border' => 'border-error/40',
                    'href' => $tileLink(['status' => '3']),
                    'active' => $currentStatus === '3',
                ],
            ]
            : [
                [
                    'label' => __('Gesamt'),
                    'value' => $tabKpis['total']   ?? 0,
                    'border' => 'border-base-300',
                    'href' => $tileLink([]),
                    'active' => $currentFrom === '' && $currentTo === '',
                ],
                [
                    'label' => __('Letzte 7d'),
                    'value' => $tabKpis['last7']   ?? 0,
                    'border' => 'border-success/40',
                    'href' => $tileLink(['from' => $from7]),
                    'active' => $currentFrom === $from7 && $currentTo === '',
                ],
                [
                    'label' => __('Letzte 30d'),
                    'value' => $tabKpis['last30']  ?? 0,
                    'border' => 'border-warning/40',
                    'href' => $tileLink(['from' => $from30]),
                    'active' => $currentFrom === $from30 && $currentTo === '',
                ],
                [
                    'label' => __('Längste Schicht (Tage)'),
                    'value' => $tabKpis['longest'] ?? 0,
                    'border' => 'border-info/40',
                    'href' => null,
                    'active' => false,
                ],
            ];
    @endphp

    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        {{-- Kopfzeile mit Modus-Badge, Tabs + Cross-Links --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="badge badge-neutral">{{ __('Archiv') }}</span>
                <div role="tablist" class="tabs tabs-box">
                    @foreach ($tabs as $key => $info)
                        <a role="tab"
                           href="{{ route('legacy.archive.index', array_merge($tabFilters, ['tab' => $key])) }}"
                           class="tab {{ $activeTab === $key ? 'tab-active' : '' }}">
                            {{ $info['label'] }}
                            <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('legacy.diary.index') }}" class="btn btn-sm btn-ghost">
                    ← {{ __('Aktive Arbeitsliste') }}
                </a>
                @if ($isAdmin)
                    <a href="{{ route('legacy.archive.week') }}" class="btn btn-sm btn-outline">
                        {{ __('Archiv-Wochenansicht') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('legacy.archive.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="flex flex-wrap items-end gap-3">
                @if ($isAdmin)
                    <div class="flex flex-1 flex-col min-w-44">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                        <select name="user" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach ($legacyUsers as $legacyUser)
                                <option value="{{ $legacyUser->id }}" @selected(($filters['user'] ?? '') == $legacyUser->id)>{{ $legacyUser->uname }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($activeTab === 'auftraege')
                    <div class="flex flex-1 flex-col min-w-44">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm w-full">
                            <option value="all" @selected(($statusFilter ?? 'all') === 'all')>{{ __('Alle') }}</option>
                            <option value="2" @selected(($statusFilter ?? '') === '2')>{{ __('Offen') }}</option>
                            <option value="3" @selected(($statusFilter ?? '') === '3')>{{ __('Problem') }}</option>
                            <option value="1" @selected(($statusFilter ?? '') === '1')>{{ __('Bestätigt') }}</option>
                            <option value="-1" @selected(($statusFilter ?? '') === '-1')>{{ __('Erledigt') }}</option>
                        </select>
                    </div>
                @endif
                <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                    @if (! empty($tabFilters))
                        <a href="{{ route('legacy.archive.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- KPI-Kacheln (per Tab, klickbare Filter) --}}
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpiTiles as $tile)
                @php
                    $tileBaseClass = 'rounded-box border bg-base-100 px-4 py-3 shadow-xs ' . $tile['border'];
                    $activeRing = $tile['active'] ? ' border-primary ring-1 ring-primary/40' : '';
                @endphp
                @if (! empty($tile['href']))
                    <a href="{{ $tile['href'] }}"
                       class="{{ $tileBaseClass }} transition hover:border-primary hover:shadow-md{{ $activeRing }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                    </a>
                @else
                    <div class="{{ $tileBaseClass }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Tabellenbereich (scrollbar, sticky header) --}}
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            @if ($activeTab === 'auftraege')
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-24 text-center">{{ __('Status') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Inhalt') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diaryEntries as $entry)
                            @php
                                $g = (int) $entry->gelesen;
                                $badgeClass = match ($g) {
                                    -1 => 'badge-neutral',
                                    1  => 'badge-success',
                                    2  => 'badge-warning',
                                    3  => 'badge-error',
                                    default => 'badge-ghost',
                                };
                            @endphp
                            <tr class="hover">
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('legacy.archive.show', $entry) }}" data-entry-modal-trigger class="link link-hover">
                                        {{ optional($entry->mitarbeiter)->uname ?? '—' }}
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-sm {{ $badgeClass }}">{{ $entry->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->von?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->bis?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="text-sm">
                                    <a href="{{ route('legacy.archive.show', $entry) }}" data-entry-modal-trigger class="link link-hover">
                                        {{ truncate($entry->inhalt ?? '', 160) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($activeTab === 'bereitschaft')
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Dauer') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($onCallEntries as $entry)
                            @php
                                $duration = ($entry->von && $entry->bis)
                                    ? ((int) $entry->von->copy()->startOfDay()->diffInDays($entry->bis->copy()->startOfDay()) + 1)
                                    : null;
                            @endphp
                            <tr class="hover">
                                <td class="whitespace-nowrap">{{ optional($entry->mitarbeiter)->uname ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->von?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->bis?->format('d.m.Y') ?? '—' }}</td>
                                <td class="text-xs text-base-content/70">{{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Dauer') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notdienstEntries as $entry)
                            @php
                                $duration = ($entry->von && $entry->bis)
                                    ? ((int) $entry->von->copy()->startOfDay()->diffInDays($entry->bis->copy()->startOfDay()) + 1)
                                    : null;
                            @endphp
                            <tr class="hover">
                                <td class="whitespace-nowrap">{{ optional($entry->mitarbeiter)->uname ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->von?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->bis?->format('d.m.Y') ?? '—' }}</td>
                                <td class="text-xs text-base-content/70">{{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Paginierung des aktiven Tabs --}}
        <div class="flex flex-none items-center justify-between gap-3">
            <div class="text-xs text-base-content/60">
                @php
                    $activePaginator = match ($activeTab) {
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
            <details class="flex-none rounded-box border border-base-300 bg-base-200">
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
