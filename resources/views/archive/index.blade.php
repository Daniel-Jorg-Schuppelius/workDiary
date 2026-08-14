{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Archiv'))

@section('content')
    @php
        use App\Enums\Vacation\VacationStatus;
        use App\Enums\Vacation\VacationType;

        /** @var string $tab */
        /** @var bool $isAdmin */
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var array<string,int> $counts */
        /** @var array<string,int> $tabKpis */
        /** @var array<string,mixed> $filters */
        /** @var string $statusFilter */
        $tabs = [
            'diary'        => ['label' => __('Aufträge'),   'count' => $counts['diary']],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $counts['bereitschaft']],
            'notdienst'    => ['label' => __('Notdienst'),  'count' => $counts['notdienst']],
            'urlaub'       => ['label' => __('Urlaub'),     'count' => $counts['urlaub']],
        ];
        $tabFilters = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '');
        $baseFilters = $tabFilters;
        unset($baseFilters['from'], $baseFilters['to'], $baseFilters['status']);
        $currentFrom   = (string) ($filters['from']   ?? '');
        $currentTo     = (string) ($filters['to']     ?? '');
        $currentStatus = (string) ($statusFilter      ?? 'all');

        $tileLink = fn(array $delta) => route('archive.index', array_merge($baseFilters, ['tab' => $tab], $delta));

        $kpiTiles = $tab === 'diary'
            ? [
                ['label' => __('Gesamt'),        'value' => $tabKpis['total'],    'border' => 'border-base-300',    'href' => $tileLink([]),               'active' => $currentStatus === 'all' && $currentFrom === '' && $currentTo === ''],
                ['label' => __('Erledigt'),       'value' => $tabKpis['erledigt'], 'border' => 'border-neutral/40',  'href' => $tileLink(['status' => '-1']), 'active' => $currentStatus === '-1'],
                ['label' => __('Offen'),          'value' => $tabKpis['offen'],    'border' => 'border-warning/40',  'href' => $tileLink(['status' => '2']),  'active' => $currentStatus === '2'],
                ['label' => __('Mit Eskalation'), 'value' => $tabKpis['alert'],    'border' => 'border-error/40',    'href' => $tileLink(['status' => '3']),  'active' => $currentStatus === '3'],
            ]
            : ($tab === 'urlaub'
                ? [
                    ['label' => __('Gesamt'),     'value' => $tabKpis['total'],     'border' => 'border-base-300',   'href' => $tileLink([]), 'active' => $currentFrom === '' && $currentTo === ''],
                    ['label' => __('Abgelehnt'),  'value' => $tabKpis['rejected'],  'border' => 'border-error/40',   'href' => null,          'active' => false],
                    ['label' => __('Storniert'),  'value' => $tabKpis['cancelled'], 'border' => 'border-neutral/40', 'href' => null,          'active' => false],
                    ['label' => __('Abgelaufen'), 'value' => $tabKpis['expired'],   'border' => 'border-info/40',    'href' => null,          'active' => false],
                ]
                : [
                    ['label' => __('Gesamt'),                  'value' => $tabKpis['total'],   'border' => 'border-base-300',     'href' => $tileLink([]), 'active' => $currentFrom === '' && $currentTo === ''],
                    ['label' => __('Längste Schicht (Tage)'),  'value' => $tabKpis['longest'], 'border' => 'border-info/40',      'href' => null,          'active' => false],
                    ['label' => __('Ø Dauer (Tage)'),          'value' => $tabKpis['avg'],     'border' => 'border-primary/40',   'href' => null,          'active' => false],
                    ['label' => __('Mitarbeiter'),             'value' => $tabKpis['users'],   'border' => 'border-secondary/40', 'href' => null,          'active' => false],
                ]
            );
    @endphp

    <x-index-page overflow="clip" :badge="__('Archiv')" badge-tone="neutral">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm"
                        :href="route('duties.index', ['tab' => match($tab) { 'diary' => 'diary', 'urlaub' => 'urlaub', default => $tab }])"
                        show-label>{{ __('Aktive Arbeitsliste') }}</x-icon-btn>
        </x-slot:actions>

        {{-- Filter --}}
        <x-filter-bar :action="route('archive.index')" :reset="! empty($tabFilters) ? route('archive.index', ['tab' => $tab]) : null">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($isAdmin)
                <x-filter-field :label="__('Mitarbeiter')" for="arc-user" class="flex-1 min-w-44">
                    <select id="arc-user" name="user_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->sqid }}" @selected((string) ($filters['user_id'] ?? '') === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
            @if ($tab === 'diary')
                <x-filter-field :label="__('Status')" for="arc-status" class="flex-1 min-w-44">
                    <select id="arc-status" name="status" class="select select-bordered select-sm w-full">
                        <option value="all"  @selected($currentStatus === 'all')>{{ __('Alle') }}</option>
                        <option value="2"    @selected($currentStatus === '2')>{{ __('Offen') }}</option>
                        <option value="3"    @selected($currentStatus === '3')>{{ __('Problem') }}</option>
                        <option value="1"    @selected($currentStatus === '1')>{{ __('Bestätigt') }}</option>
                        <option value="-1"   @selected($currentStatus === '-1')>{{ __('Erledigt') }}</option>
                    </select>
                </x-filter-field>
            @elseif ($tab === 'urlaub')
                <x-filter-field :label="__('Typ')" for="arc-vtype" class="flex-1 min-w-40">
                    <select id="arc-vtype" name="vtype" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle Typen') }}</option>
                        <option value="{{ VacationType::Vacation->value }}" @selected(($filters['vtype'] ?? '') === VacationType::Vacation->value)>{{ __('Urlaub') }}</option>
                        <option value="{{ VacationType::Sick->value }}"     @selected(($filters['vtype'] ?? '') === VacationType::Sick->value)>{{ __('Krank') }}</option>
                        <option value="{{ VacationType::Special->value }}"  @selected(($filters['vtype'] ?? '') === VacationType::Special->value)>{{ __('Sonderurlaub') }}</option>
                        <option value="{{ VacationType::Unpaid->value }}"   @selected(($filters['vtype'] ?? '') === VacationType::Unpaid->value)>{{ __('Unbezahlt') }}</option>
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Status')" for="arc-vstatus" class="flex-1 min-w-40">
                    <select id="arc-vstatus" name="vstatus" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle Status') }}</option>
                        <option value="{{ VacationStatus::Rejected->value }}"  @selected(($filters['vstatus'] ?? '') === VacationStatus::Rejected->value)>{{ __('Abgelehnt') }}</option>
                        <option value="{{ VacationStatus::Cancelled->value }}" @selected(($filters['vstatus'] ?? '') === VacationStatus::Cancelled->value)>{{ __('Storniert') }}</option>
                        <option value="{{ VacationStatus::Approved->value }}"  @selected(($filters['vstatus'] ?? '') === VacationStatus::Approved->value)>{{ __('Abgelaufen') }}</option>
                    </select>
                </x-filter-field>
            @endif
        </x-filter-bar>

        {{-- Tabs --}}
        {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
        <x-tab-nav :items="collect($tabs)->map(fn($info, $key) => [
            'label' => $info['label'],
            'count' => $info['count'],
            'route' => 'archive.index',
            'params' => array_merge($tabFilters, ['tab' => $key]),
            'active' => $tab === $key,
        ])->values()->all()" />

        {{-- KPI-Kacheln --}}
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpiTiles as $tile)
                @php
                    $base = 'rounded-box border bg-base-100 px-4 py-3 shadow-xs ' . $tile['border'];
                    $ring = $tile['active'] ? ' border-primary ring-1 ring-primary/40' : '';
                @endphp
                @if (! empty($tile['href']))
                    <a href="{{ $tile['href'] }}"
                       class="{{ $base }} transition hover:border-primary hover:shadow-md{{ $ring }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $tile['value'], 0, withThousandsSeparator: true) }}</p>
                    </a>
                @else
                    <div class="{{ $base }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $tile['value'], 0, withThousandsSeparator: true) }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Tabellenbereich --}}
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            @if ($tab === 'urlaub')
                <?php $p = array_merge($filters ?? [], ['tab' => 'urlaub']); ?>
                <x-table table-sort="server" :route="route('archive.index')" :current-sort="$sort ?? null" :current-dir="$dir ?? 'desc'" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            @if ($isAdmin)
                                <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            @endif
                            <x-table.th sort="typ">{{ __('Typ') }}</x-table.th>
                            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="end" default="desc" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                            <th class="max-w-xs">{{ __('Notiz') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($vacationEntries as $v)
                        @php
                            $statusBadge = match ($v->status) {
                                VacationStatus::Approved  => 'badge-success',
                                VacationStatus::Rejected  => 'badge-error',
                                VacationStatus::Cancelled => 'badge-ghost',
                                default                   => 'badge-neutral',
                            };
                            $statusLabel = match ($v->status) {
                                VacationStatus::Approved  => __('Abgelaufen'),
                                VacationStatus::Rejected  => __('Abgelehnt'),
                                VacationStatus::Cancelled => __('Storniert'),
                                default                   => $v->statusLabel(),
                            };
                            $typeLabel = $v->typeLabel();
                        @endphp
                        <tr class="hover">
                            @if ($isAdmin)
                                <td class="whitespace-nowrap">{{ $v->user?->name ?? '—' }}</td>
                            @endif
                            <td class="whitespace-nowrap">{{ $typeLabel }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->start_date->fdate() }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->end_date->fdate() }}</td>
                            <td><span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td class="max-w-xs truncate text-sm text-base-content/70">{{ $v->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">beach_access</span>' :colspan="$isAdmin ? 6 : 5" :title="__('Keine Einträge')" compact />
                    @endforelse
                </x-table>
            @elseif ($tab === 'diary')
                <?php $p = array_merge($filters ?? [], ['tab' => 'diary']); ?>
                <x-table table-sort="server" :route="route('archive.index')" :current-sort="$sort ?? null" :current-dir="$dir ?? 'desc'" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="status" class="w-24 text-center">{{ __('Status') }}</x-table.th>
                            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="end" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <x-table.th sort="archived" default="desc" class="w-36 whitespace-nowrap">{{ __('Archiviert am') }}</x-table.th>
                            <th>{{ __('Inhalt') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($diaryEntries as $entry)
                        <tr class="hover">
                            <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                            <td class="text-center">
                                <span @class([
                                    'badge badge-sm',
                                    'badge-success' => $entry->statusTone() === 'done',
                                    'badge-info'    => $entry->statusTone() === 'progress',
                                    'badge-warning' => $entry->statusTone() === 'open',
                                    'badge-error'   => $entry->statusTone() === 'alert',
                                    'badge-ghost'   => $entry->statusTone() === 'neutral',
                                ])>{{ $entry->statusLabel() }}</span>
                            </td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->start_at?->fdatetime() ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->end_at?->fdatetime() ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->archived_at?->fdate() ?? '—' }}</td>
                            <td class="text-sm">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content ?? '', 160) }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">menu_book</span>' :colspan="6" :title="__('Keine Einträge')" compact />
                    @endforelse
                </x-table>
            @elseif ($tab === 'bereitschaft')
                <?php $p = array_merge($filters ?? [], ['tab' => 'bereitschaft']); ?>
                <x-table table-sort="server" :route="route('archive.index')" :current-sort="$sort ?? null" :current-dir="$dir ?? 'desc'" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="end" default="desc" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <th>{{ __('Dauer') }}</th>
                            <th>{{ __('Notiz') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($shiftEntries as $entry)
                        @php
                            $duration = ($entry->start_at && $entry->end_at)
                                ? ((int) $entry->start_at->copy()->startOfDay()->diffInDays($entry->end_at->copy()->startOfDay()) + 1)
                                : null;
                        @endphp
                        <tr class="hover">
                            <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $entry->start_at?->fdatetime() ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $entry->end_at?->fdatetime() ?? '—' }}</td>
                            <td class="text-xs text-base-content/70">
                                {{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}
                            </td>
                            <td class="max-w-xs truncate text-sm">{{ $entry->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :colspan="5" :title="__('Keine Einträge')" compact />
                    @endforelse
                </x-table>
            @else
                <?php $p = array_merge($filters ?? [], ['tab' => 'notdienst']); ?>
                <x-table table-sort="server" :route="route('archive.index')" :current-sort="$sort ?? null" :current-dir="$dir ?? 'desc'" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="end" default="desc" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
                            <th>{{ __('Dauer') }}</th>
                            <th>{{ __('Grund') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($assignmentEntries as $entry)
                        @php
                            $duration = ($entry->start_at && $entry->end_at)
                                ? ((int) $entry->start_at->copy()->startOfDay()->diffInDays($entry->end_at->copy()->startOfDay()) + 1)
                                : null;
                        @endphp
                        <tr class="hover">
                            <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $entry->start_at?->fdatetime() ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $entry->end_at?->fdatetime() ?? '—' }}</td>
                            <td class="text-xs text-base-content/70">
                                {{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}
                            </td>
                            <td class="max-w-xs truncate text-sm">{{ $entry->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :colspan="5" :title="__('Keine Einträge')" compact />
                    @endforelse
                </x-table>
            @endif
        </div>

        {{-- Paginierung --}}
        @php
            $activePaginator = match ($tab) {
                'bereitschaft' => $shiftEntries,
                'notdienst'    => $assignmentEntries,
                'urlaub'       => $vacationEntries,
                default        => $diaryEntries,
            };
        @endphp
        <x-pagination :paginator="$activePaginator" standing />

        {{-- Admin: Archivierung starten --}}
        @if ($isAdmin)
            <details class="flex-none rounded-box border border-base-300 bg-base-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                    ⚙ {{ __('Archivierung starten') }}
                </summary>
                <form method="POST" action="{{ route('archive.run') }}" class="px-4 pb-4 pt-2">
                    @csrf
                    <p class="mb-3 text-sm text-base-content/70">
                        {{ __('Archiviert alle erledigten Auftragsbucheinträge und abgelaufenen Dienste, die älter als :days Tage sind.', ['days' => config('archive.threshold_days', 30)]) }}
                    </p>
                    <x-button type="submit" tone="warning" size="sm"
                              data-confirm-dialog
                              data-confirm-title="{{ __('Archivierung starten') }}"
                              data-confirm-message="{{ __('Archivierung jetzt ausführen?') }}">
                        {{ __('Archivierung starten') }}
                    </x-button>
                </form>
            </details>
        @endif

    </x-index-page>
@endsection
