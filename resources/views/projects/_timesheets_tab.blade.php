{{-- Tab: Stundenzettel — erwartet: $project, $timesheets (Collection<Timesheet>) --}}
<div class="flex flex-col gap-3">
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stundenzettel') }}</span>
            @can('create', \App\Models\Timesheet::class)
                <a href="{{ route('projects.timesheets.create', $project) }}" class="btn btn-sm btn-primary">
                    + {{ __('Stundenzettel') }}
                </a>
            @endcan
        </header>

        @if ($timesheets->isEmpty())
            <div class="px-4 py-6 text-sm text-base-content/60">{{ __('Noch keine Stundenzettel erfasst.') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm" data-sortable>
                    <thead>
                        <tr>
                            <th data-sort data-sort-type="date" data-sort-default="desc">{{ __('Datum') }}</th>
                            <th data-sort>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right" data-sort data-sort-type="duration">{{ __('Arbeit') }}</th>
                            <th class="text-right" data-sort data-sort-type="number">{{ __('Material netto') }}</th>
                            <th data-sort>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($timesheets as $ts)
                            @php
                                $h = intdiv((int)$ts->total_work_minutes, 60);
                                $m = (int)$ts->total_work_minutes % 60;
                            @endphp
                            <tr>
                                <td data-sort-value="{{ optional($ts->work_date)->format('Y-m-d') }}">{{ optional($ts->work_date)->format('d.m.Y') }}</td>
                                <td>{{ $ts->user?->name }}</td>
                                <td class="text-right tabular-nums" data-sort-value="{{ (int) $ts->total_work_minutes }}">{{ $h }}:{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }} h</td>
                                <td class="text-right tabular-nums">{{ number_format((float)$ts->total_material_net, 2, ',', '.') }} €</td>
                                <td><span class="badge badge-sm badge-{{ $ts->statusTone() }}">{{ $ts->statusLabel() }}</span></td>
                                <td class="text-right">
                                    <a href="{{ route('projects.timesheets.show', [$project, $ts]) }}" class="btn btn-xs">{{ __('Öffnen') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
