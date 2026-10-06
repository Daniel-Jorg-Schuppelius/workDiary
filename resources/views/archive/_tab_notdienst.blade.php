{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_notdienst.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archiv, Reiter „Notdienst“. Variablen: $assignmentEntries, $filters, $sort, $dir --}}
<x-table scroll="flex" :pinRows="true" table-sort="server"
         :route="route('archive.index')"
         :current-sort="$sort ?? null"
         :current-dir="$dir ?? 'desc'"
         :sort-params="array_merge($filters ?? [], ['tab' => 'notdienst'])">
    <x-slot:head>
        <tr class="bg-base-200">
            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
            <x-table.th sort="end" default="desc" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
            <th>{{ __('Dauer') }}</th>
            <th>{{ __('Grund') }}</th>
        </tr>
    </x-slot:head>
    @forelse ($assignmentEntries as $entry)
        @php
            $duration = ($entry->start_at && $entry->end_at)
                ? ((int) $entry->start_at->copy()->startOfDay()->diffInDays($entry->end_at->copy()->startOfDay()) + 1)
                : null;
        @endphp
        <tr class="hover">
            <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
            <td class="whitespace-nowrap text-xs">{{ $entry->start_at?->fdatetime() ?? '—' }}</td>
            <td class="whitespace-nowrap text-xs">{{ $entry->end_at?->fdatetime() ?? '—' }}</td>
            <td class="text-xs text-base-content/70">
                {{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}
            </td>
            <td class="max-w-xs truncate text-sm">{{ $entry->reason ?? '—' }}</td>
        </tr>
    @empty
        <x-table.empty icon="medical_services" :colspan="5" :title="__('Keine Einträge')" compact />
    @endforelse
</x-table>
