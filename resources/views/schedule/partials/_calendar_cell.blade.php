{{--
    Schichtplan-Cell-Renderer für <x-month-calendar cell-view="schedule.partials._calendar_cell" />.

    Erhält pro Tag (durch die Komponente):
      $day, $items, $isOther, $isToday, $isSunday, $isSaturday,
      $isHoliday, $holidayName, $fullHeight

    Zusätzlich aus dem View-Kontext (geerbt via @include):
      $shiftsByDate, $openSlotsByDate, $complianceByShift, $isAdmin

    Layout spiegelt EXAKT die Default-Cell aus
    `resources/views/components/month-calendar.blade.php` wider, damit
    Schichtplan und Veranstaltungs-Kalender (events/calendar) optisch
    identisch sind — Wrapper-, Header- und Items-Container-Klassen sind
    1:1 übernommen. Schicht-spezifisch sind nur:
      - Klick/Drop-Handler auf dem Cell-Wrapper (Admin)
      - Badge-Rendering im Items-Container
      - Open-Slot-Buttons im Items-Container
--}}
@php
    $dateKey       = $day->toDateString();
    $dayShifts     = $items; // alias — wir bekommen sie als $items von der Komponente
    $visibleShifts = $dayShifts->take(3);
    $overflow      = max(0, $dayShifts->count() - 3);

    // Hintergrund-Tonalität — identisch zur Default-Cell der Komponente
    // (Reihenfolge: Feiertag > anderer Monat > Samstag > sonst).
    $cellBgClass = match (true) {
        $isHoliday  => 'bg-warning/5',
        $isOther    => 'bg-base-200/40 text-base-content/50',
        $isSaturday => 'bg-base-200/40',
        default     => '',
    };
    $dayNumberClass = $isSunday || $isHoliday ? 'text-error' : '';

    // fullHeight: gleiches Wrapper-Klassen-Set wie die Default-Cell
    $cellWrapperClass = ($fullHeight ?? false)
        ? 'flex min-h-0 flex-col gap-1 overflow-hidden'
        : 'flex flex-col gap-1 min-h-28';

    $cellClickable = ($isAdmin ?? false) && ! $isOther;

    // Open-Slots vorab ermitteln für das Count-Badge
    $slots          = ($openSlotsByDate ?? [])[$dateKey] ?? [];
    $openSlotsCount = collect($slots)->sum('missing');
    $totalCount     = $dayShifts->count() + $openSlotsCount;
@endphp

<div class="schedule-cell group relative {{ $cellWrapperClass }} border-b border-r border-base-300 p-1 align-top {{ $cellBgClass }} {{ $isToday ? 'ring-1 ring-inset ring-primary' : '' }} @if ($cellClickable) cursor-pointer @endif"
     @if ($cellClickable)
         data-schedule-cell
         data-date="{{ $dateKey }}"
         data-user-id="{{ auth()->user()?->sqid }}"
         onclick='scheduleCellClick(event, @js($dateKey), @js(auth()->user()?->sqid))'
         ondragover="event.preventDefault()"
         ondrop="scheduleDropCell(event, '{{ $dateKey }}', null)"
     @endif>

    {{-- Header-Zeile — identisch zur Default-Cell (Tagesnummer + Feiertag links, Count-Badge rechts) --}}
    <div class="flex shrink-0 items-center justify-between text-xs">
        <span class="flex min-w-0 flex-col leading-tight">
            <span class="font-semibold {{ $dayNumberClass }}">{{ $day->day }}</span>
            @if ($isHoliday)
                <span class="truncate text-[0.6rem] text-warning" title="{{ $holidayName }}">{{ $holidayName }}</span>
            @endif
        </span>
        @if ($totalCount > 0)
            <span class="badge badge-xs badge-ghost">{{ $totalCount }}</span>
        @endif
    </div>

    {{-- Items-Container — identisch zur Default-Cell --}}
    <div class="@if ($fullHeight ?? false) min-h-0 flex-1 overflow-y-auto @endif space-y-1">

    {{-- Sichtbare Schicht-Badges --}}
    @foreach ($visibleShifts as $shift)
        @php
            $compl = $complianceByShift[$shift->id] ?? null;
            $complTitle = $compl ? "\n⚠ ".implode("\n⚠ ", $compl['messages']) : '';
            $shiftPayload = [
                'id' => $shift->sqid,
                'user_id' => $shift->user?->sqid,
                'date' => $shift->date?->toDateString(),
                'shift_type_id' => $shift->shiftType?->sqid,
                'start_time' => $shift->resolvedStartTime(),
                'end_time' => $shift->resolvedEndTime(),
                'note' => $shift->note,
                'status' => $shift->status,
            ];
        @endphp
        <div class="schedule-shift-badge mb-0.5 flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[0.65rem] font-semibold leading-tight shadow-xs
                    @if ($isAdmin ?? false) cursor-pointer hover:opacity-80 @endif"
             style="background:{{ $shift->shiftType?->color ?? '#6b7280' }};color:#fff;"
             @if ($isAdmin ?? false)
                 draggable="true"
                 ondragstart='scheduleDragStart(event, @js($shift->sqid))'
                 onclick='event.stopPropagation(); scheduleOpenEditDialog(@js($shift->sqid), @js($shiftPayload))'
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

    {{-- Offene Schichten (Soll-Lücken) --}}
    @foreach ($slots as $slot)
        @for ($i = 0; $i < $slot['missing']; $i++)
            <button type="button"
                    @if ($cellClickable)
                        onclick="event.stopPropagation(); scheduleOpenSlotDialog('{{ $dateKey }}', {{ $slot['shift_type_id'] }})"
                    @else
                        disabled
                    @endif
                    class="schedule-shift-badge flex w-full items-center gap-1 truncate rounded border-2 border-dashed px-1.5 py-0.5 text-[0.65rem] font-semibold leading-tight text-base-content/70 hover:bg-base-100 @if ($cellClickable) cursor-pointer @endif"
                    style="border-color:{{ $slot['color'] }};color:{{ $slot['color'] }};"
                    title="{{ __('Offene Schicht') }}: {{ $slot['name'] }}">
                <span>{{ $slot['abbreviation'] }}</span>
                <span class="ml-auto text-[0.55rem] opacity-70">+</span>
            </button>
        @endfor
    @endforeach

    </div>{{-- /Items-Container --}}

    {{-- Visuelles Add-Hint im Hover --}}
    @if ($cellClickable)
        <div class="absolute inset-0 hidden items-center justify-center text-2xl text-base-content/20 group-hover:flex pointer-events-none select-none">+</div>
    @endif
</div>
