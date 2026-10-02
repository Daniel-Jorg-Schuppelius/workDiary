{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _line_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufmaßzeile (MVP-1058). Variablen: $takeoff, $line, $formula, $boqItems, $articles --}}
@php
    $isEdit = $line->exists;
    $values = array_values((array) old('values', $line->values ?? []));
    $labels = $formula->valueLabels();
    $slots = $formula->isList() ? 6 : count($labels);
@endphp
<x-modal
    :title="$formula->label()"
    :eyebrow="$takeoff->title"
    icon="straighten"
    tone="primary"
    :action="$isEdit ? route('takeoffs.lines.update', [$takeoff, $line]) : route('takeoffs.lines.store', $takeoff)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="md">
    <input type="hidden" name="formula" value="{{ $formula->value }}">
    <x-form-group :legend="__('takeoff.values')" icon="functions" tone="primary" cols="2">
        <p class="md:col-span-2 text-xs text-muted">{{ __('takeoff.hint.' . $formula->name) }}</p>
        @for ($i = 0; $i < $slots; $i++)
            @if ($formula->isExpression())
                <x-input-field name="values[{{ $i }}]" id="takeoff-value-{{ $i }}" :label="$labels[0]" required span="2" maxlength="500" :value="$values[$i] ?? ''" placeholder="4,25*2,60-0,88*2,01" />
            @else
                <x-input-field name="values[{{ $i }}]" id="takeoff-value-{{ $i }}" inputmode="decimal"
                               :label="$formula->isList() ? $labels[0] . ' ' . ($i + 1) : $labels[$i]"
                               :required="$i < $formula->requiredValues()"
                               :value="isset($values[$i]) ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $values[$i], 3, trimTrailingZeros: true) : ($formula === \App\Enums\Takeoff\TakeoffFormula::Circle && $i === 1 ? '400' : '')" />
            @endif
        @endfor
        <x-input-field name="factor" id="takeoff-factor" inputmode="decimal" :label="__('takeoff.col.factor')" :value="old('factor', \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($line->factor ?? 1), 3, trimTrailingZeros: true))" :hint="__('takeoff.hint.factor')" />
        <x-input-field name="label" id="takeoff-label" :label="__('takeoff.col.label')" maxlength="120" :value="old('label', $line->label ?? '')" :hint="__('takeoff.hint.label')" />
    </x-form-group>
    <x-form-group :legend="__('takeoff.col.target')" icon="sell" tone="ghost" cols="2">
        @if ($boqItems->isNotEmpty())
            <x-select-field name="boq_item_id" :label="__('takeoff.field.boq_item')" span="2">
                <option value="">{{ __('takeoff.no_target') }}</option>
                @foreach ($boqItems as $item)
                    <option value="{{ $item->sqid }}" @selected(old('boq_item_id', $line->boqItem?->sqid) === $item->sqid)>{{ $item->reference_no }} — {{ \Illuminate\Support\Str::limit((string) $item->short_text, 60) }} ({{ $item->unit }})</option>
                @endforeach
            </x-select-field>
        @else
            <x-select-field name="article_id" :label="__('takeoff.field.article')" span="2">
                <option value="">{{ __('takeoff.no_target') }}</option>
                @foreach ($articles as $article)
                    <option value="{{ $article->sqid }}" @selected(old('article_id', $line->article?->sqid) === $article->sqid)>{{ trim(($article->number ? $article->number . ' · ' : '') . $article->name) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="description" :label="__('takeoff.field.description')" maxlength="255" :value="old('description', $line->description ?? '')" />
        @endif
        <x-input-field name="unit" :label="__('takeoff.field.unit')" maxlength="16" :value="old('unit', $line->unit ?? '')" :hint="__('takeoff.hint.unit')" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($line->position ?? ''))" />
    </x-form-group>
    <x-validation-errors />
</x-modal>
