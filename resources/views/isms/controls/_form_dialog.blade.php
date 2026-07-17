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
    $linkedRequirementIds = $isEdit ? $control->requirements->pluck('sqid')->all() : [];
    $ownerSqid = \App\Support\Sqid::encode(\App\Models\User::class, $control?->owner_user_id);
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
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180"
                       span="2"
                       :value="old('title', $control?->title)" />
        <x-textarea-field name="description" :label="__('isms.field.description')" rows="3" maxlength="10000"
                          span="2"
                          :value="old('description', $control?->description)" />
        <x-select-field name="implementation_status" :label="__('isms.field.implementation_status')" required>
            @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('implementation_status', $control?->implementation_status?->value ?? 'open') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')">
            <option value="">—</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->sqid }}" @selected((string) old('owner_user_id', $ownerSqid) === $owner->sqid)>{{ $owner->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="evidence_note" :label="__('isms.field.evidence_note')" maxlength="10000"
                       span="2"
                       :value="old('evidence_note', $control?->evidence_note)"
                       placeholder="{{ __('isms.hint.evidence_note') }}" />
    </x-form-group>

    <x-form-group :legend="__('isms.field.requirements')" icon="checklist" tone="info" cols="1">
        {{-- Leerer Marker: sorgt dafür, dass requirement_ids auch bei komplett
             abgewählter Auswahl übertragen wird (Sync auf leere Liste). --}}
        <input type="hidden" name="requirement_ids[]" value="">
        <label class="form-control">
            <span class="label-text">{{ __('isms.hint.requirements') }}</span>
            <select name="requirement_ids[]" multiple size="8" class="select select-bordered w-full h-auto">
                @foreach ($requirements as $requirement)
                    <option value="{{ $requirement->sqid }}"
                            @selected(in_array($requirement->sqid, old('requirement_ids', $linkedRequirementIds)))>
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
