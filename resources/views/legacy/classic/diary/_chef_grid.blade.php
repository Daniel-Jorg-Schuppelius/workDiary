{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _chef_grid.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tagesblöcke je Mitarbeiter wie die Chefsicht des Altportals (ashow/cheftb). Erwartet: $users, $monday, $weekOffset, $entriesByUserDay, $oncallByUserDay, $notdienstByUserDay, $route, $showRoute, $createLinks --}}
@php
    $days = collect(range(0, 6))->map(fn (int $offset) => $monday->copy()->addDays($offset));
    $hours = range(7, 20);
    $today = \App\Legacy\Support\LegacyClock::today();
    $span = max(1, $users->count() * 2);
    $locale = app()->getLocale();
@endphp
<div class="kp-scroll">
    <table class="eintrag">
        <tr>
            <td class="mitte"><a href="{{ route($route, ['week' => $weekOffset - 1]) }}" title="{{ __('legacy_classic.week_back') }}">&nbsp;&laquo;&nbsp;</a></td>
            <th colspan="{{ $span }}">{{ $monday->format(\App\Support\Formats::date()) }} – {{ $monday->copy()->addDays(6)->format(\App\Support\Formats::date()) }}</th>
            <td class="mitte"><a href="{{ route($route, ['week' => $weekOffset + 1]) }}" title="{{ __('legacy_classic.week_forward') }}">&nbsp;&raquo;&nbsp;</a></td>
        </tr>
        @foreach ($days as $day)
            @php
                $dateKey = $day->format('Y-m-d');
                $dateLabel = $day->format(\App\Support\Formats::date());
                $weekday = $day->copy()->locale($locale)->isoFormat('dd');
                $dayClass = match (true) {
                    $day->isSameDay($today) => 'kheute',
                    $day->isSunday() => 'kso',
                    $day->isSaturday() => 'ksa',
                    default => 'kopf',
                };
            @endphp
            <tr>
                <th class="{{ $dayClass }}" title="{{ $weekday }}">{{ $dateLabel }}</th>
                @foreach ($users as $legacyUser)
                    @php
                        $uid = (int) $legacyUser->id;
                        $headClass = isset($oncallByUserDay[$uid][$dateKey]) ? 'mitb' : (isset($notdienstByUserDay[$uid][$dateKey]) ? 'mitn' : 'mit');
                    @endphp
                    <th class="{{ $headClass }}" colspan="2">
                        @if ($createLinks)
                            <a href="{{ route('legacy.diary.create', ['user' => \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $uid), 'date' => $dateKey]) }}" title="{{ __('legacy_classic.new_entry_on', ['date' => $dateLabel]) }}">{{ $legacyUser->uname }}</a>
                        @else
                            {{ $legacyUser->uname }}
                        @endif
                    </th>
                @endforeach
                <th class="{{ $dayClass }}" title="{{ $dateLabel }}">{{ $weekday }}</th>
            </tr>
            @foreach ($hours as $hourIndex => $hour)
                @php
                    $even = $hourIndex % 2 === 0;
                    $hourStart = $day->copy()->setTime($hour, 0);
                    $hourEnd = $day->copy()->setTime($hour + 1, 0);
                    $hourLabel = sprintf('%02d - %02d', $hour, $hour + 1);
                @endphp
                <tr>
                    <td class="{{ $even ? 'mitte' : 'grau' }}">{{ $hourLabel }}</td>
                    @foreach ($users as $legacyUser)
                        @php
                            $uid = (int) $legacyUser->id;
                            $slotEntry = collect($entriesByUserDay[$uid][$dateKey] ?? [])->first(fn ($candidate) => $candidate->von && $candidate->bis && $candidate->von->lt($hourEnd) && $candidate->bis->gt($hourStart));
                        @endphp
                        @include('legacy.classic.diary._cells', ['entry' => $slotEntry, 'onCall' => isset($oncallByUserDay[$uid][$dateKey]), 'notdienst' => isset($notdienstByUserDay[$uid][$dateKey]), 'even' => $even])
                    @endforeach
                    <td class="{{ $even ? 'mitte' : 'grau' }}">{{ $hourLabel }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
</div>
