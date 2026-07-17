@extends('layouts.app')
@section('title', __('Arbeitsliste') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Arbeitsliste'))

@section('content')
    @php
        $tabs = [
            'diary'        => ['label' => __('Aufträge'),    'count' => $tabCounts['diary']],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $tabCounts['bereitschaft']],
            'notdienst'    => ['label' => __('Notdienst'),   'count' => $tabCounts['notdienst']],
            'urlaub'       => ['label' => __('Urlaub'),      'count' => $tabCounts['urlaub']],
            'krank'        => ['label' => __('Krank'),       'count' => $tabCounts['krank']],
        ];
        $tabFilters = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '');
    @endphp

    <x-index-page overflow="clip" :badge="__('Aktiv')" badge-tone="primary">
        <x-slot:actions>
                @if ($tab !== 'urlaub' && $tab !== 'krank')
                    <x-icon-btn icon="inventory_2" size="sm"
                                :href="route('archive.index', ['tab' => $tab === 'diary' ? 'diary' : $tab])"
                                show-label>{{ __('Archiv') }}</x-icon-btn>
                @endif
                @if ($tab === 'diary')
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('diary.create')"
                                show-label>{{ __('Neuer Auftrag') }}</x-icon-btn>
                @elseif ($tab === 'bereitschaft')
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('shifts.create')"
                                show-label>{{ __('Neue Bereitschaft') }}</x-icon-btn>
                @elseif ($tab === 'notdienst')
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assignments.create')"
                                show-label>{{ __('Neuer Notdienst') }}</x-icon-btn>
                @elseif ($tab === 'urlaub')
                    @can(\App\Enums\User\Permission::VacationEntitlementsManage->value)
                        <x-icon-btn icon="event_available" size="sm"
                                    :href="route('vacation-entitlements.index')"
                                    show-label>{{ __('Urlaubskonto') }}</x-icon-btn>
                    @endcan
                    @can('create', \App\Models\Vacation::class)
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('vacations.create') . '?dialog=1'"
                                    show-label>{{ __('Neuer Antrag') }}</x-icon-btn>
                    @endcan
                @endif
                @if ($tab === 'krank')
                    @can('create', \App\Models\SickLeave::class)
                        <x-icon-btn icon="add" tone="warning" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('sick-leaves.create') . '?dialog=1'"
                                    show-label>{{ __('Krank melden') }}</x-icon-btn>
                    @endcan
                @endif
            </x-slot:actions>

        {{-- Filter --}}
        <x-filter-bar :action="route('duties.index')" :reset="! empty($tabFilters) ? route('duties.index', ['tab' => $tab]) : null">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab === 'diary')
                @include('diary._filter_fields', ['idPrefix' => 'duties'])
            @elseif ($tab === 'urlaub')
                @if ($isAdmin)
                    <x-filter-field :label="__('Mitarbeiter')" for="duties-user" class="flex-1 min-w-44">
                        <select id="duties-user" name="user_id" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->sqid }}" @selected((string) ($filters['user_id'] ?? '') === $u->sqid)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </x-filter-field>
                @endif
                <x-filter-field :label="__('Typ')" for="duties-vtype" class="flex-1 min-w-40">
                    <select id="duties-vtype" name="vtype" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle Typen') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationType::Vacation->value }}" @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Vacation->value)>{{ __('Urlaub') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationType::Sick->value }}"     @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Sick->value)>{{ __('Krank') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationType::Special->value }}"  @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Special->value)>{{ __('Sonderurlaub') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationType::Unpaid->value }}"   @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Unpaid->value)>{{ __('Unbezahlt') }}</option>
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Status')" for="duties-vstatus" class="flex-1 min-w-40">
                    <select id="duties-vstatus" name="vstatus" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle Status') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationStatus::Pending->value }}"   @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Pending->value)>{{ __('Ausstehend') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationStatus::Approved->value }}"  @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Approved->value)>{{ __('Genehmigt') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationStatus::Rejected->value }}"  @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Rejected->value)>{{ __('Abgelehnt') }}</option>
                        <option value="{{ \App\Enums\Vacation\VacationStatus::Cancelled->value }}" @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Cancelled->value)>{{ __('Storniert') }}</option>
                    </select>
                </x-filter-field>
            @elseif ($tab === 'krank')
                @if ($isAdmin)
                    <x-filter-field :label="__('Mitarbeiter')" for="duties-kuser" class="flex-1 min-w-44">
                        <select id="duties-kuser" name="user_id" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->sqid }}" @selected((string) ($filters['user_id'] ?? '') === $u->sqid)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </x-filter-field>
                @endif
                <x-filter-field :label="__('Art')" for="duties-kkind" class="flex-1 min-w-40">
                    <select id="duties-kkind" name="kkind" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach (\App\Enums\Sickness\SickLeaveKind::cases() as $kindCase)
                            <option value="{{ $kindCase->value }}" @selected(($filters['kkind'] ?? '') === $kindCase->value)>{{ $kindCase->label() }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Status')" for="duties-kstatus" class="flex-1 min-w-40">
                    <select id="duties-kstatus" name="kstatus" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle Status') }}</option>
                        <option value="active"    @selected(($filters['kstatus'] ?? '') === 'active')>{{ __('Aktiv') }}</option>
                        <option value="cancelled" @selected(($filters['kstatus'] ?? '') === 'cancelled')>{{ __('Storniert') }}</option>
                    </select>
                </x-filter-field>
            @endif
            @if ($tab === 'diary')
                <x-slot:extra>
                    <div class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-sm btn-outline gap-1">
                            <x-icon name="download" /><span>{{ __('Export') }}</span>
                        </label>
                        <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                            <li><a href="{{ route('diary.export.csv', array_filter($filters)) }}">{{ __('CSV') }}</a></li>
                            <li><a href="{{ route('diary.export.pdf', array_filter($filters)) }}" target="_blank">{{ __('PDF (Druckansicht)') }}</a></li>
                        </ul>
                    </div>
                </x-slot:extra>
            @endif
        </x-filter-bar>

        {{-- Tabs --}}
        @include('duties._tab_strip', ['tabs' => $tabs, 'tab' => $tab, 'tabFilters' => $tabFilters])

        {{-- KPI-Kacheln --}}
        @php
            $kpiTiles = match ($tab) {
                'diary' => [
                    ['label' => __('Gesamt'),         'value' => $diaryCounts['all'],      'tone' => 'neutral', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary'])),                             'statusKey' => 'all'],
                    ['label' => __('Offen'),          'value' => $diaryCounts['open'],     'tone' => 'warning', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '2'])),        'statusKey' => '2'],
                    ['label' => __('In Bearbeitung'), 'value' => $diaryCounts['progress'], 'tone' => 'info',    'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => 'progress'])), 'statusKey' => 'progress'],
                    ['label' => __('Probleme'),       'value' => $diaryCounts['alert'],    'tone' => 'error',   'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => 'alert'])),    'statusKey' => 'alert'],
                    ['label' => __('Erledigt'),       'value' => $diaryCounts['done'],     'tone' => 'success', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => 'done'])),     'statusKey' => 'done'],
                    ['label' => __('Storniert'),      'value' => $diaryCounts['cancelled'],'tone' => 'neutral', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '8'])),        'statusKey' => '8'],
                ],
                'bereitschaft' => [
                    ['label' => __('Gesamt'),                 'value' => $shiftKpis['total'],   'tone' => 'neutral',   'href' => null, 'statusKey' => null],
                    ['label' => __('Längste Schicht (Tage)'), 'value' => $shiftKpis['longest'], 'tone' => 'info',      'href' => null, 'statusKey' => null],
                    ['label' => __('Ø Dauer (Tage)'),         'value' => $shiftKpis['avg'],     'tone' => 'primary',   'href' => null, 'statusKey' => null, 'format' => 'decimal'],
                    ['label' => __('Mitarbeiter'),            'value' => $shiftKpis['users'],   'tone' => 'secondary', 'href' => null, 'statusKey' => null],
                ],
                'notdienst' => [
                    ['label' => __('Gesamt'),                 'value' => $assignmentKpis['total'],   'tone' => 'neutral',   'href' => null, 'statusKey' => null],
                    ['label' => __('Längste Schicht (Tage)'), 'value' => $assignmentKpis['longest'], 'tone' => 'info',      'href' => null, 'statusKey' => null],
                    ['label' => __('Ø Dauer (Tage)'),         'value' => $assignmentKpis['avg'],     'tone' => 'primary',   'href' => null, 'statusKey' => null, 'format' => 'decimal'],
                    ['label' => __('Mitarbeiter'),            'value' => $assignmentKpis['users'],   'tone' => 'secondary', 'href' => null, 'statusKey' => null],
                ],
                'krank' => [
                    ['label' => __('Gesamt'),       'value' => $sickKpis['total'],     'tone' => 'neutral',   'href' => null, 'statusKey' => null],
                    ['label' => __('Aktuell krank'),'value' => $sickKpis['active'],    'tone' => 'warning',   'href' => null, 'statusKey' => null],
                    ['label' => __('Storniert'),    'value' => $sickKpis['cancelled'], 'tone' => 'error',     'href' => null, 'statusKey' => null],
                    ['label' => __('Mitarbeiter'),  'value' => $sickKpis['users'],     'tone' => 'secondary', 'href' => null, 'statusKey' => null],
                ],
                default => [
                    ['label' => __('Gesamt'),           'value' => $vacationKpis['total'],    'tone' => 'neutral', 'href' => null, 'statusKey' => null],
                    ['label' => __('Ausstehend'),       'value' => $vacationKpis['pending'],  'tone' => 'warning', 'href' => null, 'statusKey' => null],
                    ['label' => __('Genehmigt (Jahr)'), 'value' => $vacationKpis['approved'], 'tone' => 'success', 'href' => null, 'statusKey' => null],
                    ['label' => __('Abgelehnt'),        'value' => $vacationKpis['rejected'], 'tone' => 'error',   'href' => null, 'statusKey' => null],
                ],
            };
            $activeStatus = (string) ($filters['status'] ?? 'all');
        @endphp
        <div class="flex-none grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($kpiTiles as $tile)
                <x-kpi-tile
                    :label="$tile['label']"
                    :value="$tile['value']"
                    :tone="$tile['tone']"
                    :href="$tile['href']"
                    :format="$tile['format'] ?? 'int'"
                    :active="$tab === 'diary' && $tile['statusKey'] !== null && $activeStatus === $tile['statusKey']" />
            @endforeach
        </div>

        {{-- Inhalt --}}
        @switch ($tab)
            @case ('diary')
                @include('duties._tab_diary')
                @break
            @case ('bereitschaft')
                @include('duties._tab_bereitschaft')
                @break
            @case ('notdienst')
                @include('duties._tab_notdienst')
                @break
            @case ('urlaub')
                @include('duties._tab_urlaub')
                @break
            @case ('krank')
                @include('duties._tab_krank')
                @break
        @endswitch

    </x-index-page>
@endsection

