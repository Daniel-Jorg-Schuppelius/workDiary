{{-- Dialog for adding a MaterialUsage row to a Timesheet --}}
<x-modal
    :title="__('Material erfassen')"
    :eyebrow="__('Verbrauchsmaterial')"
    icon="category"
    tone="primary"
    :action="route('projects.timesheets.materials.store', [$project, $timesheet])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Hinzufügen')"
>
    <x-form-group :legend="__('Material')" icon="category" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Aus Stamm (optional)') }}</label>
            <select name="material_id" class="select select-bordered w-full">
                <option value="">— {{ __('frei') }} —</option>
                @foreach ($materials as $m)
                    <option value="{{ $m->id }}"
                            data-unit="{{ $m->unit }}"
                            data-price="{{ $m->default_unit_price }}"
                            data-tax="{{ $m->tax_rate }}"
                            data-name="{{ $m->name }}">
                        {{ $m->name }} ({{ $m->unit }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Bezeichnung') }} *</label>
            <input type="text" name="description" maxlength="255" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Menge') }} *</label>
            <input type="number" step="0.001" min="0.001" name="quantity" value="1" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Einheit') }} *</label>
            <input type="text" name="unit" value="Stk." maxlength="20" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('EP netto') }}</label>
            <input type="number" step="0.0001" min="0" name="unit_price" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('USt %') }}</label>
            <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="input input-bordered w-full">
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
