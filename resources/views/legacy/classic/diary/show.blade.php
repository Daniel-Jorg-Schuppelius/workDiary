{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eintrag im Tagebuch: Büro mit „ändern", Mitarbeiter mit „antworten" und „gelesen"/„erledigt" wie im Altportal. Erwartet: $entry, $isAdmin, $isOwn --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.entry_title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    @php
        $writeEnabled = (bool) config('app.legacy_write_enabled');
        $asEmployee = $isOwn && ! $isAdmin;
        $statusAction = match ((int) $entry->gelesen) {
            2, 3 => ['action' => 'status_progress', 'label' => __('legacy_classic.action.read'), 'title' => __('legacy_classic.action.read_title')],
            1 => ['action' => 'status_done', 'label' => __('legacy_classic.action.done'), 'title' => __('legacy_classic.action.done_title')],
            default => null,
        };
    @endphp

    @include('legacy.classic.diary._details', ['entry' => $entry, 'employee' => $asEmployee ? null : $entry->author?->uname, 'heading' => $asEmployee])

    @if ($writeEnabled && $isAdmin)
        <p class="kp-msg"><a href="{{ route('legacy.diary.edit', $entry) }}">&nbsp;{{ __('legacy_classic.action.change') }}&nbsp;</a></p>
    @elseif ($writeEnabled && $isOwn)
        <table class="kp-msg">
            <tr>
                <td class="mitte"><a href="{{ route('legacy.diary.edit', $entry) }}" title="{{ __('legacy_classic.action.answer_title') }}">&nbsp;{{ __('legacy_classic.action.answer') }}&nbsp;</a></td>
                @if ($statusAction !== null)
                    <td class="mitte">
                        <form method="POST" action="{{ route('legacy.diary.bulk') }}">
                            @csrf
                            <input type="hidden" name="ids[]" value="{{ $entry->id }}">
                            <input type="hidden" name="action" value="{{ $statusAction['action'] }}">
                            <button type="submit" class="kp-linkbutton" title="{{ $statusAction['title'] }}">&nbsp;{{ $statusAction['label'] }}&nbsp;</button>
                        </form>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week') }}">{{ __('legacy_classic.to_diary') }}</a></p>
@endsection
