{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _mark_billed_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Altbestand-Abschluss (Programmeinführung): offene Zeiten bis zu einem
  Stichtag als abgerechnet (exported) markieren — sie wurden vor der
  Einführung bereits außerhalb des Systems fakturiert.
--}}
<x-modal
    :title="__('finance.open_times.mark_billed.title')"
    :eyebrow="__('finance.open_times.title')"
    icon="price_check"
    tone="warning"
    :action="route('finance.open-times.mark-billed')"
    method="POST"
    :submit-label="__('finance.open_times.mark_billed.submit')"
    submit-class="btn-warning"
>
    <p class="text-sm text-base-content/70">{{ __('finance.open_times.mark_billed.hint') }}</p>
    <div class="rounded-box border border-warning/40 bg-warning/5 px-3 py-2 text-sm">
        {{ __('finance.open_times.mark_billed.warning') }}
    </div>

    <x-input-field name="cutoff" type="date" required
                   :label="__('finance.open_times.mark_billed.cutoff')"
                   :value="old('cutoff', '')" />

    <div>
        <label class="label" for="mark-billed-customer">
            <span class="label-text">{{ __('finance.open_times.mark_billed.customer') }}</span>
        </label>
        <select id="mark-billed-customer" name="customer" class="select select-bordered w-full">
            <option value="">{{ __('finance.open_times.mark_billed.customer_all') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected(old('customer') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('customer')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <label class="label cursor-pointer justify-start gap-2 py-1">
        <input type="checkbox" name="include_non_billable" value="1"
               class="checkbox checkbox-sm checkbox-warning" @checked(old('include_non_billable'))>
        <span class="label-text">{{ __('finance.open_times.mark_billed.include_non_billable') }}</span>
    </label>
</x-modal>
