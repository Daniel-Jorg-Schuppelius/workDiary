{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _request_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anfrage je Vermietungsart — erwartet: $profile, $mode (RentalMode::LongTerm|Holiday), $direct --}}
@php($holiday = $mode === \App\Enums\Rental\RentalMode::Holiday)
@php($prefix = 'unit-' . $profile->id . '-' . $mode->value)
<form method="POST" action="{{ route('customer.housing.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
    @csrf
    <input type="hidden" name="profile_id" value="{{ $profile->sqid }}">
    <input type="hidden" name="mode" value="{{ $mode->value }}">
    <x-date-range from-name="starts_on" to-name="ends_on" :from-id="$prefix . '-from'" :to-id="$prefix . '-to'"
        :from="old('profile_id') === $profile->sqid && old('mode') === $mode->value ? old('starts_on') : ($holiday ? request('arrival') : null)"
        :to="old('profile_id') === $profile->sqid && old('mode') === $mode->value ? old('ends_on') : ($holiday ? request('departure') : null)"
        :from-label="$holiday ? __('housing.arrival') : __('housing.starts_on')"
        :to-label="$holiday ? __('housing.departure') : __('housing.ends_on')"
        :from-required="true" :to-required="$holiday" layout="split" form-control class="sm:col-span-2" />
    @if ($holiday)
        <x-input-field name="guests" :id="$prefix . '-guests'" type="number" min="1" :max="$profile->max_guests" required class="input-sm" :label="__('housing.guests')" :value="old('guests', 1)" />
    @endif
    <x-textarea-field name="note" :id="$prefix . '-note'" :label="__('housing.message')" maxlength="1000" rows="2" class="textarea-sm">{{ old('profile_id') === $profile->sqid && old('mode') === $mode->value ? old('note') : '' }}</x-textarea-field>
    <div class="sm:col-span-2 flex flex-wrap justify-end gap-2">
        @if ($holiday && $direct)
            <x-button type="submit" size="sm" tone="secondary" name="booking" value="direct">{{ __('rental.portal.direct_book') }}</x-button>
        @endif
        <x-button type="submit" size="sm" icon="send">{{ __('Anfrage senden') }}</x-button>
    </div>
</form>
