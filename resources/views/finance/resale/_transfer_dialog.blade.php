{{--
  Created on   : Tue Sep 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transfer_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzabtretung (Feature 152): einen Teil der Lizenzen eines Vertrags an
  einen anderen Halter abtreten — zwei Firmen im selben Haus, ein Vertrag
  beim Anbieter. Es entsteht ein Kind-Abo mit eigenem Halter, eigener Menge
  und eigenen Perioden; der Vertrag plant mit dem Rest. Verfügbarkeit und
  Vorbelegung ($available, $quantityDefault, $prefill) rechnet der Controller.
--}}
@php
    $mode = (string) old('mode', 'customer');
@endphp
<x-modal
    :title="__('resale.transfer.title', ['subscription' => $subscription->label])"
    icon="call_split"
    tone="primary"
    size="md"
    :action="route('finance.resale.transfer.store', $subscription->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.transfer.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.transfer.hint', ['holder' => $subscription->holderLabel(), 'quantity' => $subscription->quantity, 'available' => $available]) }}</div>
    <p class="text-xs text-muted">{{ __('resale.transfer.timeline_hint') }}</p>
    {{-- Freie Lizenzen je Zeitabschnitt (Pilot 2026-10-31): mehr als frei lässt sich nicht abtreten; ohne freie Lizenz im Zeitraum bleibt die Menge gesperrt. --}}
    @if ($available <= 0)
        <div role="alert" class="alert alert-warning text-sm"><span>{{ __('resale.transfer.none_available', ['quantity' => $subscription->quantity, 'from' => $windowFrom->fdate(), 'to' => $windowTo?->fdate() ?? __('resale.transfer.segments.open_end')]) }}</span></div>
    @endif
    {{-- Schon über Rechnungen an den Halter berechnete Lizenzen bleiben bei ihm (Pilot 2026-10-31). --}}
    @if ($billedPeriods !== [])
        <ul class="text-xs text-muted space-y-0.5">
            @foreach ($billedPeriods as $entry)
                <li>{{ __('resale.transfer.billed', ['period' => $entry['period']->label(), 'billed' => $entry['billed'], 'quantity' => $entry['period']->quantity, 'holder' => $subscription->holderLabel()]) }}</li>
            @endforeach
        </ul>
    @endif
    @if ($subscription->assignments->isNotEmpty())
        <div>
            <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">{{ __('resale.transfer.segments.title') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.transfer.segments.period') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.transfer.segments.assigned') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.transfer.segments.free') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($segments as $segment)
                    <tr>
                        <td class="text-sm whitespace-nowrap">{{ $segment['from']->fdate() }} – {{ $segment['to']?->fdate() ?? __('resale.transfer.segments.open_end') }}</td>
                        <td class="text-right tabular-nums">{{ $segment['assigned'] }}</td>
                        <td class="text-right tabular-nums {{ $segment['free'] > 0 ? 'text-success' : 'text-muted' }}">{{ $segment['free'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif

    @include('finance.resale._holder_picker', ['customerDefault' => $prefill['customer_id']])

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <x-input-field name="quantity" type="number" min="1" :max="$available" :disabled="$available <= 0" :label="__('resale.transfer.quantity')" :value="old('quantity', (string) $quantityDefault)" required />
        <x-input-field name="sale_unit_price" type="number" step="0.01" min="0" :label="__('resale.field.sale_unit_price')" :value="old('sale_unit_price', $subscription->sale_unit_price?->withScale(2)->getAmount())" :hint="__('resale.dialog.price_hint')" />
    </div>
    <x-date-range layout="split" from-name="starts_on" to-name="ends_on" form-control size=""
                  :from-label="__('resale.field.starts_on')" :to-label="__('resale.field.ends_on')"
                  :from-required="true"
                  :from="old('starts_on', $prefill['starts_on'])"
                  :to="old('ends_on', $prefill['ends_on'])" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note')" />
</x-modal>
