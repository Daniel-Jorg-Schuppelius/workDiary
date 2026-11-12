{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assignment_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\AssetCompliance\AssetComplianceAssignment $assignment */
    $userSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, $assignment->responsible_user_id);
    $contactSqid = \App\Support\Sqid::encode(\App\Models\Contacts\ExternalContact::class, $assignment->external_contact_id);
@endphp

<x-modal
    :title="__('asset_compliance.assignment.edit')"
    :eyebrow="($assignment->asset->name ?? '—') . ' — ' . ($assignment->profile->name ?? '—')"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="route('asset-compliance.assignments.update', $assignment)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="interval_months_override" type="number" min="1" max="240" :label="__('asset_compliance.assignment.interval_override')"
                       :value="old('interval_months_override', $assignment->interval_months_override)"
                       :hint="__('asset_compliance.assignment.interval_override_hint', ['months' => $assignment->profile->interval_months ?? 12])" />
        <x-input-field name="last_done_on" type="date" :label="__('Zuletzt geprüft am')" :value="old('last_done_on', $assignment->last_done_on?->toDateString())" />
        <x-input-field name="next_due_on" type="date" :label="__('asset_compliance.assignment.next_due_on')"
                       :value="old('next_due_on', $assignment->next_due_on?->toDateString())" :hint="__('asset_compliance.assignment.next_due_hint')" />
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('—') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $userSqid) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="external_contact_id" :label="__('Prüfstelle (extern)')">
            <option value="">{{ __('intern') }}</option>
            @foreach ($externalContacts as $contact)
                <option value="{{ $contact->sqid }}" @selected(old('external_contact_id', $contactSqid) === $contact->sqid)>{{ $contact->name }}</option>
            @endforeach
        </x-select-field>
    </div>

    <x-textarea-field name="note" :label="__('Notiz')" rows="2" maxlength="1000" :value="old('note', $assignment->note)" />
</x-modal>
