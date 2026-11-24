{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _employee_grid.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wochenübersicht eines Mitarbeiters wie im Altportal (ushow/usertb). Erwartet: $legacyUser, $monday, $weekOffset, $entriesByUserDay, $oncallByUserDay, $notdienstByUserDay --}}
@php
    $days = collect(range(0, 6))->map(fn (int $offset) => $monday->copy()->addDays($offset));
    $hours = range(7, 20);
    $uid = (int) $legacyUser->id;
    $locale = app()->getLocale();
    $longDate = fn ($day) => $day->copy()->locale($locale)->isoFormat('dd D. MMM YYYY');
@endphp
<div class="kp-scroll">
    <table class="eintrag tb-grid">
        <tr>
            <th colspan="16" class="kopf">{{ $longDate($days->first()) }} &nbsp; - &nbsp; {{ $longDate($days->last()) }}</th>
        </tr>
        <tr>
            <th><a href="{{ route('legacy.diary.week', ['week' => $weekOffset - 1]) }}" title="{{ __('legacy_classic.week_back') }}">&nbsp;&laquo;&nbsp;</a></th>
            <th colspan="14">{{ __('legacy_classic.work_orders') }}</th>
            <th><a href="{{ route('legacy.diary.week', ['week' => $weekOffset + 1]) }}" title="{{ __('legacy_classic.week_forward') }}">&nbsp;&raquo;&nbsp;</a></th>
        </tr>
        <tr>
            <th class="kopf">{{ __('legacy_classic.time') }}</th>
            @foreach ($days as $day)
                <th colspan="2" class="kopf" title="{{ $day->format(\App\Support\Formats::date()) }}">{{ $day->copy()->locale($locale)->isoFormat('dd') }}</th>
            @endforeach
            <th class="kopf">{{ __('legacy_classic.time') }}</th>
        </tr>
        @foreach ($hours as $hourIndex => $hour)
            @php
                $even = $hourIndex % 2 === 0;
                $hourLabel = $hour . '-' . ($hour + 1);
            @endphp
            <tr>
                <td class="{{ $even ? 'mitte' : 'grau' }}">{{ $hourLabel }}</td>
                @foreach ($days as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $hourStart = $day->copy()->setTime($hour, 0);
                        $hourEnd = $day->copy()->setTime($hour + 1, 0);
                        $slotEntry = collect($entriesByUserDay[$uid][$dateKey] ?? [])->first(fn ($candidate) => $candidate->von && $candidate->bis && $candidate->von->lt($hourEnd) && $candidate->bis->gt($hourStart));
                    @endphp
                    @include('legacy.classic.diary._cells', ['entry' => $slotEntry, 'onCall' => isset($oncallByUserDay[$uid][$dateKey]), 'notdienst' => isset($notdienstByUserDay[$uid][$dateKey]), 'even' => $even, 'showRoute' => 'legacy.diary.show'])
                @endforeach
                <td class="{{ $even ? 'mitte' : 'grau' }}">{{ $hourLabel }}</td>
            </tr>
        @endforeach
    </table>
</div>
