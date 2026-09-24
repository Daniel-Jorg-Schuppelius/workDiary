{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Stammdaten-Dialog Mitglied (in #entry-modal geladen).
  Variablen: $member (ClubMember|null), $users (Collection<User>)
--}}
@php
    $isEdit = $member !== null;
    $selectedUser = old('user_id', $member?->user?->sqid ?? '');
@endphp

<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.action.create_member')"
    :eyebrow="__('club.title.members')"
    icon="person"
    tone="primary"
    :action="$isEdit ? route('club.members.update', $member) : route('club.members.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.action.create_member')">

    <x-form-group :legend="__('club.card.master_data')" icon="badge" tone="primary" cols="2">
        <x-input-field name="member_no" type="number" min="1" :label="__('club.field.member_no')" :value="old('member_no', $member?->member_no)" :hint="$isEdit ? null : __('club.hint.member_no')" />
        <x-input-field name="birth_date" type="date" :label="__('club.field.birth_date')" :value="old('birth_date', $member?->birth_date?->toDateString())" />
        <x-input-field name="first_name" :label="__('club.field.first_name')" required maxlength="120" :value="old('first_name', $member?->first_name)" />
        <x-input-field name="last_name" :label="__('club.field.last_name')" required maxlength="120" :value="old('last_name', $member?->last_name)" />
        <x-input-field name="email" type="email" :label="__('club.field.email')" maxlength="190" :value="old('email', $member?->email)" />
        <x-input-field name="phone" type="tel" :label="__('club.field.phone')" maxlength="60" :value="old('phone', $member?->phone)" />
        <x-contact-address-fields :subject="$member" />
    </x-form-group>

    @unless ($isEdit)
        <x-form-group :legend="__('club.field.kind')" icon="how_to_reg" tone="primary" cols="2">
            <x-select-field name="kind" :label="__('club.field.kind')" required>
                @foreach (\App\Enums\Club\ClubMembershipKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', 'active') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="joined_on" type="date" :label="__('club.field.joined_on')" required :value="old('joined_on', now()->orgTz()->toDateString())" />
        </x-form-group>
    @endunless

    <x-form-group :legend="__('club.field.user')" icon="account_circle" tone="primary" cols="1" :description="__('club.hint.member_login')">
        <x-select-field name="user_id" :label="__('club.field.user')">
            <option value="">{{ __('club.label.no_account') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) $selectedUser === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="5000" :value="old('notes', $member?->notes)" />
    </x-form-group>
</x-modal>
