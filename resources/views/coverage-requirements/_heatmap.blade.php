{{-- Coverage-Heatmap-Partial.
    Variablen:
      $dutyPlan      App\Models\DutyPlan
      $compact       bool (kompakte Pillen, default false)
      $forPrint      bool (s/w-freundlich, default false)
--}}
@php
    /** @var \App\Models\DutyPlan $dutyPlan */
    $compact  = $compact  ?? false;
    $forPrint = $forPrint ?? false;

    /** @var \App\Services\CoverageService $coverageService */
    $coverageService = app(\App\Services\CoverageService::class);
    $req     = $coverageService->requirementsFor($dutyPlan);
    $actual  = $coverageService->actualStaffing($dutyPlan);
    $types   = $coverageService->relevantShiftTypes($dutyPlan);
    $period  = \Carbon\CarbonPeriod::create($dutyPlan->from_date, $dutyPlan->to_date);

    // MVP-530: Qualifikations-Minima („mind. 2 Examinierte") — Ist je Qualifikation.
    $minimaQualIds = [];
    foreach ($req as $perType) {
        foreach ($perType as $cfg) {
            foreach (array_keys($cfg['qualification_minima'] ?? []) as $qid) {
                $minimaQualIds[$qid] = $qid;
            }
        }
    }
    $qualActual = $minimaQualIds === [] ? [] : $coverageService->actualQualifiedStaffing($dutyPlan, array_values($minimaQualIds));
    $qualNames  = $minimaQualIds === [] ? collect() : \App\Models\Qualification::query()
        ->whereIn('id', $minimaQualIds)->pluck('name', 'id');

    $statusClass = function (string $s) use ($forPrint): string {
        if ($forPrint) {
            return match ($s) {
                'under' => 'border-2 border-black bg-white',
                'over'  => 'border border-dashed border-black bg-white',
                'tight' => 'border border-black bg-white',
                'ok'    => 'bg-black text-white',
                default => 'bg-white text-black/40',
            };
        }
        return match ($s) {
            'under' => 'bg-error/20 text-error',
            // Q1-Kennlinien: Überbesetzung blau, „gerade noch" gelb.
            'over'  => 'bg-info/20 text-info',
            'tight' => 'bg-warning/20 text-warning',
            'ok'    => 'bg-success/20 text-success',
            default => 'bg-base-200 text-base-content/40',
        };
    };
@endphp

@if ($types->isEmpty())
    <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">table_view</span>' :title="__('Keine Schichttypen im Plan vorhanden.')" compact />
@else
    <div class="overflow-x-auto rounded-box border border-base-300">
        <table class="table table-xs w-full">
            <thead class="bg-base-200">
                <tr>
                    <th class="whitespace-nowrap">{{ __('Datum') }}</th>
                    @foreach ($types as $st)
                        <th class="text-center whitespace-nowrap" title="{{ $st->name }}">
                            <span class="badge badge-sm" @if (!$forPrint) style="background-color:{{ $st->color }};color:#fff;" @endif>
                                {{ $st->abbreviation }}
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($period as $day)
                    @php
                        $dateStr = $day->format('Y-m-d');
                        $isWeekend = $day->isWeekend();
                        $isSunday = $day->isSunday();
                    @endphp
                    <tr class="{{ $isWeekend ? 'bg-base-200/40' : '' }} {{ $isSunday ? 'text-error' : '' }}">
                        <td class="whitespace-nowrap font-medium">
                            <span class="text-xs {{ $isSunday ? 'text-error' : 'text-base-content/60' }}">{{ $day->isoFormat('dd') }}</span>
                            {{ $day->format('d.m.') }}
                        </td>
                        @foreach ($types as $st)
                            @php
                                $cfg     = $req[$dateStr][$st->id] ?? null;
                                $a       = $actual[$dateStr][$st->id] ?? 0;
                                $minVal  = $cfg['min'] ?? null;
                                $maxVal  = $cfg['max'] ?? null;
                                $status  = $coverageService->cellStatus($a, $minVal, $maxVal, $cfg['ideal'] ?? null);
                                $tooltip = $minVal === null
                                    ? sprintf('%s: %d (kein Soll)', $st->name, $a)
                                    : sprintf('%s: %d / %s%s', $st->name, $a, $minVal, $maxVal !== null ? '–' . $maxVal : '+');

                                // MVP-530: Quali-Minima verfehlt ⇒ Zelle gilt als unterbesetzt.
                                $qualGap = false;
                                foreach (($cfg['qualification_minima'] ?? []) as $qid => $needed) {
                                    $have = $qualActual[$dateStr][$st->id][$qid] ?? 0;
                                    $tooltip .= sprintf(' · %s %d/%d', $qualNames[$qid] ?? ('#' . $qid), $have, $needed);
                                    if ($have < $needed) {
                                        $qualGap = true;
                                    }
                                }
                                if ($qualGap && $status !== 'under') {
                                    $status = 'under';
                                }
                            @endphp
                            <td class="text-center px-1">
                                <span class="inline-flex min-w-10 items-center justify-center rounded px-2 py-0.5 text-xs font-semibold {{ $statusClass($status) }}"
                                      title="{{ $tooltip }}">
                                    {{ $a }}@if ($minVal !== null)<span class="opacity-60">/{{ $minVal }}</span>@endif{{ $qualGap ? '†' : '' }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (!$compact)
        <div class="mt-2 flex flex-wrap gap-3 text-xs text-base-content/60">
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded {{ $statusClass('under') }}"></span> {{ __('Unterbesetzt') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded {{ $statusClass('tight') }}"></span> {{ __('Gerade noch ausreichend') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded {{ $statusClass('ok') }}"></span> {{ __('Soll erfüllt') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded {{ $statusClass('over') }}"></span> {{ __('Überbesetzt') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded {{ $statusClass('idle') }}"></span> {{ __('Kein Soll') }}</span>
        </div>

        {{-- MVP-530: verfehlte Qualifikations-Minima als kompakte Liste („† Frühschicht Mo 15.06.: Examiniert 1/2"). --}}
        @php
            $qualGapRows = $minimaQualIds === [] ? [] : $coverageService->qualificationGaps($dutyPlan);
            $typeById = $types->keyBy('id');
        @endphp
        @if ($qualGapRows !== [])
            <div class="mt-3 rounded-box border border-error/30 bg-error/5 p-3">
                <div class="mb-1 flex items-center gap-1 text-sm font-semibold text-error">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">school</span>
                    {{ __('Qualifikations-Mindestbesetzung verfehlt') }}
                </div>
                <ul class="space-y-0.5 text-xs">
                    @foreach ($qualGapRows as $gap)
                        <li>
                            {{ \Carbon\CarbonImmutable::parse($gap['date'])->isoFormat('dd DD.MM.') }}
                            · {{ $typeById[$gap['shift_type_id']]?->name ?? ('#' . $gap['shift_type_id']) }}:
                            {{ $qualNames[$gap['qualification_id']] ?? ('#' . $gap['qualification_id']) }}
                            <span class="font-semibold">{{ $gap['actual'] }}/{{ $gap['required'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
@endif
