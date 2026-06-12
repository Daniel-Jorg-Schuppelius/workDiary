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
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $action?->title) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.root_cause') }}</span>
            <textarea name="root_cause" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.root_cause') }}">{{ old('root_cause', $action?->root_cause) }}</textarea>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.action_plan') }}</span>
            <textarea name="action_plan" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('action_plan', $action?->action_plan) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $action?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.due_on') }}</span>
            <input type="date" name="due_on"
                   class="input input-bordered w-full"
                   value="{{ old('due_on', $action?->due_on?->toDateString()) }}">
        </label>
    </x-form-group>
</x-modal>
