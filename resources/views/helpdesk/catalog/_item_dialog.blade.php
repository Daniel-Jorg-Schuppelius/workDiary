{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $item, $isEdit, $preselectedOffering, $offerings, $formTemplates,
     $slaContracts, $procedureTemplates, $orgUsers, $roles, $customers --}}
@php
    /** @var \App\Models\RequestItem $item */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.catalog.items.update', $item) : route('servicedesk.catalog.items.store');

    // Bestehende Genehmigungskette → Repeater-Zeilen (user als Sqid, kein Roh-JSON).
    $stepItems = collect((array) ($item->approval_chain ?? []))->map(function ($step): array {
        $rule = (array) ($step['approver'] ?? $step);
        $type = (string) ($rule['type'] ?? 'role');

        return [
            'type' => $type,
            'user' => $type === 'user' ? \App\Support\Sqid::encode(\App\Models\User::class, (int) ($rule['value'] ?? 0)) : '',
            'role' => $type === 'role' ? (string) ($rule['value'] ?? '') : '',
        ];
    })->values()->all();
    $stepTemplate = ['type' => 'role', 'user' => '', 'role' => ''];

    $visibility = (array) ($item->visibility ?? []);
    $visibilityRoles = (array) ($visibility['roles'] ?? []);
    $visibilityCustomerSqids = array_map(
        fn($id): string => \App\Support\Sqid::encode(\App\Models\Customer::class, (int) $id),
        (array) ($visibility['customer_ids'] ?? []),
    );
    $procedureTemplateId = (int) (($item->fulfillment_config ?? [])['procedure_template_id'] ?? 0);
@endphp

