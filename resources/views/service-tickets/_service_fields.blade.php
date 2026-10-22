{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _service_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Betroffener Service und Serviceangebot (MVP-1257). Erwartet: $services (mit offerings), $selectedService, $selectedOffering (Sqids). --}}
<x-select-field name="business_service_id" :label="__('helpdesk.service.label')">
    <option value="">{{ __('helpdesk.service.none') }}</option>
    @foreach ($services as $service)
        <option value="{{ $service->sqid }}" @selected((string) $selectedService === $service->sqid)>{{ $service->name }}</option>
    @endforeach
</x-select-field>
<x-select-field name="service_offering_id" :label="__('helpdesk.service.offering')" data-depends-on="business_service_id">
    <option value="">{{ __('helpdesk.service.noOffering') }}</option>
    @foreach ($services as $service)
        @foreach ($service->offerings as $offering)
            <option value="{{ $offering->sqid }}" data-parent="{{ $service->sqid }}" @selected((string) $selectedOffering === $offering->sqid)>{{ $offering->name }}</option>
        @endforeach
    @endforeach
</x-select-field>
