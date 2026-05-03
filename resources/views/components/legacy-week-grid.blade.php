@props([
    'users',                   // Collection<LegacyUser>
    'days',                    // Collection<Carbon> – 7 Tage Mo–So
    'hours',                   // array, z.B. range(7, 20)
    'entriesByUserDay'  => [], // [uid][date] => LegacyDiaryEntry[]
    'oncallByUserDay'   => [], // [uid][date] => true
    'notdienstByUserDay'=> [], // [uid][date] => true
    'selectedWeek'      => null,
    'monday',                  // Carbon
    'entryRoute'        => 'legacy.diary.show', // Route für Entry-Links; null = keine Links (Archiv)
    'scrollId'          => 'week-scroll',
    'tableId'           => 'week-table',
    'toggleId'          => 'week-fit-toggle',
    'storageKey'        => 'workDiaryWeekFit',
])

@php
    $dayAbbr       = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $tableMinWidth = 'calc(4.5rem + ' . count($users) . ' * 6rem + 4.5rem)';
    $selectedWeek  ??= $monday->format('o-\WW');
@endphp

{{-- Card mit Scroll-Container --}}
<div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm mt-4">
    <div id="{{ $scrollId }}" class="h-full overflow-auto">
        <table id="{{ $tableId }}" class="week-table" data-min-width="{{ $tableMinWidth }}">
            <tbody>
            @foreach ($days as $dayIndex => $day)
                @php
                    $dateKey    = $day->format('Y-m-d');
                    $isToday    = $day->isToday();
                    $isSunday   = (int) $day->dayOfWeek === 0;
                    $isSaturday = (int) $day->dayOfWeek === 6;
                    if ($isToday)        $dayTh = 'kheute';
                    elseif ($isSunday)   $dayTh = 'kso';
                    elseif ($isSaturday) $dayTh = 'ksa';
                    else                 $dayTh = 'kopf';
                @endphp

                {{-- Tages-Kopfzeile: alle th sticky top + Ecke zusätzlich sticky left --}}
                <tr>
                    <th class="{{ $dayTh }} sticky top-0 left-0 z-20">{{ $day->format('d.m.y') }}</th>
                    @foreach ($users as $user)
                        @php
                            $uid          = (int) $user->id;
                            $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                            $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);
                            if ($hasOncall)        $userTh = 'mitb';
                            elseif ($hasNotdienst) $userTh = 'mitn';
                            else                   $userTh = 'mit';
                        @endphp
                        <th colspan="2" class="{{ $userTh }} sticky top-0 z-10" title="{{ $user->uname }}">{{ $user->uname }}</th>
                    @endforeach
                    <th class="{{ $dayTh }} sticky top-0 z-10">{{ $dayAbbr[$dayIndex] }}</th>
                </tr>

                {{-- Stundenzeilen --}}
                @foreach ($hours as $hourIndex => $hour)
                    @php
                        $bg        = $hourIndex % 2 === 0;
                        $hourStart = $day->copy()->setTime($hour, 0, 0);
                        $hourEnd   = $day->copy()->setTime($hour + 1, 0, 0);
                        $hourLabel = sprintf('%02d–%02d', $hour, $hour + 1);
                    @endphp
                    <tr>
                        <td class="{{ $bg ? 'grau' : 'mitte' }} sticky left-0 z-10">{{ $hourLabel }}</td>

                        @foreach ($users as $user)
                            @php
                                $uid          = (int) $user->id;
                                $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                                $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);

                                $entry = null;
                                foreach (($entriesByUserDay[$uid][$dateKey] ?? []) as $e) {
                                    if ($e->von && $e->bis && $e->von->lt($hourEnd) && $e->bis->gt($hourStart)) {
                                        $entry = $e;
                                        break;
                                    }
                                }

                                if ($hasOncall)        $cClass = $bg ? 'rob'  : 'rogb';
                                elseif ($hasNotdienst) $cClass = $bg ? 'ron'  : 'rogn';
                                else                   $cClass = $bg ? 'ro'   : 'rog';

                                if ($entry) {
                                    $sClass = match ($entry->gelesen) {
                                        -1      => 'ok',
                                        1       => 'work',
                                        2       => 'off',
                                        3       => 'neu',
                                        default => $hasOncall    ? ($bg ? 'lob'  : 'logb')
                                                 : ($hasNotdienst ? ($bg ? 'lon'  : 'logn')
                                                                  : ($bg ? 'lo'   : 'log')),
                                    };
                                } else {
                                    if ($hasOncall)        $sClass = $bg ? 'lob' : 'logb';
                                    elseif ($hasNotdienst) $sClass = $bg ? 'lon' : 'logn';
                                    else                   $sClass = $bg ? 'lo'  : 'log';
                                }
                            @endphp
                            <td class="{{ $cClass }}">
                                @if ($entry)
                                    @if ($entryRoute)
                                        <a href="{{ route($entryRoute, [$entry, 'week_date' => $selectedWeek]) }}"
                                           class="font-normal hover:underline"
                                           title="{{ e($entry->inhalt ?? '') }}">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 10, '') }}</a>
                                    @else
                                        <span title="{{ e($entry->inhalt ?? '') }}">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 10, '') }}</span>
                                    @endif
                                @else
                                    &nbsp;
                                @endif
                            </td>
                            <td class="{{ $sClass }}">&nbsp;</td>
                        @endforeach

                        <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var scroll  = document.getElementById('{{ $scrollId }}');
    var table   = document.getElementById('{{ $tableId }}');
    var toggle  = document.getElementById('{{ $toggleId }}');
    var minW    = table ? table.dataset.minWidth : '';
    var KEY     = '{{ $storageKey }}';
    var fitMode = localStorage.getItem(KEY) === '1';

    function apply(fit) {
        if (!scroll || !table) return;
        if (fit) {
            table.classList.add('week-table--fit');
            table.style.width = '';
        } else {
            table.classList.remove('week-table--fit');
            table.style.width = minW;
        }
        if (toggle) toggle.checked = fit;
    }

    apply(fitMode);

    if (toggle) {
        toggle.addEventListener('change', function () {
            fitMode = toggle.checked;
            localStorage.setItem(KEY, fitMode ? '1' : '0');
            apply(fitMode);
        });
    }
})();
</script>
