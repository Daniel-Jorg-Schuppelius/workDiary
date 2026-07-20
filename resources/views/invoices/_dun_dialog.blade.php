{{-- Mahn-Dialog (MVP-163, Restpaket): Stufe vermerken + optionaler Mailversand --}}
<x-modal
    :title="__('Rechnung :nr mahnen', ['nr' => $invoice->number])"
    :eyebrow="$nextLevel <= 1 ? __('Zahlungserinnerung') : __(':level. Mahnung', ['level' => $nextLevel])"
    icon="notification_important"
    tone="warning"
    :action="route('invoices.dun', $invoice)"
    method="POST"
    :submit-label="__('Mahnstufe :level vermerken', ['level' => $nextLevel])"
>
    <div class="space-y-4">
        <div class="text-sm text-base-content/70">
            {{ __('Fällig seit :date · offen :total :currency · aktuelle Mahnstufe :level.', [
                'date' => optional($invoice->due_on)->fdate() ?? '—',
                'total' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->total, 2, withThousandsSeparator: true),
                'currency' => $invoice->currency->value,
                'level' => (int) $invoice->dunning_level,
            ]) }}
        </div>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="send_mail" value="1" @checked($defaultTo !== '') class="checkbox checkbox-sm">
            <span class="label-text">{{ $nextLevel <= 1 ? __('Zahlungserinnerung per E-Mail senden (Rechnung als PDF-Anhang)') : __('Mahnung per E-Mail senden (Rechnung als PDF-Anhang)') }}</span>
        </label>

        <x-input-field name="email" type="email" :label="__('Empfänger')" :value="$defaultTo"
                       placeholder="empfaenger@firma.de" />

        <x-textarea-field name="note" :label="__('Individueller Zusatztext (optional)')" rows="3" />
    </div>
</x-modal>
