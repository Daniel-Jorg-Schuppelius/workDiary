{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _report_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übergabe-/Rücknahmeformular je Leihobjekt (MVP-263/265); $mode = handover|return --}}
@php($isReturn = ($mode ?? 'handover') === 'return')
<form method="POST"
      action="{{ $isReturn ? route('rental.return', $case) : route('rental.handover', $case) }}"
      enctype="multipart/form-data"
      class="mt-2 grid w-80 gap-2 rounded-box border border-base-300 p-3 text-left">
    @csrf
    <input type="hidden" name="asset_id" value="{{ $caseAsset->asset->sqid }}">

    <x-select-field name="condition" :label="__('Zustand')" required>
        @foreach (\App\Enums\Rental\RentalCondition::cases() as $condition)
            <option value="{{ $condition->value }}" @selected($condition === \App\Enums\Rental\RentalCondition::Good)>{{ $condition->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="meter_value" type="number" step="0.0001" min="0" :label="__('Zählerstand')" />
    <x-input-field name="operating_hours" type="number" step="0.01" min="0" :label="__('Betriebsstunden')" />
    <x-input-field name="fuel_level" :label="__('Füllstand')" />

    @if ($isReturn)
        <x-textarea-field name="damages" :label="__('Schäden')" rows="2"></x-textarea-field>
        <x-textarea-field name="missing_parts" :label="__('Fehlteile')" rows="2"></x-textarea-field>
        <label class="label cursor-pointer justify-start gap-2">
            <input type="hidden" name="cleaning_required" value="0">
            <input type="checkbox" name="cleaning_required" value="1" class="checkbox checkbox-sm">
            <span class="label-text text-sm">{{ __('Reinigung erforderlich') }}</span>
        </label>
        <x-select-field name="follow_up" :label="__('Folgeentscheidung')" required>
            @foreach (\App\Enums\Rental\RentalReturnFollowUp::cases() as $followUp)
                <option value="{{ $followUp->value }}">{{ $followUp->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="follow_up_note" :label="__('Begründung (Pflicht bei Sperre/Reparatur/Reklamation)')" rows="2"></x-textarea-field>
    @endif

    <x-input-field name="signature_name" :label="__('Unterschrift (Name)')" />
    <x-input-field name="photos[]" type="file" :label="__('Fotos')" multiple accept="image/*" />
    <x-textarea-field name="note" :label="__('Notiz')" rows="2"></x-textarea-field>

    <button type="submit" class="btn btn-sm btn-primary">
        {{ $isReturn ? __('Rücknahme protokollieren') : __('Übergabe protokollieren') }}
    </button>
</form>
