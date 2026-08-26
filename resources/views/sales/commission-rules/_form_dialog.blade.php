{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Provisionsregel-Dialog (Feature 146).
  Variablen: $rule (CommissionRule|null), $users, $leadSources, $productGroups
--}}
@php
    $isEdit = $rule !== null;
    $scopeValue = old('scope_value', $rule?->scope_value);
@endphp

<x-modal
    :title="$isEdit ? __('commission.action.edit_rule') : __('commission.action.create_rule')"
    :eyebrow="__('commission.page.rules')"
    icon="percent"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('commission-rules.update', $rule) : route('commission-rules.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('commission.action.save') : __('commission.action.create_rule')">

    <x-form-group :legend="__('commission.group.rule')" icon="percent" tone="primary" cols="2">
        <x-input-field name="name" :label="__('commission.field.name')" required minlength="2" maxlength="120" span="2" :value="old('name', $rule?->name)" />

        <x-select-field name="scope" :label="__('commission.field.scope')" required>
            @foreach (\App\Enums\Sales\CommissionScope::cases() as $scope)
                <option value="{{ $scope->value }}" @selected(old('scope', $rule?->scope?->value ?? 'all') === $scope->value)>{{ $scope->label() }}</option>
            @endforeach
        </x-select-field>

        {{-- Ein Feld für beide Bereichsarten: der gewählte Geltungsbereich
             entscheidet, welche Gruppe gilt (Servervalidierung prüft das). --}}
        <x-select-field name="scope_value" :label="__('commission.field.scope_value')" :hint="__('commission.hint.scope_value')">
            <option value="">—</option>
            <optgroup label="{{ __('commission.scope.lead_source') }}">
                @foreach ($leadSources as $source)
                    <option value="{{ $source->value }}" @selected($scopeValue === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </optgroup>
            <optgroup label="{{ __('commission.scope.product_group') }}">
                @foreach ($productGroups as $group)
                    <option value="{{ $group }}" @selected($scopeValue === $group)>{{ $group }}</option>
                @endforeach
            </optgroup>
        </x-select-field>

        <x-select-field name="user_id" :label="__('commission.field.user')" :hint="__('commission.hint.user')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('user_id', $rule?->user?->sqid) === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="rate_percent" type="number" step="0.01" min="0" max="100" required
                       :label="__('commission.field.rate_percent')" :value="old('rate_percent', $rule?->rate_percent?->getNumericValue())" />
        <x-input-field name="priority" type="number" min="0" max="65535" required
                       :label="__('commission.field.priority')" :hint="__('commission.hint.priority')"
                       :value="old('priority', $rule?->priority ?? 100)" />
    </x-form-group>

    <x-form-group :legend="__('commission.group.validity')" icon="event" tone="info" cols="2">
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="valid_from" to-name="valid_to" type="date"
                      :from="old('valid_from', $rule?->valid_from?->format('Y-m-d'))"
                      :to="old('valid_to', $rule?->valid_to?->format('Y-m-d'))"
                      :from-label="__('commission.field.valid_from')"
                      :to-label="__('commission.field.valid_to')" />
        <x-checkbox-field name="is_active" :label="__('commission.field.is_active')" :checked="(bool) old('is_active', $rule?->is_active ?? true)" />
        <x-textarea-field name="note" :label="__('commission.field.note')" rows="2" maxlength="255" span="2" :value="old('note', $rule?->note)" />
    </x-form-group>
</x-modal>
