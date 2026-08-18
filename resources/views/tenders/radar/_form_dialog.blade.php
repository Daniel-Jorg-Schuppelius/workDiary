{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Tenders\TenderFilterProfile $profile */
    $action = $profile->exists
        ? route('tender-radar.profiles.update', $profile)
        : route('tender-radar.profiles.store');
    // Listen werden als Freitext bearbeitet — so, wie Vergabestellen ihre
    // CPV-Listen veröffentlichen.
    $asText = static fn (?array $values): string => implode(', ', $values ?? []);
@endphp

<x-modal
    :title="$profile->exists ? __('Suchprofil bearbeiten') : __('Suchprofil anlegen')"
    :eyebrow="__('Bekanntmachungs-Radar')"
    icon="radar"
    tone="primary"
    :action="$action"
    :method="$profile->exists ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-input-field name="name" :label="__('Bezeichnung')" :value="old('name', $profile->name)" required
                   placeholder="{{ __('z. B. Hochbau in NRW') }}" />

    <x-checkbox-field name="active" :label="__('Aktiv')" :checked="old('active', $profile->active ?? true)"
                      :hint="__('Nur aktive Profile werden beim täglichen Abruf abgeglichen.')" />

    <x-input-field name="cpv_codes" :label="__('CPV-Codes')" :value="old('cpv_codes', $asText($profile->cpv_codes))"
                   placeholder="45, 71300000"
                   :hint="__('Was beschafft wird. Präfixe genügen: 45 trifft alle Bauleistungen.')" />

    <x-input-field name="nuts_codes" :label="__('Regionen (NUTS)')" :value="old('nuts_codes', $asText($profile->nuts_codes))"
                   placeholder="DEA, DE2"
                   :hint="__('Wo geliefert oder gebaut wird. DEA trifft ganz Nordrhein-Westfalen.')" />

    <x-input-field name="keywords" :label="__('Stichwörter')" :value="old('keywords', $asText($profile->keywords))"
                   placeholder="{{ __('Rohbau, Sanierung') }}"
                   :hint="__('Ein Treffer genügt. Gesucht wird in Titel, Beschreibung und Vergabestelle.')" />

    <x-input-field name="excluded_keywords" :label="__('Ausschlusswörter')" :value="old('excluded_keywords', $asText($profile->excluded_keywords))"
                   placeholder="{{ __('Abbruch, Winterdienst') }}"
                   :hint="__('Wiegen schwerer als Stichwörter: Ein Treffer hier verwirft die Bekanntmachung.')" />

    {{-- Zeilenweise, weil ein Auftraggeber „Stadt Musterhausen" heißt — an
         Leerzeichen zu trennen zerrisse jeden zweiten Namen. --}}
    <x-textarea-field name="excluded_buyers" :label="__('Ausgeschlossene Auftraggeber')" rows="3"
                      :hint="__('Eine Zeile je Auftraggeber. Verglichen wird nur das Auftraggeberfeld, nicht der Fließtext — sonst verwürfe der Name auch Bekanntmachungen, die ihn nur erwähnen.')">{{ old('excluded_buyers', implode("\n", $profile->excluded_buyers ?? [])) }}</x-textarea-field>

    <x-form-group :cols="2">
        <x-input-field type="number" step="0.01" min="0" name="min_value" :label="__('Mindestwert (EUR)')"
                       :value="old('min_value', $profile->min_value)" />
        <x-input-field type="number" step="0.01" min="0" name="max_value" :label="__('Höchstwert (EUR)')"
                       :value="old('max_value', $profile->max_value)" />
    </x-form-group>

    <p class="text-xs text-base-content/60">
        {{ __('Bekanntmachungen ohne Wertangabe werden von den Wertgrenzen nicht ausgeschlossen — sonst entginge, was seinen Wert nicht nennt.') }}
    </p>

    @if ($profile->exists)
        <x-slot:footerExtra>
            <x-action-form :action="route('tender-radar.profiles.destroy', $profile)" method="DELETE"
                           :confirm="__('Suchprofil löschen? Bereits gefundene Bekanntmachungen bleiben erhalten.')"
                           :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
