{{-- Notdienst: Tabelle --}}
<?php $p = array_merge($filters ?? [], ['tab' => 'notdienst']); ?>
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
                <th>{{ __('Bereitschaft') }}</th>
                <th>{{ __('Grund') }}</th>
                <th class="w-24 text-right">{{ __('Aktion') }}</th>
            </tr>
        </x-slot:head>
            @forelse ($assignments as $a)
                @php $isSunday = $a->start_at && $a->start_at->isSunday(); @endphp
                <tr class="hover {{ $isSunday ? 'text-error' : '' }}">
                    <td>{{ $a->user?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $a->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $a->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="text-base-content/60 whitespace-nowrap text-xs">
                        @if ($a->shift)
                            {{ $a->shift->start_at?->format('d.m.') }}–{{ $a->shift->end_at?->format('d.m.') }}
                        @else — @endif
                    </td>
                    <td class="max-w-xs truncate">{{ $a->reason ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('assignments.edit', $a)"
                                    :label="__('Bearbeiten')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :colspan="6" :title="__('Keine Einträge')" compact />
            @endforelse
</x-table>
@if ($assignments->total() > 0)
    @include('duties._pagination', ['paginator' => $assignments])
@endif
