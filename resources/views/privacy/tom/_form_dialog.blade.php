{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anlage-Dialog technische/organisatorische Maßnahme (in #entry-modal geladen). Variablen: $categories, $statuses, $protectionLevels --}}
<x-modal
    :title="__('Neue Maßnahme')"
    :eyebrow="__('TOM-Katalog')"
    icon="shield"
    tone="primary"
    :action="route('dataprotection.tom.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Maßnahme')" icon="shield" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Bezeichnung')" :value="old('name')" required />
        <x-input-field name="category" :label="__('Maßnahmenbereich')">
            <select id="category" name="category" class="select select-bordered w-full">
                @foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="implementation_status" :label="__('privacy.tom.implementation_status')">
            <select id="implementation_status" name="implementation_status" class="select select-bordered w-full">
                @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(old('implementation_status', \App\Enums\Privacy\ImplementationStatus::Planned->value) === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="protection_level" :label="__('privacy.tom.protection_level')">
            <select id="protection_level" name="protection_level" class="select select-bordered w-full">
                <option value="">{{ __('privacy.tom.protection_level_none') }}</option>
                @foreach ($protectionLevels as $p)<option value="{{ $p->value }}" @selected(old('protection_level') === $p->value)>{{ $p->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="description" :label="__('Beschreibung')" span="2">
            <textarea id="description" name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
        </x-input-field>
        <x-input-field name="addressed_risks" :label="__('Adressierte Risiken')" span="2">
            <textarea id="addressed_risks" name="addressed_risks" rows="2" class="textarea textarea-bordered w-full">{{ old('addressed_risks') }}</textarea>
        </x-input-field>
        <x-input-field name="evidence" :label="__('Nachweise (Richtlinien, Protokolle, Zertifikate …)')" span="2">
            <textarea id="evidence" name="evidence" rows="2" class="textarea textarea-bordered w-full">{{ old('evidence') }}</textarea>
        </x-input-field>
    </x-form-group>
</x-modal>
