{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_tab.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tab: Zeiterfassung — erwartet: $project, $timeEntries, $totalMinutes, $rangeMinutes, $rangeLabel, $myMinutes --}}
@php
    $fmt = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');
    $viewer = auth()->user();
    // Massen-Neuzuordnung (MVP-508): eigene Permission, kein update-Bypass.
    $canReassign = $viewer !== null
        && ($viewer->isAdmin() || \Illuminate\Support\Facades\Gate::allows('timeEntry.reassign'))
        && $timeEntries->isNotEmpty();
    // Portal-Veröffentlichung (MVP-511): eigene Sichtbarkeits-Permission.
    $canPublish = $viewer !== null
        && ($viewer->isAdmin() || \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::CustomerPortalVisibilityManage->value))
        && $timeEntries->isNotEmpty();
    $canBulk = $canReassign || $canPublish;
    $editPolicy = app(\App\Services\Timekeeping\TimeEntryEditPolicy::class);
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
        {{-- data-bulk-form als <div>: die Zeilen enthalten eigene Lösch-Formulare,
             ein umschließendes <form> würde sie im Browser auflösen. Die Aktion
             läuft über den Dialog-Link (data-bulk-dialog-link), kein Submit. --}}
        @if ($canBulk)
        <div data-bulk-form>
            <x-bulk-toolbar :label="__(':n Zeiteinträge ausgewählt')" class="mb-2">
                <x-slot:actions>
                    @if ($canReassign)
                        <a href="{{ route('projects.time-entries.reassign-dialog', $project) }}"
                           data-bulk-dialog-link data-entry-modal-trigger
                           class="btn btn-primary btn-sm">
                            <x-icon name="person_add" /> {{ __('Benutzer zuordnen') }}
                        </a>
                    @endif
                    @if ($canPublish)
                        {{-- Kontrollierte Portal-Veröffentlichung (MVP-511):
                             ids[] werden von bulk-selection.js gespiegelt. --}}
                        <form method="POST" action="{{ route('projects.time-entries.portal-visibility', $project) }}"
                              data-bulk-ids-form class="inline">
                            @csrf
                            <input type="hidden" name="mode" value="publish">
                            <button type="submit" class="btn btn-success btn-sm"
                                    data-confirm-dialog
                                    data-confirm-message="{{ __('Die ausgewählten Zeiten im Kundenportal sichtbar machen? Beschreibungen erscheinen nur in der dafür freigegebenen Detailstufe.') }}"
                                    data-confirm-label="{{ __('Veröffentlichen') }}">
                                <x-icon name="visibility" /> {{ __('Für Portal veröffentlichen') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('projects.time-entries.portal-visibility', $project) }}"
                              data-bulk-ids-form class="inline">
                            @csrf
                            <input type="hidden" name="mode" value="retract">
                            <button type="submit" class="btn btn-warning btn-sm"
                                    data-confirm-dialog
                                    data-confirm-message="{{ __('Die ausgewählten Zeiten aus dem Kundenportal zurückziehen?') }}"
                                    data-confirm-label="{{ __('Zurückziehen') }}">
                                <x-icon name="visibility_off" /> {{ __('Zurückziehen') }}
                            </button>
                        </form>
                    @endif
                </x-slot:actions>
            </x-bulk-toolbar>
        @endif
        <x-table table-sort="server" bare
                 :route="route('projects.show', $project)"
                 :current-sort="$timeSort"
                 :current-dir="$timeDir"
                 :sort-params="['tab' => 'time']"
                 empty-icon="schedule" :empty-title="__('Noch keine Zeiteinträge erfasst.')">
                <x-slot:head>
                    <tr class="text-xs text-muted">
                        @if ($canBulk)
                            <th class="w-8">
                                <input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all
                                       aria-label="{{ __('Alle auswählen') }}">
                            </th>
                        @endif
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
                        @if ($canBulk)
                            @php($hardLock = $editPolicy->isHardLocked($entry))
                            {{-- Harte Sperren blocken nur die Neuzuordnung — die
                                 Portal-Veröffentlichung bleibt auch für abgerechnete
                                 Zeiten erlaubt, daher kein disable bei $canPublish. --}}
                            <td>
                                <input type="checkbox" class="checkbox checkbox-sm"
                                       data-bulk-checkbox value="{{ $entry->sqid }}"
                                       @disabled($hardLock['locked'] && ! $canPublish)
                                       @if ($hardLock['locked']) title="{{ $editPolicy->reasonLabel($hardLock['reason']) }}" @endif
                                       aria-label="{{ __('Zeiteintrag vom :date auswählen', ['date' => $entry->date?->fdate() ?? '—']) }}">
                            </td>
                        @endif
                        <td class="whitespace-nowrap text-xs" data-sort-value="{{ $entry->date->format('Y-m-d') }}">{{ $entry->date->fdate() }}</td>
                        <td class="text-xs">{{ $entry->user->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-right text-xs font-medium">
                            {{ $entry->hoursFormatted() }}
                            @if (! $entry->billable)
                                <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('nicht abrechenbar') }}</x-status-badge>
                            @endif
                            @if ($canPublish && $entry->customer_visible_at !== null)
                                <x-status-badge tone="info" size="xs" class="ml-1" :title="__('Im Kundenportal veröffentlicht')">{{ __('Portal') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-xs text-base-content/70">{{ $entry->task->title ?? '—' }}</td>
                        <td class="max-w-xs text-xs text-base-content/70">
                            <span class="block truncate">{{ $entry->description }}</span>
                            @if ($entry->tags->isNotEmpty())
                                <span class="mt-0.5 flex flex-wrap gap-1">
                                    @foreach ($entry->tags as $tag)
                                        <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->name }}</span>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $entry)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('projects.time-entries.edit', [$project, $entry])"
                                            :label="__('Bearbeiten')" />
                                {{-- Zeitaufteilung (Feature 103, MVP-514) --}}
                                <x-icon-btn icon="call_split"
                                            data-entry-modal-trigger
                                            :href="route('time-entries.allocations.edit', $entry)"
                                            :label="__('allocation.action.split')" />
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
        @if ($canBulk)
        </div>
        @endif
    </x-card>
</div>

{{-- App-Standard: stehendes Pagination-Footer-Panel; via data-tab-footer nur
     im Zeiterfassungs-Tab sichtbar (Initialzustand serverseitig). --}}
<x-pagination :paginator="$timeEntries" standing data-tab-footer="time"
              :hidden="request('tab', 'overview') !== 'time'" />
