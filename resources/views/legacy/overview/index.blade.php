@extends('layouts.app')
@section('title', __('Überblick') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Überblick'))

@section('content')
    @php
        $statusTiles = [
            ['key' => 'all',   'label' => __('Alle Aufträge'), 'count' => $statusCounts['all'],   'class' => 'border-base-300', 'status' => 'all'],
            ['key' => 'open',  'label' => __('Offen'),         'count' => $statusCounts['open'],  'class' => 'border-warning text-warning', 'status' => 2],
            ['key' => 'work',  'label' => __('In Arbeit'),     'count' => $statusCounts['work'],  'class' => 'border-info text-info',       'status' => 1],
            ['key' => 'alert', 'label' => __('Eskaliert'),     'count' => $statusCounts['alert'], 'class' => 'border-error text-error',     'status' => 3],
            ['key' => 'done',  'label' => __('Erledigt'),      'count' => $statusCounts['done'],  'class' => 'border-success text-success', 'status' => -1],
        ];
    @endphp

    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4 overflow-auto pr-1">
        {{-- Status-Kacheln --}}
        <section>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-base-content/70">{{ __('Status') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($statusTiles as $tile)
                    <a href="{{ route('legacy.diary.index', ['status' => $tile['status']]) }}"
                       class="rounded-box border bg-base-100 px-4 py-3 shadow-xs transition hover:bg-base-200 {{ $tile['class'] }}">
                        <div class="text-2xl font-bold">{{ $tile['count'] }}</div>
                        <div class="text-xs uppercase tracking-wider opacity-80">{{ $tile['label'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Heute: Bereitschaft / Notdienst --}}
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-base-content/70">
                        {{ __('Bereitschaft heute') }} ({{ $today->format('d.m.Y') }})
                    </h2>
                    <a href="{{ route('legacy.oncall.index') }}" class="btn btn-xs btn-ghost">{{ __('Alle') }} →</a>
                </div>
                @if ($oncallToday->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Niemand im Bereitschaftsdienst.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($oncallToday as $shift)
                            <li class="flex items-center justify-between rounded-box border border-base-200 bg-base-200/40 px-3 py-2 text-sm">
                                <span class="font-semibold">{{ optional($shift->mitarbeiter)->uname ?? '—' }}</span>
                                <span class="text-xs text-base-content/70">
                                    {{ $shift->von?->format('d.m.') }} – {{ $shift->bis?->format('d.m.Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-base-content/70">
                        {{ __('Notdienst heute') }} ({{ $today->format('d.m.Y') }})
                    </h2>
                    <a href="{{ route('legacy.notdienst.index') }}" class="btn btn-xs btn-ghost">{{ __('Alle') }} →</a>
                </div>
                @if ($notdienstToday->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Niemand im Notdienst.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($notdienstToday as $shift)
                            <li class="flex items-center justify-between rounded-box border border-base-200 bg-base-200/40 px-3 py-2 text-sm">
                                <span class="font-semibold">{{ optional($shift->mitarbeiter)->uname ?? '—' }}</span>
                                <span class="text-xs text-base-content/70">
                                    {{ $shift->von?->format('d.m.') }} – {{ $shift->bis?->format('d.m.Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Feiertage Widget --}}
        <section class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-base-content/70">
                    {{ __('Nächste Feiertage') }}
                </h2>
                <a href="{{ route('holidays.index') }}" class="btn btn-xs btn-ghost">{{ __('Verwaltung') }} →</a>
            </div>
            @if ($todayHolidayName)
                <div class="alert alert-warning mb-3 py-2 text-sm">
                    🎉 {{ __('Heute') }}: <span class="font-semibold">{{ $todayHolidayName }}</span>
                </div>
            @endif
            @if ($upcomingHolidays->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine bevorstehenden Feiertage.') }}</p>
            @else
                <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($upcomingHolidays as $holiday)
                        <li class="rounded-box border border-base-200 bg-base-200/40 px-3 py-2 text-sm">
                            <div class="font-mono text-xs text-base-content/60">
                                {{ $holiday['date']->format('D, d.m.Y') }}
                            </div>
                            <div class="truncate font-semibold" title="{{ $holiday['name'] }}">
                                {{ $holiday['name'] }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
