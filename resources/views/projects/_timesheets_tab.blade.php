{{-- Tab: Stundenzettel — erwartet: $project, $timesheets (Collection<Timesheet>) --}}
<div class="flex flex-col gap-3">
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Stundenzettel') }}</span>
            @can('create', \App\Models\Timesheet::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.create', $project)"
                            show-label>{{ __('Stundenzettel') }}</x-icon-btn>
            @endcan
        </header>

        @if ($timesheets->isEmpty())
            <div class="p-4">
                <x-empty-state compact
                    icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>'
                    :title="__('Noch keine Stundenzettel erfasst.')" />
            </div>
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date" default="desc">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Arbeit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Material netto') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($timesheets as $ts)
                    @php
                        $h = intdiv((int)$ts->total_work_minutes, 60);
                        $m = (int)$ts->total_work_minutes % 60;
                        $tsIsSunday = $ts->work_date && \Carbon\Carbon::parse($ts->work_date)->isSunday();
                    @endphp
                    <tr class="{{ $tsIsSunday ? 'text-error' : '' }}">
                        <td data-sort-value="{{ optional($ts->work_date)->format('Y-m-d') }}">{{ optional($ts->work_date)->format('d.m.Y') }}</td>
                        <td>{{ $ts->user?->name }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $ts->total_work_minutes }}">{{ $h }}:{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }} h</td>
                        <td class="text-right tabular-nums">{{ number_format((float)$ts->total_material_net, 2, ',', '.') }} €</td>
                        <td><x-status-badge size="sm" :tone="$ts->statusTone()">{{ $ts->statusLabel() }}</x-status-badge></td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new"
                                        :href="route('projects.timesheets.show', [$project, $ts])"
                                        :label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</div>
