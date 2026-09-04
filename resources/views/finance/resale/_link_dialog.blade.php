{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsposition von Hand einer Periode zuordnen (Feature 152, MVP-761):
  Positionen des Rechnungsempfängers im Fenster um den Periodenbeginn.
--}}
@php
    $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp
<x-modal
    :title="__('resale.link.link_title', ['period' => $period->label()])"
    icon="add_link"
    tone="primary"
    size="lg"
    :action="route('finance.resale.periods.link.store', $period->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.link.link_submit')"
>
    <div class="text-sm text-base-content/70">
        {{ $period->subscription->label }} · {{ $period->subscription->holderLabel() }} ·
        {{ __('resale.link.needed', ['months' => $fmt($needed)]) }}
    </div>
    @if (! $hasContacts)
        <div class="alert alert-warning text-sm"><span>{{ __('resale.link.no_contacts') }}</span></div>
    @elseif ($lines->isEmpty())
        <div class="alert alert-info text-sm"><span>{{ __('resale.link.no_lines') }}</span></div>
    @endif
    <x-select-field name="line_id" :label="__('resale.link.line')" required>
        <option value="">—</option>
        @foreach ($lines as $line)
            @php $lineSqid = \App\Support\Sqid::encode(\App\Models\LexofficeVoucherLine::class, $line->id); @endphp
            <option value="{{ $lineSqid }}" @selected((string) old('line_id') === $lineSqid) @disabled(in_array($line->id, $linkedIds, true))>
                {{ $line->voucher->voucher_number }} · {{ $line->voucher->voucher_date?->format('d.m.Y') }} · {{ \Illuminate\Support\Str::limit($line->name, 40) }} · {{ $fmt((float) $line->quantity) }}{{ $line->unit_name ? ' ' . $line->unit_name : ' ×' }} · {{ $line->unit_net->withScale(2)->format() }}
            </option>
        @endforeach
    </x-select-field>
    <x-input-field name="months" type="number" step="0.01" min="0.01" :label="__('resale.link.months_field')" :value="old('months', number_format($needed, 2, '.', ''))" required :hint="__('resale.link.months_hint')" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note')" />
</x-modal>
