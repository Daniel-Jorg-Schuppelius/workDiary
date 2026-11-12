{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retentions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sicherheitseinbehalte der Ausgangsrechnung mit Freigabe (MVP-1560). Erwartet: $invoice --}}
@php
    $num = static fn ($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true);
    // Am Entwurf ist der Einbehalt noch Beleginhalt; freigegeben wird erst der ausgestellte.
    $canRelease = (auth()->user()?->canManageBilling() ?? false) && $invoice->status !== \App\Enums\Invoicing\InvoiceStatus::Draft;
@endphp
@if ($invoice->retentions->isNotEmpty())
<x-card :title="__('invoicing.retention.heading')" icon="savings" :count="$invoice->retentions->count()" padding="p-0">
    <ul class="divide-y divide-base-300">
        @foreach ($invoice->retentions as $retention)
            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 text-sm">
                <div class="min-w-0">
                    <div class="font-medium">
                        {{ $retention->kind->label() }}
                        @if ($retention->percent !== null)<span class="text-xs font-normal text-muted">({{ $num($retention->percent->getNumericValue()) }} %)</span>@endif
                    </div>
                    @php
                        $meta = array_filter([
                            $retention->due_on !== null ? __('invoicing.retention.due_on') . ' ' . $retention->due_on->fdate() : null,
                            $retention->released_on !== null ? __('invoicing.retention.released_on') . ' ' . $retention->released_on->fdate() : null,
                        ]);
                    @endphp
                    @if ($meta !== [])
                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-muted">
                            <span class="tabular-nums">{{ implode(' · ', $meta) }}</span>
                            @if ($retention->isOverdue())<x-status-badge tone="warning" size="xs">{{ __('invoicing.retention.release_due') }}</x-status-badge>@endif
                        </div>
                    @endif
                    @if ($retention->note)
                        <div class="truncate text-xs text-muted">{{ $retention->note }}</div>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <span class="whitespace-nowrap font-medium tabular-nums">{{ $num($retention->amount->toFloat()) }} {{ $invoice->currency->value }}</span>
                    <x-status-badge :tone="$retention->status->tone()">{{ $retention->status->label() }}</x-status-badge>
                    @if ($canRelease && $retention->status->canTransitionTo(\App\Enums\Invoicing\RetentionStatus::Released))
                        <x-icon-btn icon="lock_open" size="xs" tone="ghost"
                                    data-entry-modal-trigger
                                    :href="route('invoices.retentions.release.form', [$invoice, $retention])"
                                    :label="__('invoicing.retention.release')" />
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</x-card>
@endif
