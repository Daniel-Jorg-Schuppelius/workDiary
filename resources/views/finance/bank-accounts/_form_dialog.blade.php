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
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('bank.field.label') }} *</label>
            <input type="text" name="label" required maxlength="120"
                   value="{{ old('label', $account->label) }}" class="input input-bordered w-full">
        </div>
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('bank.field.iban') }} *</label>
            <input type="text" name="iban" required maxlength="64"
                   value="{{ old('iban', $account->iban) }}" class="input input-bordered w-full font-mono uppercase">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('bank.field.bic') }}</label>
            <input type="text" name="bic" maxlength="32"
                   value="{{ old('bic', $account->bic) }}" class="input input-bordered w-full uppercase">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('bank.field.account_holder') }}</label>
            <input type="text" name="account_holder" maxlength="200"
                   value="{{ old('account_holder', $account->account_holder) }}" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('bank.field.datev_account_no') }}</label>
            <input type="text" name="datev_account_no" maxlength="20"
                   value="{{ old('datev_account_no', $account->datev_account_no) }}" class="input input-bordered w-full">
        </div>
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
            <form method="POST" action="{{ route('finance.bank-accounts.destroy', $account->sqid) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('bank.action.delete_account') }}?"
                  data-confirm-label="{{ __('bank.action.delete_account') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('bank.action.delete_account') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
