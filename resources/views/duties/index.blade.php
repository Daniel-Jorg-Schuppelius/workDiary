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
        ];
        $tabFilters = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '');
    @endphp

    <x-page-shell overflow="clip">

        {{-- Toolbar: Status-Badge + Aktions-Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <span class="badge badge-primary">{{ __('Aktiv') }}</span>
            <div class="flex items-center gap-2">
                @if ($tab === 'diary')
                    <a href="{{ route('diary.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                        <x-icon name="add" /><span>{{ __('Neuer Auftrag') }}</span>
                    </a>
                @elseif ($tab === 'bereitschaft')
                    <a href="{{ route('shifts.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                        <x-icon name="add" /><span>{{ __('Neue Bereitschaft') }}</span>
                    </a>
                @elseif ($tab === 'notdienst')
                    <a href="{{ route('assignments.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                        <x-icon name="add" /><span>{{ __('Neuer Notdienst') }}</span>
                    </a>
                @else
                    @can('create', \App\Models\Vacation::class)
                        <a href="{{ route('vacations.create') }}?dialog=1" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                            <x-icon name="add" /><span>{{ __('Neuer Antrag') }}</span>
                        </a>
                    @endcan
                @endif
                @if ($tab !== 'urlaub')
                    <a href="{{ route('archive.index', ['tab' => $tab === 'diary' ? 'diary' : $tab]) }}" class="btn btn-sm btn-ghost gap-1">
                        <x-icon name="inventory_2" /><span>{{ __('Archiv') }}</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div role="tablist" class="tabs tabs-box self-start">
            @foreach ($tabs as $key => $info)
                <a role="tab"
                   href="{{ route('duties.index', array_merge($tabFilters, ['tab' => $key])) }}"
                   class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                    {{ $info['label'] }}
                    <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                </a>
            @endforeach
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('duties.index') }}"
              class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-wrap items-end gap-3">
                @if ($tab === 'diary')
                    <div class="flex-1 min-w-52">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Suche') }}</span></label>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="{{ __('Inhalt oder Antwort …') }}"
                               class="input input-bordered input-sm w-full">
                    </div>
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm w-full">
                            <option value="all"  @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                            <option value="2"    @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                            <option value="3"    @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                            <option value="1"    @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                            <option value="-1"   @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
                        </select>
                    </div>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-col min-w-36">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Tag') }}</span></label>
                            <select name="tag" class="select select-bordered select-sm">
                                <option value="">—</option>
                                @foreach ($allTags as $tag)
                                    <option value="{{ $tag->id }}" @selected((int) ($filters['tag'] ?? 0) === $tag->id)>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex items-center gap-2 pb-2">
                        <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="checkbox checkbox-primary checkbox-sm">
                        <label for="mine" class="text-sm text-base-content/75">{{ __('Nur meine') }}</label>
                    </div>
                @elseif ($tab === 'urlaub')
                    @if ($isAdmin)
                        <div class="flex flex-1 flex-col min-w-44">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user_id" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
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
                            <option value="{{ \App\Models\Vacation::STATUS_PENDING }}"   @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_PENDING)>{{ __('Ausstehend') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_APPROVED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_APPROVED)>{{ __('Genehmigt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_REJECTED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_REJECTED)>{{ __('Abgelehnt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_CANCELLED }}" @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_CANCELLED)>{{ __('Storniert') }}</option>
                        </select>
                    </div>
                @endif
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                    @if (! empty($tabFilters))
                        <a href="{{ route('duties.index', ['tab' => $tab]) }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- KPI-Kacheln --}}
        @php
            $kpiTiles = $tab === 'diary'
                ? [
                    ['label' => __('Gesamt'),   'value' => $diaryCounts['all'],   'tone' => 'neutral', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary'])),                    'statusKey' => 'all'],
                    ['label' => __('Offen'),    'value' => $diaryCounts['open'],  'tone' => 'warning', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '2'])),  'statusKey' => '2'],
                    ['label' => __('Probleme'), 'value' => $diaryCounts['alert'], 'tone' => 'error',   'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '3'])),  'statusKey' => '3'],
                    ['label' => __('Erledigt'), 'value' => $diaryCounts['done'],  'tone' => 'success', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '-1'])), 'statusKey' => '-1'],
                ]
                : ($tab === 'bereitschaft'
                    ? [
                        ['label' => __('Gesamt'),                 'value' => $shiftKpis['total'],   'tone' => 'neutral',   'href' => null, 'statusKey' => null],
                        ['label' => __('Längste Schicht (Tage)'), 'value' => $shiftKpis['longest'], 'tone' => 'info',      'href' => null, 'statusKey' => null],
                        ['label' => __('Ø Dauer (Tage)'),         'value' => $shiftKpis['avg'],     'tone' => 'primary',   'href' => null, 'statusKey' => null, 'format' => 'decimal'],
                        ['label' => __('Mitarbeiter'),            'value' => $shiftKpis['users'],   'tone' => 'secondary', 'href' => null, 'statusKey' => null],
                    ]
                    : ($tab === 'notdienst'
                        ? [
                            ['label' => __('Gesamt'),                 'value' => $assignmentKpis['total'],   'tone' => 'neutral',   'href' => null, 'statusKey' => null],
                            ['label' => __('Längste Schicht (Tage)'), 'value' => $assignmentKpis['longest'], 'tone' => 'info',      'href' => null, 'statusKey' => null],
                            ['label' => __('Ø Dauer (Tage)'),         'value' => $assignmentKpis['avg'],     'tone' => 'primary',   'href' => null, 'statusKey' => null, 'format' => 'decimal'],
                            ['label' => __('Mitarbeiter'),            'value' => $assignmentKpis['users'],   'tone' => 'secondary', 'href' => null, 'statusKey' => null],
                        ]
                        : [
                            ['label' => __('Gesamt'),           'value' => $vacationKpis['total'],    'tone' => 'neutral', 'href' => null, 'statusKey' => null],
                            ['label' => __('Ausstehend'),       'value' => $vacationKpis['pending'],  'tone' => 'warning', 'href' => null, 'statusKey' => null],
                            ['label' => __('Genehmigt (Jahr)'), 'value' => $vacationKpis['approved'], 'tone' => 'success', 'href' => null, 'statusKey' => null],
                            ['label' => __('Abgelehnt'),        'value' => $vacationKpis['rejected'], 'tone' => 'error',   'href' => null, 'statusKey' => null],
                        ]
                    ));
            $activeStatus = (string) ($filters['status'] ?? 'all');
        @endphp
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
        @endswitch

    </x-page-shell>
@endsection

