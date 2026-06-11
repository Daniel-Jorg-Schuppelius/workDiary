{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog ISMS-Maßnahme (in #entry-modal geladen).
  SoA-Regel: applicable=false ⇒ justification Pflicht (Request-Validierung
  required_if + zentral im ControlService).
  Variablen: $control (IsmsControl|null), $owners (Collection id/name)
--}}
@php
    $isEdit = $control !== null;
    $isCatalog = $isEdit && $control->source === \App\Enums\Isms\ControlSource::Iso27001AnnexA;
    $applicableOld = (bool) old('applicable', $control?->applicable ?? true);
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_control') : __('isms.action.create_control')"
    :eyebrow="__('isms.title.controls')"
    icon="verified_user"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.controls.update', $control) : route('isms.controls.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_control')">

    <x-form-group :legend="__('isms.group.control')" icon="verified_user" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.code') }} @unless ($isCatalog) * @endunless</span>
            <input type="text" name="code" maxlength="24"
                   class="input input-bordered w-full font-mono"
                   value="{{ old('code', $control?->code) }}"
                   placeholder="{{ __('isms.hint.code') }}"
                   @if ($isCatalog) readonly @else required @endif>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $control?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $control?->title) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.description') }}</span>
            <textarea name="description" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('description', $control?->description) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.soa')" icon="rule_folder" tone="warning" cols="1">
        {{-- Checkbox + Hidden-0: applicable kommt immer mit (Toggle aus = 0). --}}
        <input type="hidden" name="applicable" value="0">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="applicable" value="1" class="toggle toggle-success"
                   @checked($applicableOld)>
            <span class="label-text">{{ __('isms.field.applicable') }}</span>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.justification') }} <span class="text-base-content/60">({{ __('isms.hint.justification') }})</span></span>
            <textarea name="justification" rows="2" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('justification', $control?->justification) }}</textarea>
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('isms.field.implementation_status') }} *</span>
                <select name="implementation_status" required class="select select-bordered w-full">
                    @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('implementation_status', $control?->implementation_status?->value ?? 'open') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('isms.field.evidence_note') }}</span>
                <input type="text" name="evidence_note" maxlength="10000"
                       class="input input-bordered w-full"
                       value="{{ old('evidence_note', $control?->evidence_note) }}"
                       placeholder="{{ __('isms.hint.evidence_note') }}">
            </label>
        </div>
    </x-form-group>
</x-modal>
