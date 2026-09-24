{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _guardian_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Vertretung (Sorgeberechtigte) anlegen/bearbeiten (in #entry-modal geladen).
  Variablen: $member (ClubMember), $guardian (ClubGuardian|null), $users (Collection<User>)
--}}
@php
    $isEdit = $guardian !== null;
    $selectedUser = old('user_id', $guardian?->user?->sqid ?? '');
    $selectedPermissions = (array) old('permissions', $guardian?->permissions ?? [\App\Enums\Club\ClubGuardianPermission::Register->value]);
@endphp

<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.action.add_guardian')"
    :eyebrow="$member->displayNo() . ' · ' . $member->fullName()"
    icon="family_restroom"
    tone="primary"
    :action="$isEdit ? route('club.members.guardians.update', [$member, $guardian]) : route('club.members.guardians.store', $member)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.field.contact')" icon="contact_mail" tone="primary" cols="2" :description="__('club.hint.guardian')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="160" span="2" :value="old('name', $guardian?->name)" />
        <x-input-field name="email" type="email" :label="__('club.field.email')" maxlength="190" :value="old('email', $guardian?->email)" />
        <x-input-field name="phone" type="tel" :label="__('club.field.phone')" maxlength="60" :value="old('phone', $guardian?->phone)" />
        <x-contact-address-fields :subject="$guardian" />
        <x-select-field name="user_id" :label="__('club.field.user')" span="2">
            <option value="">{{ __('club.label.no_account') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) $selectedUser === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('club.field.permissions')" icon="rule" tone="primary" cols="1">
        @foreach (\App\Enums\Club\ClubGuardianPermission::cases() as $permission)
            <x-checkbox-field name="permissions[]" :id="'guardian-perm-' . $permission->value" :value="$permission->value"
                              :label="$permission->label()" :checked="in_array($permission->value, $selectedPermissions, true)"
                              :with-hidden="false" />
        @endforeach
    </x-form-group>

    <x-form-group :legend="__('club.field.period')" icon="date_range" tone="primary" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="valid_from" to-name="valid_to" type="date"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('valid_from', $guardian?->valid_from?->toDateString())"
                      :to="old('valid_to', $guardian?->valid_to?->toDateString())" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="2" :value="old('note', $guardian?->note)" />
    </x-form-group>
</x-modal>
