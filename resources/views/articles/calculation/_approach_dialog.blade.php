{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _approach_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kostenansatz einer Leistung (MVP-1055). Variablen: $article, $approach, $wageGroups, $components --}}
@php
    $isEdit = $approach->exists;
    $kind = $approach->cost_kind instanceof \App\Enums\Article\CostKind ? $approach->cost_kind : \App\Enums\Article\CostKind::Labour;
    $action = $isEdit ? route('articles.cost-approaches.update', [$article, $approach]) : route('articles.cost-approaches.store', $article);
@endphp
<x-modal
    :title="$kind->label()"
    :eyebrow="$article->name"
    icon="calculate"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="md">
    <input type="hidden" name="cost_kind" value="{{ $kind->value }}">
    <x-form-group :legend="$kind->label()" icon="calculate" tone="primary" cols="2">
        @if ($kind === \App\Enums\Article\CostKind::Labour)
            <x-select-field name="wage_group_id" :label="__('article.calculation.field.wage_group')" span="2" :hint="__('article.calculation.hint.wage_group')">
                <option value="">{{ __('article.calculation.average_wage') }}</option>
                @foreach ($wageGroups as $group)
                    <option value="{{ $group->sqid }}" @selected(old('wage_group_id', $approach->wageGroup?->sqid) === $group->sqid)>{{ $group->name }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="minutes" type="number" :label="__('article.calculation.field.minutes')" required min="0" step="0.01" :value="old('minutes', $approach->minutes ?? '')" />
        @elseif ($kind === \App\Enums\Article\CostKind::Material)
            <x-select-field name="component_article_id" :label="__('article.calculation.field.component')" span="2" :hint="__('article.calculation.hint.component')">
                <option value="">{{ __('article.calculation.no_component') }}</option>
                @foreach ($components as $component)
                    <option value="{{ $component->sqid }}" @selected(old('component_article_id', $approach->componentArticle?->sqid) === $component->sqid)>{{ trim(($component->number ? $component->number . ' · ' : '') . $component->name) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="unit_cost_amount" type="number" :label="__('article.calculation.field.unit_cost')" min="0" step="0.0001" :value="old('unit_cost_amount', $approach->unit_cost_amount?->getAmount() ?? '')" />
        @else
            <x-input-field name="unit_cost_amount" type="number" :label="__('article.calculation.field.unit_cost')" required min="0" step="0.0001" :value="old('unit_cost_amount', $approach->unit_cost_amount?->getAmount() ?? '')" />
        @endif
        <x-input-field name="quantity" type="number" :label="__('article.calculation.field.quantity')" required min="0" step="0.0001" :value="old('quantity', $approach->quantity ?? '1')" :hint="__('article.calculation.hint.quantity', ['unit' => $article->base_unit])" />
        <x-input-field name="unit" :label="__('article.calculation.field.unit')" maxlength="32" :value="old('unit', $approach->unit ?? '')" />
        <x-input-field name="description" :label="__('article.calculation.field.description')" maxlength="255" span="2" :value="old('description', $approach->description ?? '')" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($approach->position ?? 0))" />
    </x-form-group>
    <x-validation-errors />
</x-modal>
