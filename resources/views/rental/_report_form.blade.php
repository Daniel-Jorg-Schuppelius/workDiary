{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _report_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übergabe-/Rücknahmeformular je Leihobjekt (MVP-263/265); $mode = handover|return --}}
@php
    $isReturn = ($mode ?? 'handover') === 'return';
    $reportProfile = $caseAsset->asset?->rentalProfile;
    // Zubehör und Prüfpunkte kommen aus dem Verleihprofil (MVP-1604).
    $reportAccessories = array_values(array_filter((array) ($caseAsset->accessories ?: $reportProfile?->accessories)));
    $reportChecklist = array_values(array_filter((array) $reportProfile?->checklist_labels));
@endphp
<form method="POST"
      action="{{ $isReturn ? route('rental.return', $case) : route('rental.handover', $case) }}"
      enctype="multipart/form-data"
      class="mt-2 grid w-96 max-w-full gap-2 rounded-box border border-base-300 p-3 text-left">
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

    @if ($reportChecklist !== [])
        <fieldset class="grid gap-1">
            <legend class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('rental.report.checklist') }}</legend>
            @foreach ($reportChecklist as $point)
                <label class="label cursor-pointer justify-start gap-2 py-0.5">
                    <input type="checkbox" name="checklist[]" value="{{ $point }}" class="checkbox checkbox-sm">
                    <span class="label-text text-sm">{{ $point }}</span>
                </label>
            @endforeach
        </fieldset>
    @endif

    @if ($reportAccessories !== [])
        <fieldset class="grid gap-1">
            <legend class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('rental.report.accessories') }}</legend>
            @foreach ($reportAccessories as $index => $accessory)
                <div class="flex items-center gap-2">
                    <input type="hidden" name="accessory_items[{{ $index }}][label]" value="{{ $accessory }}">
                    <input type="hidden" name="accessory_items[{{ $index }}][present]" value="0">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                        <input type="checkbox" name="accessory_items[{{ $index }}][present]" value="1" class="checkbox checkbox-sm" checked>
                        <span class="truncate text-sm">{{ $accessory }}</span>
                    </label>
                    <input type="number" name="accessory_items[{{ $index }}][quantity]" value="1" min="1" step="1"
                           class="input input-xs input-bordered w-16" aria-label="{{ __('rental.report.quantity_of', ['label' => $accessory]) }}">
                </div>
            @endforeach
            <p class="text-xs text-muted">{{ __('rental.report.accessories_hint') }}</p>
        </fieldset>
    @endif

    <fieldset class="grid gap-1">
        <legend class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('rental.report.condition_items') }}</legend>
        @for ($row = 0; $row < 3; $row++)
            <div class="flex items-center gap-1">
                <input type="text" name="condition_items[{{ $row }}][label]" maxlength="255" class="input input-xs input-bordered min-w-0 flex-1"
                       placeholder="{{ __('rental.report.item_label') }}" aria-label="{{ __('rental.report.item_label_n', ['n' => $row + 1]) }}">
                <select name="condition_items[{{ $row }}][state]" class="select select-xs select-bordered w-28" aria-label="{{ __('rental.report.item_state_n', ['n' => $row + 1]) }}">
                    @foreach (\App\Enums\Rental\RentalConditionItemState::cases() as $state)
                        <option value="{{ $state->value }}">{{ $state->label() }}</option>
                    @endforeach
                </select>
            </div>
            <input type="text" name="condition_items[{{ $row }}][note]" maxlength="1000" class="input input-xs input-bordered"
                   placeholder="{{ __('rental.report.item_note') }}" aria-label="{{ __('rental.report.item_note_n', ['n' => $row + 1]) }}">
        @endfor
        <p class="text-xs text-muted">{{ __('rental.report.rows_hint') }}</p>
    </fieldset>

    @if ($isReturn)
        <x-textarea-field name="damages" :label="__('Schäden')" rows="2"></x-textarea-field>
        <x-textarea-field name="missing_parts" :label="__('Fehlteile')" rows="2"></x-textarea-field>
        <label class="label cursor-pointer justify-start gap-2">
            <input type="hidden" name="cleaning_required" value="0">
            <input type="checkbox" name="cleaning_required" value="1" class="checkbox checkbox-sm">
            <span class="label-text text-sm">{{ __('Reinigung erforderlich') }}</span>
        </label>
        <p class="text-xs text-muted">{{ __('rental.cleaning.form_hint') }}</p>
        <fieldset class="grid gap-1">
            <legend class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('rental.report.consumables') }}</legend>
            @for ($row = 0; $row < 2; $row++)
                <div class="flex items-center gap-1">
                    <input type="text" name="consumables[{{ $row }}][label]" maxlength="255" class="input input-xs input-bordered min-w-0 flex-1"
                           placeholder="{{ __('rental.report.consumable_label') }}" aria-label="{{ __('rental.report.consumable_label_n', ['n' => $row + 1]) }}">
                    <input type="number" name="consumables[{{ $row }}][quantity]" min="0" step="0.01" class="input input-xs input-bordered w-20"
                           placeholder="{{ __('Menge') }}" aria-label="{{ __('rental.report.consumable_quantity_n', ['n' => $row + 1]) }}">
                    <input type="text" name="consumables[{{ $row }}][unit]" maxlength="20" class="input input-xs input-bordered w-16"
                           placeholder="{{ __('Einheit') }}" aria-label="{{ __('rental.report.consumable_unit_n', ['n' => $row + 1]) }}">
                </div>
            @endfor
        </fieldset>
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

    <x-button type="submit">
        {{ $isReturn ? __('Rücknahme protokollieren') : __('Übergabe protokollieren') }}
    </x-button>
</form>
