@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared body for ExpenseCategory create/edit --}}
@php
    /** @var \App\Models\ExpenseCategory $category */
    $isEdit = $category?->exists ?? false;
@endphp

<x-form-group :legend="__('Identifikation')" icon="badge" tone="primary" cols="2">
    <x-filter-field show-label :label="__('Bezeichnung')" for="expcat-label">
        <input type="text" id="expcat-label" name="label" required maxlength="120"
               value="{{ old('label', $category->label) }}"
               class="input input-bordered input-sm w-full">
    </x-filter-field>

    <x-filter-field show-label :label="__('Slug')" for="expcat-slug">
        <input type="text" id="expcat-slug" name="slug" required maxlength="64"
               pattern="[a-z0-9_]+"
               value="{{ old('slug', $category->slug) }}"
               class="input input-bordered input-sm w-full font-mono"
               @if ($isEdit) readonly @endif>
    </x-filter-field>

    <x-filter-field show-label :label="__('Icon (Material Symbol)')" for="expcat-icon">
        <input type="text" id="expcat-icon" name="icon" maxlength="64"
               value="{{ old('icon', $category->icon ?: 'receipt_long') }}"
               class="input input-bordered input-sm w-full font-mono">
    </x-filter-field>

    <x-filter-field show-label :label="__('Farbe')" for="expcat-color">
        @php
            $colorOptions = [
                'primary'   => __('Primär (Hauptaktion)'),
                'secondary' => __('Sekundär (Ergänzend)'),
                'accent'    => __('Akzent (Hervorhebung)'),
                'info'      => __('Info (Hinweis, Blau)'),
                'success'   => __('Erfolg (Grün)'),
                'warning'   => __('Warnung (Gelb/Orange)'),
                'error'     => __('Fehler (Rot)'),
                'neutral'   => __('Neutral (Grau)'),
                'ghost'     => __('Dezent (Ghost)'),
            ];
            $currentColor = old('color', $category->color ?: 'primary');
        @endphp
        <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full border border-base-300" aria-hidden="true"
                  data-color-preview style="background-color: var(--color-{{ $currentColor }});"></span>
            <select id="expcat-color" name="color" class="select select-bordered select-sm w-full"
                    data-color-preview>
                @foreach ($colorOptions as $tone => $label)
                    <option value="{{ $tone }}" @selected($currentColor === $tone)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="mt-1 text-[0.7rem] text-base-content/60">
            {{ __('Bestimmt die Akzentfarbe für Icon, Badge und Hervorhebungen in Listen.') }}
        </p>
    </x-filter-field>

    <div class="md:col-span-2">
        <x-filter-field show-label :label="__('Beschreibung')" for="expcat-description">
            <input type="text" id="expcat-description" name="description" maxlength="500"
                   value="{{ old('description', $category->description) }}"
                   class="input input-bordered input-sm w-full">
        </x-filter-field>
    </div>

    <x-filter-field show-label :label="__('Sortierung')" for="expcat-sort">
        <input type="number" id="expcat-sort" name="sort" min="0" max="9999"
               value="{{ old('sort', $category->sort ?? 100) }}"
               class="input input-bordered input-sm w-full">
    </x-filter-field>

    @unless ($skipStatusControls)
    <x-filter-field show-label :label="__('Aktiv')" for="expcat-is-active">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="expcat-is-active" name="is_active" value="1"
                   class="toggle toggle-success"
                   @checked(old('is_active', $isEdit ? $category->is_active : true))>
            <span class="label-text">{{ __('Kategorie verfügbar') }}</span>
        </label>
    </x-filter-field>
    @endunless
</x-form-group>

<x-form-group :legend="__('Standardwerte')" icon="tune" tone="info" cols="2"
              :description="__('Vorbelegungen für neue Spesen in dieser Kategorie. Mitarbeitende können beim Erfassen davon abweichen.')">
    <x-filter-field show-label :label="__('Steuersatz (Default, %)')" for="expcat-tax-rate">
        <input type="number" id="expcat-tax-rate" name="default_tax_rate"
               min="0" max="99.99" step="0.01"
               value="{{ old('default_tax_rate', $category->default_tax_rate ?? 19) }}"
               class="input input-bordered input-sm w-full tabular-nums">
    </x-filter-field>

    <div class="flex flex-col gap-2 pt-6">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="default_billable" value="0">
            <input type="checkbox" name="default_billable" value="1" class="checkbox checkbox-sm checkbox-primary"
                   @checked(old('default_billable', $category->default_billable ?? false))>
            <span class="label-text">{{ __('Standardmäßig an Kunden berechnen') }}</span>
        </label>

        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="requires_receipt" value="0">
            <input type="checkbox" name="requires_receipt" value="1" class="checkbox checkbox-sm checkbox-warning"
                   @checked(old('requires_receipt', $category->requires_receipt ?? true))>
            <span class="label-text">{{ __('Beleg-Upload erforderlich') }}</span>
        </label>
    </div>
</x-form-group>
