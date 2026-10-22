{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_rule_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abrechnungs-Regel anlegen oder bearbeiten; Artikel aus dem Artikelkatalog (MVP-1026) --}}
@php($editing = $rule->exists)
<x-modal
    :title="$editing ? __('Abrechnungs-Regel bearbeiten') : __('Neue Abrechnungs-Regel')"
    :eyebrow="$project->name"
    icon="receipt_long"
    tone="primary"
    :action="$editing ? route('projects.billing-rules.update', [$project, $rule]) : route('projects.billing-rules.store', $project)"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Regel speichern')"
>
    <x-form-group :legend="__('Zuordnung')" icon="rule" tone="primary" cols="2">
        <x-select-field name="applies_to_kind" :label="__('Tätigkeitsart')">
            <option value="">{{ __('Alle (Fallback)') }}</option>
            @foreach ($kinds as $value => $label)
                <option value="{{ $value }}" @selected((string) old('applies_to_kind', $rule->applies_to_kind) === (string) $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="item_type" :label="__('Item-Typ')">
            @foreach ($itemTypes as $value => $label)
                <option value="{{ $value }}" @selected((string) old('item_type', $rule->item_type) === (string) $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-article-catalog-select :articles="$articles" span="2" :selected="$selectedArticle ?? null"
                                  :hint="$articles === [] ? __('article.catalog.empty_hint') : __('article.catalog.service_hint')" />
    </x-form-group>

    <x-form-group :legend="__('Preis & Priorität')" icon="payments" tone="info" cols="2">
        <x-input-field name="unit_name" :label="__('Einheit')" type="text" placeholder="Stunde" :value="$rule->unit_name" />
        <x-input-field name="vat_rate"
                       :label="__('VAT %')"
                       type="number"
                       step="0.01"
                       min="0"
                       max="100"
                       placeholder="19"
                       :value="$rule->vat_rate?->getNumericValue()" />
        <x-input-field name="net_unit_price" :label="__('Preis (netto)')" type="number" step="0.0001" min="0" :value="$rule->net_unit_price?->getAmount()" />
        <x-input-field name="priority" :label="__('Priorität')" type="number" :value="$rule->priority ?? 0" step="1" min="0" max="1000" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
