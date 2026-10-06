{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _profile_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verleihprofil bearbeiten (MVP-259). Das Asset eines Profils wechselt nicht.
--}}
@php
    $accessories = old('accessories', $profile->accessories ?? []);
@endphp
<x-modal
    :title="__('Verleihprofil bearbeiten')"
    :eyebrow="$profile->asset?->name ?? __('Gerätepool')"
    icon="construction"
    :action="route('rental.profiles.update', $profile)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Profil speichern')"
>
    {{-- Die Validierung der Aktion verlangt das Asset auch beim Ändern. --}}
    <input type="hidden" name="asset_id" value="{{ $profile->asset?->sqid }}">

    {{-- Eigene ids: Die Seite darunter trägt dieselben Feldnamen im Anlegen-Formular. --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="group_code" id="profile-edit-group" maxlength="60"
                       :label="__('Gerätegruppe (Code)')"
                       :value="old('group_code', $profile->group_code)" />
        <x-select-field name="default_rate_card_id" id="profile-edit-rate-card" :label="__('Standard-Preisliste')">
            <option value="">{{ __('keine') }}</option>
            @foreach ($rateCards as $card)
                <option value="{{ $card->sqid }}" @selected($card->id === $profile->default_rate_card_id)>{{ $card->name }} (v{{ $card->version }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="buffer_before_hours" id="profile-edit-buffer-before" type="number" min="0" max="720" required
                       :label="__('Puffer vor Verleih (Stunden)')"
                       :value="old('buffer_before_hours', $profile->buffer_before_hours)" />
        <x-input-field name="buffer_after_hours" id="profile-edit-buffer-after" type="number" min="0" max="720" required
                       :label="__('Puffer nach Verleih (Stunden, z. B. Reinigung)')"
                       :value="old('buffer_after_hours', $profile->buffer_after_hours)" />
    </div>

    <div class="grid gap-1">
        <x-checkbox-field name="is_rentable" id="profile-edit-rentable" :toggle="false"
                          :label="__('leihfähig')"
                          :checked="(bool) old('is_rentable', $profile->is_rentable)" />
        <x-checkbox-field name="portal_bookable" id="profile-edit-portal" :toggle="false"
                          :label="__('im Kundenportal anfragbar')"
                          :checked="(bool) old('portal_bookable', $profile->portal_bookable)" />
        <x-checkbox-field name="requires_inspection" id="profile-edit-inspection" :toggle="false"
                          :label="__('Prüfpflicht: überfällige Prüfung blockiert Verleih')"
                          :checked="(bool) old('requires_inspection', $profile->requires_inspection)" />
    </div>

    <x-textarea-field name="accessories" id="profile-edit-accessories" rows="3"
                      :label="__('Zubehör (eine Position je Zeile)')">{{ is_array($accessories) ? implode("\n", $accessories) : $accessories }}</x-textarea-field>
    <x-textarea-field name="notes" id="profile-edit-notes" rows="3"
                      :label="__('Notizen')">{{ old('notes', $profile->notes) }}</x-textarea-field>
</x-modal>
