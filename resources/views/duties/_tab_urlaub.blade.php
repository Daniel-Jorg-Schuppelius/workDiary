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
                        {{ $v->start_date->fdate() }}
                        @if ($v->start_date->ne($v->end_date))
                            – {{ $v->end_date->fdate() }}
                        @endif
                    </td>
                    <td class="tabular-nums">{{ $days }}</td>
                    <td>
                        <x-status-badge tone="ghost" size="sm">{{ $v->typeLabel() }}</x-status-badge>
                    </td>
                    <td>
                        <x-status-badge size="sm" :tone="$v->statusTone()">{{ $v->statusLabel() }}</x-status-badge>
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
                                    <x-action-form :action="route('vacations.approve', $v)" method="PATCH">
                                        <x-icon-btn icon="check" tone="success" type="submit" :label="__('Genehmigen')" />
                                    </x-action-form>
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
                                <x-action-form :action="route('vacations.cancel', $v)" method="PATCH"
                                      :confirm="__('Urlaubsantrag wirklich stornieren?')"
                                      :confirm-label="__('Stornieren')">
                                    <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Stornieren')" />
                                </x-action-form>
                            @endcan

                            @can('delete', $v)
                                <x-action-form :action="route('vacations.destroy', $v)" method="DELETE"
                                      :confirm="__('Urlaubsantrag wirklich löschen?')"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </x-action-form>
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
