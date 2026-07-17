{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _register_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-414: Kasse anlegen. Variablen: $register (immer null — Kassen sind nach Anlage fix) --}}
<x-modal
    :title="__('Kasse anlegen')"
    :eyebrow="__('Kassenbuch')"
    icon="point_of_sale"
    tone="primary"
    :action="route('cash-registers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Kasse')" icon="point_of_sale" tone="primary">
        <x-input-field name="name" :label="__('Bezeichnung')" required maxlength="120" :value="old('name', '')" />
        <x-input-field name="opening_balance" type="number" :label="__('Anfangsbestand (EUR)')" required min="0" step="0.01" :value="old('opening_balance', '0.00')" />
        <x-input-field name="opened_on" type="date" :label="__('Eröffnet am')" required :value="old('opened_on', now()->format('Y-m-d'))" />
        <p class="text-xs text-base-content/60">{{ __('Hinweis: Buchungen sind unveränderlich (GoBD) — Korrekturen nur als Storno-Gegenbuchung.') }}</p>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
