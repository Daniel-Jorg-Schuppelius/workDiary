{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _payload_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Strukturierte Art.-30-Felder (als payload-JSON gespeichert). $payload optional vorbelegen. --}}
@php $p = $payload ?? []; @endphp
<x-form-group :legend="__('Verzeichnis-Angaben (Art. 30)')" icon="fact_check" tone="ghost" cols="2">
    <x-input-field name="data_categories" :label="__('Datenkategorien')">
        <textarea id="data_categories" name="data_categories" rows="2" class="textarea textarea-bordered w-full">{{ old('data_categories', $p['data_categories'] ?? '') }}</textarea>
    </x-input-field>
    <x-input-field name="legal_basis" :label="__('Rechtsgrundlagen')">
        <textarea id="legal_basis" name="legal_basis" rows="2" class="textarea textarea-bordered w-full">{{ old('legal_basis', $p['legal_basis'] ?? '') }}</textarea>
    </x-input-field>
    <x-input-field name="recipients" :label="__('Empfänger')">
        <textarea id="recipients" name="recipients" rows="2" class="textarea textarea-bordered w-full">{{ old('recipients', $p['recipients'] ?? '') }}</textarea>
    </x-input-field>
    <x-input-field name="transfers" :label="__('Drittlandtransfers')">
        <textarea id="transfers" name="transfers" rows="2" class="textarea textarea-bordered w-full">{{ old('transfers', $p['transfers'] ?? '') }}</textarea>
    </x-input-field>
    <x-input-field name="retention" :label="__('Aufbewahrung / Löschung')">
        <textarea id="retention" name="retention" rows="2" class="textarea textarea-bordered w-full">{{ old('retention', $p['retention'] ?? '') }}</textarea>
    </x-input-field>
    <x-input-field name="tom" :label="__('TOM (techn./org. Maßnahmen)')">
        <textarea id="tom" name="tom" rows="2" class="textarea textarea-bordered w-full">{{ old('tom', $p['tom'] ?? '') }}</textarea>
    </x-input-field>
</x-form-group>
