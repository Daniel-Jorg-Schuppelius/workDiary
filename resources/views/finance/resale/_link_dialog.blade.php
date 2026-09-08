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
        {{ __('resale.link.needed', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($needed, (float) $period->termMonths())]) }}
    </div>
    @if (! $hasContacts)
        <div class="alert alert-warning text-sm"><span>{{ __('resale.link.no_contacts') }}</span></div>
    @elseif ($rows === [])
        <div class="alert alert-info text-sm"><span>{{ __('resale.link.no_lines') }}</span></div>
    @endif
    {{-- Nur Abo-Positionen: Support-Stunden und Hardware gehören nicht in diese Liste (Einstufung über „Produkte"). --}}
    <x-select-field name="line_id" :label="__('resale.link.line')" required :hint="__('resale.link.line_hint')">
        <option value="">—</option>
        @foreach ($rows as $row)
            @php
                $line = $row['line'];
                $lineSqid = \App\Support\Sqid::encode(\App\Models\LexofficeVoucherLine::class, $line->id);
                $used = in_array($line->id, $linkedIds, true) || $row['free'] <= 0.001;
            @endphp
            <option value="{{ $lineSqid }}" @selected((string) old('line_id') === $lineSqid) @disabled($used)>
                {{ $line->voucher->voucher_number }} · {{ $line->voucher->voucher_date?->format('d.m.Y') }}@if ($line->voucher->servicePeriodLabel() !== null) · {{ __('resale.reconcile.service_period') }} {{ $line->voucher->servicePeriodLabel() }}@endif · Pos. {{ $line->position }} · {{ \Illuminate\Support\Str::limit($line->article?->name ?? $line->name, 40) }} · {{ \App\Services\Reselling\Register\LicenseMonths::label($row['licences'] * $row['per_licence'], $row['per_licence']) }} × {{ $line->unit_net->withScale(2)->format() }}
                @if ($used) · {{ __('resale.link.line_used') }} @elseif ($row['free'] < $row['licences'] * $row['per_licence'] - 0.001) · {{ __('resale.invoices.remaining', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($row['free'], $row['per_licence'])]) }} @endif
            </option>
        @endforeach
    </x-select-field>
    <x-input-field name="months" type="number" step="0.01" min="0.01" :label="__('resale.link.months_field')" :value="old('months', number_format($needed, 2, '.', ''))" required :hint="__('resale.link.months_hint')" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note')" />
</x-modal>
