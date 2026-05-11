@extends('layouts.app')
@section('title', __('Zentrale') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Zentrale'))

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
        </div>

        {{-- KPI-Kacheln (Lagebild) --}}
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $kpis = [
                    ['key' => 'alert',      'label' => __('Probleme'),      'value' => $statusCounts['alert'],      'border' => 'border-error/40',    'status' => '3'],
                    ['key' => 'open',       'label' => __('Offen'),         'value' => $statusCounts['open'],       'border' => 'border-warning/40',  'status' => '2'],
                    ['key' => 'progress',   'label' => __('Bestätigt'),     'value' => $statusCounts['progress'],   'border' => 'border-success/40',  'status' => '1'],
                    ['key' => 'doneRecent', 'label' => __('Erledigt (7d)'), 'value' => $statusCounts['doneRecent'], 'border' => 'border-neutral/40',  'status' => '-1'],
                ];
            @endphp
            @foreach ($kpis as $tile)
                <a href="{{ route('legacy.diary.index', ['status' => $tile['status']]) }}"
                   class="rounded-box border bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary hover:shadow-md {{ $tile['border'] }}"
                   title="{{ __('Zur Arbeitsliste filtern') }}">
                    <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                    <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                </a>
            @endforeach
        </div>

        {{-- Hauptbereich: 2 Spalten (links Plan + Kontakte, rechts offene Meldungen) --}}
        <div class="min-h-0 flex-1 grid gap-4 lg:grid-cols-3">
            <div class="flex min-h-0 flex-col gap-4 lg:col-span-2 overflow-auto">

                {{-- Wochenplan --}}
                <div class="flex-none overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <div class="border-b border-base-300 bg-base-200/60 px-3 py-2 text-xs uppercase tracking-wider text-base-content/60">
                        {{ __('Wochenplan') }}
                    </div>
                    <div class="overflow-x-auto">
                        <x-table size="xs">
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
                        </x-table>
                    </div>
                </div>

                {{-- Kontakte: Heute / Morgen / Wochenende --}}
                <div class="grid flex-none gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['label' => __('Heute'),  'nd' => $todayNotdienst,    'br' => $todayBereitschaft,    'tone' => 'border-primary/40'],
                        ['label' => __('Morgen'), 'nd' => $tomorrowNotdienst, 'br' => $tomorrowBereitschaft, 'tone' => 'border-base-300'],
                    ] as $card)
                        <div class="rounded-box border bg-base-100 p-3 shadow-xs {{ $card['tone'] }}">
                            <p class="text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ $card['label'] }}</p>
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
                    <span class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Offene Meldungen') }}</span>
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
                                        <td class="whitespace-nowrap">{{ $issue->bis?->format('d.m.Y') ?? '–' }}</td>
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
