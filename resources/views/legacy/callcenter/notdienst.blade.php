@extends('layouts.app')
@section('title', __('Callcenter Notdienstplan') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Callcenter'))

@section('content')
    <div class="flex h-[calc(100dvh-11rem)] w-full flex-col gap-4">
        @if (! empty($callcenterUser))
            <div class="flex-none flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-100 px-4 py-3 text-sm shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-base-content/75">{{ __('Eingeloggt als') }}<strong>{{ $callcenterUser }}</strong></span>
                    <span class="text-base-content/40">|</span>
                    <span class="text-base-content/75">KW {{ $rangeStart->isoWeek() }} · {{ $rangeStart->format('d.m.') }} – {{ $rangeEnd->format('d.m.Y') }}</span>
                </div>
                <form method="POST" action="{{ route('legacy.callcenter.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline btn-error">{{ __('Abmelden') }}</button>
                </form>
            </div>
        @endif

        {{-- Aktiv jetzt --}}
        <div class="flex-none grid gap-3 md:grid-cols-2">
            <div class="rounded-box border border-error/40 bg-error/5 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.2em] text-error">{{ __('Notdienst aktuell') }}</p>
                @if ($todayNotdienst && $todayNotdienst['user'])
                    <p class="mt-2 font-['Space_Grotesk'] text-2xl font-semibold text-base-content">{{ $todayNotdienst['user'] }}</p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ optional($todayNotdienst['von'])->format('d.m.Y') ?? '-' }} – {{ optional($todayNotdienst['bis'])->format('d.m.Y') ?? '-' }}
                    </p>
                    @if ($todayNotdienst['email'])
                        <a href="mailto:{{ $todayNotdienst['email'] }}" class="btn btn-sm btn-error mt-3">✉ {{ $todayNotdienst['email'] }}</a>
                    @endif
                @else
                    <p class="mt-2 text-base-content/60">{{ __('Heute kein Notdienst eingetragen.') }}</p>
                @endif
            </div>

            <div class="rounded-box border border-warning/40 bg-warning/5 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.2em] text-warning">{{ __('Bereitschaft aktuell') }}</p>
                @if ($todayBereitschaft && $todayBereitschaft['user'])
                    <p class="mt-2 font-['Space_Grotesk'] text-2xl font-semibold text-base-content">{{ $todayBereitschaft['user'] }}</p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ optional($todayBereitschaft['von'])->format('d.m.Y') ?? '-' }} – {{ optional($todayBereitschaft['bis'])->format('d.m.Y') ?? '-' }}
                    </p>
                    @if ($todayBereitschaft['email'])
                        <a href="mailto:{{ $todayBereitschaft['email'] }}" class="btn btn-sm btn-warning mt-3">✉ {{ $todayBereitschaft['email'] }}</a>
                    @endif
                @else
                    <p class="mt-2 text-base-content/60">{{ __('Heute keine Bereitschaft eingetragen.') }}</p>
                @endif
            </div>
        </div>

        {{-- Wochennavigation --}}
        <div class="flex-none flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('legacy.callcenter.notdienst', ['week' => $weekOffset - 1]) }}" class="btn btn-sm btn-outline btn-primary">&laquo; {{ __('Vorwoche') }}</a>
            <p class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Wochenplan') }}</p>
            <a href="{{ route('legacy.callcenter.notdienst', ['week' => $weekOffset + 1]) }}" class="btn btn-sm btn-outline btn-primary">{{ __('Nächste Woche') }} &raquo;</a>
        </div>

        {{-- Wochentabelle --}}
        <div class="flex-none rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="overflow-auto">
                <table class="table table-sm table-zebra">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="text-left">{{ __('Schicht') }}</th>
                            @foreach ($notdienstByDay as $item)
                                <th class="text-center {{ $item['isToday'] ? 'bg-primary/10 text-primary' : '' }}">
                                    {{ $item['date']->isoFormat('dd') }}<br>
                                    <span class="text-xs font-normal">{{ $item['date']->format('d.m.') }}</span>
                                    @if ($item['isToday'])<br><span class="badge badge-primary badge-xs">{{ __('Heute') }}</span>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-medium text-error">{{ __('Notdienst') }}</td>
                            @foreach ($notdienstByDay as $item)
                                <td class="text-center {{ $item['isToday'] ? 'bg-primary/5 font-semibold' : '' }}">
                                    {{ $item['user'] ?: '–' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="font-medium text-warning">{{ __('Bereitschaft') }}</td>
                            @foreach ($bereitschaftByDay as $item)
                                <td class="text-center {{ $item['isToday'] ? 'bg-primary/5 font-semibold' : '' }}">
                                    {{ $item['user'] ?: '–' }}
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Offene Probleme --}}
        <div class="min-h-0 flex-1 rounded-box border border-base-300 bg-base-100 shadow-sm overflow-hidden">
            <div class="border-b border-base-300 bg-base-200 px-4 py-2 flex items-center justify-between">
                <p class="text-xs uppercase tracking-[0.2em] text-base-content/70">{{ __('Offene & problematische Einträge') }}</p>
                <span class="badge badge-sm">{{ $openIssues->count() }}</span>
            </div>
            <div class="h-full overflow-auto">
                @if ($openIssues->isEmpty())
                    <p class="p-4 text-sm text-base-content/60">{{ __('Keine offenen Einträge.') }}</p>
                @else
                    <ul class="divide-y divide-base-300">
                        @foreach ($openIssues as $issue)
                            @php
                                $isAlert = (int) $issue->gelesen === 3;
                            @endphp
                            <li class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge badge-sm {{ $isAlert ? 'badge-error' : 'badge-warning' }}">
                                        {{ $isAlert ? __('Problem') : __('Offen') }}
                                    </span>
                                    <span class="text-sm font-medium">{{ optional($issue->mitarbeiter)->uname ?? __('Unbekannt') }}</span>
                                    <span class="text-xs text-base-content/60">{{ __('bis') }} {{ $issue->bis?->format('d.m.Y H:i') ?? '-' }}</span>
                                </div>
                                <p class="mt-1 text-sm text-base-content">{{ \Illuminate\Support\Str::limit($issue->inhalt ?? '', 160) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
