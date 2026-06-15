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
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="250"
                   class="input input-bordered w-full"
                   value="{{ old('title', $vulnerability?->title) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.identifier') }}</span>
            <input type="text" name="identifier" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('identifier', $vulnerability?->identifier) }}"
                   placeholder="{{ __('isms.hint.identifier') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.cvss') }}</span>
            <input type="number" name="cvss_score" min="0" max="10" step="0.1"
                   class="input input-bordered w-full"
                   value="{{ old('cvss_score', $vulnerability?->cvss_score) }}"
                   placeholder="{{ __('isms.hint.cvss') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.severity') }}</span>
            <select name="severity" class="select select-bordered w-full">
                <option value="">{{ __('isms.hint.severity_from_cvss') }}</option>
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', $vulnerability?->severity?->value) === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.affected_component') }}</span>
            <input type="text" name="affected_component" maxlength="250"
                   class="input input-bordered w-full"
                   value="{{ old('affected_component', $vulnerability?->affected_component) }}"
                   placeholder="{{ __('isms.hint.affected_component') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.product') }}</span>
            <select name="isms_software_product_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((string) old('isms_software_product_id', $vulnerability?->isms_software_product_id) === (string) $product->id)>{{ $product->name }} {{ $product->product_version }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.vulnerability_handling')" icon="schedule" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $vulnerability?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.due_on') }}</span>
            <input type="date" name="due_on"
                   class="input input-bordered w-full"
                   value="{{ old('due_on', $vulnerability?->due_on?->toDateString()) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.advisory_ref') }}</span>
            <input type="text" name="advisory_ref" maxlength="250"
                   class="input input-bordered w-full"
                   value="{{ old('advisory_ref', $vulnerability?->advisory_ref) }}">
        </label>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.vulnerability_exploitability_note') }}</p>
    </x-form-group>
</x-modal>
