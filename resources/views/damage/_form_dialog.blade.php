{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schadensfall anlegen/bearbeiten (MVP-919). Erwartet: $case (?DamageCase), $subject (Träger), $users --}}
@php
    $isEdit = $case !== null;
    $kind = old('kind', $case?->kind->value ?? $subject?->damageDefaultKind()->value);
    $currency = old('currency', $case?->currency->value ?? 'EUR');
@endphp
<x-modal
    :title="$isEdit ? __('damage.dialog.edit') : __('damage.dialog.create')"
    :eyebrow="$subject?->damageSubjectLabel() ?? __('damage.title')"
    icon="car_crash"
    tone="primary"
    :action="$isEdit ? route('damage-cases.update', $case) : route('damage-cases.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('damage.action.save') : __('damage.action.open')"
>
    @unless ($isEdit)
        <input type="hidden" name="subject_type" value="{{ $subject->getMorphClass() }}">
        <input type="hidden" name="subject" value="{{ $subject->sqid }}">
    @endunless

    <x-form-group :legend="__('damage.section.case')" icon="car_crash" tone="primary" cols="2">
        <x-input-field name="title" :label="__('damage.field.title')" :value="old('title', $case?->title)" required span="2" />
        <x-select-field name="kind" :label="__('damage.field.kind')" required>
            @foreach (\App\Enums\Damage\DamageKind::cases() as $k)
                <option value="{{ $k->value }}" @selected($kind === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('damage.field.responsible_user_id')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((int) old('responsible_user_id', $case?->responsible_user_id) === $user->id || old('responsible_user_id') === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="occurred_at" type="datetime-local" :label="__('damage.field.occurred_at')" :value="old('occurred_at', $case?->occurred_at?->orgTz()->format('Y-m-d\TH:i'))" />
        <x-textarea-field name="description" :label="__('damage.field.description')" rows="3" span="2">{{ old('description', $case?->description) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('damage.section.insurance')" icon="shield" tone="primary" cols="2">
        <x-input-field name="insurer_name" :label="__('damage.field.insurer_name')" :value="old('insurer_name', $case?->insurer_name)" span="2" />
        <x-input-field name="policy_number" :label="__('damage.field.policy_number')" :value="old('policy_number', $case?->policy_number)" />
        <x-input-field name="claim_number" :label="__('damage.field.claim_number')" :value="old('claim_number', $case?->claim_number)" />
    </x-form-group>

    <x-form-group :legend="__('damage.section.amounts')" icon="payments" tone="primary" cols="3">
        <x-input-field name="estimated_amount" type="number" step="0.01" min="0" :label="__('damage.field.estimated_amount')" :value="old('estimated_amount', $case?->estimated_amount)" />
        <x-input-field name="deductible_amount" type="number" step="0.01" min="0" :label="__('damage.field.deductible_amount')" :value="old('deductible_amount', $case?->deductible_amount)" />
        <x-select-field name="currency" :label="__('damage.field.currency')" required>
            <x-currency-options :selected="$currency" />
        </x-select-field>
    </x-form-group>
</x-modal>
