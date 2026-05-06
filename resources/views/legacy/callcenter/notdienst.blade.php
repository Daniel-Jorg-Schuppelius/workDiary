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

    {{-- Notdienst-Tabelle --}}
    <div class="overflow-x-auto mb-6">
        <x-table size="xs">
            <thead>
                <tr>
                    <th>{{ __('Dienst') }}</th>
                    @foreach ($notdienstByDay as $item)
                        <th class="{{ $item['isToday'] ? 'bg-primary/10' : '' }}">
                            {{ $item['date']->isoFormat('ddd') }}<br>
                            {{ $item['date']->format('d.m.') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-semibold whitespace-nowrap">{{ __('Notdienst') }}</td>
                    @foreach ($notdienstByDay as $item)
                        <td class="text-center {{ $item['isToday'] ? 'bg-primary/10 font-semibold' : '' }}">
                            {{ $item['user'] ?: '-' }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="font-semibold whitespace-nowrap">{{ __('Bereitschaft') }}</td>
                    @foreach ($bereitschaftByDay as $item)
                        <td class="text-center {{ $item['isToday'] ? 'bg-primary/10 font-semibold' : '' }}">
                            {{ $item['user'] ?: '-' }}
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
                    <p class="font-semibold">{{ $todayNotdienst['user'] ?: '-' }}</p>
                    @if ($todayNotdienst['email'])
                        <a href="mailto:{{ $todayNotdienst['email'] }}" class="link link-primary text-sm">{{ $todayNotdienst['email'] }}</a>
                    @endif
                </div>
            @endif
            @if ($todayBereitschaft)
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/50 mb-1">{{ __('Bereitschaft heute') }}</p>
                    <p class="font-semibold">{{ $todayBereitschaft['user'] ?: '-' }}</p>
                    @if ($todayBereitschaft['email'])
                        <a href="mailto:{{ $todayBereitschaft['email'] }}" class="link link-primary text-sm">{{ $todayBereitschaft['email'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Offene Meldungen --}}
    @if ($openIssues->isNotEmpty())
        <h2 class="text-sm font-semibold mb-2">{{ __('Offene Meldungen') }}</h2>
        <div class="overflow-x-auto">
            <x-table size="xs">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Von') }}</th>
                        <th>{{ __('Bis') }}</th>
                        <th>{{ __('Inhalt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($openIssues as $issue)
                        <tr>
                            <td>{{ $issue->id }}</td>
                            <td>{{ $issue->statusLabel() }}</td>
                            <td>{{ optional($issue->author)->uname ?? '-' }}</td>
                            <td>{{ $issue->von?->format('d.m.Y') ?? '-' }}</td>
                            <td>{{ $issue->bis?->format('d.m.Y') ?? '-' }}</td>
                            <td>{{ truncate($issue->inhalt ?? '', 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>
    @else
        <p class="text-sm text-base-content/50">{{ __('Keine offenen Meldungen.') }}</p>
    @endif
@endsection
