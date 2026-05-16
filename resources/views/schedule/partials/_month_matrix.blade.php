{{--
    Month matrix — server-side Blade calendar, no Alpine.js.
    Available: $anchor, $from, $to, $shifts, $shiftsByDate, $users, $holidays, $isAdmin
--}}
@php
    use Carbon\CarbonImmutable;

    // Build calendar weeks
    $monthStart = $anchor->startOfMonth();
    $monthEnd   = $anchor->endOfMonth();
    // Extend to full weeks (Mon–Sun)
    $calStart   = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
    $calEnd     = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);

    $weeks  = [];
    $cursor = $calStart;
    while ($cursor->lte($calEnd)) {
        $week = [];
        for ($d = 0; $d < 7; $d++) {
            $holidayName = $holidays->nameFor($cursor);
            $week[] = [
                'date'        => $cursor->toDateString(),
                'day'         => $cursor->day,
                'isToday'     => $cursor->isToday(),
                'isWeekend'   => $cursor->isWeekend(),
                'isSunday'    => $cursor->isSunday(),
                'isHoliday'   => $holidayName !== null,
                'holidayName' => $holidayName ?? '',
                'inMonth'     => $cursor->month === $anchor->month,
            ];
            $cursor = $cursor->addDay();
        }
        $weeks[] = $week;
    }
    $dayNames = [__('Mo'), __('Di'), __('Mi'), __('Do'), __('Fr'), __('Sa'), __('So')];
@endphp

<div class="schedule-month-matrix w-full">

    {{-- ── Day-name header ── --}}
    <div class="grid grid-cols-7 border-b border-base-300 bg-base-100 text-center text-xs font-semibold uppercase tracking-wider text-base-content/50">
        @foreach ($dayNames as $dn)
            <div class="border-r border-base-300 px-1 py-2 last:border-r-0">{{ $dn }}</div>
        @endforeach
    </div>

    {{-- ── Weeks ── --}}
    @foreach ($weeks as $week)
        <div class="grid min-h-[6rem] grid-cols-7 border-b border-base-300 last:border-b-0">
            @foreach ($week as $day)
                @php
                    $dayShifts = $shiftsByDate->get($day['date'], collect());
                    $visibleShifts = $dayShifts->take(3);
                    $overflow = $dayShifts->count() - 3;
                @endphp
                <div class="schedule-cell group relative flex flex-col border-r border-base-300 px-1 pt-0.5 pb-1 last:border-r-0
                            @if (! $day['inMonth']) opacity-40 bg-base-200/20 @endif
                            @if ($day['isToday']) bg-primary/10 @endif
                            @if ($day['isWeekend'] && ! $day['isToday'] && $day['inMonth']) bg-base-200/40 @endif
                            @if ($day['isHoliday']) bg-warning/5 @endif
                            @if ($isAdmin && $day['inMonth']) cursor-pointer @endif"
                     @if ($isAdmin && $day['inMonth'])
                         data-schedule-cell
                         data-date="{{ $day['date'] }}"
                         data-user-id="{{ auth()->id() }}"
                         onclick="scheduleCellClick(event, '{{ $day['date'] }}', {{ auth()->id() }})"
                         ondragover="event.preventDefault()"
                         ondrop="scheduleDropCell(event, '{{ $day['date'] }}', null)"
                     @endif>

                    {{-- Day number --}}
                    <span class="mb-0.5 self-end text-xs {{ $day['isToday'] ? 'flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-content' : ($day['inMonth'] ? ($day['isSunday'] ? 'text-error font-semibold' : '') : 'text-base-content/30') }}">
                        {{ $day['day'] }}
                    </span>

                    @if ($day['isHoliday'])
                        <span class="mb-0.5 truncate text-[0.6rem] text-warning" title="{{ $day['holidayName'] }}">
                            {{ truncate($day['holidayName'], 12) }}
                        </span>
                    @endif

                    {{-- Shift badges --}}
                    @foreach ($visibleShifts as $shift)
                        @php
                            $compl = $complianceByShift[$shift->id] ?? null;
                            $complTitle = $compl ? "\n⚠ ".implode("\n⚠ ", $compl['messages']) : '';
                        @endphp
                        <div class="schedule-shift-badge mb-0.5 flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[0.65rem] font-semibold leading-tight shadow-xs
                                    @if ($isAdmin) cursor-pointer hover:opacity-80 @endif"
                             style="background:{{ $shift->shiftType?->color ?? '#6b7280' }};color:#fff;"
                             @if ($isAdmin)
                                 draggable="true"
                                 ondragstart="scheduleDragStart(event, {{ $shift->id }})"
                                 onclick="event.stopPropagation(); scheduleOpenEditDialog({{ $shift->id }}, {{ json_encode($shift) }})"
                             @endif
                             title="{{ $shift->shiftType?->name ?? __('Schicht') }}{{ $shift->resolvedStartTime() ? ': '.$shift->resolvedStartTime() : '' }}{{ $shift->note ? ' · '.$shift->note : '' }}{{ $complTitle }}">
                            {{ $shift->shiftType?->abbreviation ?? '?' }}
                            @if ($shift->resolvedStartTime() || $shift->resolvedEndTime())
                                <span class="font-normal opacity-80">{{ $shift->resolvedStartTime() ?? '' }}–{{ $shift->resolvedEndTime() ?? '' }}</span>
                            @endif
                            @if ($compl)
                                <span class="ml-auto inline-flex h-3 w-3 items-center justify-center rounded-full bg-white/90 text-[0.55rem] font-bold {{ $compl['severity'] === 'error' ? 'text-error' : 'text-warning' }}" aria-hidden="true">!</span>
                            @endif
                        </div>
                    @endforeach

                    @if ($overflow > 0)
                        <span class="text-[0.6rem] text-base-content/50">+{{ $overflow }} {{ __('mehr') }}</span>
                    @endif

                    {{-- Open slots (Soll-Lücken) --}}
                    @php $slots = $openSlotsByDate[$day['date']] ?? []; @endphp
                    @foreach ($slots as $slot)
                        @for ($i = 0; $i < $slot['missing']; $i++)
                            <button type="button"
                                    @if ($isAdmin && $day['inMonth'])
                                        onclick="event.stopPropagation(); scheduleOpenSlotDialog('{{ $day['date'] }}', {{ $slot['shift_type_id'] }})"
                                    @else
                                        disabled
                                    @endif
                                    class="schedule-shift-badge mb-0.5 flex items-center gap-1 truncate rounded border-2 border-dashed px-1.5 py-0.5 text-[0.65rem] font-semibold leading-tight text-base-content/70 hover:bg-base-100 @if ($isAdmin && $day['inMonth']) cursor-pointer @endif"
                                    style="border-color:{{ $slot['color'] }};color:{{ $slot['color'] }};"
                                    title="{{ __('Offene Schicht') }}: {{ $slot['name'] }}">
                                <span>{{ $slot['abbreviation'] }}</span>
                                <span class="ml-auto text-[0.55rem] opacity-70">+</span>
                            </button>
                        @endfor
                    @endforeach

                    {{-- Visual add hint --}}
                    @if ($isAdmin && $day['inMonth'])
                        <div class="absolute inset-0 hidden items-center justify-center text-2xl text-base-content/20 group-hover:flex pointer-events-none select-none">+</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

</div>
