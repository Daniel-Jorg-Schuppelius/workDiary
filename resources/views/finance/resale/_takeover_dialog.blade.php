{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _takeover_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übernahme durch eine andere Firma ab Stichtag (Phase 166, MVP-1451): neuer
  Halter, Stichtag, offene Perioden davor als Forderungsausfall verzichten.
  Erwartet $subscription, $from, $whole, $openPeriods, $customerDefault und
  die Halterwahl ($customers, $foreignByCustomer).
--}}
@php
    $mode = (string) old('mode', 'customer');
@endphp
<x-modal
    :title="__('resale.takeover.title', ['subscription' => $subscription->label])"
    icon="handshake"
    tone="primary"
    size="md"
    :action="route('finance.resale.takeover.store', $subscription->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.takeover.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.takeover.hint', ['holder' => $subscription->holderLabel(), 'quantity' => $subscription->quantity]) }}</div>
    @if ($subscription->isAssignment())
        <p class="text-xs text-muted">{{ __('resale.takeover.assignment_hint', ['contract' => $subscription->parent?->holderLabel() ?? '—']) }}</p>
    @endif

    @include('finance.resale._holder_picker', ['customerDefault' => $customerDefault])

    <x-input-field name="starts_on" type="date" :label="__('resale.takeover.starts_on')" :value="old('starts_on', $from->toDateString())" required :hint="__('resale.takeover.starts_on_hint')" />
    @if ($whole)
        <div role="status" class="alert alert-info text-sm"><span>{{ __('resale.takeover.whole') }}</span></div>
    @endif

    <div>
        <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">{{ __('resale.takeover.open_before') }}</div>
        @if ($openPeriods->isEmpty())
            <p class="text-sm text-muted">{{ __('resale.takeover.none_open') }}</p>
        @else
            <ul class="text-sm max-h-40 overflow-y-auto space-y-0.5 border border-base-300 rounded-box px-3 py-2">
                @foreach ($openPeriods as $period)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span class="tabular-nums">{{ $period->label() }}</span>
                        <span class="tabular-nums text-muted">{{ $period->expected_sale?->format() ?? '—' }} · {{ $period->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <x-checkbox-field name="waive_before" :toggle="false" tone="warning" :checked="(bool) old('waive_before', true)" :label="__('resale.takeover.waive_before')" :hint="__('resale.takeover.waive_before_hint')" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note')" />
</x-modal>
