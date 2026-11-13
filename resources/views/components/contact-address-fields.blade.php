{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : contact-address-fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Adress-Satellit einer Partei im Formular (MVP-869): Straße/PLZ/Ort
  (address_*), Vorbelegung aus der Primäradresse; der Controller schreibt
  über WritesContactDetails nach contact_addresses. Mit kind="shipping" die
  abweichende Lieferanschrift (shipping_*, MVP-1689) samt Adresszusatz und Land.
--}}
@props([
    'subject' => null,       // Partei mit HasContactAndBankDetails, null beim Anlegen
    'withCountry' => false,
    'kind' => 'billing',     // billing | shipping
])

@if ($kind === \App\Models\Contacts\ContactAddress::KIND_SHIPPING)
    @php
        $shipping = $subject?->shippingAddress();
    @endphp
    <x-input-field name="shipping_street" span="2" :label="__('Straße / Hausnr.')" maxlength="255" :value="old('shipping_street', $shipping?->street)" />
    <x-input-field name="shipping_supplement" span="2" :label="__('stammdaten.shipping.supplement')" maxlength="255" :value="old('shipping_supplement', $shipping?->supplement)" />
    <x-input-field name="shipping_zip" :label="__('PLZ')" maxlength="32" :value="old('shipping_zip', $shipping?->zip)" />
    <x-input-field name="shipping_city" :label="__('Ort')" maxlength="128" :value="old('shipping_city', $shipping?->city)" />
    <x-input-field name="shipping_country" :label="__('Land (ISO 2)')" maxlength="2" class="uppercase" :value="old('shipping_country', $shipping?->country_code)" />
@else
    @php
        $address = $subject?->primaryAddress();
    @endphp
    <x-input-field name="address_street" span="2" :label="__('Straße / Hausnr.')" maxlength="255" :value="old('address_street', $address?->street)" />
    <x-input-field name="address_zip" :label="__('PLZ')" maxlength="32" :value="old('address_zip', $address?->zip)" />
    <x-input-field name="address_city" :label="__('Ort')" maxlength="128" :value="old('address_city', $address?->city)" />
    @if ($withCountry)
        <x-input-field name="country" :label="__('Land (ISO 2)')" maxlength="2" class="uppercase" :value="old('country', $address?->country_code)" />
    @endif
@endif
