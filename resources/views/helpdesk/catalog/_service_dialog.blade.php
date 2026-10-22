{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _service_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $service, $isEdit, $otherServices, $dependsOn (Sqids) --}}
@php
    /** @var \App\Models\ServiceTicket\BusinessService $service */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.catalog.services.update', $service) : route('servicedesk.catalog.services.store');
@endphp

<x-modal
    :title="$isEdit ? __('Fachdienst bearbeiten') : __('Neuer Fachdienst')"
    :eyebrow="__('Servicekatalog')"
    icon="category"
    tone="primary"
    size="md"
    :action="$action"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Fachdienst')" icon="category" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="150" span="2" :value="old('name', $service->name)" />
        <x-input-field name="description" :label="__('Beschreibung')" maxlength="500" span="2" :value="old('description', $service->description)" />
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="(bool) old('active', $service->active)"
                          :hint="__('Inaktive Fachdienste erscheinen nicht im bestellbaren Katalog.')" />
    </x-form-group>

    @if ($otherServices->isNotEmpty())
        {{-- Servicelandkarte (MVP-1256): fällt ein gewählter Dienst aus, ist dieser mitbetroffen. --}}
        <x-form-group :legend="__('helpdesk.map.dependsOn')" icon="account_tree" tone="ghost">
            @php $selected = old('depends_on', $dependsOn); @endphp
            <x-select-field name="depends_on[]" id="service-depends-on" :label="__('helpdesk.map.dependsOn')" multiple size="6" class="h-auto py-1" error="depends_on"
                            :hint="__('helpdesk.map.dependsOnHint')">
                @foreach ($otherServices as $other)
                    <option value="{{ $other->sqid }}" @selected(in_array($other->sqid, (array) $selected, true))>{{ $other->name }}</option>
                @endforeach
            </x-select-field>
        </x-form-group>
    @endif
</x-modal>
