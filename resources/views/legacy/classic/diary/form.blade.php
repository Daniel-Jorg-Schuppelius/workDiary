{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eintragen und Ändern wie im Altportal (tbedit.php): Büro mit Mitarbeiter, Zeitraum (Datumswähler) und Hinweis, Mitarbeiter antwortet. Erwartet: $entry, $isEdit, $isAdmin, $users --}}
@extends('legacy.classic.shell')
@section('title', $isEdit ? __('legacy_classic.entry_edit_title') : __('legacy_classic.entry_new_title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    @php
        $dateFormat = \App\Legacy\Http\Requests\SaveLegacyDiaryEntryRequest::CLASSIC_DATE;
        $timeFormat = \App\Legacy\Http\Requests\SaveLegacyDiaryEntryRequest::CLASSIC_TIME;
        $rawDate = request()->query('date');
        $day = is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) === 1
            ? \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $rawDate)
            : \App\Legacy\Support\LegacyClock::today();
        $userKey = fn ($id) => \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $id);
        $selectedUser = (string) old('user', $entry !== null ? $userKey($entry->user) : request()->query('user', ''));
        $asAnswer = $isEdit && ! $isAdmin;
    @endphp

    @if ($asAnswer)
        <form method="POST" action="{{ route('legacy.diary.update', $entry) }}">
            @csrf
            @method('PUT')
            <table class="kp-msg">
                <tr><td class="ohne">{{ __('legacy_classic.label.from') }}</td><td class="ohne">{{ $entry->von?->format(\App\Support\Formats::date()) }}</td><td class="ohne">{{ $entry->von?->format($timeFormat) }}</td><td class="ohne">{{ __('legacy_classic.oclock') }}</td></tr>
                <tr><td class="ohne">{{ __('legacy_classic.label.until') }}</td><td class="ohne">{{ $entry->bis?->format(\App\Support\Formats::date()) }}</td><td class="ohne">{{ $entry->bis?->format($timeFormat) }}</td><td class="ohne">{{ __('legacy_classic.oclock') }}</td></tr>
                <tr><td class="ohne" colspan="4">{{ __('legacy_classic.label.information') }}<br><span class="kp-pre">{{ $entry->inhalt }}</span></td></tr>
                <tr>
                    <td class="ohne" colspan="4">
                        <label for="tb-antwort">{{ __('legacy_classic.label.answer') }}</label><br>
                        <textarea id="tb-antwort" name="antwort" cols="25" rows="5">{{ old('antwort', $entry->antwort) }}</textarea>
                    </td>
                </tr>
                <tr><td class="ohne kp-center" colspan="4"><button type="submit">{{ __('legacy_classic.save') }}</button></td></tr>
            </table>
            {{-- Eine Antwort meldet wie im Altportal ein Problem; Auftrag und Zeitraum bleiben. --}}
            <input type="hidden" name="inhalt" value="{{ $entry->inhalt }}">
            <input type="hidden" name="von" value="{{ $entry->von?->format('Y-m-d H:i:s') }}">
            <input type="hidden" name="bis" value="{{ $entry->bis?->format('Y-m-d H:i:s') }}">
            <input type="hidden" name="gelesen" value="3">
            @if ($entry->sms === 'j')
                <input type="hidden" name="sms" value="j">
            @endif
        </form>
    @else
        <form method="POST" action="{{ $isEdit ? route('legacy.diary.update', $entry) : route('legacy.diary.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif
            <table class="kp-msg">
                @if ($isAdmin)
                    <tr>
                        <td class="ohne" colspan="4">
                            <label for="tb-user" class="kp-sr-only">{{ __('legacy_classic.label.employee') }}</label>
                            <select id="tb-user" name="user">
                                <option value="">{{ __('legacy_classic.choose_employee') }}</option>
                                @foreach ($users as $legacyUser)
                                    <option value="{{ $userKey($legacyUser->id) }}" @selected($selectedUser === $userKey($legacyUser->id))>{{ $legacyUser->uname }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="ohne"><label for="tb-von-date">{{ __('legacy_classic.label.from') }}</label></td>
                    <td class="ohne"><input id="tb-von-date" type="date" name="von_date" value="{{ old('von_date', ($entry?->von ?? $day)->format($dateFormat)) }}"></td>
                    <td class="ohne"><input type="time" name="von_time" aria-label="{{ __('legacy_classic.label.from_time') }}" value="{{ old('von_time', $entry?->von?->format($timeFormat) ?? '07:00') }}"></td>
                    <td class="ohne">{{ __('legacy_classic.oclock') }}</td>
                </tr>
                <tr>
                    <td class="ohne"><label for="tb-bis-date">{{ __('legacy_classic.label.until') }}</label></td>
                    <td class="ohne"><input id="tb-bis-date" type="date" name="bis_date" value="{{ old('bis_date', ($entry?->bis ?? $day)->format($dateFormat)) }}"></td>
                    <td class="ohne"><input type="time" name="bis_time" aria-label="{{ __('legacy_classic.label.until_time') }}" value="{{ old('bis_time', $entry?->bis?->format($timeFormat) ?? '17:00') }}"></td>
                    <td class="ohne">{{ __('legacy_classic.oclock') }}</td>
                </tr>
                <tr>
                    <td class="ohne" colspan="4">
                        <label for="tb-inhalt">{{ __('legacy_classic.label.information') }}</label><br>
                        <textarea id="tb-inhalt" name="inhalt" cols="25" rows="5">{{ old('inhalt', $entry?->inhalt) }}</textarea>
                    </td>
                </tr>
                <tr>
                    <td class="ohne">&nbsp;</td>
                    <td class="ohne"><label for="tb-sms">{{ __('legacy_classic.label.notify') }}</label></td>
                    <td class="ohne" colspan="2"><input id="tb-sms" class="rad" type="checkbox" name="sms" value="j" @checked(old('sms', $entry?->sms) === 'j')></td>
                </tr>
                <tr><td class="ohne kp-center" colspan="4"><button type="submit">{{ $isEdit ? __('legacy_classic.action.change') : __('legacy_classic.action.enter') }}</button></td></tr>
            </table>
            {{-- Wie im Altportal ist ein vom Büro gespeicherter Auftrag wieder ungelesen. --}}
            <input type="hidden" name="gelesen" value="2">
            @if ($isEdit)
                <input type="hidden" name="antwort" value="{{ $entry->antwort }}">
            @endif
        </form>

        @if ($isEdit)
            <form method="POST" action="{{ route('legacy.diary.destroy', $entry) }}" class="kp-msg">
                @csrf
                @method('DELETE')
                <label><input type="checkbox" name="confirm" value="1" required> {{ __('legacy_classic.delete_entry') }}</label>
                <button type="submit" class="kp-danger">{{ __('legacy_classic.action.delete') }}</button>
            </form>
        @endif
    @endif

    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week') }}">{{ __('legacy_classic.to_diary') }}</a></p>
@endsection
