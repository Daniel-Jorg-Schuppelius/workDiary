{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_rule_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for a new Project Billing Rule (Lexoffice) --}}
<x-modal
    :title="__('Neue Abrechnungs-Regel')"
    :eyebrow="$project->name"
    icon="receipt_long"
    tone="primary"
    :action="route('projects.billing-rules.store', $project)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel speichern')"
>
    <x-form-group :legend="__('Zuordnung')" icon="rule" tone="primary" cols="2">
        <x-select-field name="applies_to_kind" :label="__('Tätigkeitsart')">
            <option value="">{{ __('Alle (Fallback)') }}</option>
            @foreach ($kinds as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="item_type" :label="__('Item-Typ')">
            @foreach ($itemTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-select-field>
        <div class="fieldset md:col-span-2">
            <label for="lexoffice_article_id" class="fieldset-label">{{ __('Lexoffice-Artikel') }}</label>
            <select id="lexoffice_article_id" name="lexoffice_article_id" class="select select-bordered w-full">
                <option value="">{{ __('— ohne Artikel —') }}</option>
                @foreach ($articles as $art)
                    <option value="{{ $art->external_id }}">
                        {{ $art->name }}@if ($art->net_unit_price !== null) — {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($art->net_unit_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} €@endif
                    </option>
                @endforeach
            </select>
            @if ($articles->isEmpty())
                <p class="text-xs text-muted mt-1">
                    {{ __('Noch keine Artikel synchronisiert. Führe :cmd aus.', ['cmd' => 'php artisan lexoffice:sync-articles']) }}
                </p>
            @endif
        </div>
    </x-form-group>

    <x-form-group :legend="__('Preis & Priorität')" icon="payments" tone="info" cols="2">
        <x-input-field name="unit_name" :label="__('Einheit')" type="text" placeholder="Stunde" />
        <x-input-field name="vat_rate"
                       :label="__('VAT %')"
                       type="number"
                       step="0.01"
                       min="0"
                       max="100"
                       placeholder="19" />
        <x-input-field name="net_unit_price" :label="__('Preis (netto)')" type="number" step="0.0001" min="0" />
        <x-input-field name="priority" :label="__('Priorität')" type="number" value="0" step="1" min="0" max="1000" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
