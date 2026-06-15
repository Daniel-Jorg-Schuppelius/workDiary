{{--
  Created on   : Sat Jun 13 2026
  License      : AGPL-3.0-or-later

  Import-Dialog für Bankdateien (CAMT.053/MT940) — Feature 045, Priorität 3.
--}}
<x-modal
    :title="__('bank.import.dialog_title')"
    icon="upload"
    tone="primary"
    size="lg"
    :action="route('finance.reconciliation.upload')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('bank.action.upload')"
>
    <div class="text-sm text-base-content/70">{{ __('bank.import.dialog_hint') }}</div>

    <div>
        <label class="label" for="bank-import-file">
            <span class="label-text">{{ __('bank.import.file') }}</span>
        </label>
        <input id="bank-import-file"
               type="file"
               name="file"
               accept=".xml,.txt,.sta,.940,text/xml,application/xml,text/plain"
               required
               class="file-input file-input-bordered w-full @error('file') file-input-error @enderror">
        @error('file')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    @if ($accounts->isNotEmpty())
        <div>
            <label class="label" for="bank-import-account">
                <span class="label-text">{{ __('bank.import.account_optional') }}</span>
            </label>
            <select id="bank-import-account" name="bank_account" class="select select-bordered w-full">
                <option value="">{{ __('bank.import.account_optional') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->sqid }}">{{ $account->label }}</option>
                @endforeach
            </select>
        </div>
    @endif
</x-modal>
