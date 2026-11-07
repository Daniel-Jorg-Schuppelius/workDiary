{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _send_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zustellung einer extern ausgestellten Rechnung (Feature 196): Kanal, Empfänger, Vorlage und die
     ausdrücklichen Bestätigungen zum Anbieterversand. Erwartet: $invoice, $kind, $templates, $checks. --}}
@php
    $isResend = $kind === \App\Enums\Finance\ExternalPackageKind::Resend;
    $askProvider = ! $isResend && $invoice->provider_send_state === \App\Enums\Finance\ProviderSendState::Unknown && ! $providerSendDisabled;
    $askRedelivery = ! $isResend && $invoice->provider_send_state === \App\Enums\Finance\ProviderSendState::Sent;
@endphp
<x-modal
    :title="$isResend ? __('finance.external_invoice.send.resend_title', ['number' => $invoice->displayNumber()]) : __('finance.external_invoice.send.title', ['number' => $invoice->displayNumber()])"
    :eyebrow="__('finance.external_invoice.eyebrow', ['number' => $invoice->displayNumber()])"
    icon="send"
    tone="primary"
    :action="route('finance.external-invoices.send', $invoice)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('finance.external_invoice.send.submit')"
>
    <input type="hidden" name="kind" value="{{ $kind->value }}">
    <div class="space-y-4">
        <p class="text-sm text-base-content/70">{{ $isResend ? __('finance.external_invoice.send.resend_intro') : __('finance.external_invoice.send.intro') }}</p>

        <x-select-field name="dispatch_channel" :label="__('finance.external_invoice.send.channel')" required>
            <option value="{{ \App\Models\Document\DocumentDispatch::CHANNEL_EMAIL }}" @selected(old('dispatch_channel', 'email') === 'email')>{{ __('values.email') }}</option>
            @unless ($isResend)
                <option value="{{ \App\Models\Document\DocumentDispatch::CHANNEL_PEPPOL }}" @selected(old('dispatch_channel') === 'peppol')>{{ __('values.peppol') }}</option>
            @endunless
        </x-select-field>

        @foreach ($checks as $channel => $check)
            @if ($check['errors'] !== [] || $check['warnings'] !== [])
                <div class="rounded-box border border-base-300 p-3 text-sm">
                    <p class="font-medium">{{ __('finance.external_invoice.send.checks', ['channel' => __('values.' . $channel)]) }}</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($check['errors'] as $error)<li class="text-error">{{ $error }}</li>@endforeach
                        @foreach ($check['warnings'] as $warning)<li class="text-warning">{{ $warning }}</li>@endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        @if ($askProvider)
            <x-checkbox-field name="confirm_provider_not_sent" tone="warning" :label="__('finance.external_invoice.send.confirm_provider_not_sent')"
                              :hint="__('finance.external_invoice.send.confirm_provider_not_sent_hint')" />
        @endif
        @if ($askRedelivery)
            <x-checkbox-field name="confirm_redelivery" tone="warning" :label="__('finance.external_invoice.send.confirm_redelivery')"
                              :hint="__('finance.external_invoice.send.confirm_redelivery_hint')" />
        @endif

        <x-select-field name="template_id" :label="__('finance.external_invoice.send.template')">
            @foreach ($templates as $tpl)
                <option value="{{ $tpl->sqid }}" @selected($tpl->id === $defaultTemplateId)>{{ $tpl->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="to[]" :label="__('finance.external_invoice.send.to')" :value="old('to') ? implode(', ', (array) old('to')) : $defaultTo"
                       :hint="__('finance.external_invoice.send.to_hint')" />
        <x-input-field name="cc[]" :label="__('finance.external_invoice.send.cc')" />
        <x-input-field name="bcc[]" :label="__('finance.external_invoice.send.bcc')" />
        <x-textarea-field name="custom_text" :label="__('finance.external_invoice.send.custom_text')" rows="3" maxlength="5000" :value="old('custom_text')" />
    </div>
</x-modal>
