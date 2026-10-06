{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_diary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archiv, Reiter „Aufträge“. Variablen: $diaryEntries, $filters, $sort, $dir --}}
<x-table scroll="flex" :pinRows="true" table-sort="server"
         :route="route('archive.index')"
         :current-sort="$sort ?? null"
         :current-dir="$dir ?? 'desc'"
         :sort-params="array_merge($filters ?? [], ['tab' => 'diary'])">
    <x-slot:head>
        <tr class="bg-base-200">
            <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
            <x-table.th sort="status" class="w-24 text-center">{{ __('Status') }}</x-table.th>
            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
            <x-table.th sort="end" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
            <x-table.th sort="archived" default="desc" class="w-36 whitespace-nowrap">{{ __('Archiviert am') }}</x-table.th>
            <th>{{ __('Inhalt') }}</th>
        </tr>
    </x-slot:head>
    @forelse ($diaryEntries as $entry)
        <tr class="hover">
            <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
            <td class="text-center">
                <x-status-badge :tone="$entry->status->badgeTone()">{{ $entry->statusLabel() }}</x-status-badge>
            </td>
            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->start_at?->fdatetime() ?? '—' }}</td>
            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->end_at?->fdatetime() ?? '—' }}</td>
            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->archived_at?->fdate() ?? '—' }}</td>
            <td class="text-sm">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content ?? '', 160) }}</td>
        </tr>
    @empty
        <x-table.empty icon="menu_book" :colspan="6" :title="__('Keine Einträge')" compact />
    @endforelse
</x-table>
