{{--
  Created on   : Sat Jun 13 2026
  License      : AGPL-3.0-or-later

  Anlage/Bearbeitung eines eigenen Bankkontos (Feature 045) als Modal.
--}}
@php
    /** @var \App\Models\Finance\BankAccount $account */
    $isEdit = $account?->exists ?? false;
@endphp
<x-modal
    :title="$isEdit ? __('bank.action.edit_account') : __('bank.action.new_account')"
    icon="account_balance"
    tone="primary"
    :action="$isEdit ? route('finance.bank-accounts.update', $account->sqid) : route('finance.bank-accounts.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <x-form-group :legend="__('bank.title.account')" icon="account_balance" tone="primary" cols="2">
        <x-input-field name="label" :label="__('bank.field.label')" required maxlength="120" span="2" :value="old('label', $account->label)" />
        <x-input-field name="iban" :label="__('bank.field.iban')" required maxlength="64" span="2" class="font-mono uppercase" :value="old('iban', $account->iban)" />
        <x-input-field name="bic" :label="__('bank.field.bic')" maxlength="32" class="uppercase" :value="old('bic', $account->bic)" />
        <x-input-field name="account_holder" :label="__('bank.field.account_holder')" maxlength="200" :value="old('account_holder', $account->account_holder)" />
        <x-input-field name="datev_account_no" :label="__('bank.field.datev_account_no')" maxlength="20" :value="old('datev_account_no', $account->datev_account_no)" />
        <div class="fieldset">
            <label class="label cursor-pointer justify-start gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm"
                       @checked(old('is_active', $account->is_active ?? true))>
                <span class="label-text">{{ __('bank.field.is_active') }}</span>
            </label>
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('finance.bank-accounts.destroy', $account->sqid)" method="DELETE"
                  :confirm="__('bank.action.delete_account') . '?'"
                  :confirm-label="__('bank.action.delete_account')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('bank.action.delete_account') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
