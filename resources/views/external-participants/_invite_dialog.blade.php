{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _invite_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Externen einladen"-Dialog (in #entry-modal geladen, Feature 033): Name,
  Rolle, Art, optionale E-Mail, erlaubte Aktionen (Checkboxen) und Gültigkeit
  in Tagen. Der vollständige Link wird nach dem Anlegen genau EINMAL als Flash
  angezeigt — gespeichert wird nur der SHA-256-Hash.
  Variablen: $type, $subjectId, $parties, $abilities, $defaultTtl
--}}

<x-modal
    :title="__('external.invite.title')"
    :eyebrow="__('external.invite.eyebrow')"
    icon="badge"
    tone="primary"
    size="md"
    :action="route('external.store', ['type' => $type, 'id' => $subjectId])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('external.invite.submit')">

    <x-form-group :legend="__('external.group.contact')" icon="person" tone="primary" cols="2">
        @if (($contacts ?? collect())->isNotEmpty())
            {{-- Wiederverwendbares Profil wählen (Rang 30): füllt leere Felder serverseitig. --}}
            <div class="md:col-span-2">
                <x-select-field name="external_contact" :label="__('external.contact.pick')">
                    <option value="">{{ __('external.contact.pick_none') }}</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->sqid }}" @selected(old('external_contact') === $contact->sqid)>{{ $contact->name }}@if ($contact->email) · {{ $contact->email }}@endif</option>
                    @endforeach
                </x-select-field>
            </div>
        @endif
        <x-input-field name="name" :label="__('external.field.name')" minlength="2" maxlength="160" :value="old('name')" />
        <x-input-field name="email" type="email" :label="__('external.field.email')" maxlength="190" :value="old('email')" />
        <x-input-field name="role" :label="__('external.field.role')" maxlength="120" :value="old('role')" placeholder="{{ __('external.hint.role') }}" />
        <x-select-field name="party" :label="__('external.field.party')">
            @foreach ($parties as $party)
                <option value="{{ $party->value }}" @selected(old('party') === $party->value)>{{ $party->label() }}</option>
            @endforeach
        </x-select-field>
        <label class="label cursor-pointer justify-start gap-2 md:col-span-2">
            <input type="hidden" name="save_contact" value="0">
            <input type="checkbox" name="save_contact" value="1" class="checkbox checkbox-sm" @checked(old('save_contact'))>
            <span class="label-text">{{ __('external.contact.save_as') }}</span>
        </label>
    </x-form-group>

    <x-form-group :legend="__('external.group.abilities')" icon="lock" tone="primary" cols="1">
        <p class="text-xs text-base-content/60">{{ __('external.hint.abilities') }}</p>
        <div class="flex flex-wrap gap-4">
            @foreach ($abilities as $ability)
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="abilities[]" value="{{ $ability->value }}" class="checkbox checkbox-sm">
                    <span class="label-text">{{ $ability->label() }}</span>
                </label>
            @endforeach
        </div>
    </x-form-group>

    <x-form-group :legend="__('external.group.validity')" icon="schedule" tone="primary" cols="1">
        <x-input-field name="ttl_days" type="number" :label="__('external.field.ttl_days')" required min="1" max="180" step="1" :value="old('ttl_days', $defaultTtl)" :hint="__('external.hint.ttl_days')" />
        <p class="text-xs text-base-content/60">{{ __('external.invite.once_hint') }}</p>
    </x-form-group>
</x-modal>
