{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _action_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Korrekturmaßnahme (in #entry-modal
  geladen): Ursachenanalyse, Maßnahmenplan, Verantwortlicher, Fälligkeit.
  Statuswechsel inkl. Wirksamkeitsprüfung laufen über das Dropdown in der
  Liste (Pflicht-Notiz — AuditService).
  Variablen: $finding (IsmsAuditFinding), $action (IsmsCorrectiveAction|null),
             $owners (Collection id/name)
--}}
@php
    $isEdit = $action !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_action') : __('isms.action.create_action')"
    :eyebrow="$finding->displayNo() . ' · ' . $finding->title"
    icon="build_circle"
    tone="primary"
    :action="$isEdit ? route('isms.audits.actions.update', $action) : route('isms.audits.actions.store', $finding)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_action')">

    <x-form-group :legend="__('isms.group.corrective_action')" icon="build_circle" tone="primary" cols="2">
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180"
                       span="2"
                       :value="old('title', $action?->title)" />
        <x-textarea-field name="root_cause" :label="__('isms.field.root_cause')" rows="2" maxlength="10000"
                          span="2"
                          placeholder="{{ __('isms.hint.root_cause') }}"
                          :value="old('root_cause', $action?->root_cause)" />
        <x-textarea-field name="action_plan" :label="__('isms.field.action_plan')" rows="3" maxlength="10000"
                          span="2"
                          :value="old('action_plan', $action?->action_plan)" />
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')">
            <option value="">—</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->sqid }}" @selected((string) old('owner_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $action?->owner_user_id)) === $owner->sqid)>{{ $owner->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="due_on" type="date" :label="__('isms.field.due_on')"
                       :value="old('due_on', $action?->due_on?->toDateString())" />
    </x-form-group>
</x-modal>
