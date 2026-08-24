{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : month-calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-month-calendar> — wiederverwendbare Monatsraster-Komponente
    (Mo–So, 5–6 Wochen-Zeilen).

    Props:
      - month        Carbon-Instanz oder Y-m / Y-m-d-String: Anker-Monat
      - itemsByDay   Collection|array (key: Y-m-d) → Items für den Tag
      - itemView     optionaler Blade-Partial-Name. Wird INNERHALB der
                     Standard-Cell pro Tag mit $day, $items, $isOther,
                     $isToday gerendert. Ohne itemView wird ein generischer
                     Default ausgegeben (Anzahl + title-Tooltip pro Item).
      - cellView     optionaler Blade-Partial-Name. Wenn gesetzt, ÜBERSCHREIBT
                     die komplette Tag-Zelle (inkl. Wrapper-<div>, Border,
                     Background) — für komplexe Fälle, in denen die Cell
                     eigene HTML-Attribute (z. B. data-*, onclick) und
                     Tonalitäten braucht (siehe Schichtplan-Drag-&-Drop in
                     `resources/views/schedule/partials/_calendar_cell.blade.php`).
                     Bekommt $day, $items, $isOther, $isToday, $isSunday,
                     $isSaturday, $isHoliday, $holidayName, $fullHeight.
                     Hat Vorrang vor itemView.
      - fullHeight   true → das Grid füllt den verbleibenden Flex-Container
                     vollständig aus (jede Wochenzeile bekommt gleichmäßig
                     1fr). Erfordert, dass der Parent flex-col/min-h-0 ist
                     und die View `@section('wrapper-height-class', ...)` +
                     `@section('main-class', ...)` setzt — analog zum
                     Schichtplan (`resources/views/schedule/index.blade.php`).
                     Default false → fixe Mindesthöhe pro Tag-Zelle.
      - showWeekHeader   Wochentag-Kopfzeile anzeigen (Default true)
      - showWeekNumbers  KW-Spalte links rendern (Default false)
      - holidays     optional App\Services\HolidayService — wenn gesetzt,
                     bekommt jeder Feiertag einen warning-getönten Hintergrund
                     plus den Feiertagsnamen unter der Tagesnummer (gleiche
                     Konvention wie der Schichtplan, siehe
                     `resources/views/schedule/partials/_week_matrix.blade.php`).
                     Sonntage werden unabhängig davon mit error-Text (rot)
                     markiert; Samstage etwas abgesetzt mit base-200/40-Hintergrund.

    Slots:
      - default          (optional) — kompletter Toolbar/Beschriftungs-Block
                                       oberhalb des Grids; wird vom Caller
                                       gestellt (z. B. Vor-/Nachmonat-Buttons).
      - cell             (named, optional) — wenn gesetzt, wird er für jede
                                              Tag-Zelle gerendert. Innerhalb
                                              des Slots stehen `$day` und
                                              `$items` durch Variablen-Lookup
                                              NICHT zur Verfügung — nutze
                                              dafür `itemView`.

    Beispiel:
        <x-month-calendar
            :month="$monthStart"
            :items-by-day="$eventsByDay"
            item-view="events.partials._calendar_cell"
            full-height />
--}}
@props([
    'month',
    'itemsByDay' => [],
    'itemView' => null,
    'cellView' => null,
    'cellData' => [],
    'fullHeight' => false,
    'showWeekHeader' => true,
    'showWeekNumbers' => false,
    'holidays' => null,
])

@php
    use Carbon\CarbonImmutable;

    $monthAnchor = $month instanceof \DateTimeInterface
        ? CarbonImmutable::instance($month)->startOfMonth()
        : CarbonImmutable::parse((string) $month)->startOfMonth();

    $weekDays = [
        ['label' => __('Mo'), 'tone' => ''],
        ['label' => __('Di'), 'tone' => ''],
        ['label' => __('Mi'), 'tone' => ''],
        ['label' => __('Do'), 'tone' => ''],
        ['label' => __('Fr'), 'tone' => ''],
        ['label' => __('Sa'), 'tone' => 'text-muted'],
        ['label' => __('So'), 'tone' => 'text-error'],
    ];

    $gridStart = $monthAnchor->startOfWeek(CarbonImmutable::MONDAY);
    $gridEnd   = $monthAnchor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

    $days = [];
    for ($cursor = $gridStart; $cursor->lte($gridEnd); $cursor = $cursor->addDay()) {
        $days[] = $cursor;
    }
    $weekCount = max(1, (int) ceil(count($days) / 7));

    // Items-Map normalisieren: alles auf Collections umstellen, damit Partials
    // konsistent ->count(), ->take() etc. nutzen können.
    $itemsCollection = collect($itemsByDay)->map(fn($v) => collect($v));

    // Layout-Klassen je nach fullHeight
    $wrapperClasses = $fullHeight
        ? 'flex min-h-0 flex-1 flex-col overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs'
        : 'flex flex-col overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs';

    $gridClasses = $fullHeight
        ? 'grid min-h-0 flex-1 overflow-auto'
        : 'grid overflow-auto';

    $headerColCount = $showWeekNumbers ? 8 : 7;
    $headerGridCols = $showWeekNumbers ? 'grid-cols-[3rem_repeat(7,1fr)]' : 'grid-cols-7';

    $cellWrapperClass = $fullHeight ? 'flex min-h-0 flex-col gap-1 overflow-hidden' : 'flex flex-col gap-1 min-h-28';
