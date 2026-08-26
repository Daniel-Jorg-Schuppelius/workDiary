{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog ISMS-Schwachstelle (in #entry-modal).
  Severity wird aus CVSS abgeleitet, wenn keine explizite Auswahl getroffen
  wird. Die Ausnutzbarkeits-Entscheidung läuft über den eigenen Dialog.
  Variablen: $vulnerability (IsmsVulnerability|null), $products, $owners
--}}
@php
    $isEdit = $vulnerability !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_vulnerability') : __('isms.action.create_vulnerability')"
    :eyebrow="__('isms.title.vulnerabilities')"
    icon="bug_report"
    tone="warning"
    size="lg"
    :action="$isEdit ? route('isms.vulnerabilities.update', $vulnerability) : route('isms.vulnerabilities.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_vulnerability')">

    <x-form-group :legend="__('isms.group.vulnerability')" icon="bug_report" tone="warning" cols="2">
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="250" span="2" :value="old('title', $vulnerability?->title)" />
        <x-input-field name="identifier" :label="__('isms.field.identifier')" maxlength="64" :value="old('identifier', $vulnerability?->identifier)" placeholder="{{ __('isms.hint.identifier') }}" />
        <x-input-field name="cvss_score" type="number" :label="__('isms.field.cvss')" min="0" max="10" step="0.1" :value="old('cvss_score', $vulnerability?->cvss_score)" placeholder="{{ __('isms.hint.cvss') }}" />
        <x-select-field name="severity" :label="__('isms.field.severity')">
                <option value="">{{ __('isms.hint.severity_from_cvss') }}</option>
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', $vulnerability?->severity?->value) === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="affected_component" :label="__('isms.field.affected_component')" maxlength="250" :value="old('affected_component', $vulnerability?->affected_component)" placeholder="{{ __('isms.hint.affected_component') }}" />
        <x-select-field name="isms_software_product_id" :label="__('isms.field.product')">
                <option value="">—</option>
                @foreach ($products as $product)
                    <option value="{{ $product->sqid }}" @selected((string) old('isms_software_product_id', \App\Support\Sqid::encode(\App\Models\Isms\IsmsSoftwareProduct::class, $vulnerability?->isms_software_product_id)) === $product->sqid)>{{ $product->name }} {{ $product->product_version }}</option>
                @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('isms.group.vulnerability_handling')" icon="schedule" tone="primary" cols="2">
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->sqid }}" @selected((string) old('owner_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $vulnerability?->owner_user_id)) === $owner->sqid)>{{ $owner->name }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="due_on" type="date" :label="__('isms.field.due_on')" :value="old('due_on', $vulnerability?->due_on?->toDateString())" />
        <x-input-field name="advisory_ref" :label="__('isms.field.advisory_ref')" maxlength="250" span="2" :value="old('advisory_ref', $vulnerability?->advisory_ref)" />
        <p class="text-xs text-muted sm:col-span-2">{{ __('isms.hint.vulnerability_exploitability_note') }}</p>
    </x-form-group>
</x-modal>
