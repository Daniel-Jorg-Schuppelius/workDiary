{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_tab.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tab: Zeiterfassung — erwartet: $project, $timeEntries, $totalMinutes, $rangeMinutes, $rangeLabel, $myMinutes, $machineMinutes, $seesAllTimes,
     $targetMinutes, $openShortfalls, $shortfalls, $showAllShortfalls, $canManageTargets --}}
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
    {{-- Ohne Sicht auf alle Zeiten zählen beide Summen nur die eigenen; die
         dritte Kachel wäre dann doppelt. --}}
    @php
        $machineMinutes = (int) ($machineMinutes ?? 0);
        $tileCols = match (($seesAllTimes ? 3 : 2) + ($machineMinutes > 0 ? 1 : 0)) {
            4 => 'sm:grid-cols-4',
            3 => 'sm:grid-cols-3',
            default => 'sm:grid-cols-2',
        };
    @endphp
    <div class="grid grid-cols-1 gap-3 {{ $tileCols }}">
        <x-kpi-tile :label="__('Gesamt')" :value="$fmt($totalMinutes)" tone="neutral"
                    :hint="$seesAllTimes ? null : __('nur eigene Zeiten')" />
        <x-kpi-tile :label="$rangeLabel" :value="$fmt($rangeMinutes)" tone="neutral"
                    :hint="$seesAllTimes ? null : __('nur eigene Zeiten')" />
        @if ($seesAllTimes)
            <x-kpi-tile :label="__('Meine Stunden')" :value="$fmt($myMinutes)" :hint="$rangeLabel" tone="neutral" />
        @endif
        @if ($machineMinutes > 0)
            {{-- Gerätelaufzeit (Feature 193): in „Gesamt“ enthalten, hier gesondert und gefiltert erreichbar. --}}
            <x-kpi-tile :label="\App\Enums\TimeEntry\TimeEntryKind::Machine->label()" :value="$fmt($machineMinutes)" tone="secondary"
                        :hint="__('in Gesamt enthalten')"
                        :href="route('projects.times', ['project' => $project->sqid, 'kind' => \App\Enums\TimeEntry\TimeEntryKind::Machine->value])" />
        @endif
    </div>

    {{-- Sollzeiten je Person (Feature 194): Soll im Zeitraum, Befunde unter Soll, Pflege. --}}
    @php
        $targetMinutes = $targetMinutes ?? null;
        $openShortfalls = (int) ($openShortfalls ?? 0);
        $shortfalls = $shortfalls ?? collect();
        $canManageTargets = (bool) ($canManageTargets ?? false);
        $shortfallsUrl = route('projects.show', ['project' => $project, 'tab' => 'time', 'show' => 'shortfalls']);
    @endphp
    @if ($canManageTargets || $targetMinutes !== null)
        <x-card :title="__('Sollzeiten')" icon="flag" :count="$openShortfalls > 0 ? $openShortfalls : null">
            <x-slot:actions>
                @if ($canManageTargets)
                    <x-icon-btn icon="edit_calendar" size="sm" data-entry-modal-trigger
                                :href="route('projects.time-targets.edit', $project)" show-label>{{ __('Sollzeiten pflegen') }}</x-icon-btn>
                @endif
            </x-slot:actions>
            @if ($targetMinutes !== null)
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-kpi-tile :label="__('Soll im Zeitraum')" :value="$fmt((int) $targetMinutes)" :hint="$rangeLabel" tone="neutral" :href="$shortfallsUrl" />
                    <x-kpi-tile :label="__('Offene Unterschreitungen')" :value="$openShortfalls" :tone="$openShortfalls > 0 ? 'warning' : 'success'" :href="$shortfallsUrl" />
                </div>
            @else
                <x-empty-state compact icon="flag" :title="__('Noch keine Sollzeiten hinterlegt.')" />
            @endif
            @if ($shortfalls->isNotEmpty())
                <x-table class="mt-3">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Person') }}</th>
                            <th class="text-right">{{ __('Soll') }}</th>
                            <th class="text-right">{{ __('Gebucht') }}</th>
                            <th class="text-right">{{ __('Fehlend') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($shortfalls as $shortfall)
                        <tr>
                            <td class="whitespace-nowrap">{{ $shortfall->date->fdate() }}</td>
                            <td>{{ $shortfall->user?->name }}</td>
                            <td class="text-right">{{ $fmt((int) $shortfall->target_minutes) }}</td>
                            <td class="text-right">{{ $fmt((int) $shortfall->booked_minutes) }}</td>
                            <td class="text-right">{{ $fmt((int) $shortfall->shortfall_minutes) }}</td>
                            <td><x-status-badge :tone="$shortfall->status->tone()">{{ $shortfall->status->label() }}</x-status-badge></td>
                            <td class="text-right whitespace-nowrap">
                                @if ($canManageTargets && $shortfall->status === \App\Enums\Project\ShortfallStatus::Open)
                                    <x-action-form :action="route('projects.time-shortfalls.acknowledge', [$project, $shortfall])">
                                        <x-icon-btn icon="done" size="xs" tone="ghost" type="submit" :title="__('Befund bestätigen')" />
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-2 text-xs">
                    @if ($showAllShortfalls ?? false)
                        <a class="link link-hover" href="{{ route('projects.show', ['project' => $project, 'tab' => 'time']) }}">{{ __('Nur offene') }}</a>
                    @else
                        <a class="link link-hover" href="{{ $shortfallsUrl }}">{{ __('Alle anzeigen') }}</a>
                    @endif
                </div>
            @endif
        </x-card>
    @endif

    {{-- Tabelle — Standard-Karte; Leerzustand kommt aus x-table --}}
    <x-card :title="__('Zeiteinträge')" icon="schedule" :count="$timeEntries->total()">
        <x-slot:actions>
            @can('create', \App\Models\Time\TimeEntry::class)
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
                        <x-button :href="route('projects.time-entries.reassign-dialog', $project)" icon="person_add"
                                data-bulk-dialog-link data-entry-modal-trigger>
                            {{ __('Benutzer zuordnen') }}
                        </x-button>
                    @endif
                    @if ($canPublish)
                        {{-- Kontrollierte Portal-Veröffentlichung (MVP-511):
                             ids[] werden von bulk-selection.js gespiegelt. --}}
                        <form method="POST" action="{{ route('projects.time-entries.portal-visibility', $project) }}"
                              data-bulk-ids-form class="inline">
                            @csrf
                            <input type="hidden" name="mode" value="publish">
                            <x-button type="submit" tone="success" icon="visibility" data-confirm-dialog
                                    data-confirm-message="{{ __('Die ausgewählten Zeiten im Kundenportal sichtbar machen? Beschreibungen erscheinen nur in der dafür freigegebenen Detailstufe.') }}"
                                    data-confirm-label="{{ __('Veröffentlichen') }}">
                                {{ __('Für Portal veröffentlichen') }}
                            </x-button>
                        </form>
                        <form method="POST" action="{{ route('projects.time-entries.portal-visibility', $project) }}"
                              data-bulk-ids-form class="inline">
                            @csrf
                            <input type="hidden" name="mode" value="retract">
                            <x-button type="submit" tone="warning" icon="visibility_off" data-confirm-dialog
                                    data-confirm-message="{{ __('Die ausgewählten Zeiten aus dem Kundenportal zurückziehen?') }}"
                                    data-confirm-label="{{ __('Zurückziehen') }}">
                                {{ __('Zurückziehen') }}
                            </x-button>
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
                    <tr id="time-entry-{{ $entry->sqid }}" class="hover:bg-base-200/50">
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
                        <td class="text-xs text-muted">{{ $entry->task->title ?? '—' }}</td>
                        <td class="max-w-xs text-xs text-muted">
                            <span class="block truncate">{{ $entry->description }}</span>
                            @if ($entry->tags->isNotEmpty())
                                <span class="mt-0.5 flex flex-wrap gap-1">
                                    @foreach ($entry->tags as $tag)
                                        <x-status-badge tone="plain" size="xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->displayName() }}</x-status-badge>
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
