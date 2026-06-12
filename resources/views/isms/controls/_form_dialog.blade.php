{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog normneutrale Maßnahme (in #entry-modal
  geladen). Anforderungs-Mapping als Mehrfachauswahl (n:m, auch
  normübergreifend); die SoA-Aussage liegt NICHT hier, sondern am
  ApplicabilityStatement (Anforderungen-Seite).
  Variablen: $control (IsmsControl|null), $requirements (Collection),
             $owners (Collection id/name)
--}}
@php
    $isEdit = $control !== null;
    $linkedRequirementIds = $isEdit ? $control->requirements->pluck('id')->all() : [];
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
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.implementation_status') }} *</span>
            <select name="implementation_status" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('implementation_status', $control?->implementation_status?->value ?? 'open') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
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
            <span class="label-text">{{ __('isms.field.evidence_note') }}</span>
            <input type="text" name="evidence_note" maxlength="10000"
                   class="input input-bordered w-full"
                   value="{{ old('evidence_note', $control?->evidence_note) }}"
                   placeholder="{{ __('isms.hint.evidence_note') }}">
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.field.requirements')" icon="checklist" tone="info" cols="1">
        {{-- Leerer Marker: sorgt dafür, dass requirement_ids auch bei komplett
             abgewählter Auswahl übertragen wird (Sync auf leere Liste). --}}
        <input type="hidden" name="requirement_ids[]" value="">
        <label class="form-control">
            <span class="label-text">{{ __('isms.hint.requirements') }}</span>
            <select name="requirement_ids[]" multiple size="8" class="select select-bordered w-full h-auto">
                @foreach ($requirements as $requirement)
                    <option value="{{ $requirement->id }}"
                            @selected(in_array($requirement->id, old('requirement_ids', $linkedRequirementIds)))>
                        {{ $requirement->normLabel() }} {{ $requirement->ref_no }} — {{ $requirement->title }}
                    </option>
                @endforeach
            </select>
        </label>
        @if ($requirements->isEmpty())
            <p class="text-xs text-base-content/60">{{ __('isms.hint.no_requirements_yet') }}</p>
        @endif
    </x-form-group>
</x-modal>
