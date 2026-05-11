@extends('layouts.app')
@section('title', __('Callcenter Notdienstplan') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Callcenter'))

@section('content')
    {{-- Login-Info + Abmelden --}}
    @if (! empty($callcenterUser))
        <div class="mb-2 text-xs text-base-content/60">
            {{ __('Eingeloggt als') }} <strong>{{ $callcenterUser }}</strong>
            &nbsp;|&nbsp;
            <form method="POST" action="{{ route('legacy.callcenter.logout') }}" class="inline">
                @csrf
                <button type="submit" class="link link-primary text-xs">{{ __('Abmelden') }}</button>
            </form>
        </div>
    @endif

    {{-- Wochennavigation --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
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

    {{-- Notdienst-/Bereitschafts-Tabelle --}}
    <div class="overflow-x-auto mb-6 max-h-[60vh] overflow-y-auto rounded-box border border-base-300">
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

    {{-- Heute: Kontakt-Info --}}
    @if ($todayNotdienst || $todayBereitschaft)
        <div class="rounded-box border border-base-300 bg-base-200 p-4 mb-6 grid gap-3 sm:grid-cols-2">
            @if ($todayNotdienst)
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/50 mb-1">{{ __('Notdienst heute') }}</p>
                    <p class="font-semibold">{{ $todayNotdienst['user'] ?: '–' }}</p>
                    @if ($todayNotdienst['email'])
                        <a href="mailto:{{ $todayNotdienst['email'] }}" class="link link-primary text-sm">{{ $todayNotdienst['email'] }}</a>
                    @endif
                    @if ($todayNotdienst['von'] && $todayNotdienst['bis'])
                        <p class="text-xs text-base-content/60 mt-1">{{ $todayNotdienst['von']->format('d.m.Y H:i') }} – {{ $todayNotdienst['bis']->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
            @endif
            @if ($todayBereitschaft)
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/50 mb-1">{{ __('Bereitschaft heute') }}</p>
                    <p class="font-semibold">{{ $todayBereitschaft['user'] ?: '–' }}</p>
                    @if ($todayBereitschaft['email'])
                        <a href="mailto:{{ $todayBereitschaft['email'] }}" class="link link-primary text-sm">{{ $todayBereitschaft['email'] }}</a>
                    @endif
                    @if ($todayBereitschaft['von'] && $todayBereitschaft['bis'])
                        <p class="text-xs text-base-content/60 mt-1">{{ $todayBereitschaft['von']->format('d.m.Y H:i') }} – {{ $todayBereitschaft['bis']->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Offene Meldungen --}}
    @if ($openIssues->isNotEmpty())
        <h2 class="text-sm font-semibold mb-2">{{ __('Offene Meldungen') }}</h2>
        <div class="overflow-x-auto rounded-box border border-base-300 max-h-[50vh] overflow-y-auto">
            <x-table size="xs" :pin-rows="true">
                <thead class="bg-base-200">
                    <tr>
                        <th class="w-14">#</th>
                        <th class="w-24 text-center">{{ __('Status') }}</th>
                        <th class="w-32">{{ __('Mitarbeiter') }}</th>
                        <th class="w-28">{{ __('Von') }}</th>
                        <th class="w-28">{{ __('Bis') }}</th>
                        <th>{{ __('Inhalt') }}</th>
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
                        <tr class="{{ $rowClass }}">
                            <td>{{ $issue->id }}</td>
                            <td class="text-center"><span class="badge badge-sm {{ $badgeClass }}">{{ $issue->statusLabel() }}</span></td>
                            <td>{{ optional($issue->author)->uname ?? '–' }}</td>
                            <td class="whitespace-nowrap">{{ $issue->von?->format('d.m.Y H:i') ?? '–' }}</td>
                            <td class="whitespace-nowrap">{{ $issue->bis?->format('d.m.Y H:i') ?? '–' }}</td>
                            <td class="max-w-md" title="{{ $issue->inhalt ?? '' }}">{{ truncate($issue->inhalt ?? '', 120) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>
    @else
        <p class="text-sm text-base-content/50">{{ __('Keine offenen Meldungen.') }}</p>
    @endif
@endsection
