{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage-/Bearbeitungs-Dialog Audit (in #entry-modal geladen): Stammdaten,
  geprüfte Norm (optional), Plan-/Durchführungszeitraum (x-date-range),
  Auditoren inkl. Unabhängigkeitsprüfung und Ergebnis-Zusammenfassung
  (Pflicht für den Statuswechsel auf „Bericht erstellt" — AuditService).
  Variablen: $audit (IsmsAudit|null), $scopes, $auditorOptions (id/name)
--}}
@php
    $isEdit = $audit !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_audit') : __('isms.action.create_audit')"
    :eyebrow="__('isms.title.audits')"
    icon="fact_check"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.audits.update', $audit) : route('isms.audits.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_audit')">

    <x-form-group :legend="__('isms.group.audit')" icon="fact_check" tone="primary" cols="2">
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180"
                       span="2"
                       :value="old('title', $audit?->title)" />
        @unless ($isEdit)
            <x-select-field name="scope" :label="__('isms.field.scope')" required>
                @foreach ($scopes as $scopeOption)
                    <option value="{{ $scopeOption->sqid }}" @selected(old('scope') === $scopeOption->sqid || (old('scope') === null && $scopeOption->is_default))>{{ $scopeOption->name }}</option>
                @endforeach
            </x-select-field>
        @endunless
        <x-select-field name="kind" :label="__('isms.field.audit_kind')" required>
            @foreach (\App\Enums\Isms\AuditKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $audit?->kind?->value ?? 'internal') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="norm" :label="__('isms.field.norm')" maxlength="64"
                       :value="old('norm', $audit?->norm)"
                       placeholder="{{ __('isms.hint.norm') }}" />
        <x-input-field name="edition" :label="__('isms.field.edition')" maxlength="16"
                       :value="old('edition', $audit?->edition)"
                       placeholder="{{ __('isms.hint.edition') }}" />
        <x-textarea-field name="criteria" :label="__('isms.field.criteria')" rows="3" maxlength="10000"
                          span="2"
                          placeholder="{{ __('isms.hint.criteria') }}"
                          :value="old('criteria', $audit?->criteria)" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.audit_schedule')" icon="event" tone="info" cols="2">
        <x-input-field name="planned_on" type="date" :label="__('isms.field.planned_on')"
                       span="2"
                       :value="old('planned_on', $audit?->planned_on?->toDateString())" />
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      from-name="performed_from"
                      to-name="performed_to"
                      :from="old('performed_from', $audit?->performed_from?->toDateString())"
                      :to="old('performed_to', $audit?->performed_to?->toDateString())"
                      :from-label="__('isms.field.performed_from')"
                      :to-label="__('isms.field.performed_to')"
                      size="md" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.auditors')" icon="group" tone="warning" cols="2">
        <x-select-field name="lead_auditor_user_id" :label="__('isms.field.lead_auditor')">
            <option value="">—</option>
            @foreach ($auditorOptions as $auditor)
                <option value="{{ $auditor->id }}" @selected((string) old('lead_auditor_user_id', $audit?->lead_auditor_user_id) === (string) $auditor->id)>{{ $auditor->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="auditors" :label="__('isms.field.auditors')" maxlength="5000"
                       :value="old('auditors', $audit?->auditors)"
                       placeholder="{{ __('isms.hint.auditors') }}" />
        <x-textarea-field name="independence_note" :label="__('isms.field.independence_note')" rows="2" maxlength="10000"
                          span="2"
                          placeholder="{{ __('isms.hint.independence_note') }}"
                          :value="old('independence_note', $audit?->independence_note)" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.audit_result')" icon="summarize" tone="success" cols="1">
        <x-textarea-field name="summary" :label="__('isms.field.summary')" rows="3" maxlength="10000"
                          placeholder="{{ __('isms.hint.summary') }}"
                          :value="old('summary', $audit?->summary)" />
    </x-form-group>
</x-modal>
