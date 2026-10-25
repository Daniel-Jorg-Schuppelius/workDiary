{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retentions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einbehalte an der Eingangsrechnung (MVP-953). Erwartet: $incoming --}}
@php
    $num = static fn ($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true);
    $canManage = auth()->user()?->canManageBilling() ?? false;
    // Ausgangsbelege tragen keinen Einbehalt, im Zahllauf ist die Zahlung festgelegt.
    $canAdd = $canManage && $incoming->paid_in_run_id === null && $incoming->direction !== \App\Enums\Billing\DocumentDirection::Outgoing;
@endphp
@if ($incoming->retentions->isNotEmpty() || $incoming->direction !== \App\Enums\Billing\DocumentDirection::Outgoing)
<x-card :title="__('sepa.retention.title')" icon="savings" :count="$incoming->retentions->isNotEmpty() ? $incoming->retentions->count() : null" padding="p-0">
    @if ($canAdd)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="outline" size="sm" data-entry-modal-trigger
                        :href="route('finance.incoming-invoices.retentions.form', $incoming)"
                        show-label>{{ __('sepa.retention.add') }}</x-icon-btn>
        </x-slot:actions>
    @endif
    @if ($incoming->retentions->isNotEmpty())
        <ul class="divide-y divide-base-300">
            @foreach ($incoming->retentions as $retention)
                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <div class="font-medium">
                            {{ $retention->kind->label() }}
                            @if ($retention->percent !== null)<span class="text-xs font-normal text-muted">({{ $num($retention->percent) }} %)</span>@endif
                        </div>
                        @php
                            $meta = array_filter([
                                $retention->due_on !== null ? __('sepa.retention.field.due_on') . ' ' . $retention->due_on->fdate() : null,
                                $retention->released_on !== null ? __('sepa.retention.field.released_on') . ' ' . $retention->released_on->fdate() : null,
                                $retention->paid_in_run_id !== null ? __('sepa.retention.in_run') : null,
                            ]);
                        @endphp
                        @if ($meta !== [])
                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-muted">
                                <span class="tabular-nums">{{ implode(' · ', $meta) }}</span>
                                @if ($retention->isOverdue())<x-status-badge tone="warning" size="xs">{{ __('sepa.retention.due') }}</x-status-badge>@endif
                            </div>
                        @endif
                        @if ($retention->note)
                            <div class="truncate text-xs text-muted">{{ $retention->note }}</div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="whitespace-nowrap font-medium tabular-nums">{{ $num($retention->amount) }} {{ $retention->currency }}</span>
                        <x-status-badge :tone="$retention->status->tone()">{{ $retention->status->label() }}</x-status-badge>
                        @if ($canManage && $retention->status === \App\Enums\Invoicing\RetentionStatus::Open)
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('finance.incoming-invoices.retentions.release', $retention) }}" data-confirm-dialog data-confirm-message="{{ __('sepa.retention.confirm_release') }}">
                                    @csrf
                                    <x-icon-btn icon="lock_open" size="xs" type="submit" :title="__('sepa.retention.release')" />
                                </form>
                                @if ($incoming->paid_in_run_id === null)
                                    <form method="POST" action="{{ route('finance.incoming-invoices.retentions.destroy', $retention) }}" data-confirm-dialog data-confirm-tone="error" data-confirm-message="{{ __('sepa.retention.confirm_remove') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="xs" type="submit" :title="__('Entfernen')" />
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="savings" :title="__('sepa.retention.empty')" :message="__('sepa.retention.hint')" compact class="m-4" />
    @endif
</x-card>
@endif