@endphp

<div {{ $attributes->class([$wrapperClasses]) }}>
    @if (trim($slot ?? '') !== '')
        {{-- Default-Slot: optionale Toolbar/Beschriftung oberhalb des Grids --}}
        <div class="shrink-0 border-b border-base-300 px-3 py-2">{{ $slot }}</div>
    @endif

    @if ($showWeekHeader)
        <div class="grid shrink-0 {{ $headerGridCols }} border-b border-base-300 bg-base-200 text-center text-xs font-semibold uppercase tracking-wide">
            @if ($showWeekNumbers)
                <div class="px-2 py-2 text-muted">{{ __('KW') }}</div>
            @endif
            @foreach ($weekDays as $w)
                <div class="px-2 py-2 {{ $w['tone'] }}">{{ $w['label'] }}</div>
            @endforeach
        </div>
    @endif

    <div class="{{ $gridClasses }} {{ $headerGridCols }}"
         @if ($fullHeight) style="grid-template-rows: repeat({{ $weekCount }}, minmax(0, 1fr));" @endif>
        @foreach ($days as $index => $day)
            @if ($showWeekNumbers && $index % 7 === 0)
                <div class="flex items-start justify-center border-b border-r border-base-300 bg-base-200/60 px-1 py-1 text-xs font-mono text-muted">
                    {{ $day->isoWeek }}
                </div>
            @endif
            @php
                $isOther     = $day->month !== $monthAnchor->month;
                $isToday     = $day->isToday();
                $isSunday    = $day->isSunday();
                $isSaturday  = $day->isSaturday();
                $holidayName = $holidays !== null ? $holidays->nameFor($day) : null;
                $isHoliday   = $holidayName !== null;
                $items       = $itemsCollection->get($day->toDateString(), collect());

                // Hintergrund-Tonalität nach Schichtplan-Konvention:
                //   - andere Monatstage: leicht gegraut
                //   - Feiertag: warning-Tönung (Gelb)
                //   - Samstag (kein Feiertag): leicht abgesetzt
                //   - sonst neutral
                $cellBgClass = match (true) {
                    $isHoliday  => 'bg-warning/5',
                    $isOther    => 'bg-base-200/40 text-muted',
                    $isSaturday => 'bg-base-200/40',
                    default     => '',
                };
                $dayNumberClass = $isSunday || $isHoliday ? 'text-error' : '';
            @endphp
            @if ($cellView)
                @include($cellView, array_merge([
                    'day'         => $day,
                    'items'       => $items,
                    'isOther'     => $isOther,
                    'isToday'     => $isToday,
                    'isSunday'    => $isSunday,
                    'isSaturday'  => $isSaturday,
                    'isHoliday'   => $isHoliday,
                    'holidayName' => $holidayName,
                    'fullHeight'  => $fullHeight,
                ], (array) $cellData))
            @else
                <div class="{{ $cellWrapperClass }} border-b border-r border-base-300 p-1 align-top {{ $cellBgClass }} {{ $isToday ? 'ring-1 ring-inset ring-primary' : '' }}">
                    <div class="flex shrink-0 items-center justify-between text-xs">
                        <span class="flex min-w-0 flex-col leading-tight">
                            <span class="font-semibold {{ $dayNumberClass }}">{{ $day->day }}</span>
                            @if ($isHoliday)
                                <span class="truncate text-[0.6rem] text-warning" title="{{ $holidayName }}">{{ $holidayName }}</span>
                            @endif
                        </span>
                        @if ($items->isNotEmpty())
                            <span class="badge badge-xs badge-ghost">{{ $items->count() }}</span>
                        @endif
                    </div>
                    <div class="@if ($fullHeight) min-h-0 flex-1 overflow-y-auto @endif space-y-1">
                        @if ($itemView)
                            @include($itemView, ['day' => $day, 'items' => $items, 'isOther' => $isOther, 'isToday' => $isToday])
                        @else
                            @foreach ($items as $item)
                                @php
                                    $label = is_array($item) ? ($item['label'] ?? '') : (is_object($item) ? ($item->label ?? (string) $item) : (string) $item);
                                @endphp
                                <div class="truncate rounded bg-base-200 px-1 py-0.5 text-xs" title="{{ $label }}">
                                    {{ $label }}
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
