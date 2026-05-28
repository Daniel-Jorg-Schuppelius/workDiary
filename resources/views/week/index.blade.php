@extends('layouts.app')
@section('title', __('Wochenansicht') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Wochenansicht'))
@section('wrapper-height-class', 'h-[calc(100dvh_-_var(--app-header-h))] overflow-clip')
@section('main-class', 'min-h-0 overflow-clip flex flex-col')

@php
    $hours = range(0, 23);
    $statusToneClass = [
        'done' => 'wd-week-entry--done',
        'progress' => 'wd-week-entry--progress',
        'open' => 'wd-week-entry--open',
        'alert' => 'wd-week-entry--alert',
        'neutral' => 'wd-week-entry--neutral',
    ];
    $workHoursCfg = config('app.work_hours');
    $toPct = static function (string $hhmm): float {
        [$h, $m] = array_pad(array_map('intval', explode(':', $hhmm)), 2, 0);
        return (($h * 60 + $m) / 1440) * 100;
    };
    $bands = [
        'coreTop' => $toPct($workHoursCfg['core']['start'] ?? '08:00'),
        'coreBottom' => $toPct($workHoursCfg['core']['end'] ?? '16:00'),
        'extTop' => $toPct($workHoursCfg['extended']['start'] ?? '06:00'),
        'extBottom' => $toPct($workHoursCfg['extended']['end'] ?? '19:00'),
        'coreDays' => $workHoursCfg['core']['days'] ?? [],
        'extDays' => $workHoursCfg['extended']['days'] ?? [],
    ];
    $weekCount = count($weekViews);
@endphp

@section('content')
<div class="wd-week flex h-full min-h-0 flex-col gap-4"
     data-week-tabs
     data-active-week="{{ $activeKey }}">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="flex flex-wrap items-center gap-3">
            @if ($weekCount > 0)
                <span class="font-['Space_Grotesk'] text-sm text-base-content/70">
                    {{ trans_choice('{1} :count Woche|[2,*] :count Wochen', $weekCount, ['count' => $weekCount]) }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <label class="flex cursor-pointer items-center gap-2 text-sm" title="{{ __('Ansicht an Bildschirm anpassen') }}">
                <x-icon name="fit_screen" class="text-base-content/70" />
                <span class="text-sm text-base-content/70">{{ __('Auf Bildschirm') }}</span>
                <input type="checkbox" id="wd-week-fit-btn" class="toggle toggle-sm toggle-primary">
            </label>

            <div class="join">
                <a href="{{ route('week.index', ['scope' => 'mine']) }}"
                   class="join-item btn btn-sm {{ $teamScope ? 'btn-ghost' : 'btn-primary' }}">{{ __('Meine Woche') }}</a>
                <a href="{{ route('week.index', ['scope' => 'team']) }}"
                   class="join-item btn btn-sm {{ $teamScope ? 'btn-primary' : 'btn-ghost' }}">{{ __('Team-Woche') }}</a>
            </div>

            <x-help-button topic="time-entries.start" />
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-4 rounded-box border border-base-300 bg-base-100 px-4 py-2 text-xs text-base-content/70 shadow-xs">
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-band--core"></span>{{ __('Kernarbeitszeit') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-band--extended"></span>{{ __('Erweiterte Arbeitszeit') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-shift"></span>{{ __('Bereitschaft') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-emergency"></span>{{ __('Notdienst') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--open"></span>{{ __('Offen') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--alert"></span>{{ __('Problem') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--progress"></span>{{ __('Bestätigt') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--done"></span>{{ __('Erledigt') }}</span>
    </div>

    {{-- User-Tabs (nur in Team-Sicht) --}}
    @if ($teamScope && $weekUsers->isNotEmpty())
        <div role="tablist" class="tabs tabs-box">
            <a role="tab"
               href="{{ route('week.index', ['scope' => 'team']) }}"
               class="tab {{ ! $filterUserId ? 'tab-active' : '' }}">
                {{ __('Alle') }}
            </a>
            @foreach ($weekUsers as $u)
                @php
                    $hue = $service->userHue((int) $u->id);
                    $isActive = (int) $filterUserId === (int) $u->id;
                    $color = "hsl({$hue} 70% 45%)";
                    $soft = "hsl({$hue} 70% 92%)";
                @endphp
                <a role="tab"
                   href="{{ route('week.index', ['scope' => 'team', 'user' => $u->sqid]) }}"
                   class="tab gap-2 {{ $isActive ? 'tab-active' : '' }}"
                   style="--tab-bg: {{ $soft }}; --tab-border-color: {{ $color }}; {{ $isActive ? 'color: ' . $color . ';' : '' }}">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
                    <span>{{ $u->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Truncation-Hinweis --}}
    @if ($weeksTruncated)
        <div class="alert alert-warning text-sm">
            <span>
                {{ __('Der gewählte Zeitraum umfasst :total Wochen — es werden nur die ersten :shown angezeigt. Bitte engere die Auswahl im Header ein.', [
                    'total' => $totalWeeks,
                    'shown' => $weekCount,
                ]) }}
            </span>
        </div>
    @endif

    {{-- Wochen-Tabs (nur bei >1 Woche) --}}
    @if ($weekCount > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($weekViews as $wv)
                <button type="button" role="tab"
                        data-week-tab="{{ $wv['key'] }}"
                        class="tab whitespace-nowrap gap-1.5 {{ $wv['key'] === $activeKey ? 'tab-active' : '' }}">
                    <span class="font-semibold">{{ __('KW') }} {{ $wv['isoWeek'] }}</span>
                    <span class="text-[0.65rem] text-base-content/50 tabular-nums">{{ $wv['shortLabel'] }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Wochen-Grids --}}
    @if ($weekCount === 0)
        <div class="alert alert-info">
            <span>{{ __('Keine Wochen im gewählten Zeitraum.') }}</span>
        </div>
    @elseif ($weekCount === 1)
        @include('week._grid', [
            'wv' => $weekViews[0],
            'service' => $service,
            'holidays' => $holidays,
            'hours' => $hours,
            'statusToneClass' => $statusToneClass,
            'bands' => $bands,
        ])
    @else
        @foreach ($weekViews as $wv)
            <div data-week-pane="{{ $wv['key'] }}"
                 class="min-h-0 flex-1 flex flex-col {{ $wv['key'] === $activeKey ? '' : 'hidden' }}">
                @include('week._grid', [
                    'wv' => $wv,
                    'service' => $service,
                    'holidays' => $holidays,
                    'hours' => $hours,
                    'statusToneClass' => $statusToneClass,
                    'bands' => $bands,
                ])
            </div>
        @endforeach
    @endif
</div>

<script>
(function () {
    var KEY  = 'workDiaryWeekCalFit';
    var btn  = document.getElementById('wd-week-fit-btn');
    var fit  = localStorage.getItem(KEY) === '1';

    function applyToAll(fitMode) {
        var scrolls = document.querySelectorAll('.wd-week-scroll');
        scrolls.forEach(function (scroll) {
            var grid = scroll.querySelector('.wd-week-grid');
            if (!grid) return;
            if (fitMode) {
                grid.classList.add('wd-week-grid--fit');
                var firstHeader = grid.querySelector('.wd-week-day-header');
                var headerH = firstHeader ? firstHeader.offsetHeight : 48;
                var available = scroll.clientHeight - headerH;
                if (available > 0) {
                    var h = Math.max(available / 24, 20);
                    grid.style.setProperty('--wd-hour-h', h + 'px');
                }
                scroll.style.overflowY = 'hidden';
                scroll.style.overflowX = 'hidden';
            } else {
                grid.classList.remove('wd-week-grid--fit');
                grid.style.removeProperty('--wd-hour-h');
                scroll.style.overflowY = '';
                scroll.style.overflowX = '';
            }
        });
        if (btn) btn.checked = fitMode;
    }

    applyToAll(fit);

    if (btn) {
        btn.addEventListener('change', function () {
            fit = btn.checked;
            localStorage.setItem(KEY, fit ? '1' : '0');
            applyToAll(fit);
        });
    }

    window.addEventListener('resize', function () {
        if (fit) applyToAll(true);
    });

    // Vanilla-JS Tab-Umschaltung für die Wochen-Tabs (Alpine ist im Projekt
    // nicht eingebunden, daher keine x-show-Direktive).
    var root = document.querySelector('[data-week-tabs]');
    if (root) {
        var tabs  = root.querySelectorAll('[data-week-tab]');
        var panes = root.querySelectorAll('[data-week-pane]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-week-tab');
                tabs.forEach(function (t) {
                    t.classList.toggle('tab-active', t === tab);
                });
                panes.forEach(function (p) {
                    p.classList.toggle('hidden', p.getAttribute('data-week-pane') !== key);
                });
                if (fit) {
                    // Frisch sichtbarer Container braucht eine Neuberechnung der
                    // Stundenhöhe, weil er vorher display:none hatte.
                    setTimeout(function () { applyToAll(true); }, 0);
                }
            });
        });
    }
})();
</script>

{{-- Outlook-Style: Klick in den Tageskalender → Kontextmenü mit zeitlich
     passender Anlage von Tagebucheintrag / Bereitschaft / Notdienst. --}}
<div id="wd-week-create-menu"
     class="fixed z-50 hidden min-w-44 rounded-box border border-base-300 bg-base-100 p-1 text-sm shadow-xl"
     role="menu" aria-label="{{ __('Neu anlegen') }}">
    <div class="px-3 py-1 text-[0.65rem] uppercase tracking-wider text-base-content/60" data-wd-menu-label></div>
    <a href="#" class="block rounded-md px-3 py-2 hover:bg-base-200" data-wd-menu-kind="diary" data-entry-modal-trigger>{{ __('Tagebucheintrag') }}</a>
    <a href="#" class="block rounded-md px-3 py-2 hover:bg-base-200" data-wd-menu-kind="shift" data-entry-modal-trigger>{{ __('Bereitschaft') }}</a>
    <a href="#" class="block rounded-md px-3 py-2 hover:bg-base-200" data-wd-menu-kind="assignment" data-entry-modal-trigger>{{ __('Notdienst') }}</a>
</div>
<script>
(function () {
    var menu = document.getElementById('wd-week-create-menu');
    if (!menu) return;

    // URL-Templates: Platzhalter __START__ / __END__ werden ersetzt.
    var urls = {
        diary:      '{{ route('diary.create',       ['date' => '__START__']) }}',
        shift:      '{{ route('shifts.create',      ['start_at' => '__START__', 'end_at' => '__END__']) }}',
        assignment: '{{ route('assignments.create', ['start_at' => '__START__', 'end_at' => '__END__']) }}'
    };
    var label = menu.querySelector('[data-wd-menu-label]');
    var links = menu.querySelectorAll('[data-wd-menu-kind]');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function timeFromY(dayEl, clientY) {
        var rect = dayEl.getBoundingClientRect();
        var ratio = Math.min(Math.max((clientY - rect.top) / rect.height, 0), 0.9999);
        var totalMinutes = Math.floor(ratio * 24 * 60);
        totalMinutes = Math.floor(totalMinutes / 15) * 15;
        return { h: Math.floor(totalMinutes / 60), m: totalMinutes % 60 };
    }

    function buildDateTime(dateStr, t) {
        return dateStr + 'T' + pad(t.h) + ':' + pad(t.m);
    }

    function closeMenu() { menu.classList.add('hidden'); }

    function openMenuAt(x, y, dateStr, t) {
        var startStr = buildDateTime(dateStr, t);
        var endH = (t.h + 1) % 24;
        var endStr = buildDateTime(dateStr, { h: endH, m: t.m });
        label.textContent = dateStr.split('-').reverse().join('.') + ' · ' + pad(t.h) + ':' + pad(t.m);
        links.forEach(function (a) {
            var kind = a.getAttribute('data-wd-menu-kind');
            var tpl  = urls[kind];
            a.href = tpl.replace('__START__', encodeURIComponent(startStr))
                        .replace('__END__',   encodeURIComponent(endStr));
        });
        menu.classList.remove('hidden');
        var rect = menu.getBoundingClientRect();
        var maxX = window.innerWidth  - rect.width  - 8;
        var maxY = window.innerHeight - rect.height - 8;
        menu.style.left = Math.min(x, maxX) + 'px';
        menu.style.top  = Math.min(y, maxY) + 'px';
    }

    // Klick-Delegation über alle (auch versteckten) Wochen-Grids hinweg.
    document.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, .wd-week-entry, .wd-week-shift, .wd-week-emergency, [role="tab"]')) {
            return;
        }
        var dayEl = ev.target.closest('.wd-week-day');
        if (!dayEl) { closeMenu(); return; }
        var dateStr = dayEl.getAttribute('data-date');
        if (!dateStr) return;
        var t = timeFromY(dayEl, ev.clientY);
        openMenuAt(ev.clientX, ev.clientY, dateStr, t);
        ev.stopPropagation();
    });

    document.addEventListener('click', function (ev) {
        if (!menu.contains(ev.target) && !ev.target.closest('.wd-week-day')) closeMenu();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeMenu();
    });
    links.forEach(function (a) {
        a.addEventListener('click', function () { setTimeout(closeMenu, 0); });
    });
})();
</script>
@endsection
