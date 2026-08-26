{{--
  Created on   : Fri Aug 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _pdf_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal
    :title="__('invoice-import.title')"
    :eyebrow="__('invoice-import.eyebrow')"
    icon="document_scanner"
    tone="primary"
    :action="route('invoices.pdf-import.store')"
    method="POST"
    enctype="multipart/form-data"
    :submit-label="__('invoice-import.submit')"
>
    <div class="space-y-4">
        <div class="alert alert-info text-sm">
            <x-icon name="auto_fix_high" />
            <span>{{ __('invoice-import.intro') }}</span>
        </div>

        <x-form-group :legend="__('invoice-import.group_source')" icon="upload_file" tone="primary">
            <label class="form-control">
                <span class="label-text">{{ __('invoice-import.file') }} <span class="text-error">*</span></span>
                <input type="file" name="file" accept=".pdf,.xml,.docx,.doc,.xlsx,.xls,application/pdf,application/xml,text/xml,application/msword,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                       class="file-input file-input-bordered file-input-sm w-full" required>
                <span class="label-text-alt">{{ __('invoice-import.file_hint') }}</span>
            </label>
        </x-form-group>

        <x-form-group :legend="__('invoice-import.group_target')" icon="receipt_long" tone="info" cols="2">
            <x-select-field name="customer_id" :label="__('Kunde')" required>
                <option value="">{{ __('-- bitte wählen --') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="delivery_format" :label="__('invoice-import.delivery_format')">
                <option value="">{{ __('invoice-import.customer_default_option') }}</option>
                @foreach ($formats as $format)
                    <option value="{{ $format->value }}" @selected(old('delivery_format') === $format->value)>{{ $format->label() }}</option>
                @endforeach
            </x-select-field>
        </x-form-group>

        <p class="text-xs text-muted">{{ __('invoice-import.review_hint') }}</p>
        <x-validation-errors />
    </div>
</x-modal>
