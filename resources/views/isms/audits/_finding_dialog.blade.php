{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _finding_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Auditfeststellung (in #entry-modal
  geladen): Art (Nichtkonformität major/minor, Beobachtung, Verbesserung),
  optionaler Bezug auf die betroffene Normanforderung. Anlegbar nur bei
  laufendem Audit (inProgress/reportIssued — AuditService).
  Variablen: $audit (IsmsAudit), $finding (IsmsAuditFinding|null),
             $requirements (Collection)
--}}
@php
    $isEdit = $finding !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_finding') : __('isms.action.create_finding')"
    :eyebrow="$audit->displayNo() . ' · ' . $audit->title"
    icon="report"
    tone="primary"
    :action="$isEdit ? route('isms.audits.findings.update', $finding) : route('isms.audits.findings.store', $audit)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_finding')">

    <x-form-group :legend="__('isms.group.finding')" icon="report" tone="primary" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $finding?->title) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.finding_kind') }} *</span>
            <select name="kind" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\FindingKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', $finding?->kind?->value ?? 'observation') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.requirement') }}</span>
            <select name="isms_requirement_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($requirements as $requirement)
                    <option value="{{ $requirement->id }}" @selected((string) old('isms_requirement_id', $finding?->isms_requirement_id) === (string) $requirement->id)>
                        {{ $requirement->normLabel() }} · {{ $requirement->ref_no }} — {{ $requirement->title }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.description') }}</span>
            <textarea name="description" rows="4" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('description', $finding?->description) }}</textarea>
        </label>
    </x-form-group>
</x-modal>