<x-modal
    :title="$isEdit ? __('Katalogeintrag bearbeiten') : __('Neuer Katalogeintrag')"
    :eyebrow="__('Servicekatalog')"
    icon="storefront"
    tone="primary"
    size="lg"
    :action="$action"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Katalogeintrag')" icon="storefront" tone="primary" cols="2">
        <x-select-field name="service_offering_id" :label="__('Serviceangebot')" required span="2">
            <option value="">—</option>
            @foreach ($offerings as $offering)
                <option value="{{ $offering->sqid }}"
                        @selected(old('service_offering_id', (int) ($preselectedOffering ?? 0) === (int) $offering->id ? $offering->sqid : null) === $offering->sqid)>
                    {{ $offering->businessService?->name }} — {{ $offering->name }}
                </option>
            @endforeach
        </x-select-field>

        <x-input-field name="name" :label="__('Name')" required maxlength="150" span="2" :value="old('name', $item->name)" />
        <x-input-field name="description" :label="__('Beschreibung')" maxlength="500" span="2" :value="old('description', $item->description)" />

        <x-select-field name="form_template_id" :label="__('Bestellformular (032-Vorlage)')">
            <option value="">{{ __('— Kein Formular —') }}</option>
            @foreach ($formTemplates as $template)
                <option value="{{ $template->sqid }}" @selected(old('form_template_id', (int) $item->form_template_id === (int) $template->id ? $template->sqid : null) === $template->sqid)>{{ $template->name }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="sla_contract_id" :label="__('SLA-Vertrag')">
            <option value="">{{ __('— Kein SLA —') }}</option>
            @foreach ($slaContracts as $contract)
                <option value="{{ $contract->sqid }}" @selected(old('sla_contract_id', (int) $item->sla_contract_id === (int) $contract->id ? $contract->sqid : null) === $contract->sqid)>{{ $contract->label }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Genehmigungskette')" icon="approval" tone="info">
        <div x-data="repeater"
             data-prefix="approval_steps"
             data-items="{{ json_encode($stepItems) }}"
             data-template="{{ json_encode($stepTemplate) }}"
             class="space-y-2 sm:col-span-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-6 items-end">
                        <div class="fieldset md:col-span-2">
                            <label class="fieldset-label">{{ __('Schritt-Typ') }}</label>
                            <select :name="fieldName(i, 'type')" x-model="it.type"
                                    class="select select-sm select-bordered w-full">
                                <option value="role">{{ __('Rolle') }}</option>
                                <option value="user">{{ __('Benutzer') }}</option>
                            </select>
                        </div>
                        <div class="fieldset md:col-span-3" x-show="it.type === 'user'">
                            <label class="fieldset-label">{{ __('Genehmiger (Benutzer)') }}</label>
                            <select :name="fieldName(i, 'user')" x-model="it.user"
                                    class="select select-sm select-bordered w-full">
                                <option value="">—</option>
                                @foreach ($orgUsers as $orgUser)
                                    <option value="{{ $orgUser->sqid }}">{{ $orgUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset md:col-span-3" x-show="it.type === 'role'">
                            <label class="fieldset-label">{{ __('Genehmiger (Rolle)') }}</label>
                            <select :name="fieldName(i, 'role')" x-model="it.role"
                                    class="select select-sm select-bordered w-full">
                                <option value="">—</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <x-icon-btn icon="close" tone="error" type="button"
                                        :label="__('Schritt entfernen')" @click="remove(i)" />
                        </div>
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('Genehmigungsschritt hinzufügen') }}
            </x-icon-btn>
            <p class="text-xs text-base-content/60">{{ __('Ohne Schritte wird die Bestellung direkt erfüllt. Selbstfreigabe ist immer gesperrt.') }}</p>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Fulfillment')" icon="task_alt" tone="primary" cols="2">
        {{-- Fulfillment-Umschaltung via Alpine.data("reveal") (components.js) — CSP-Build-konform. --}}
        <div class="contents" x-data="reveal(@js(old('fulfillment', $item->fulfillment ?? 'task')))">
            <x-select-field name="fulfillment" :label="__('Erfüllung durch')" required x-model="value">
                <option value="task">{{ __('Aufgabe') }}</option>
                <option value="project">{{ __('Projekt') }}</option>
                <option value="diary">{{ __('Auftragsbuch-Eintrag') }}</option>
                <option value="procedure">{{ __('Verfahrenslauf') }}</option>
            </x-select-field>

            <div class="fieldset" x-show="is('procedure')" x-cloak>
                <label class="fieldset-label" for="procedure_template_id">{{ __('Verfahrensvorlage') }}</label>
                <select id="procedure_template_id" name="procedure_template_id" class="select select-bordered w-full">
                    <option value="">—</option>
                    @foreach ($procedureTemplates as $template)
                        <option value="{{ $template->sqid }}" @selected($procedureTemplateId === (int) $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
                @error('procedure_template_id')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Sichtbarkeit')" icon="visibility" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="visibility_roles">{{ __('Auf Rollen beschränken') }}</label>
            <select id="visibility_roles" name="visibility_roles[]" multiple size="5" class="select select-bordered w-full">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(in_array($role->value, $visibilityRoles, true))>{{ $role->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-base-content/60">{{ __('Leer = alle internen Benutzer.') }}</p>
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="visibility_customer_ids">{{ __('Im Portal auf Kunden beschränken') }}</label>
            <select id="visibility_customer_ids" name="visibility_customer_ids[]" multiple size="5" class="select select-bordered w-full">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected(in_array($customer->sqid, $visibilityCustomerSqids, true))>{{ $customer->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-base-content/60">{{ __('Leer = alle Portal-Kunden (sofern Portal aktiv).') }}</p>
        </div>

        <x-checkbox-field name="visibility_portal" :label="__('Im Kundenportal bestellbar')"
                          :checked="(bool) old('visibility_portal', (bool) ($visibility['portal'] ?? false))" />
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="(bool) old('active', $item->active)"
                          :hint="__('Inaktive Einträge sind nicht bestellbar; laufende Requests bleiben unberührt.')" />
    </x-form-group>
</x-modal>
