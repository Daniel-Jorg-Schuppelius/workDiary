@php
    /** @var \App\Models\ClassificationRequirement $requirement */
    /** @var array<string, string> $entryTypeOptions */
    /** @var array<string, array{enforce_phase: string, severity: string, min_count: int, max_count: int|null, allow_multi: bool}> $entryTypePresets */
    /** @var array<string, array{enforce_phase: string, severity: string, min_count: int, max_count: int|null, allow_multi: bool}> $requiredDomainPresets */
    /** @var array<string, string> $requiredDomainOptions */
    /** @var array<string, string> $phaseLabels */
    /** @var array<string, string> $severityLabels */
    $isEdit = (bool) ($requirement->id ?? false);
    $entryTypePresetsJson = json_encode($entryTypePresets, JSON_UNESCAPED_UNICODE);
    $requiredDomainPresetsJson = json_encode($requiredDomainPresets, JSON_UNESCAPED_UNICODE);
    $hasOldInput = session()->getOldInput() !== [];
@endphp

<x-modal
    :title="$isEdit ? __('Pflichtregel bearbeiten') : __('Pflichtregel anlegen')"
    icon="rule_settings"
    tone="primary"
    size="xl"
    :action="$isEdit ? route('admin.classification-requirements.update', $requirement) : route('admin.classification-requirements.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="label" for="req-entry-type"><span class="label-text">{{ __('Auftragstyp') }}</span></label>
            <select id="req-entry-type"
                    name="entry_type_code"
                    class="select select-bordered w-full"
                    required
                    data-entry-type-presets='{{ $entryTypePresetsJson }}'
                    data-requirement-edit-mode="{{ $isEdit ? '1' : '0' }}"
                    data-has-old-input="{{ $hasOldInput ? '1' : '0' }}">
                <option value="">{{ __('Bitte wählen') }}</option>
                @foreach ($entryTypeOptions as $code => $label)
                    <option value="{{ $code }}" @selected(old('entry_type_code', $requirement->entry_type_code) === $code)>{{ $label }} ({{ $code }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="req-domain"><span class="label-text">{{ __('Pflicht-Domain') }}</span></label>
            <select id="req-domain"
                    name="required_domain"
                    class="select select-bordered w-full"
                    required
                    data-required-domain-presets='{{ $requiredDomainPresetsJson }}'>
                <option value="">{{ __('Bitte wählen') }}</option>
                @foreach ($requiredDomainOptions as $code => $label)
                    <option value="{{ $code }}" @selected(old('required_domain', $requirement->required_domain) === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="req-phase"><span class="label-text">{{ __('Phase') }}</span></label>
            <select id="req-phase" name="enforce_phase" class="select select-bordered w-full" required data-preset-target="enforce_phase">
                @foreach ($phaseLabels as $code => $label)
                    <option value="{{ $code }}" @selected(old('enforce_phase', $requirement->enforce_phase?->value) === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="req-severity"><span class="label-text">{{ __('Schweregrad') }}</span></label>
            <select id="req-severity" name="severity" class="select select-bordered w-full" required data-preset-target="severity">
                @foreach ($severityLabels as $code => $label)
                    <option value="{{ $code }}" @selected(old('severity', $requirement->severity?->value) === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="req-min"><span class="label-text">{{ __('Minimalanzahl') }}</span></label>
            <input id="req-min" type="number" name="min_count" min="1" max="50" value="{{ old('min_count', $requirement->min_count ?? 1) }}" class="input input-bordered w-full" required data-preset-target="min_count" />
        </div>
        <div>
            <label class="label" for="req-max"><span class="label-text">{{ __('Maximalanzahl') }}</span></label>
            <input id="req-max" type="number" name="max_count" min="1" max="50" value="{{ old('max_count', $requirement->max_count) }}" class="input input-bordered w-full" data-preset-target="max_count" />
        </div>
        <div class="md:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="allow_multi" value="1" class="toggle toggle-primary" @checked(old('allow_multi', $requirement->allow_multi ?? false)) data-preset-target="allow_multi" />
                <span class="label-text">{{ __('Mehrfachauswahl zulassen') }}</span>
            </label>
        </div>
        <div class="md:col-span-2">
            <label class="label" for="req-only-if"><span class="label-text">{{ __('only_if_json (optional)') }}</span></label>
            <textarea id="req-only-if" name="only_if_json" rows="5" class="textarea textarea-bordered w-full font-mono" placeholder='{"priority": ["high", "critical"]}'>{{ old('only_if_json', $onlyIfJsonText) }}</textarea>
            <p class="text-xs text-base-content/60 mt-1">{{ __('JSON-Objekt mit Schlüsseln und erlaubten Werten, z. B. {"priority": ["high"]}.') }}</p>
        </div>
        <div class="md:col-span-2">
            <label class="label" for="req-note"><span class="label-text">{{ __('Hinweis') }}</span></label>
            <input id="req-note" type="text" name="note" maxlength="255" value="{{ old('note', $requirement->note) }}" class="input input-bordered w-full" />
        </div>
    </div>
</x-modal>

<script>
    (function () {
        var entryTypeSelect = document.getElementById('req-entry-type');
        var requiredDomainSelect = document.getElementById('req-domain');
        if (!entryTypeSelect || !requiredDomainSelect) {
            return;
        }

        var entryTypePresetsRaw = entryTypeSelect.dataset.entryTypePresets || '{}';
        var entryTypePresets = {};
        try {
            entryTypePresets = JSON.parse(entryTypePresetsRaw);
        } catch (_error) {
            entryTypePresets = {};
        }

        var requiredDomainPresetsRaw = requiredDomainSelect.dataset.requiredDomainPresets || '{}';
        var requiredDomainPresets = {};
        try {
            requiredDomainPresets = JSON.parse(requiredDomainPresetsRaw);
        } catch (_error) {
            requiredDomainPresets = {};
        }

        var fields = {
            enforce_phase: document.querySelector('[data-preset-target="enforce_phase"]'),
            severity: document.querySelector('[data-preset-target="severity"]'),
            min_count: document.querySelector('[data-preset-target="min_count"]'),
            max_count: document.querySelector('[data-preset-target="max_count"]'),
            allow_multi: document.querySelector('[data-preset-target="allow_multi"]')
        };

        function combinedPreset() {
            var preset = {};
            var requiredDomainPreset = requiredDomainPresets[requiredDomainSelect.value];
            var entryTypePreset = entryTypePresets[entryTypeSelect.value];

            if (requiredDomainPreset) {
                Object.assign(preset, requiredDomainPreset);
            }
            if (entryTypePreset) {
                Object.assign(preset, entryTypePreset);
            }

            return preset;
        }

        function applyPreset(force) {
            var preset = combinedPreset();
            if (Object.keys(preset).length === 0) {
                return;
            }

            if (fields.enforce_phase && (force || fields.enforce_phase.value === '')) {
                fields.enforce_phase.value = preset.enforce_phase;
            }
            if (fields.severity && (force || fields.severity.value === '')) {
                fields.severity.value = preset.severity;
            }
            if (fields.min_count && (force || fields.min_count.value === '' || fields.min_count.value === '1')) {
                fields.min_count.value = String(preset.min_count);
            }
            if (fields.max_count && force) {
                fields.max_count.value = preset.max_count === null ? '' : String(preset.max_count);
            }
            if (fields.allow_multi && force) {
                fields.allow_multi.checked = Boolean(preset.allow_multi);
            }
        }

        entryTypeSelect.addEventListener('change', function () {
            applyPreset(true);
        });

        requiredDomainSelect.addEventListener('change', function () {
            applyPreset(true);
        });

        var isEditMode = entryTypeSelect.dataset.requirementEditMode === '1';
        var hasOldInput = entryTypeSelect.dataset.hasOldInput === '1';
        if (!isEditMode && !hasOldInput && (entryTypeSelect.value !== '' || requiredDomainSelect.value !== '')) {
            applyPreset(false);
        }
    })();
</script>
