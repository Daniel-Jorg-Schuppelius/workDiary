{{-- Krank: Tabelle --}}
<?php $p = array_merge($filters ?? [], ['tab' => 'krank']); ?>
@if (! empty($sicknessStatus) && ! empty($sicknessStatusUser))
    @php
        /** @var \App\Support\Sickness\ContinuedPaymentStatus $sicknessStatus */
        $entWeeks = (int) config('sickness.continued_pay_weeks', 6);
        $tone = $sicknessStatus->exhausted ? 'error' : ($sicknessStatus->usedPercent() >= 75 ? 'warning' : 'success');
    @endphp
    <x-card class="mb-3">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-64">
                <div class="flex items-baseline justify-between gap-2">
                    <h3 class="text-sm font-semibold text-base-content/80">
                        {{ __('Lohnfortzahlung :name', ['name' => $sicknessStatusUser->name]) }}
                        <x-status-badge tone="ghost" size="sm" class="ml-1">§ 3 EntgFG</x-status-badge>
                    </h3>
                    <span class="text-xs text-base-content/60">
                        {{ __(':weeks Wochen Anspruch', ['weeks' => $entWeeks]) }}
                    </span>
                </div>
                <progress class="progress progress-{{ $tone }} w-full mt-2"
                          value="{{ $sicknessStatus->usedDays }}"
                          max="{{ $sicknessStatus->entitlementDays }}"></progress>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-base-content/70 mt-2">
                    <span>
                        <strong class="text-{{ $tone }}">{{ $sicknessStatus->usedDays }}</strong>
                        / {{ $sicknessStatus->entitlementDays }} {{ __('Tage genutzt') }}
                        ({{ $sicknessStatus->usedPercent() }} %)
                    </span>
                    <span>
                        {{ __('Verbleibend') }}:
                        <strong>{{ max(0, $sicknessStatus->remainingDays) }}</strong> {{ __('Tage') }}
                    </span>
                    @if ($sicknessStatus->chainStart)
                        <span>{{ __('Beginn Krankheitskette') }}: {{ $sicknessStatus->chainStart->fdate() }}</span>
                    @endif
                    @if ($sicknessStatus->exhaustionDate)
                        <span class="text-{{ $tone }}">
                            @if ($sicknessStatus->exhausted)
                                {{ __('Anspruch ausgeschöpft seit :date', ['date' => $sicknessStatus->exhaustionDate->fdate()]) }}
                            @else
                                {{ __('Voraussichtliches Ende: :date', ['date' => $sicknessStatus->exhaustionDate->fdate()]) }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </x-card>
@endif

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
            <x-table.th sort="art">{{ __('Art') }}</x-table.th>
            <th>{{ __('AU') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="max-w-xs">{{ __('Notiz') }}</th>
            <th class="w-px"></th>
        </tr>
    </x-slot:head>
        @forelse ($sickLeaves as $s)
            @php $days = $s->workingDays($holidayService); @endphp
            <tr class="hover">
                @if ($isAdmin)
                    <td class="font-medium">{{ $s->user?->name ?? '—' }}</td>
                @endif
                <td class="whitespace-nowrap">
                    {{ $s->start_date->fdate() }}
                    @if ($s->start_date->ne($s->end_date))
                        – {{ $s->end_date->fdate() }}
                    @endif
                </td>
                <td class="tabular-nums">{{ $days }}</td>
                <td>
                    <x-status-badge size="sm" :tone="$s->kind === \App\Enums\Sickness\SickLeaveKind::FollowUp ? 'info' : 'ghost'">
                        {{ $s->kindLabel() }}
                    </x-status-badge>
                </td>
                <td>
                    @if ($s->attachments->isNotEmpty())
                        @foreach ($s->attachments as $att)
                            <a class="tooltip" data-tip="{{ $att->original_name }}"
                               href="{{ \App\Http\Controllers\SickLeaveController::attachmentDownloadUrl($s, $att) }}">
                                <x-icon name="description" class="h-4 w-4 text-info" />
                            </a>
                        @endforeach
                    @else
                        <span class="text-base-content/40">—</span>
                    @endif
                    @if ($s->au_number)
                        <span class="block text-xs text-base-content/60 mt-0.5">#{{ $s->au_number }}</span>
                    @endif
                </td>
                <td>
                    @if ($s->isCancelled())
                        <x-status-badge tone="error" size="sm">{{ __('Storniert') }}</x-status-badge>
                    @else
                        <x-status-badge tone="success" size="sm">{{ __('Gemeldet') }}</x-status-badge>
                    @endif
                    @if ($s->kasse_notified_at)
                        <span class="tooltip tooltip-right ml-1" data-tip="{{ __('Krankenkasse informiert am :date', ['date' => $s->kasse_notified_at->fdate()]) }}">
                            <x-icon name="check_circle" class="h-3 w-3 text-success" />
                        </span>
                    @endif
                </td>
                <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $s->note }}</td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $s)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('sick-leaves.edit', $s) . '?dialog=1'"
                                        :label="__('Bearbeiten')" />
                        @endcan

                        @can('cancel', $s)
                            <x-action-form :action="route('sick-leaves.cancel', $s)" method="PATCH"
                                  :confirm="__('Krankmeldung wirklich stornieren?')"
                                  :confirm-label="__('Stornieren')">
                                <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Stornieren')" />
                            </x-action-form>
                        @endcan

                        @can('delete', $s)
                            <x-action-form :action="route('sick-leaves.destroy', $s)" method="DELETE"
                                  :confirm="__('Krankmeldung wirklich löschen?')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">sick</span>' :colspan="$isAdmin ? 8 : 7" :title="__('Keine Krankmeldungen im Zeitraum')" compact />
        @endforelse
</x-table>
@if ($sickLeaves->hasPages())
    @include('duties._pagination', ['paginator' => $sickLeaves])
@endif
