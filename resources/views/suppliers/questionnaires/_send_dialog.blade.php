{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _send_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Selbstauskunft anfragen (MVP-937). Erwartet: $supplier, $questionnaires --}}
<x-modal
    :title="__('supplier_questionnaire.send')"
    :eyebrow="$supplier->name"
    icon="send"
    tone="primary"
    :action="route('supplier-questionnaires.send', $supplier)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('supplier_questionnaire.send')"
>
    @if ($questionnaires->isEmpty())
        <p class="text-sm">{{ __('supplier_questionnaire.empty') }} <a class="link" href="{{ route('supplier-questionnaires.index') }}">{{ __('supplier_questionnaire.title') }}</a></p>
    @else
        <x-form-group :legend="__('supplier_questionnaire.send')" icon="send" tone="primary">
            <x-select-field name="questionnaire_id" :label="__('supplier_questionnaire.field.name')" required>
                @foreach ($questionnaires as $questionnaire)
                    <option value="{{ $questionnaire->sqid }}">{{ $questionnaire->name }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="recipient_email" type="email" :label="__('supplier_questionnaire.field.recipient_email')" :value="old('recipient_email', $supplier->email)" required />
            <p class="text-xs text-muted">{{ __('supplier_questionnaire.send_hint', ['days' => \App\Services\Supplier\SupplierQuestionnaireService::LINK_DAYS]) }}</p>
        </x-form-group>
    @endif
</x-modal>
