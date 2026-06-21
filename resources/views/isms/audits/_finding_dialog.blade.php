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
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180"
                       span="2"
                       :value="old('title', $finding?->title)" />
        <x-select-field name="kind" :label="__('isms.field.finding_kind')" required>
            @foreach (\App\Enums\Isms\FindingKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $finding?->kind?->value ?? 'observation') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="isms_requirement_id" :label="__('isms.field.requirement')">
            <option value="">—</option>
            @foreach ($requirements as $requirement)
                <option value="{{ $requirement->id }}" @selected((string) old('isms_requirement_id', $finding?->isms_requirement_id) === (string) $requirement->id)>
                    {{ $requirement->normLabel() }} · {{ $requirement->ref_no }} — {{ $requirement->title }}
                </option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('isms.field.description')" rows="4" maxlength="10000"
                          span="2"
                          :value="old('description', $finding?->description)" />
    </x-form-group>
</x-modal>
