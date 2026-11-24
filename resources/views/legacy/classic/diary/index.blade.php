{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Überblick wie im Altportal (uesicht.php): erst die Auswahl, dann die Liste. Erwartet: $entries, $canViewAll --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.overview_title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.diary_title') }}</h1>

    @if (request()->boolean('anzeigen'))
        @php
            $statusKey = match ((string) request()->query('status', 'all')) {
                '-1' => 'done',
                '1' => 'confirmed',
                '2' => 'unread',
                '3' => 'problem',
                default => 'all',
            };
            $sinceToday = (string) request()->query('zeitpunkt', '') !== '2';
        @endphp
        @unless ($canViewAll)
            <p><b>{{ auth()->user()?->name }}</b></p>
        @endunless
        <p>{{ __('legacy_classic.overview.heading.' . $statusKey) }} {{ $sinceToday ? __('legacy_classic.overview.since_today') : __('legacy_classic.overview.until_today') }}</p>
        <div class="kp-scroll">
            <table class="eintrag">
                <tr>
                    <th class="kopf">{{ __('legacy_classic.col.created') }}</th>
                    @if ($canViewAll)
                        <th class="kopf">{{ __('legacy_classic.col.employee') }}</th>
                    @endif
                    <th class="kopf">{{ __('legacy_classic.col.from') }}</th>
                    <th class="kopf">{{ __('legacy_classic.col.until') }}</th>
                    <th class="kopf">{{ __('legacy_classic.col.content') }}</th>
                    <th class="kopf">{{ __('legacy_classic.col.answer') }}</th>
                    <th class="kopf">{{ __('legacy_classic.col.status') }}</th>
                </tr>
                @forelse ($entries as $row)
                    @php $cell = $loop->index % 2 === 0 ? 'mitte' : 'grau'; @endphp
                    <tr>
                        <td class="{{ $cell }}" title="{{ \App\Legacy\Support\LegacyClock::format($row->aktuell) }}">{{ $row->aktuell?->format(\App\Support\Formats::date()) }}</td>
                        @if ($canViewAll)
                            <td class="{{ $cell }}" title="{{ $row->author?->uname }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate((string) $row->author?->uname, 15, '') }}</td>
                        @endif
                        <td class="{{ $cell }}" title="{{ \App\Legacy\Support\LegacyClock::format($row->von) }}">{{ $row->von?->format(\App\Support\Formats::date()) }}</td>
                        <td class="{{ $cell }}" title="{{ \App\Legacy\Support\LegacyClock::format($row->bis) }}">{{ $row->bis?->format(\App\Support\Formats::date()) }}</td>
                        <td class="{{ $cell }}" title="{{ $row->inhalt }}"><a href="{{ route('legacy.diary.show', $row) }}" class="kp-normal">{{ \CommonToolkit\Helper\Data\StringHelper::truncate((string) $row->inhalt, 25, '') }}</a></td>
                        <td class="{{ $cell }}" title="{{ $row->antwort }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate((string) $row->antwort, 25, '') }}</td>
                        <td class="{{ $cell }}">{{ $row->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewAll ? 7 : 6 }}" class="mitte">{{ __('legacy_classic.none') }}</td></tr>
                @endforelse
            </table>
        </div>
        @include('legacy.classic._pager', ['paginator' => $entries])
        <p><a href="{{ route('legacy.diary.index') }}">{{ __('legacy_classic.overview.back_to_choice') }}</a></p>
    @else
        <form method="GET" action="{{ route('legacy.diary.index') }}">
            <input type="hidden" name="anzeigen" value="1">
            <table class="kp-msg">
                @foreach (['1' => 'since_today_choice', '2' => 'until_today_choice'] as $value => $labelKey)
                    <tr>
                        <td class="rechts"><label for="tb-zeitpunkt-{{ $value }}">{{ __('legacy_classic.overview.' . $labelKey) }}</label></td>
                        <td class="ohne"><input class="rad" type="radio" id="tb-zeitpunkt-{{ $value }}" name="zeitpunkt" value="{{ $value }}" @checked($value === '1')></td>
                    </tr>
                @endforeach
                <tr><td class="ohne" colspan="2">&nbsp;</td></tr>
                @foreach (['2' => 'unread', '1' => 'progress', '-1' => 'done', '3' => 'problem', 'all' => 'all'] as $value => $labelKey)
                    <tr>
                        <td class="rechts"><label for="tb-status-{{ $loop->index }}">{{ __('legacy_classic.overview.choice.' . $labelKey) }}</label></td>
                        <td class="ohne"><input class="rad" type="radio" id="tb-status-{{ $loop->index }}" name="status" value="{{ $value }}" @checked($value === '2')></td>
                    </tr>
                @endforeach
                <tr><td class="ohne" colspan="2">&nbsp;</td></tr>
                <tr><td class="rechts" colspan="2"><button type="submit">{{ __('legacy_classic.show') }}</button></td></tr>
            </table>
        </form>
    @endif
@endsection
