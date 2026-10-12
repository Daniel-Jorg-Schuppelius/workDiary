{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : contact-persons-editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'persons' => [],   // gespeicherte Ansprechpartner (Anrede, Namensteile, E-Mail, Telefon, primär)
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
        $contactPersons = [['email' => '', 'phone' => '', 'primary' => true]];
    }
@endphp

<div class="rounded-box border border-base-300 p-3" data-contact-persons>
    <div class="mb-2 flex items-center justify-between">
        <h3 class="font-medium text-sm">{{ __('Ansprechpartner') }}</h3>
        <x-icon-btn icon="person_add" type="button" data-contact-add show-label>{{ __('Person') }}</x-icon-btn>
    </div>
    <div class="space-y-2" data-contact-rows>
        @foreach ($contactPersons as $i => $cp)
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center border-b border-base-300 pb-2 last:border-b-0" data-contact-row>
                <select aria-label="{{ __('stammdaten.contact.salutation') }}" name="contact_persons[{{ $i }}][salutation]"
                        class="select select-bordered sm:col-span-2">
                    <option value="">{{ __('stammdaten.contact.salutation') }}</option>
                    @foreach (\App\Enums\Stammdaten\ContactSalutation::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($cp['salutation'] ?? '') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <input aria-label="{{ __('Titel') }}" type="text" name="contact_persons[{{ $i }}][title]" value="{{ $cp['title'] ?? '' }}"
                       placeholder="{{ __('Titel') }}" maxlength="64"
                       class="input input-bordered sm:col-span-2">
                <input aria-label="{{ __('Vorname') }}" type="text" name="contact_persons[{{ $i }}][first_name]" value="{{ $cp['first_name'] ?? '' }}"
                       placeholder="{{ __('Vorname') }}" maxlength="128"
                       class="input input-bordered sm:col-span-2">
                <input aria-label="{{ __('Weitere Vornamen') }}" type="text" name="contact_persons[{{ $i }}][middle_names]" value="{{ $cp['middle_names'] ?? '' }}"
                       placeholder="{{ __('Weitere Vornamen') }}" maxlength="128"
                       class="input input-bordered sm:col-span-3">
                <input aria-label="{{ __('Nachname') }}" type="text" name="contact_persons[{{ $i }}][last_name]" value="{{ $cp['last_name'] ?? '' }}"
                       placeholder="{{ __('Nachname') }}" maxlength="128"
                       class="input input-bordered sm:col-span-3">
                <input aria-label="{{ __('E-Mail') }}" type="email" name="contact_persons[{{ $i }}][email]" value="{{ $cp['email'] ?? '' }}"
                       placeholder="{{ __('E-Mail') }}" maxlength="255"
                       class="input input-bordered sm:col-span-5">
                <input aria-label="{{ __('Telefon') }}" type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $cp['phone'] ?? '' }}"
                       placeholder="{{ __('Telefon') }}" maxlength="64"
                       class="input input-bordered sm:col-span-4">
                <label class="label cursor-pointer gap-1 text-xs sm:col-span-2">
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
