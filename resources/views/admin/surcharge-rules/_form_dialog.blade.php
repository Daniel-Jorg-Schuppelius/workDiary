{{-- Dialog: Zuschlagsregel anlegen/bearbeiten (Feature 005) --}}
@php
    /** @var \App\Models\Surcharge\SurchargeRule $rule */
    $isEdit = $rule->exists;
    $kindValue = old('kind', $rule->kind?->value ?? \App\Enums\Surcharge\SurchargeKind::Night->value);
@endphp
<x-modal
    :title="$isEdit ? __('surcharge.title.edit_rule') : __('surcharge.title.create_rule')"
    :eyebrow="$isEdit ? $rule->code : null"
    icon="percent"
    tone="primary"
    :action="$isEdit ? route('admin.surcharge-rules.update', $rule) : route('admin.surcharge-rules.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('surcharge.action.save') : __('surcharge.action.create')"
>
    <x-form-group :legend="__('surcharge.field.basics')" icon="badge" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-code">{{ __('surcharge.field.code') }}</label>
            <input id="surcharge-code" type="text" name="code" required maxlength="20"
                   pattern="[a-z0-9][a-z0-9._-]*"
                   value="{{ old('code', $rule->code) }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="night">
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.code_help') }}</p>
            @error('code')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-input-field name="label" :label="__('surcharge.field.label')" required maxlength="100"
                       :value="old('label', $rule->label)"
                       :placeholder="__('surcharge.field.label_placeholder')" />

        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-kind">{{ __('surcharge.field.kind') }}</label>
            <select id="surcharge-kind" name="kind" class="select select-bordered w-full" required>
                @foreach (\App\Enums\Surcharge\SurchargeKind::options() as $value => $label)
                    <option value="{{ $value }}" @selected($kindValue === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.kind_help') }}</p>
            @error('kind')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <x-input-field type="number" name="percentage" :label="__('surcharge.field.percentage')" required
                       min="0" max="999.99" step="0.01"
                       :value="old('percentage', $rule->percentage)"
                       class="tabular-nums" />
    </x-form-group>

    <x-form-group :legend="__('surcharge.field.window')" icon="schedule" tone="info" cols="2"
                  :description="__('surcharge.field.window_help')">
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      type="time"
                      :linked="false"
                      from-name="window_start"
                      to-name="window_end"
                      :from="old('window_start', $rule->window_start ? substr($rule->window_start, 0, 5) : null)"
                      :to="old('window_end', $rule->window_end ? substr($rule->window_end, 0, 5) : null)"
                      :from-label="__('surcharge.field.window_start')"
                      :to-label="__('surcharge.field.window_end')"
                      :from-error="$errors->first('window_start')"
                      :to-error="$errors->first('window_end')"
                      size="md" />
    </x-form-group>

    <x-form-group :legend="__('surcharge.field.payroll')" icon="payments" tone="warning" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-wage-type">{{ __('surcharge.field.wage_type_code') }}</label>
            <input id="surcharge-wage-type" type="text" name="wage_type_code" maxlength="20"
                   value="{{ old('wage_type_code', $rule->wage_type_code) }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="2010">
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.wage_type_code_help') }}</p>
            @error('wage_type_code')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-priority">{{ __('surcharge.field.priority') }}</label>
            <input id="surcharge-priority" type="number" name="priority" required min="0" max="1000"
                   value="{{ old('priority', $rule->priority ?? 0) }}"
                   class="input input-bordered w-full tabular-nums">
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.priority_help') }}</p>
            @error('priority')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-tax-free-limit">{{ __('surcharge.field.tax_free_limit_pct') }}</label>
            <input id="surcharge-tax-free-limit" type="number" name="tax_free_limit_pct" min="0" max="999.99" step="0.01"
                   value="{{ old('tax_free_limit_pct', $rule->tax_free_limit_pct) }}"
                   class="input input-bordered w-full tabular-nums"
                   placeholder="25">
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.tax_free_limit_pct_help') }}</p>
            @error('tax_free_limit_pct')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="surcharge-taxable-wage-type">{{ __('surcharge.field.taxable_wage_type_code') }}</label>
            <input id="surcharge-taxable-wage-type" type="text" name="taxable_wage_type_code" maxlength="20"
                   value="{{ old('taxable_wage_type_code', $rule->taxable_wage_type_code) }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="2011">
            <p class="text-xs text-base-content/60">{{ __('surcharge.field.taxable_wage_type_code_help') }}</p>
            @error('taxable_wage_type_code')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('surcharge.field.validity')" icon="event_available" tone="success" cols="2">
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      from-name="valid_from"
                      to-name="valid_until"
                      :from="old('valid_from', $rule->valid_from?->toDateString())"
                      :to="old('valid_until', $rule->valid_until?->toDateString())"
                      :from-label="__('surcharge.field.valid_from')"
                      :to-label="__('surcharge.field.valid_until')"
                      :from-error="$errors->first('valid_from')"
                      :to-error="$errors->first('valid_until')"
                      size="md" />

        <div class="fieldset sm:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" class="toggle toggle-primary"
                       @checked(old('active', $rule->active ?? true))>
                <span class="label-text">{{ __('surcharge.field.rule_active') }}</span>
            </label>
            @error('active')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.surcharge-rules.destroy', $rule)"
                  method="DELETE"
                  :confirm="__('surcharge.action.delete_confirm')"
                  :confirm-label="__('surcharge.action.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('surcharge.action.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
