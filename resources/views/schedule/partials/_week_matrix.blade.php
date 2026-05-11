{{--
    Week matrix — server-side Blade, no Alpine.js.
    Available: $from, $to, $shifts, $users, $holidays (HolidayService), $isAdmin
--}}
@php
    $weekDays = [];
    $cursor   = $from->copy();
    while ($cursor->lte($to)) {
        $holidayName = $holidays->nameFor($cursor);
        $weekDays[] = [
            'date'        => $cursor->toDateString(),
            'label'       => $cursor->translatedFormat('D d.m.'),
            'isToday'     => $cursor->isToday(),
            'isWeekend'   => $cursor->isWeekend(),
            'isHoliday'   => $holidayName !== null,
            'holidayName' => $holidayName ?? '',
        ];
        $cursor = $cursor->addDay();
    }

    $rowUsers = $isAdmin
        ? $users
        : $users->filter(fn($u) => $shifts->contains('user_id', $u->id));
    if ($rowUsers->isEmpty()) { $rowUsers = $users; }
@endphp

<div class="schedule-matrix w-full select-none">

    {{-- ── Header ── --}}
    <div class="sticky top-0 z-20 flex border-b border-base-300 bg-base-100 shadow-xs">
        <div class="w-36 shrink-0 border-r border-base-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-base-content/50">
            {{ __('Mitarbeiter') }}
        </div>
        @foreach ($weekDays as $day)
            <div class="flex min-w-[8rem] flex-1 flex-col items-center border-r border-base-300 px-1 py-1.5 text-center text-xs font-medium
                        @if ($day['isToday']) bg-primary/10 text-primary font-bold
                        @elseif ($day['isWeekend']) bg-base-200/60 text-base-content/50
                        @endif
                        @if ($day['isHoliday']) bg-warning/10 @endif">
                <span>{{ $day['label'] }}</span>
                @if ($day['isHoliday'])
                    <span class="mt-0.5 max-w-full truncate text-[0.6rem] text-warning" title="{{ $day['holidayName'] }}">
                        {{ truncate($day['holidayName'], 14) }}
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ── Rows ── --}}
    @forelse ($rowUsers as $rowUser)
        @php $userShifts = $shifts->where('user_id', $rowUser->id); @endphp
        <div class="flex border-b border-base-300 hover:bg-base-200/20">

            {{-- User label --}}
            <div class="sticky left-0 z-10 flex w-36 shrink-0 items-center border-r border-base-300 bg-base-100 px-3 py-2 text-sm font-medium">
                <span class="truncate" title="{{ $rowUser->name }}">{{ truncate($rowUser->name, 18) }}</span>
            </div>

            {{-- Day cells --}}
            @foreach ($weekDays as $day)
                @php
                    $cellShifts = $userShifts->filter(fn($s) => $s->date->toDateString() === $day['date']);
                @endphp
                <div class="schedule-cell group relative min-h-[4rem] min-w-[8rem] flex-1 border-r border-base-300 p-1
                            @if ($day['isToday']) bg-primary/5
                            @elseif ($day['isWeekend']) bg-base-200/40
                            @endif
                            @if ($day['isHoliday']) bg-warning/5 @endif
                            @if ($isAdmin) cursor-pointer @endif"
                     @if ($isAdmin)
                         data-schedule-cell
                         data-date="{{ $day['date'] }}"
                         data-user-id="{{ $rowUser->id }}"
                         onclick="scheduleCellClick(event, '{{ $day['date'] }}', {{ $rowUser->id }})"
                         ondragover="event.preventDefault()"
                         ondrop="scheduleDropCell(event, '{{ $day['date'] }}', {{ $rowUser->id }})"
                     @endif>

                    {{-- Shift badges --}}
                    @foreach ($cellShifts as $shift)
                        <div class="schedule-shift-badge mb-0.5 flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold leading-tight shadow-xs
                                    @if ($isAdmin) cursor-pointer hover:opacity-80 @endif"
                             style="background:{{ $shift->shiftType?->color ?? '#6b7280' }};color:#fff;"
                             @if ($isAdmin)
                                 draggable="true"
                                 ondragstart="scheduleDragStart(event, {{ $shift->id }})"
                                 onclick="event.stopPropagation(); scheduleOpenEditDialog({{ $shift->id }}, @json($shift))"
                             @endif
                             title="{{ $shift->shiftType?->name ?? __('Schicht') }}{{ $shift->resolvedStartTime() ? ': '.$shift->resolvedStartTime() : '' }}{{ $shift->resolvedEndTime() ? '–'.$shift->resolvedEndTime() : '' }}{{ $shift->note ? ' · '.$shift->note : '' }}">
                            <span>{{ $shift->shiftType?->abbreviation ?? '?' }}</span>
                            @if ($shift->resolvedStartTime())
                                <span class="font-normal opacity-80">{{ $shift->resolvedStartTime() }}</span>
                            @endif
                        </div>
                    @endforeach

                    {{-- Visual add hint --}}
                    @if ($isAdmin)
                        <div class="absolute inset-0 hidden items-center justify-center text-2xl text-base-content/20 group-hover:flex pointer-events-none select-none">+</div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <p class="py-12 text-center text-sm text-base-content/50">{{ __('Keine Schichten in diesem Zeitraum.') }}</p>
    @endforelse

</div>
