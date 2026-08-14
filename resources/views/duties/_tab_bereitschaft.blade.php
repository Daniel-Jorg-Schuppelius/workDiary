{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_bereitschaft.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereitschaft: Tabelle --}}
<?php $p = array_merge($filters ?? [], ['tab' => 'bereitschaft']); ?>
<x-table scroll="flex" :pinRows="true" :zebra="true"
         table-sort="server"
         :route="route('duties.index')"
         :current-sort="$sort ?? null"
         :current-dir="$dir ?? 'desc'"
         :sort-params="$p">
        <x-slot:head>
            <tr class="bg-base-200">
                <x-table.th sort="mitarbeiter">{{ __('Mitarbeiter') }}</x-table.th>
                <x-table.th sort="von" default>{{ __('Beginn') }}</x-table.th>
                <x-table.th sort="bis">{{ __('Ende') }}</x-table.th>
                <th>{{ __('Notiz') }}</th>
                <th class="w-24 text-right">{{ __('Aktion') }}</th>
            </tr>
        </x-slot:head>
            @forelse ($shifts as $shift)
                @php $isSunday = $shift->start_at && $shift->start_at->isSunday(); @endphp
                <tr class="hover {{ $isSunday ? 'text-error' : '' }}">
                    <td>{{ $shift->user?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $shift->start_at?->fdatetime() ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $shift->end_at?->fdatetime() ?? '—' }}</td>
                    <td class="max-w-xs truncate">{{ $shift->note ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('shifts.edit', $shift)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :colspan="5" :title="__('Keine Einträge')" compact />
            @endforelse
</x-table>
@if ($shifts->total() > 0)
    @include('duties._pagination', ['paginator' => $shifts])
@endif
