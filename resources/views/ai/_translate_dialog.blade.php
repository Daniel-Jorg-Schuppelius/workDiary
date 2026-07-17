{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _translate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Zielsprache für die Positions-Übersetzung (Feature 025, MVP-409).
     Die Übersetzung erscheint als Vorschlag — übernommen wird erst per Klick. --}}
<x-modal
    :title="__('ai.suggestion.translate')"
    icon="translate"
    tone="info"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('ai.suggestion.translate_submit')"
>
    <div class="fieldset">
        <label class="fieldset-label" for="ai-target-lang">{{ __('ai.suggestion.target_language') }}</label>
        <select id="ai-target-lang" name="target_language" class="select select-bordered w-full">
            @foreach (['en' => 'English', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español', 'de' => 'Deutsch'] as $code => $label)
                <option value="{{ $code }}" @selected(old('target_language', 'en') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs text-base-content/60">{{ __('ai.suggestion.translate_help') }}</p>
    </div>
</x-modal>
