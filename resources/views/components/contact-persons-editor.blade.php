{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : contact-persons-editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'persons' => [],   // gespeicherte Ansprechpartner (Name, E-Mail, Telefon, primär)
])

{{--
    <x-contact-persons-editor> — Zeileneditor der Ansprechpartner im Kunden-
    und Lieferantenformular (Felder `contact_persons[i][…]`). Hinzufügen und
    Entfernen übernimmt `contact-persons.js`; die Anzeige ist `<x-contact-persons>`.
--}}
@php
    // Mindestens eine leere Zeile als Eingabehilfe.
    $contactPersons = old('contact_persons', $persons);
    if (empty($contactPersons)) {
        $contactPersons = [['name' => '', 'email' => '', 'phone' => '', 'primary' => true]];
    }
@endphp

<div class="rounded-box border border-base-300 p-3" data-contact-persons>
    <div class="mb-2 flex items-center justify-between">
        <h3 class="font-medium text-sm">{{ __('Ansprechpartner') }}</h3>
        <x-icon-btn icon="person_add" type="button" data-contact-add show-label>{{ __('Person') }}</x-icon-btn>
    </div>
    <div class="space-y-2" data-contact-rows>
        @foreach ($contactPersons as $i => $cp)
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center" data-contact-row>
                <input aria-label="{{ __('Name') }}" type="text" name="contact_persons[{{ $i }}][name]" value="{{ $cp['name'] ?? '' }}"
                       placeholder="{{ __('Name') }}" maxlength="200"
                       class="input input-bordered sm:col-span-3">
                <input aria-label="{{ __('E-Mail') }}" type="email" name="contact_persons[{{ $i }}][email]" value="{{ $cp['email'] ?? '' }}"
                       placeholder="{{ __('E-Mail') }}" maxlength="255"
                       class="input input-bordered sm:col-span-4">
                <input aria-label="{{ __('Telefon') }}" type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $cp['phone'] ?? '' }}"
                       placeholder="{{ __('Telefon') }}" maxlength="64"
                       class="input input-bordered sm:col-span-3">
                <label class="label cursor-pointer gap-1 text-xs sm:col-span-1">
                    <input type="hidden" name="contact_persons[{{ $i }}][primary]" value="0">
                    <input type="checkbox" name="contact_persons[{{ $i }}][primary]" value="1"
                           class="checkbox checkbox-xs"
                           @checked($cp['primary'] ?? false)>
                    <span>{{ __('Primär') }}</span>
                </label>
                <x-icon-btn icon="close" type="button" data-contact-remove class="sm:col-span-1" :label="__('Entfernen')" />
            </div>
        @endforeach
    </div>
    @error('contact_persons')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
</div>
