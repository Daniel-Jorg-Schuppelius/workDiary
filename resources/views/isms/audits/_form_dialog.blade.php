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
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $audit?->title) }}">
        </label>
        @unless ($isEdit)
            <label class="form-control">
                <span class="label-text">{{ __('isms.field.scope') }} *</span>
                <select name="scope" required class="select select-bordered w-full">
                    @foreach ($scopes as $scopeOption)
                        <option value="{{ $scopeOption->sqid }}" @selected(old('scope') === $scopeOption->sqid || (old('scope') === null && $scopeOption->is_default))>{{ $scopeOption->name }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.audit_kind') }} *</span>
            <select name="kind" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\AuditKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', $audit?->kind?->value ?? 'internal') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.norm') }}</span>
            <input type="text" name="norm" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('norm', $audit?->norm) }}"
                   placeholder="{{ __('isms.hint.norm') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.edition') }}</span>
            <input type="text" name="edition" maxlength="16"
                   class="input input-bordered w-full"
                   value="{{ old('edition', $audit?->edition) }}"
                   placeholder="{{ __('isms.hint.edition') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.criteria') }}</span>
            <textarea name="criteria" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.criteria') }}">{{ old('criteria', $audit?->criteria) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.audit_schedule')" icon="event" tone="info" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.planned_on') }}</span>
            <input type="date" name="planned_on"
                   class="input input-bordered w-full"
                   value="{{ old('planned_on', $audit?->planned_on?->toDateString()) }}">
        </label>
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
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.lead_auditor') }}</span>
            <select name="lead_auditor_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($auditorOptions as $auditor)
                    <option value="{{ $auditor->id }}" @selected((string) old('lead_auditor_user_id', $audit?->lead_auditor_user_id) === (string) $auditor->id)>{{ $auditor->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.auditors') }}</span>
            <input type="text" name="auditors" maxlength="5000"
                   class="input input-bordered w-full"
                   value="{{ old('auditors', $audit?->auditors) }}"
                   placeholder="{{ __('isms.hint.auditors') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.independence_note') }}</span>
            <textarea name="independence_note" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.independence_note') }}">{{ old('independence_note', $audit?->independence_note) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.audit_result')" icon="summarize" tone="success" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.summary') }}</span>
            <textarea name="summary" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.summary') }}">{{ old('summary', $audit?->summary) }}</textarea>
        </label>
    </x-form-group>
</x-modal>
