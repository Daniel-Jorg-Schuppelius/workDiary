{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_entries.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zeiten am Ticket (MVP-1259). Erwartet: $ticket, $timeEntries (bereits auf den Betrachter eingegrenzt), $canBookTime --}}
<x-card data-ticket-time>
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <x-icon name="timer" class="text-muted" />
        <h3 class="font-semibold">{{ __('helpdesk.time.title') }}</h3>
        @if ($timeEntries->isNotEmpty())
            <span class="text-sm text-muted">
                {{ __('helpdesk.time.total') }}: <x-duration :minutes="(int) $timeEntries->sum('minutes')" />
                · {{ __('helpdesk.time.billable') }}: <x-duration :minutes="(int) $timeEntries->where('billable', true)->sum('minutes')" />
            </span>
        @endif
        <span class="flex-1"></span>
        @if ($canBookTime)
            <x-icon-btn icon="add" size="sm" data-entry-modal-trigger
                        :href="route('projects.time-entries.create', ['project' => $ticket->project, 'service_ticket' => $ticket->sqid])"
                        show-label>{{ __('helpdesk.time.book') }}</x-icon-btn>
        @endif
    </div>

    @if ($timeEntries->isEmpty())
        <p class="text-sm text-muted">{{ $ticket->project ? __('helpdesk.time.empty') : __('helpdesk.time.needsProject') }}</p>
    @else
        <x-table :bare="true" size="sm">
            <x-slot:head>
                <th>{{ __('Datum') }}</th>
                <th>{{ __('Person') }}</th>
                <th>{{ __('Beschreibung') }}</th>
                <th class="text-right">{{ __('Dauer') }}</th>
            </x-slot:head>
            @foreach ($timeEntries as $entry)
                <tr>
                    <td class="whitespace-nowrap">{{ $entry->date?->fdate() }}</td>
                    <td>{{ $entry->user?->name ?? '—' }}</td>
                    <td>{{ $entry->description ?: '—' }}</td>
                    <td class="text-right tabular-nums"><x-duration :minutes="(int) $entry->minutes" /></td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-card>
