{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _follow_up_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachfassen protokollieren (Feature 112, MVP-601). Das Ergebnis landet als
  Kommunikationsnotiz in der Kundenakte; ohne Folgetermin gilt das Nachfassen
  als abgeschlossen.
--}}
<x-modal
    :title="__('quotes.follow_up.dialog_title', ['number' => $quote->number])"
    :eyebrow="__('quotes.follow_up.title')"
    icon="phone_forwarded"
    :action="route('quotes.follow-ups.store', $quote)"
    method="POST"
    :submit-label="__('quotes.follow_up.submit')"
>
    <p class="text-sm text-base-content/70">{{ __('quotes.follow_up.dialog_hint') }}</p>

    <x-textarea-field name="result" required rows="4"
                      :label="__('quotes.follow_up.result')"
                      :value="old('result', '')"
                      :hint="__('quotes.follow_up.result_hint')" />

    <x-input-field name="next_at" type="date"
                   :label="__('quotes.follow_up.next_at')"
                   :value="old('next_at', '')"
                   :hint="__('quotes.follow_up.next_at_hint')" />
</x-modal>
