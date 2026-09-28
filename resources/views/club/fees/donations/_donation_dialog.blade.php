{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _donation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zuwendung erfassen/bearbeiten (MVP-1003). Variablen: $donation (ClubDonation|null), $members, $feesConfirmable
--}}
<x-modal :title="$donation !== null ? __('club.donations.action.edit') : __('club.donations.action.add')" :eyebrow="__('club.donations.title')"
         icon="volunteer_activism" tone="primary"
         :action="$donation !== null ? route('club.fees.donations.update', $donation) : route('club.fees.donations.store')"
         :method="$donation !== null ? 'PUT' : 'POST'" :form-data="['data-entry-form' => '']" :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.donations.field.donor')" icon="person" tone="primary" cols="2" :description="__('club.donations.hint.donor')">
        <x-select-field name="member" :label="__('club.donations.field.member')" span="2">
            <option value="">{{ __('club.donations.no_member') }}</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected(old('member', $donation?->member?->sqid) === $member->sqid)>{{ $member->fullName() }} ({{ $member->displayNo() }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="donor_name" :label="__('club.donations.field.donor_name')" maxlength="200" span="2" :value="old('donor_name', $donation?->donor_name)" />
        <x-textarea-field name="donor_address" :label="__('club.donations.field.donor_address')" rows="2" maxlength="1000" span="2" :value="old('donor_address', $donation?->donor_address)" />
    </x-form-group>
    <x-form-group :legend="__('club.donations.field.amount')" icon="payments" tone="ghost" cols="2">
        <x-select-field name="kind" :label="__('club.donations.field.kind')">
            @foreach (\App\Enums\Club\ClubDonationKind::cases() as $kind)
                @continue($kind === \App\Enums\Club\ClubDonationKind::MembershipFee && ! $feesConfirmable && $donation?->kind !== $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $donation?->kind?->value ?? 'donation') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="amount" type="number" step="0.01" min="0.01" required :label="__('club.donations.field.amount')" :value="old('amount', $donation?->amount?->getAmount())" />
        <x-input-field name="received_on" type="date" required :label="__('club.donations.field.received_on')" :value="old('received_on', $donation?->received_on?->toDateString() ?? now()->toDateString())" />
        <x-checkbox-field name="is_expense_waiver" :label="__('club.donations.field.expense_waiver')" :hint="__('club.donations.hint.expense_waiver')" :checked="(bool) old('is_expense_waiver', $donation?->is_expense_waiver ?? false)" />
        <x-textarea-field name="note" :label="__('club.donations.field.note')" rows="2" maxlength="2000" span="2" :value="old('note', $donation?->note)" />
    </x-form-group>
</x-modal>
