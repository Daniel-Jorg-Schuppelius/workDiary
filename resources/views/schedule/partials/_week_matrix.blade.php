{{--
    Week matrix — server-side Blade, no Alpine.js.
    Available: $from, $to, $shifts, $users, $holidays (HolidayService), $isAdmin
--}}
@php
    /** @var \Carbon\Carbon $from */
    /** @var \Carbon\Carbon $to */
    /** @var \Illuminate\Support\Collection<int, \App\Models\OnCallShift> $shifts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $isAdmin */
    $weekDays = [];
    $cursor   = $from->copy();
    while ($cursor->lte($to)) {
        $holidayName = $holidays->nameFor($cursor);
        $weekDays[] = [
            'date'        => $cursor->toDateString(),
            'label'       => $cursor->translatedFormat('D d.m.'),
            'isToday'     => $cursor->isToday(),
            'isWeekend'   => $cursor->isWeekend(),
            'isSunday'    => $cursor->isSunday(),
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
            @php
                if ($day['isToday']) {
                    $headerStateClass = 'bg-primary/10 text-primary font-bold';
                } elseif ($day['isSunday']) {
                    $headerStateClass = 'bg-base-200/60 text-error';
                } elseif ($day['isWeekend']) {
                    $headerStateClass = 'bg-base-200/60 text-base-content/50';
                } else {
                    $headerStateClass = '';
                }
                $headerHolidayClass = $day['isHoliday'] ? 'bg-warning/10' : '';
            @endphp
            <div class="flex min-w-32 flex-1 flex-col items-center border-r border-base-300 px-1 py-1.5 text-center text-xs font-medium {{ $headerStateClass }} {{ $headerHolidayClass }}">
                <span>{{ $day['label'] }}</span>
                @if ($day['isHoliday'])
                    <span class="mt-0.5 max-w-full truncate text-[0.6rem] text-warning" title="{{ $day['holidayName'] }}">
                        {{ \CommonToolkit\Helper\Data\StringHelper::truncate($day['holidayName'], 14) }}
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
                <span class="truncate" title="{{ $rowUser->name }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($rowUser->name, 18) }}</span>
            </div>

            {{-- Day cells --}}
            @foreach ($weekDays as $day)
                @php
                    $cellShifts = $userShifts->filter(fn($s) => $s->date->toDateString() === $day['date']);
                    if ($day['isToday']) {
                        $cellStateClass = 'bg-primary/5';
                    } elseif ($day['isWeekend']) {
                        $cellStateClass = 'bg-base-200/40';
                    } else {
                        $cellStateClass = '';
                    }
                    $cellHolidayClass = $day['isHoliday'] ? 'bg-warning/5' : '';
                    $cellCursorClass = $isAdmin ? 'cursor-pointer' : '';
                @endphp
                <div class="schedule-cell group relative min-h-16 min-w-32 flex-1 border-r border-base-300 p-1 {{ $cellStateClass }} {{ $cellHolidayClass }} {{ $cellCursorClass }}"
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
                        @php
                            $compl = $complianceByShift[$shift->id] ?? null;
                            $complTitle = $compl ? "\n⚠ ".implode("\n⚠ ", $compl['messages']) : '';
                        @endphp
                        <div class="schedule-shift-badge mb-0.5 flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold leading-tight shadow-xs
                                    @if ($isAdmin) cursor-pointer hover:opacity-80 @endif"
                             style="background:{{ $shift->shiftType?->color ?? '#6b7280' }};color:#fff;"
                             @if ($isAdmin)
                                 draggable="true"
                                 ondragstart="scheduleDragStart(event, {{ $shift->id }})"
                                 onclick="event.stopPropagation(); scheduleOpenEditDialog({{ $shift->id }}, {{ json_encode($shift) }})"
                             @endif
                             title="{{ $shift->shiftType?->name ?? __('Schicht') }}{{ $shift->resolvedStartTime() ? ': '.$shift->resolvedStartTime() : '' }}{{ $shift->resolvedEndTime() ? '–'.$shift->resolvedEndTime() : '' }}{{ $shift->note ? ' · '.$shift->note : '' }}{{ $complTitle }}">
                            <span>{{ $shift->shiftType?->abbreviation ?? '?' }}</span>
                            @if ($shift->resolvedStartTime() || $shift->resolvedEndTime())
                                <span class="font-normal opacity-80">{{ $shift->resolvedStartTime() ?? '' }}–{{ $shift->resolvedEndTime() ?? '' }}</span>
                            @endif
                            @if ($compl)
                                <span class="ml-auto inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white/90 text-[0.6rem] font-bold {{ $compl['severity'] === 'error' ? 'text-error' : 'text-warning' }}" aria-hidden="true">!</span>
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

    {{-- ── Open-slot row (Soll vs Ist) ── --}}
    @php
        $hasOpenSlots = collect($weekDays)->contains(fn($d) => ! empty($openSlotsByDate[$d['date']] ?? []));
    @endphp
    @if ($hasOpenSlots)
        <div class="flex border-b border-base-300 bg-base-200/30">
            <div class="sticky left-0 z-10 flex w-36 shrink-0 items-center border-r border-base-300 bg-base-200/60 px-3 py-2 text-sm font-semibold text-base-content/70">
                {{ __('Offen') }}
            </div>
            @foreach ($weekDays as $day)
                @php $slots = $openSlotsByDate[$day['date']] ?? []; @endphp
                <div class="schedule-cell relative min-h-12 min-w-32 flex-1 border-r border-base-300 p-1">
                    @foreach ($slots as $slot)
                        @for ($i = 0; $i < $slot['missing']; $i++)
                            <button type="button"
                                    @if ($isAdmin)
                                        onclick="scheduleOpenSlotDialog('{{ $day['date'] }}', {{ $slot['shift_type_id'] }})"
                                    @else
                                        disabled
                                    @endif
                                    class="schedule-shift-badge mb-0.5 flex w-full items-center gap-1 rounded border-2 border-dashed px-1.5 py-0.5 text-xs font-semibold leading-tight text-base-content/70 hover:bg-base-100 @if ($isAdmin) cursor-pointer @endif"
                                    style="border-color:{{ $slot['color'] }};color:{{ $slot['color'] }};"
                                    title="{{ __('Offene Schicht') }}: {{ $slot['name'] }}">
                                <span>{{ $slot['abbreviation'] }}</span>
                                <span class="ml-auto text-[0.6rem] opacity-70">+</span>
                            </button>
                        @endfor
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

</div>
