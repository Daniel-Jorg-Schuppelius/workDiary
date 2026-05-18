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
                        <span class="badge badge-sm badge-ghost ml-1">§ 3 EntgFG</span>
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
                        <span>{{ __('Beginn Krankheitskette') }}: {{ $sicknessStatus->chainStart->format('d.m.Y') }}</span>
                    @endif
                    @if ($sicknessStatus->exhaustionDate)
                        <span class="text-{{ $tone }}">
                            @if ($sicknessStatus->exhausted)
                                {{ __('Anspruch ausgeschöpft seit :date', ['date' => $sicknessStatus->exhaustionDate->format('d.m.Y')]) }}
                            @else
                                {{ __('Voraussichtliches Ende: :date', ['date' => $sicknessStatus->exhaustionDate->format('d.m.Y')]) }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </x-card>
@endif

<x-table scroll="flex" :pinRows="true" :zebra="true" size="sm">
    <thead class="bg-base-200">
        <tr>
            @if ($isAdmin)
                <th><x-sort-th column="mitarbeiter" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Mitarbeiter') }}</x-sort-th></th>
            @endif
            <th><x-sort-th column="von" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'" default="von">{{ __('Zeitraum') }}</x-sort-th></th>
            <th>{{ __('Tage') }}</th>
            <th><x-sort-th column="art" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Art') }}</x-sort-th></th>
            <th>{{ __('AU') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="max-w-xs">{{ __('Notiz') }}</th>
            <th class="w-px"></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($sickLeaves as $s)
            @php $days = $s->workingDays($holidayService); @endphp
            <tr class="hover">
                @if ($isAdmin)
                    <td class="font-medium">{{ $s->user?->name ?? '—' }}</td>
                @endif
                <td class="whitespace-nowrap">
                    {{ $s->start_date->format('d.m.Y') }}
                    @if ($s->start_date->ne($s->end_date))
                        – {{ $s->end_date->format('d.m.Y') }}
                    @endif
                </td>
                <td class="tabular-nums">{{ $days }}</td>
                <td>
                    <span class="badge badge-sm @if ($s->kind === \App\Models\SickLeave::KIND_FOLLOW_UP) badge-info @else badge-ghost @endif">
                        {{ $s->kindLabel() }}
                    </span>
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
                        <span class="badge badge-sm badge-error">{{ __('Storniert') }}</span>
                    @else
                        <span class="badge badge-sm badge-success">{{ __('Gemeldet') }}</span>
                    @endif
                    @if ($s->kasse_notified_at)
                        <span class="tooltip tooltip-right ml-1" data-tip="{{ __('Krankenkasse informiert am :date', ['date' => $s->kasse_notified_at->format('d.m.Y')]) }}">
                            <x-icon name="check_circle" class="h-3 w-3 text-success" />
                        </span>
                    @endif
                </td>
                <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $s->note }}</td>
                <td>
                    <div class="flex items-center gap-1">
                        @can('update', $s)
                            <a href="{{ route('sick-leaves.edit', $s) }}?dialog=1"
                               title="{{ __('Bearbeiten') }}"
                               class="btn btn-xs btn-ghost"
                               data-entry-modal-trigger>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414z"/></svg>
                            </a>
                        @endcan

                        @can('cancel', $s)
                            <form method="POST" action="{{ route('sick-leaves.cancel', $s) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Krankmeldung wirklich stornieren?') }}"
                                  data-confirm-label="{{ __('Stornieren') }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                    title="{{ __('Stornieren') }}"
                                    class="btn btn-xs btn-ghost text-warning">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </button>
                            </form>
                        @endcan

                        @can('delete', $s)
                            <form method="POST" action="{{ route('sick-leaves.destroy', $s) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Krankmeldung wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    title="{{ __('Löschen') }}"
                                    class="btn btn-xs btn-ghost text-error">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3m-7 0h8"/></svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isAdmin ? 8 : 7 }}" class="p-0">
                    <x-empty-state :compact="true" :title="__('Keine Krankmeldungen im Zeitraum')" />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-table>
@if ($sickLeaves->hasPages())
    @include('duties._pagination', ['paginator' => $sickLeaves])
@endif
