{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_urlaub.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Archiv, Reiter „Urlaub“. Variablen: $vacationEntries, $isAdmin, $filters, $sort, $dir --}}
<x-table scroll="flex" :pinRows="true" table-sort="server"
         :route="route('archive.index')"
         :current-sort="$sort ?? null"
         :current-dir="$dir ?? 'desc'"
         :sort-params="array_merge($filters ?? [], ['tab' => 'urlaub'])">
    <x-slot:head>
        <tr class="bg-base-200">
            @if ($isAdmin)
                <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
            @endif
            <x-table.th sort="typ">{{ __('Typ') }}</x-table.th>
            <x-table.th sort="start" class="w-28 whitespace-nowrap">{{ __('Von') }}</x-table.th>
            <x-table.th sort="end" default="desc" class="w-28 whitespace-nowrap">{{ __('Bis') }}</x-table.th>
            <x-table.th sort="status">{{ __('Status') }}</x-table.th>
            <th class="max-w-xs">{{ __('Notiz') }}</th>
        </tr>
    </x-slot:head>
    @forelse ($vacationEntries as $v)
        @php
            $statusTone = match ($v->status) {
                \App\Enums\Vacation\VacationStatus::Approved  => 'success',
                \App\Enums\Vacation\VacationStatus::Rejected  => 'error',
                \App\Enums\Vacation\VacationStatus::Cancelled => 'ghost',
                default                   => 'neutral',
            };
            $statusLabel = match ($v->status) {
                \App\Enums\Vacation\VacationStatus::Approved  => __('Abgelaufen'),
                \App\Enums\Vacation\VacationStatus::Rejected  => __('Abgelehnt'),
                \App\Enums\Vacation\VacationStatus::Cancelled => __('Storniert'),
                default                   => $v->statusLabel(),
            };
            $typeLabel = $v->typeLabel();
        @endphp
        <tr class="hover">
            @if ($isAdmin)
                <td class="whitespace-nowrap">{{ $v->user?->name ?? '—' }}</td>
            @endif
            <td class="whitespace-nowrap">{{ $typeLabel }}</td>
            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->start_date->fdate() }}</td>
            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->end_date->fdate() }}</td>
            <td><x-status-badge :tone="$statusTone">{{ $statusLabel }}</x-status-badge></td>
            <td class="max-w-xs truncate text-sm text-base-content/70">{{ $v->note ?? '—' }}</td>
        </tr>
    @empty
        <x-table.empty icon="beach_access" :colspan="$isAdmin ? 6 : 5" :title="__('Keine Einträge')" compact />
    @endforelse
</x-table>
