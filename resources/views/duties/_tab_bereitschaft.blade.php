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
                    <td class="whitespace-nowrap">{{ $shift->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $shift->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="max-w-xs truncate">{{ $shift->note ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('shifts.edit', $shift) }}" data-entry-modal-trigger
                           class="btn btn-ghost btn-xs" title="{{ __('Bearbeiten') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :colspan="5" :title="__('Keine Einträge')" compact />
            @endforelse
</x-table>
@if ($shifts->total() > 0)
    @include('duties._pagination', ['paginator' => $shifts])
@endif
