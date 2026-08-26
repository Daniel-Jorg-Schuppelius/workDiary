{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anlage-Dialog Betroffenenanfrage (in #entry-modal geladen). Variablen: $types --}}
<x-modal
    :title="__('Neue Betroffenenanfrage')"
    :eyebrow="__('Betroffenenanfragen')"
    icon="contact_mail"
    tone="primary"
    :action="route('dataprotection.requests.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Anfrage')" icon="contact_mail" tone="primary" cols="2">
        <x-input-field name="type" :label="__('Art der Anfrage')" required>
            <select id="type" name="type" class="select select-bordered w-full" required>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </select>
        </x-input-field>
        <x-input-field name="channel" :label="__('Eingangskanal (optional)')" :value="old('channel')" placeholder="email, post, telefon …" />
        <x-input-field name="subject" :label="__('Betroffene Person (Identität)')" span="2">
            <textarea id="subject" name="subject" rows="2" class="textarea textarea-bordered w-full" required>{{ old('subject') }}</textarea>
            <p class="text-xs text-muted mt-1">{{ __('Wird verschlüsselt gespeichert (Crypto-Shredding nach Aufbewahrung).') }}</p>
        </x-input-field>
        <x-input-field name="content" :label="__('Anliegen / Sachverhalt')" span="2">
            <textarea id="content" name="content" rows="5" class="textarea textarea-bordered w-full" required>{{ old('content') }}</textarea>
        </x-input-field>
    </x-form-group>
</x-modal>
