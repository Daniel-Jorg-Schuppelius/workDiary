{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Externes Kontaktprofil anlegen/bearbeiten (Feature 033, Rang 30) — Modal.
  Variablen: $contact (ExternalContact|null), $parties
--}}
<x-modal
    :title="$contact ? __('external.contact.edit') : __('external.contact.new')"
    :eyebrow="__('external.contact.eyebrow')"
    icon="contacts"
    tone="primary"
    size="md"
    :action="$contact ? route('external-contacts.update', $contact) : route('external-contacts.store')"
    :method="$contact ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('external.contact.submit')">

    <x-form-group :legend="__('external.group.contact')" icon="person" tone="primary" cols="2">
        <x-input-field name="name" :label="__('external.field.name')" required minlength="2" maxlength="160" :value="old('name', $contact?->name)" />
        <x-input-field name="email" type="email" :label="__('external.field.email')" maxlength="190" :value="old('email', $contact?->email)" />
        <x-input-field name="role" :label="__('external.field.role')" maxlength="120" :value="old('role', $contact?->role)" placeholder="{{ __('external.hint.role') }}" />
        <x-select-field name="party" :label="__('external.field.party')" required>
            @foreach ($parties as $party)
                <option value="{{ $party->value }}" @selected(old('party', $contact?->party?->value) === $party->value)>{{ $party->label() }}</option>
            @endforeach
        </x-select-field>
        <div class="md:col-span-2">
            <x-input-field name="notes" :label="__('external.contact.notes')" maxlength="2000" :value="old('notes', $contact?->notes)" />
        </div>
    </x-form-group>
</x-modal>
