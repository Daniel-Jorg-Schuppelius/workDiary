@php
    /** @var \App\Models\Classification $classification */
    /** @var \App\Models\Classification|null $sourceClassification */
    /** @var array<string, string> $domainLabels */
    $isEdit = (bool) ($classification->id ?? false);
    $selectedDomain = old('domain', $classification->domain?->value);
    $selectedCode = old('code', $sourceClassification?->code ?? $classification->code);
@endphp

<x-modal
    :title="$isEdit ? __('Klassifikation bearbeiten') : __('Klassifikation anlegen')"
    icon="category"
    tone="primary"
    size="xl"
    :action="$isEdit ? route('admin.classifications.update', $classification) : route('admin.classifications.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    @if ($sourceClassification)
        <div class="alert alert-info mb-4">
            <x-icon name="info" />
            <span>{{ __('Es wird ein organisationsspezifischer Override für den Plattform-Default :code angelegt.', ['code' => $sourceClassification->code]) }}</span>
        </div>
        <input type="hidden" name="source_classification_id" value="{{ $sourceClassification->id }}" />
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="label" for="classification-domain"><span class="label-text">{{ __('Domain') }}</span></label>
            <select id="classification-domain" name="domain" class="select select-bordered w-full" {{ $sourceClassification ? 'disabled' : '' }}>
                <option value="">{{ __('Bitte wählen') }}</option>
                @foreach ($domains as $domain)
                    <option value="{{ $domain->value }}" @selected($selectedDomain === $domain->value)>
                        {{ $domainLabels[$domain->value] ?? $domain->value }}
                    </option>
                @endforeach
            </select>
            @if ($sourceClassification)
                <input type="hidden" name="domain" value="{{ $sourceClassification->domain->value }}" />
            @endif
        </div>

        <div>
            <label class="label" for="classification-code"><span class="label-text">{{ __('Code') }}</span></label>
            <input id="classification-code"
                   type="text"
                   name="code"
                   value="{{ $selectedCode }}"
                   class="input input-bordered w-full font-mono"
                   maxlength="60"
                   {{ ($isEdit || $sourceClassification) ? 'readonly' : '' }} />
        </div>

        <div class="md:col-span-2">
            <label class="label" for="classification-label"><span class="label-text">{{ __('Bezeichnung') }}</span></label>
            <input id="classification-label" type="text" name="label" value="{{ old('label', $classification->label ?? $sourceClassification?->label) }}" class="input input-bordered w-full" maxlength="180" required />
        </div>

        <div>
            <label class="label" for="classification-sort"><span class="label-text">{{ __('Sortierung') }}</span></label>
            <input id="classification-sort" type="number" name="sort_order" value="{{ old('sort_order', $classification->sort_order ?? $sourceClassification?->sort_order ?? 100) }}" class="input input-bordered w-full" min="0" max="100000" />
        </div>

        <div>
            <label class="label" for="classification-color"><span class="label-text">{{ __('Farbe (#RRGGBB)') }}</span></label>
            <input id="classification-color" type="text" name="color_hex" value="{{ old('color_hex', $classification->color_hex ?? $sourceClassification?->color_hex) }}" class="input input-bordered w-full font-mono" maxlength="7" placeholder="#0055AA" />
        </div>

        <div>
            <label class="label" for="classification-icon"><span class="label-text">{{ __('Icon') }}</span></label>
            <input id="classification-icon" type="text" name="icon" value="{{ old('icon', $classification->icon ?? $sourceClassification?->icon) }}" class="input input-bordered w-full" maxlength="60" placeholder="build" />
        </div>

        <div class="flex items-end">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="active" value="1" class="toggle toggle-primary" @checked(old('active', $classification->active ?? $sourceClassification?->active ?? true)) />
                <span class="label-text">{{ __('Aktiv') }}</span>
            </label>
        </div>

        <div class="md:col-span-2">
            <label class="label" for="classification-description"><span class="label-text">{{ __('Beschreibung') }}</span></label>
            <textarea id="classification-description" name="description" class="textarea textarea-bordered w-full" rows="3">{{ old('description', $classification->description ?? $sourceClassification?->description) }}</textarea>
        </div>
    </div>
</x-modal>
