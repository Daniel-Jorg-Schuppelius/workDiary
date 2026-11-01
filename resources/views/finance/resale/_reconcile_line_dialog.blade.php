{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reconcile_line_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsposition ohne passende Periode einer offenen Periode zuordnen
  (Phase 168, MVP-1462). Erwartet $customer, $line (MirrorLine), $free
  (freie Lizenzmonate der Position), $rows (period, fit, open), passende zuerst.
--}}
@php
    $licenceMonths = \App\Services\Reselling\Register\LicenseMonths::class;
    $first = $rows[0] ?? null;
@endphp
<x-modal
    :title="__('resale.board.line_dialog.title', ['voucher' => (string) $line->voucherNumber])"
    icon="add_link"
    tone="primary"
    size="md"
    :action="route('finance.resale.reconcile.assign', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.link.link_submit')"
>
    <input type="hidden" name="line_id" value="{{ $line->key }}">
    <div class="text-sm text-base-content/70">
        {{ $line->label() }} · {{ $line->servicePeriodLabel() ?? $line->voucherDate?->fdate() }} ·
        {{ __('resale.invoices.remaining', ['amount' => $licenceMonths::label($free, \App\Services\Reselling\Register\LicenseMonths::split($line)['months'])]) }}
    </div>
    @if ($rows === [])
        <div role="status" class="alert alert-info text-sm"><span>{{ __('resale.board.line_dialog.none') }}</span></div>
    @else
        <x-select-field name="period_id" :label="__('resale.field.period')" required>
            @foreach ($rows as $row)
                <option value="{{ $row['period']->sqid }}" @selected(old('period_id') === $row['period']->sqid)>
                    {{ $row['period']->subscription->label }} · {{ $row['period']->label() }} · {{ __('resale.link.needed', ['amount' => $licenceMonths::label($row['open'], (float) $row['period']->termMonths())]) }}@if ($row['fit']->needsConfirmation()) · ⚠ {{ $row['fit']->label() }}@endif
                </option>
            @endforeach
        </x-select-field>
        <x-input-field name="months" type="number" step="0.01" min="0.01" :label="__('resale.link.months_field')" :value="old('months', number_format(min($free, $first['open'] ?? $free), 2, '.', ''))" required :hint="__('resale.link.months_hint')" />
        <x-checkbox-field name="confirm_fit" :toggle="false" tone="warning" :checked="(bool) old('confirm_fit')" :label="__('resale.fit.confirm_field')" :hint="__('resale.fit.confirm_hint')" />
    @endif
</x-modal>
