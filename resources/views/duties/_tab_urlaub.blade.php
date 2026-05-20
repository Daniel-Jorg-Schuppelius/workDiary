{{-- Urlaub: Tabelle --}}
<?php $p = array_merge($filters ?? [], ['tab' => 'urlaub']); ?>
use App\Enums\Vacation\VacationStatus;
<x-table scroll="flex" :pinRows="true" :zebra="true" size="sm"
         table-sort="server"
         :route="route('duties.index')"
         :current-sort="$sort ?? null"
         :current-dir="$dir ?? 'desc'"
         :sort-params="$p">
        <x-slot:head>
            <tr class="bg-base-200">
                @if ($isAdmin)
                    <x-table.th sort="mitarbeiter">{{ __('Mitarbeiter') }}</x-table.th>
                @endif
                <x-table.th sort="von" default>{{ __('Zeitraum') }}</x-table.th>
                <th>{{ __('Tage') }}</th>
                <x-table.th sort="typ">{{ __('Typ') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <th class="max-w-xs">{{ __('Notiz') }}</th>
                <th class="w-px"></th>
            </tr>
        </x-slot:head>
            @forelse ($vacations as $v)
                @php $days = $v->workingDays($holidayService); @endphp
                <tr class="hover">
                    @if ($isAdmin)
                        <td class="font-medium">{{ $v->user?->name ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap">
                        {{ $v->start_date->format('d.m.Y') }}
                        @if ($v->start_date->ne($v->end_date))
                            – {{ $v->end_date->format('d.m.Y') }}
                        @endif
                    </td>
                    <td class="tabular-nums">{{ $days }}</td>
                    <td>
                        <span class="badge badge-sm badge-ghost">{{ $v->typeLabel() }}</span>
                    </td>
                    <td>
                        <span class="badge badge-sm badge-{{ $v->statusTone() }}">{{ $v->statusLabel() }}</span>
                        @if ($v->reject_reason)
                            <span class="tooltip tooltip-right" data-tip="{{ $v->reject_reason }}">
                                <x-icon name="info" class="text-error" />
                            </span>
                        @endif
                    </td>
                    <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $v->note }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            @can('decide', $v)
                                @if ($v->status === \App\Models\VacationStatus::Pending->value)
                                    <form method="POST" action="{{ route('vacations.approve', $v) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <x-icon-btn icon="check" tone="success" type="submit" :label="__('Genehmigen')" />
                                    </form>
                                    <x-icon-btn icon="close" tone="error"
                                                data-entry-modal-trigger
                                                :href="route('vacations.reject-form', $v) . '?dialog=1'"
                                                :label="__('Ablehnen')" />
                                @endif
                            @endcan

                            @can('update', $v)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('vacations.edit', $v) . '?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan

                            @can('cancel', $v)
                                <form method="POST" action="{{ route('vacations.cancel', $v) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Urlaubsantrag wirklich stornieren?') }}"
                                      data-confirm-label="{{ __('Stornieren') }}">
                                    @csrf @method('PATCH')
                                    <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Stornieren')" />
                                </form>
                            @endcan

                            @can('delete', $v)
                                <form method="POST" action="{{ route('vacations.destroy', $v) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Urlaubsantrag wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">beach_access</span>' :colspan="$isAdmin ? 7 : 6" :title="__('Keine Einträge gefunden')" compact />
            @endforelse
</x-table>
@if ($vacations->hasPages())
    @include('duties._pagination', ['paginator' => $vacations])
@endif
