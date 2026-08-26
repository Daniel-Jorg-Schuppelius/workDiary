{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dun_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
                'total' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->total?->toFloat() ?? 0.0), 2, withThousandsSeparator: true),
                'currency' => $invoice->currency->value,
                'level' => (int) $invoice->dunning_level,
            ]) }}
        </div>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="send_mail" value="1" @checked($defaultTo !== '') class="checkbox checkbox-sm">
            <span class="label-text">{{ __('Per E-Mail senden (Mahnschreiben + Original-Rechnung als PDF-Anhang)') }}</span>
        </label>

        <x-input-field name="email" type="email" :label="__('Empfänger')" :value="$defaultTo"
                       placeholder="empfaenger@firma.de" />

        {{-- MVP-650: optionale Mahngebühr + Zahlungsziel fürs Mahnschreiben.
             Vorbelegung aus der Org-Konfiguration (MVP-691); Eingaben bleiben Override. --}}
        <div class="grid grid-cols-2 gap-2">
            <x-input-field name="fee" type="number" step="0.01" min="0" :label="__('Mahngebühr (optional)')"
                           :value="old('fee', $defaultFee ?? null)" placeholder="0,00" />
            <x-input-field name="pay_until" type="date" :label="__('Zahlbar bis (optional)')"
                           :value="old('pay_until', $defaultPayUntil ?? now()->addDays(14)->toDateString())" />
        </div>

        @if (($interest ?? null) !== null)
            {{-- Verzugszins-Ausweis (MVP-691): reine Anzeige, keine Buchung. --}}
            <div class="text-xs text-base-content/70">
                {{ __('finance.dunning.interest_hint_dialog', [
                    'amount' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($interest['amount'], 2, withThousandsSeparator: true),
                    'currency' => $invoice->currency->value,
                    'rate' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($interest['rate'], 2),
                    'days' => $interest['days'],
                ]) }}
            </div>
        @endif

        <x-textarea-field name="note" :label="__('Individueller Zusatztext (optional)')" rows="3"
                          :value="old('note', $aiText ?? '')" />

        {{-- KI-Mahntext-Entwurf (Feature 084, MVP-405-Rest): lädt den Dialog mit
             Vorschlag im Feld neu — Entwurf, nie Auto-Versand. --}}
        @if ($aiUsable ?? false)
            <div class="flex items-center gap-2">
                <x-icon-btn icon="auto_awesome" size="xs" tone="ghost"
                            data-entry-modal-trigger
                            :href="route('invoices.dun.form', [$invoice, 'ki' => 1])"
                            show-label>{{ __('ai.covering.suggest_dunning') }}</x-icon-btn>
                @if (! empty($aiText))
                    <span class="text-xs text-muted">{{ __('ai.covering.draft_hint') }}</span>
                @elseif (! empty($aiError))
                    <span class="text-xs text-warning">{{ $aiError }}</span>
                @endif
            </div>
        @endif
    </div>
</x-modal>
