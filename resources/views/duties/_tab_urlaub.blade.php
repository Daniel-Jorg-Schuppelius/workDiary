{{-- Urlaub: Tabelle --}}
<?php $p = array_merge($filters ?? [], ['tab' => 'urlaub']); ?>
<x-table scroll="flex" :pinRows="true" :zebra="true" size="sm">
        <thead class="bg-base-200">
            <tr>
                @if ($isAdmin)
                    <th><x-sort-th column="mitarbeiter" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Mitarbeiter') }}</x-sort-th></th>
                @endif
                <th><x-sort-th column="von" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'" default="von">{{ __('Zeitraum') }}</x-sort-th></th>
                <th>{{ __('Tage') }}</th>
                <th><x-sort-th column="typ" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Typ') }}</x-sort-th></th>
                <th><x-sort-th column="status" :route="route('duties.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Status') }}</x-sort-th></th>
                <th class="max-w-xs">{{ __('Notiz') }}</th>
                <th class="w-px"></th>
            </tr>
        </thead>
        <tbody>
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
                                <svg class="inline h-3 w-3 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        @endif
                    </td>
                    <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $v->note }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            {{-- Admin: Genehmigen / Ablehnen --}}
                            @can('decide', $v)
                                @if ($v->status === \App\Models\Vacation::STATUS_PENDING)
                                    <form method="POST" action="{{ route('vacations.approve', $v) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            title="{{ __('Genehmigen') }}"
                                            class="btn btn-xs btn-ghost text-success">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </form>
                                    <a href="{{ route('vacations.reject-form', $v) }}?dialog=1"
                                       title="{{ __('Ablehnen') }}"
                                       class="btn btn-xs btn-ghost text-error"
                                       data-entry-modal-trigger>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </a>
                                @endif
                            @endcan

                            {{-- Bearbeiten --}}
                            @can('update', $v)
                                <a href="{{ route('vacations.edit', $v) }}?dialog=1"
                                   title="{{ __('Bearbeiten') }}"
                                   class="btn btn-xs btn-ghost"
                                   data-entry-modal-trigger>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414z"/></svg>
                                </a>
                            @endcan

                            {{-- Stornieren --}}
                            @can('cancel', $v)
                                <form method="POST" action="{{ route('vacations.cancel', $v) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Urlaubsantrag wirklich stornieren?') }}"
                                      data-confirm-label="{{ __('Stornieren') }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        title="{{ __('Stornieren') }}"
                                        class="btn btn-xs btn-ghost text-warning">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    </button>
                                </form>
                            @endcan

                            {{-- Löschen --}}
                            @can('delete', $v)
                                <form method="POST" action="{{ route('vacations.destroy', $v) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Urlaubsantrag wirklich löschen?') }}"
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
                    <td colspan="{{ $isAdmin ? 7 : 6 }}" class="p-0">
                        <x-empty-state :compact="true" :title="__('Keine Einträge gefunden')" />
                    </td>
                </tr>
            @endforelse
        </tbody>
</x-table>
@if ($vacations->hasPages())
    @include('duties._pagination', ['paginator' => $vacations])
@endif
