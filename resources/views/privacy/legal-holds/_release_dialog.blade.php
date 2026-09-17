{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _release_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Legal Hold aufheben (MVP-801). Variablen: $hold --}}
<x-modal
    :title="__('Legal Hold aufheben')"
    :eyebrow="$hold->reference ?? __('Datenschutz')"
    icon="lock_open"
    tone="warning"
    :action="route('dataprotection.legal-holds.release', $hold)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Legal Hold aufheben')">

    <x-form-group :legend="__('Aufhebung')" :description="__('Danach greifen Löschkonzept und Aufräumläufe wieder. Die Begründung bleibt als Nachweis erhalten.')" icon="lock_open" tone="warning">
        <x-textarea-field name="release_reason" :label="__('Grund der Aufhebung')" rows="3" required />
    </x-form-group>
</x-modal>
