{{-- Versand-Dialog: Multi-Recipient (To/CC/BCC), Template-Auswahl, Freitext, Queue-Dispatch --}}
<x-modal
    :title="__('Rechnung :nr per E-Mail senden', ['nr' => $invoice->number])"
    :eyebrow="$invoice->documentLabel()"
    icon="mail"
    tone="primary"
    :action="route('invoices.send', $invoice)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Senden')"
>
    <div class="space-y-4">

        <div class="text-sm text-base-content/70">
            {{ __('invoice-import.mail_hint') }}
        </div>

        <x-select-field name="delivery_format" :label="__('invoice-import.delivery_format')" required>
            @foreach (\App\Enums\Invoicing\InvoiceDeliveryFormat::cases() as $format)
                <option value="{{ $format->value }}" @selected(old('delivery_format', $invoice->delivery_format->value) === $format->value)>{{ $format->label() }}</option>
            @endforeach
        </x-select-field>

        {{-- Template-Auswahl --}}
        <x-select-field name="template_id" :label="__('Vorlage')" required>
            @foreach ($templates as $tpl)
                <option value="{{ $tpl->sqid }}" @selected($tpl->id === $defaultTemplateId)>
                    {{ $tpl->name }}@if ($tpl->is_default) ({{ __('Standard') }})@endif
                </option>
            @endforeach
        </x-select-field>

        {{-- An (To) --}}
        <x-input-field name="to[]" :label="__('An (To, mehrere mit Komma)')" required :value="$defaultTo"
                       data-multi-email
                       placeholder="empfaenger@firma.de"
                       :hint="__('Mehrere Empfänger durch Komma trennen.')" />

        {{-- CC --}}
        <x-input-field name="cc[]" :label="__('CC (optional)')" data-multi-email
                       placeholder="cc@firma.de" />

        {{-- BCC --}}
        <x-input-field name="bcc[]" :label="__('BCC (optional)')" data-multi-email
                       placeholder="bcc@firma.de" />

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="bcc_sender" value="1" checked class="checkbox checkbox-sm">
            <span class="label-text">{{ __('Kopie an Absender (:from)', ['from' => config('mail.from.address')]) }}</span>
        </label>

        {{-- Freitext --}}
        <x-textarea-field name="custom_text" :label="__('Individueller Begleittext (optional)')" rows="3"
                          :value="old('custom_text', $aiText ?? '')"
                          placeholder="{{ __('Wird als Platzhalter custom_text im Template eingesetzt.') }}" />

        {{-- KI-Begleittext-Entwurf (Feature 084, MVP-405-Rest): lädt den Dialog mit
             Vorschlag im Feld neu — Entwurf, nie Auto-Versand. --}}
        @if ($aiUsable ?? false)
            <div class="flex items-center gap-2">
                <x-icon-btn icon="auto_awesome" size="xs" tone="ghost"
                            data-entry-modal-trigger
                            :href="route('invoices.send.form', [$invoice, 'ki' => 1])"
                            show-label>{{ __('ai.covering.suggest_mail') }}</x-icon-btn>
                @if (! empty($aiText))
                    <span class="text-xs text-base-content/60">{{ __('ai.covering.draft_hint') }}</span>
                @elseif (! empty($aiError))
                    <span class="text-xs text-warning">{{ $aiError }}</span>
                @endif
            </div>
        @endif

        <details class="text-xs">
            <summary class="cursor-pointer text-base-content/60">{{ __('Verfügbare Variablen') }}</summary>
            <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                @foreach ($variables as $key => $label)
                    <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
                @endforeach
            </ul>
        </details>

    </div>

    {{-- Kleines Inline-Script: splittet komma-getrennte Eingaben in mehrere [] Felder --}}
    <script @cspNonce>
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (! form.matches('form[data-entry-form]')) return;
        form.querySelectorAll('input[data-multi-email]').forEach(function (inp) {
            const raw = (inp.value || '').trim();
            if (! raw) { inp.remove(); return; }
            const parts = raw.split(/[,;\s]+/).filter(Boolean);
            if (parts.length <= 1) { inp.value = parts[0] || ''; return; }
            const name = inp.getAttribute('name');
            inp.value = parts[0];
            for (let i = 1; i < parts.length; i++) {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = name;
                h.value = parts[i];
                inp.parentNode.appendChild(h);
            }
        });
    }, true);
    </script>
</x-modal>
