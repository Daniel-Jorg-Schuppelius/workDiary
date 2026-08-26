{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timesheets_tab.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tab: Stundenzettel — erwartet: $project, $timesheets (Collection<Timesheet>) --}}
<div class="flex flex-col gap-3">
    <x-card padding="p-0">
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
                    icon="description"
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
                        $tsIsSunday = $ts->work_date && \Carbon\Carbon::parse($ts->work_date)->isSunday();
                    @endphp
                    <tr class="{{ $tsIsSunday ? 'text-error' : '' }}">
                        <td data-sort-value="{{ optional($ts->work_date)->format('Y-m-d') }}">{{ optional($ts->work_date)->fdate() }}</td>
                        <td>{{ $ts->user?->name }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $ts->total_work_minutes }}">{{ \App\Support\Formats::duration((int) $ts->total_work_minutes, 'clock') }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float)$ts->total_material_net, 2, withThousandsSeparator: true) }} €</td>
                        <td>
                            <x-status-badge size="sm" :tone="$ts->statusTone()">{{ $ts->statusLabel() }}</x-status-badge>
                            @if (($ts->non_billable_count ?? 0) > 0)
                                <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('nicht abrechenbar') }}: {{ $ts->non_billable_count }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new"
                                        :href="route('projects.timesheets.show', [$project, $ts])"
                                        :label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</div>

{{-- Stehendes Pagination-Panel, nur im Stundenzettel-Tab sichtbar. --}}
<x-pagination :paginator="$timesheets" standing data-tab-footer="timesheets"
              :hidden="request('tab', 'overview') !== 'timesheets'" />
