{{-- Tab: Zeiterfassung — erwartet: $project, $timeEntries, $totalMinutes, $rangeMinutes, $rangeLabel, $myMinutes --}}
@php
    $fmt = fn(int $min) => intdiv($min, 60) . ':' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . ' h';
@endphp

<div class="flex flex-col gap-3">
    {{-- Summary — Standard-KPI-Kacheln wie auf der Kunden-Detailseite --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Gesamt')" :value="$fmt($totalMinutes)" tone="neutral" />
        <x-kpi-tile :label="$rangeLabel" :value="$fmt($rangeMinutes)" tone="neutral" />
        <x-kpi-tile :label="__('Meine Stunden')" :value="$fmt($myMinutes)" :hint="$rangeLabel" tone="neutral" />
    </div>

    {{-- Tabelle — Standard-Karte; Leerzustand kommt aus x-table --}}
    <x-card :title="__('Zeiteinträge')" icon="schedule" :count="$timeEntries->total()">
        <x-slot:actions>
            @can('create', \App\Models\TimeEntry::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.time-entries.create', $project)"
                            show-label>{{ __('Zeiteintrag') }}</x-icon-btn>
            @endcan
        </x-slot:actions>
        <x-table table-sort="server" bare
                 :route="route('projects.show', $project)"
                 :current-sort="$timeSort"
                 :current-dir="$timeDir"
                 :sort-params="['tab' => 'time']"
                 empty-icon="schedule" :empty-title="__('Noch keine Zeiteinträge erfasst.')">
                <x-slot:head>
                    <tr class="text-xs text-base-content/50">
                        <x-table.th sort="date" default="desc">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort="user">{{ __('Mitarbeitende') }}</x-table.th>
                        <x-table.th sort="minutes" align="right">{{ __('Zeit') }}</x-table.th>
                        <x-table.th sort="task">{{ __('Aufgabe') }}</x-table.th>
                        <x-table.th sort="description">{{ __('Beschreibung') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($timeEntries as $entry)
                    <tr class="hover:bg-base-200/50">
                        <td class="whitespace-nowrap text-xs" data-sort-value="{{ $entry->date->format('Y-m-d') }}">{{ $entry->date->fdate() }}</td>
                        <td class="text-xs">{{ $entry->user->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-right text-xs font-medium">
                            {{ $entry->hoursFormatted() }}
                            @if (! $entry->billable)
                                <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('nicht abrechenbar') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-xs text-base-content/70">{{ $entry->task->title ?? '—' }}</td>
                        <td class="max-w-xs truncate text-xs text-base-content/70">{{ $entry->description }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $entry)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('projects.time-entries.edit', [$project, $entry])"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $entry)
                                <form method="POST" action="{{ route('projects.time-entries.destroy', [$project, $entry]) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Zeiteintrag löschen') }}"
                                      data-confirm-label="{{ __('Löschen') }}"
                                      class="inline">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
            @endforeach
        </x-table>
    </x-card>
</div>

{{-- App-Standard: stehendes Pagination-Footer-Panel; via data-tab-footer nur
     im Zeiterfassungs-Tab sichtbar (Initialzustand serverseitig). --}}
<x-pagination :paginator="$timeEntries" standing data-tab-footer="time"
              :hidden="request('tab', 'overview') !== 'time'" />
