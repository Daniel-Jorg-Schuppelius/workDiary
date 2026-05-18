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
            'urlaub'    => ['label' => __('Urlaub'),    'count' => $counts['urlaub'] ?? 0],
        ];
        $activeTab = $tab ?? 'auftraege';
        $currentFrom = (string) ($filters['from'] ?? '');
        $currentTo = (string) ($filters['to'] ?? '');
        $currentStatus = (string) ($statusFilter ?? 'all');
        $baseFilters = $tabFilters; // bestehender Mitarbeiterfilter etc.
        unset($baseFilters['from'], $baseFilters['to'], $baseFilters['status']);

        $tileLink = function (array $delta) use ($baseFilters, $activeTab) {
            return route('legacy.archive.index', array_merge($baseFilters, ['tab' => $activeTab], $delta));
        };

        $currentSort = $sort ?? '';
        $currentDir = $dir ?? 'desc';

        $kpiTiles = $activeTab === 'auftraege'
            ? [
                [
                    'label'  => __('Gesamt'),
                    'value'  => $tabKpis['total']    ?? 0,
                    'border' => 'border-base-300',
                    'href'   => $tileLink([]),
                    'active' => $currentStatus === 'all' && $currentFrom === '' && $currentTo === '',
                ],
                [
                    'label'  => __('Erledigt'),
                    'value'  => $tabKpis['erledigt'] ?? 0,
                    'border' => 'border-neutral/40',
                    'href'   => $tileLink(['status' => '-1']),
                    'active' => $currentStatus === '-1',
                ],
                [
                    'label'  => __('Offen'),
                    'value'  => $tabKpis['offen']    ?? 0,
                    'border' => 'border-warning/40',
                    'href'   => $tileLink(['status' => '2']),
                    'active' => $currentStatus === '2',
                ],
                [
                    'label'  => __('Mit Eskalation'),
                    'value'  => $tabKpis['alert']    ?? 0,
                    'border' => 'border-error/40',
                    'href'   => $tileLink(['status' => '3']),
                    'active' => $currentStatus === '3',
                ],
            ]
            : ($activeTab === 'urlaub'
                ? [
                    ['label' => __('Gesamt'),     'value' => $tabKpis['total']     ?? 0, 'border' => 'border-base-300',   'href' => $tileLink([]), 'active' => $currentFrom === '' && $currentTo === ''],
                    ['label' => __('Abgelehnt'),  'value' => $tabKpis['rejected']  ?? 0, 'border' => 'border-error/40',   'href' => null,          'active' => false],
                    ['label' => __('Storniert'),  'value' => $tabKpis['cancelled'] ?? 0, 'border' => 'border-neutral/40', 'href' => null,          'active' => false],
                    ['label' => __('Abgelaufen'), 'value' => $tabKpis['expired']   ?? 0, 'border' => 'border-info/40',    'href' => null,          'active' => false],
                ]
                : [
                    [
                        'label'  => __('Gesamt'),
                        'value'  => $tabKpis['total']   ?? 0,
                        'border' => 'border-base-300',
                        'href'   => $tileLink([]),
                        'active' => $currentFrom === '' && $currentTo === '',
                    ],
                    [
                        'label'  => __('Längste Schicht (Tage)'),
                        'value'  => $tabKpis['longest'] ?? 0,
                        'border' => 'border-info/40',
                        'href'   => null,
                        'active' => false,
                    ],
                    [
                        'label'  => __('Ø Dauer (Tage)'),
                        'value'  => $tabKpis['avg']     ?? 0,
                        'border' => 'border-primary/40',
                        'href'   => null,
                        'active' => false,
                    ],
                    [
                        'label'  => __('Mitarbeiter'),
                        'value'  => $tabKpis['users']   ?? 0,
                        'border' => 'border-secondary/40',
                        'href'   => null,
                        'active' => false,
                    ],
                ]
            );
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
                <a href="{{ route('legacy.diary.index', ['tab' => match($activeTab) { 'urlaub' => 'urlaub', default => $activeTab }]) }}" class="btn btn-sm btn-ghost">
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
                @elseif ($activeTab === 'urlaub')
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Typ') }}</span></label>
                        <select name="vtype" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Typen') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_VACATION }}" @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_VACATION)>{{ __('Urlaub') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_SICK }}"     @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_SICK)>{{ __('Krank') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_SPECIAL }}"  @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_SPECIAL)>{{ __('Sonderurlaub') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_UNPAID }}"   @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_UNPAID)>{{ __('Unbezahlt') }}</option>
                        </select>
                    </div>
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="vstatus" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Status') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_REJECTED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_REJECTED)>{{ __('Abgelehnt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_CANCELLED }}" @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_CANCELLED)>{{ __('Storniert') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_APPROVED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_APPROVED)>{{ __('Abgelaufen') }}</option>
                        </select>
                    </div>
                    @if ($vacationIsAdmin)
                        <div class="flex items-center gap-2 pb-2">
                            <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="toggle toggle-sm toggle-primary">
                            <label for="mine" class="label-text text-sm">{{ __('Nur meine') }}</label>
                        </div>
                    @endif
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
            @if ($activeTab === 'urlaub')
                @php $p = array_merge($tabFilters, ['tab' => 'urlaub']); @endphp
                <x-table table-sort="server" :route="route('legacy.archive.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            @if ($vacationIsAdmin)
                                <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            @endif
                            <x-table.th sort="typ">{{ __('Typ') }}</x-table.th>
                            <x-table.th sort="von" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                            <th class="max-w-xs">{{ __('Notiz') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($vacationEntries as $v)
                        @php
                            $statusBadge = match ($v->status) {
                                \App\Models\Vacation::STATUS_APPROVED  => 'badge-success',
                                \App\Models\Vacation::STATUS_REJECTED  => 'badge-error',
                                \App\Models\Vacation::STATUS_CANCELLED => 'badge-ghost',
                                default                                => 'badge-neutral',
                            };
                            $statusLabel = match ($v->status) {
                                \App\Models\Vacation::STATUS_APPROVED  => __('Abgelaufen'),
                                \App\Models\Vacation::STATUS_REJECTED  => __('Abgelehnt'),
                                \App\Models\Vacation::STATUS_CANCELLED => __('Storniert'),
                                default                                => $v->status,
                            };
                            $typeLabel = match ($v->type) {
                                \App\Models\Vacation::TYPE_VACATION => __('Urlaub'),
                                \App\Models\Vacation::TYPE_SICK     => __('Krank'),
                                \App\Models\Vacation::TYPE_SPECIAL  => __('Sonderurlaub'),
                                \App\Models\Vacation::TYPE_UNPAID   => __('Unbezahlt'),
                                default                             => $v->type,
                            };
                        @endphp
                        <tr class="hover">
                            @if ($vacationIsAdmin)
                                <td class="whitespace-nowrap">{{ $v->user?->name ?? '—' }}</td>
                            @endif
                            <td class="whitespace-nowrap">{{ $typeLabel }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->start_date->format('d.m.Y') }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->end_date->format('d.m.Y') }}</td>
                            <td><span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td class="max-w-xs truncate text-sm text-base-content/70">{{ $v->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">beach_access</span>' :colspan="$vacationIsAdmin ? 6 : 5" :title="__('Keine Einträge.')" compact />
                    @endforelse
                </x-table>
            @elseif ($activeTab === 'auftraege')
                @php $p = array_merge($tabFilters, ['tab' => 'auftraege']); @endphp
                <x-table table-sort="server" :route="route('legacy.archive.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="status" class="w-24 text-center">{{ __('Status') }}</x-table.th>
                            <x-table.th sort="von" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <th>{{ __('Inhalt') }}</th>
                        </tr>
                    </x-slot:head>
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
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">menu_book</span>' :colspan="5" :title="__('Keine Einträge.')" compact />
                    @endforelse
                </x-table>
            @elseif ($activeTab === 'bereitschaft')
                @php $p = array_merge($tabFilters, ['tab' => 'bereitschaft']); @endphp
                <x-table table-sort="server" :route="route('legacy.archive.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="von" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <th>{{ __('Dauer') }}</th>
                        </tr>
                    </x-slot:head>
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
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :colspan="4" :title="__('Keine Einträge.')" compact />
                    @endforelse
                </x-table>
            @elseif ($activeTab === 'notdienst')
                @php $p = array_merge($tabFilters, ['tab' => 'notdienst']); @endphp
                <x-table table-sort="server" :route="route('legacy.archive.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="von" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <th>{{ __('Dauer') }}</th>
                        </tr>
                    </x-slot:head>
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
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :colspan="4" :title="__('Keine Einträge.')" compact />
                    @endforelse
                </x-table>
            @endif
        </div>

        {{-- Paginierung des aktiven Tabs --}}
        @php
            $activePaginator = match ($activeTab) {
                'bereitschaft' => $onCallEntries,
                'notdienst'    => $notdienstEntries,
                'urlaub'       => $vacationEntries,
                default        => $diaryEntries,
            };
        @endphp
        @if ($activePaginator->total() > 0)
            <div class="flex-none">
                <p class="mb-1 text-xs text-base-content/60">
                    {{ __('Seite') }} {{ $activePaginator->currentPage() }} / {{ $activePaginator->lastPage() }}
                    · {{ $activePaginator->total() }} {{ __('Einträge') }}
                </p>
                @if ($activePaginator->hasPages())
                    <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                        {{ $activePaginator->links('vendor.pagination.daisyui-simple') }}
                    </div>
                @endif
            </div>
        @endif

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
