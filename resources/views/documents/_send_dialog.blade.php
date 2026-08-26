{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Filename     : _send_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Generischer Beleg-Versanddialog (Feature 128, MVP-692): Vorlage, Multi-Recipient (To/CC/BCC), Freitext, Queue-Dispatch --}}
<x-modal
    :title="$title"
    :eyebrow="$eyebrow"
    icon="mail"
    tone="primary"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Senden')"
>
    <div class="space-y-4">

        {{-- Vorlagen-Auswahl (leer = eingebauter Fallback der Belegart) --}}
        <x-select-field name="template_id" :label="__('Vorlage')">
            @if ($templates->isEmpty())
                <option value="">{{ __('Standardtext (keine Vorlage hinterlegt)') }}</option>
            @endif
            @foreach ($templates as $tpl)
                <option value="{{ $tpl->sqid }}" @selected($tpl->id === $defaultTemplateId)>
                    {{ $tpl->name }}@if ($tpl->is_default) ({{ __('Standard') }})@endif
                </option>
            @endforeach
        </x-select-field>

        {{-- An (To) --}}
        <x-input-field name="to[]" :label="__('An (To, mehrere mit Komma)')" required :value="old('to.0', $defaultTo)"
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
                          :value="old('custom_text', '')"
                          placeholder="{{ __('Wird als Platzhalter custom_text im Template eingesetzt.') }}" />

        <details class="text-xs">
            <summary class="cursor-pointer text-muted">{{ __('Verfügbare Variablen') }}</summary>
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
