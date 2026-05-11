@extends('layouts.app')
@section('title', __('Zentrale') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Zentrale'))

@section('content')
    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        {{-- Kopfzeile: Login-Info / Wochennavigation --}}
        <div class="flex flex-none flex-wrap items-center gap-2">
            @if (! empty($callcenterUser))
                <span class="text-xs text-base-content/60">
                    {{ __('Eingeloggt als') }} <strong>{{ $callcenterUser }}</strong>
                </span>
                <form method="POST" action="{{ route('legacy.callcenter.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="link link-primary text-xs">{{ __('Abmelden') }}</button>
                </form>
                <span class="text-base-content/30">|</span>
            @endif
            <a href="{{ route('legacy.callcenter.notdienst', ['week' => $weekOffset - 1]) }}"
               class="btn btn-sm btn-ghost">«</a>
            <span class="text-sm font-semibold">
                {{ $rangeStart->format('d.m.Y') }} &ndash; {{ $rangeEnd->format('d.m.Y') }}
            </span>
            <a href="{{ route('legacy.callcenter.notdienst', ['week' => $weekOffset + 1]) }}"
               class="btn btn-sm btn-ghost">»</a>
            @if ($weekOffset !== 0)
                <a href="{{ route('legacy.callcenter.notdienst') }}"
                   class="btn btn-sm btn-outline">{{ __('Aktuelle Woche') }}</a>
            @endif
            <span class="ml-auto text-xs text-base-content/50">
                {{ __('Stand') }}: {{ $today->isoFormat('dddd, DD.MM.YYYY') }}
            </span>
        </div>

        {{-- Hero: Lagebild (KPIs + Status-Mix + Trend) --}}
        <div class="flex-none grid gap-3 lg:grid-cols-12">
            {{-- Status-KPIs --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:col-span-7 xl:col-span-7">
                @php
                    $from30 = $today->copy()->subDays(30)->format('Y-m-d');
                    $from7 = $today->copy()->subDays(7)->format('Y-m-d');
                    $kpis = [
                        ['key' => 'alert',      'label' => __('Probleme'),      'value' => $statusCounts['alert'],      'border' => 'border-error/40',   'tone' => 'text-error',           'params' => ['status' => '3',  'from' => $from30]],
                        ['key' => 'open',       'label' => __('Offen'),         'value' => $statusCounts['open'],       'border' => 'border-warning/40', 'tone' => 'text-warning',         'params' => ['status' => '2',  'from' => $from30]],
                        ['key' => 'progress',   'label' => __('Bestätigt'),     'value' => $statusCounts['progress'],   'border' => 'border-success/40', 'tone' => 'text-success',         'params' => ['status' => '1',  'from' => $from30]],
                        ['key' => 'doneRecent', 'label' => __('Erledigt (7d)'), 'value' => $statusCounts['doneRecent'], 'border' => 'border-base-300',   'tone' => 'text-base-content/70', 'params' => ['status' => '-1', 'from' => $from7]],
                    ];
                @endphp
                @foreach ($kpis as $tile)
                    <a href="{{ route('legacy.diary.index', $tile['params']) }}"
                       class="group rounded-box border bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary hover:shadow-md {{ $tile['border'] }}"
                       title="{{ __('Zur Arbeitsliste filtern (ab :date)', ['date' => \Carbon\Carbon::parse($tile['params']['from'])->format('d.m.Y')]) }}">
                        <div class="flex items-center justify-between">
                            <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                            <span class="text-base-content/30 transition group-hover:text-primary">›</span>
                        </div>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold {{ $tile['tone'] }}">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                    </a>
                @endforeach
            </div>

            {{-- Status-Mix + Mini-Lage --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs lg:col-span-5 xl:col-span-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Status-Mix aktiv') }}</p>
                @php
                    $sAlert = (int) $statusCounts['alert'];
                    $sOpen = (int) $statusCounts['open'];
                    $sProg = (int) $statusCounts['progress'];
                    $tot = max(1, (int) $statusTotal);
                    $pAlert = (int) round($sAlert * 100 / $tot);
                    $pOpen = (int) round($sOpen * 100 / $tot);
                    $pProg = max(0, 100 - $pAlert - $pOpen);
                @endphp
                @if ($statusTotal === 0)
                    <p class="mt-2 text-sm text-base-content/50">{{ __('Aktuell keine offenen Vorgänge.') }}</p>
                @else
                    <div class="mt-2 flex h-2 w-full overflow-hidden rounded-full bg-base-200">
                        <div class="bg-error" style="width: {{ $pAlert }}%"></div>
                        <div class="bg-warning" style="width: {{ $pOpen }}%"></div>
                        <div class="bg-success" style="width: {{ $pProg }}%"></div>
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[0.7rem] text-base-content/70">
                        <li><span class="inline-block size-2 rounded-full bg-error align-middle"></span> {{ __('Probleme') }} <span class="font-semibold">{{ $pAlert }}%</span> ({{ $sAlert }})</li>
                        <li><span class="inline-block size-2 rounded-full bg-warning align-middle"></span> {{ __('Offen') }} <span class="font-semibold">{{ $pOpen }}%</span> ({{ $sOpen }})</li>
                        <li><span class="inline-block size-2 rounded-full bg-success align-middle"></span> {{ __('Bestätigt') }} <span class="font-semibold">{{ $pProg }}%</span> ({{ $sProg }})</li>
                    </ul>
                @endif

                {{-- Sub-KPIs: Überfällig / Heute fällig / Nächste 7d --}}
                @php
                    $yesterday = $today->copy()->subDay()->format('Y-m-d');
                    $todayStr = $today->format('Y-m-d');
                    $next7Str = $today->copy()->addDays(7)->format('Y-m-d');
                @endphp
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <a href="{{ route('legacy.diary.index', ['status' => '2', 'to' => $yesterday]) }}"
                       class="rounded-box border border-base-300 px-2 py-1.5 transition hover:border-error hover:bg-error/5"
                       title="{{ __('Vorgänge mit Frist bis :date', ['date' => \Carbon\Carbon::parse($yesterday)->format('d.m.Y')]) }}">
                        <p class="text-[0.65rem] uppercase tracking-wider text-base-content/60">{{ __('Überfällig') }}</p>
                        <p class="font-['Space_Grotesk'] text-lg font-semibold {{ $overdueCount > 0 ? 'text-error' : 'text-base-content/70' }}">{{ $overdueCount }}</p>
                    </a>
                    <a href="{{ route('legacy.diary.index', ['status' => '2', 'to' => $todayStr]) }}"
                       class="rounded-box border border-base-300 px-2 py-1.5 transition hover:border-warning hover:bg-warning/5"
                       title="{{ __('Vorgänge mit Frist bis :date', ['date' => $today->format('d.m.Y')]) }}">
                        <p class="text-[0.65rem] uppercase tracking-wider text-base-content/60">{{ __('Heute fällig') }}</p>
                        <p class="font-['Space_Grotesk'] text-lg font-semibold {{ $dueTodayCount > 0 ? 'text-warning' : 'text-base-content/70' }}">{{ $dueTodayCount }}</p>
                    </a>
                    <a href="{{ route('legacy.diary.index', ['status' => '2', 'to' => $next7Str]) }}"
                       class="rounded-box border border-base-300 px-2 py-1.5 transition hover:border-primary hover:bg-primary/5"
                       title="{{ __('Vorgänge mit Frist bis :date', ['date' => \Carbon\Carbon::parse($next7Str)->format('d.m.Y')]) }}">
                        <p class="text-[0.65rem] uppercase tracking-wider text-base-content/60">{{ __('Nächste 7d') }}</p>
                        <p class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $dueNext7Count }}</p>
                    </a>
                </div>
            </div>
        </div>

        {{-- Hauptbereich: 2 Spalten (links Plan + Kontakte, rechts offene Meldungen) --}}
        <div class="min-h-0 flex-1 grid gap-4 lg:grid-cols-3">
            <div class="flex min-h-0 flex-col gap-4 lg:col-span-2 overflow-auto">

                {{-- Wochenplan --}}
                <div class="flex-none overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <div class="flex items-center justify-between border-b border-base-300 bg-base-200/60 px-3 py-2">
                        <span class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Wochenplan') }}</span>
                        <span class="text-[0.7rem] text-base-content/50">
                            {{ $rangeStart->isoFormat('DD.MM.') }} – {{ $rangeEnd->isoFormat('DD.MM.YYYY') }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-xs">
                            <thead>
                                <tr>
                                    <th class="sticky top-0 left-0 z-20 bg-base-200">{{ __('Dienst') }}</th>
                                    @foreach ($notdienstByDay as $item)
                                        @php
                                            $headBg = $item['isToday']
                                                ? 'bg-primary/10'
                                                : ($item['isHoliday']
                                                    ? 'bg-error/15'
                                                    : ($item['isSunday']
                                                        ? 'bg-error/10'
                                                        : ($item['isSaturday']
                                                            ? 'bg-error/5'
                                                            : 'bg-base-200')));
                                        @endphp
                                        <th class="sticky top-0 z-10 text-center {{ $headBg }}"
                                            @if ($item['isHoliday']) title="{{ $item['holidayName'] }}" @endif>
                                            <div class="font-semibold">{{ $item['date']->isoFormat('ddd') }}</div>
                                            <div class="text-[0.7rem] opacity-80">{{ $item['date']->format('d.m.') }}</div>
                                            @if ($item['isHoliday'])
                                                <div class="holiday-name mt-0.5 text-[0.65rem] font-medium uppercase tracking-wider text-error">{{ $item['holidayName'] }}</div>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-semibold whitespace-nowrap sticky left-0 z-10 bg-base-100">{{ __('Notdienst') }}</td>
                                    @foreach ($notdienstByDay as $item)
                                        <td class="text-center {{ $item['isToday'] ? 'bg-primary/10 font-semibold' : ($item['isHoliday'] ? 'bg-error/5' : '') }}"
                                            @if ($item['user'] && $item['von'] && $item['bis']) title="{{ $item['von']->format('d.m.Y H:i') }} – {{ $item['bis']->format('d.m.Y H:i') }}" @endif>
                                            {{ $item['user'] ?: '–' }}
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td class="font-semibold whitespace-nowrap sticky left-0 z-10 bg-base-100">{{ __('Bereitschaft') }}</td>
                                    @foreach ($bereitschaftByDay as $item)
                                        <td class="text-center {{ $item['isToday'] ? 'bg-primary/10 font-semibold' : ($item['isHoliday'] ? 'bg-error/5' : '') }}"
                                            @if ($item['user'] && $item['von'] && $item['bis']) title="{{ $item['von']->format('d.m.Y H:i') }} – {{ $item['bis']->format('d.m.Y H:i') }}" @endif>
                                            {{ $item['user'] ?: '–' }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Kontakte: Heute / Morgen --}}
                <div class="grid flex-none gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['label' => __('Heute'),  'sub' => $today->isoFormat('dd, DD.MM.'),                'nd' => $todayNotdienst,    'br' => $todayBereitschaft,    'tone' => 'border-primary/40'],
                        ['label' => __('Morgen'), 'sub' => $today->copy()->addDay()->isoFormat('dd, DD.MM.'), 'nd' => $tomorrowNotdienst, 'br' => $tomorrowBereitschaft, 'tone' => 'border-base-300'],
                    ] as $card)
                        <div class="rounded-box border bg-base-100 p-3 shadow-xs {{ $card['tone'] }}">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ $card['label'] }}</p>
                                <span class="text-[0.7rem] text-base-content/50">{{ $card['sub'] }}</span>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div>
                                    <p class="text-[0.7rem] uppercase text-base-content/50">{{ __('Notdienst') }}</p>
                                    <p class="text-sm font-semibold">{{ $card['nd']['user'] ?? '–' }}</p>
                                    @if (! empty($card['nd']['email']))
                                        <a href="mailto:{{ $card['nd']['email'] }}" class="link link-primary text-xs">{{ $card['nd']['email'] }}</a>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-[0.7rem] uppercase text-base-content/50">{{ __('Bereitschaft') }}</p>
                                    <p class="text-sm font-semibold">{{ $card['br']['user'] ?? '–' }}</p>
                                    @if (! empty($card['br']['email']))
                                        <a href="mailto:{{ $card['br']['email'] }}" class="link link-primary text-xs">{{ $card['br']['email'] }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Wochenende / Feiertage in dieser Woche --}}
                    <div class="rounded-box border border-warning/40 bg-base-100 p-3 shadow-xs">
                        <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Wochenende & Feiertage') }}</p>
                        @if ($weekendNotdienst->isEmpty() && $weekendBereitschaft->isEmpty())
                            <p class="mt-2 text-sm text-base-content/50">{{ __('Keine Wochenend-/Feiertagsbesetzung in dieser Woche.') }}</p>
                        @else
                            <ul class="mt-2 space-y-1 text-xs">
                                @foreach ($weekendNotdienst as $d)
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">{{ $d['date']->isoFormat('ddd') }} {{ $d['date']->format('d.m.') }} – {{ __('ND') }}</span>
                                        <span class="font-semibold">{{ $d['user'] ?: '–' }}</span>
                                    </li>
                                @endforeach
                                @foreach ($weekendBereitschaft as $d)
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">{{ $d['date']->isoFormat('ddd') }} {{ $d['date']->format('d.m.') }} – {{ __('BS') }}</span>
                                        <span class="font-semibold">{{ $d['user'] ?: '–' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Trend (14 Tage neu erfasst) + Top-Autoren --}}
                <div class="grid flex-none gap-3 lg:grid-cols-2">
                    {{-- 14-Tage-Trend --}}
                    <div class="rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Neue Einträge (14 Tage)') }}</p>
                            <span class="text-[0.7rem] text-base-content/50">{{ __('Σ') }} {{ (int) $trend->sum('count') }}</span>
                        </div>
                        @php $count = $trend->count(); @endphp
                        <div class="mt-3 flex h-20 items-end gap-1">
                            @foreach ($trend as $idx => $point)
                                @php
                                    $h = $trendMax > 0 ? max(2, (int) round(($point['count'] / $trendMax) * 72)) : 2;
                                    $isToday = $point['date']->isSameDay($today);
                                    $cls = $isToday ? 'bg-primary' : ($point['count'] === 0 ? 'bg-base-200' : 'bg-base-content/30');
                                @endphp
                                <div class="group flex flex-1 flex-col items-center gap-1"
                                     title="{{ $point['date']->format('d.m.Y') }}: {{ $point['count'] }}">
                                    <div class="w-full rounded-sm {{ $cls }} transition group-hover:bg-primary"
                                         style="height: {{ $h }}px"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex justify-between text-[0.6rem] text-base-content/50">
                            <span>{{ $trend->first()['date']->format('d.m.') }}</span>
                            <span>{{ $trend->last()['date']->format('d.m.') }}</span>
                        </div>
                    </div>

                    {{-- Top-Autoren mit offenen Vorgängen --}}
                    <div class="rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
                        <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Top Verantwortliche (offen)') }}</p>
                        @if ($topAuthors->isEmpty())
                            <p class="mt-2 text-sm text-base-content/50">{{ __('Keine offenen Zuweisungen.') }}</p>
                        @else
                            @php $maxCnt = max(1, (int) $topAuthors->max('cnt')); @endphp
                            <ul class="mt-2 space-y-1.5 text-xs">
                                @foreach ($topAuthors as $row)
                                    @php
                                        $name = optional($row->author)->uname ?? '–';
                                        $pct = (int) round(((int) $row->cnt) * 100 / $maxCnt);
                                    @endphp
                                    <li>
                                        <div class="flex items-center justify-between">
                                            <span class="truncate font-semibold">{{ $name }}</span>
                                            <span class="text-base-content/70">{{ (int) $row->cnt }}</span>
                                        </div>
                                        <div class="mt-0.5 h-1.5 w-full overflow-hidden rounded-full bg-base-200">
                                            <div class="h-full bg-primary/70" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Nächste Feiertage --}}
                @if (! empty($upcomingHolidays))
                    <div class="flex-none rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
                        <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Nächste Feiertage (30 Tage)') }}</p>
                        <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($upcomingHolidays as $h)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $h['date']->isoFormat('dd, DD.MM.YYYY') }}</span>
                                    <span class="text-base-content/70">{{ $h['name'] }}</span>
                                    <span class="badge badge-xs badge-ghost">+{{ $h['daysAway'] }}d</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Offene Meldungen (Spalte rechts) --}}
            <div class="flex min-h-0 flex-col overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                <div class="flex flex-none items-center justify-between border-b border-base-300 bg-base-200/60 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Offene Meldungen') }}</span>
                        <span class="badge badge-xs badge-ghost">{{ $openIssues->count() }}</span>
                    </div>
                    <a href="{{ route('legacy.diary.index', ['status' => '2']) }}" class="link link-hover text-xs">{{ __('Alle anzeigen') }}</a>
                </div>
                <div class="min-h-0 flex-1 overflow-auto">
                    @if ($openIssues->isNotEmpty())
                        <x-table size="xs" :pin-rows="true">
                            <thead class="bg-base-200">
                                <tr>
                                    <th class="w-12">#</th>
                                    <th class="w-20 text-center">{{ __('Status') }}</th>
                                    <th>{{ __('Inhalt') }}</th>
                                    <th class="w-24 whitespace-nowrap">{{ __('Bis') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($openIssues as $issue)
                                    @php
                                        $g = (int) $issue->gelesen;
                                        $badgeClass = match ($g) {
                                            -1 => 'badge-neutral',
                                            1  => 'badge-success',
                                            2  => 'badge-warning',
                                            3  => 'badge-error',
                                            default => 'badge-ghost',
                                        };
                                        $rowClass = match ($g) {
                                            3 => 'bg-error/5',
                                            2 => 'bg-warning/5',
                                            default => '',
                                        };
                                        $bisDate = $issue->bis;
                                        $isDueToday = $bisDate && $bisDate->isSameDay($today);
                                        $daysLeft = $bisDate ? (int) $today->diffInDays($bisDate, false) : null;
                                    @endphp
                                    <tr class="hover {{ $rowClass }}">
                                        <td>
                                            <a href="{{ route('legacy.diary.show', $issue) }}" data-entry-modal-trigger
                                               class="link link-hover">{{ $issue->id }}</a>
                                        </td>
                                        <td class="text-center"><span class="badge badge-sm {{ $badgeClass }}">{{ $issue->statusLabel() }}</span></td>
                                        <td class="max-w-md" title="{{ $issue->inhalt ?? '' }}">
                                            <a href="{{ route('legacy.diary.show', $issue) }}" data-entry-modal-trigger class="block link link-hover">
                                                <span class="block text-[0.7rem] text-base-content/50">{{ optional($issue->author)->uname ?? '–' }}</span>
                                                <span class="line-clamp-2">{{ truncate($issue->inhalt ?? '', 100) }}</span>
                                            </a>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if ($bisDate)
                                                <div>{{ $bisDate->format('d.m.Y') }}</div>
                                                @if ($isDueToday)
                                                    <span class="text-[0.65rem] font-semibold text-warning">{{ __('heute') }}</span>
                                                @elseif ($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 7)
                                                    <span class="text-[0.65rem] text-base-content/60">{{ __('in :n d', ['n' => $daysLeft]) }}</span>
                                                @endif
                                            @else
                                                <span class="text-base-content/40">–</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    @else
                        <p class="p-4 text-sm text-base-content/50">{{ __('Keine offenen Meldungen.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
