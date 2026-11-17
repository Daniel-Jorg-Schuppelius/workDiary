{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _variant_field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variante des Materialverbrauchs (MVP-1690): nur Materialien, deren lagergeführter
     Artikel mehrere aktive Varianten hat (Material::variantChoices()). Der Binder
     (data-depends-strict) zeigt das Feld nur bei solcher Materialwahl und sendet es
     verborgen nicht; leer bucht die Standardvariante. Erwartet $materials, $variantChoices;
     optional $variantFieldId, $selectedMaterial und $selectedVariant (Sqids) sowie
     $keptVariant/$keptMaterial (gespeicherte Variante und Material der bearbeiteten Zeile).
     Ist die gespeicherte Variante nicht mehr wählbar (MVP-1711), steht sie unter den Optionen
     ihres Materials; hat das Material keine anderen, zeigt ein gesperrtes Feld sie nur an —
     ohne gesendeten Wert behält das Speichern sie, solange das Material bleibt. --}}
@php
    $variantChoices ??= [];
    $selectedMaterial = (string) old('material_id', $selectedMaterial ?? '');
    $selectedVariant = (string) old('article_variant_id', $selectedVariant ?? '');
    $variantLabel = static fn ($v): string => trim(($v->sku ? $v->sku . ' · ' : '') . ($v->name ?? '')) ?: (string) $v->sqid;
    $keptVariant ??= null;
    $keptMaterial ??= null;
    $keptChoices = $keptMaterial !== null ? ($variantChoices[$keptMaterial->id] ?? collect()) : collect();
    $kept = $keptVariant !== null && $keptMaterial !== null && (int) $keptVariant->article_id === (int) $keptMaterial->article_id
        && ! $keptChoices->contains('id', $keptVariant->id) ? $keptVariant : null;
    $variantFieldId ??= 'article_variant_id';
@endphp
@if ($variantChoices !== [])
    <x-select-field span="2" name="article_variant_id" :id="$variantFieldId"
                    :label="__('validation.attributes.article_variant_id')" :hint="__('material.variant.hint')"
                    data-depends-on="material_id" data-depends-strict="1" data-depends-autoselect="off">
        <option value="">{{ __('material.variant.default') }}</option>
        @foreach ($materials as $m)
            @foreach ($variantChoices[$m->id] ?? [] as $v)
                <option value="{{ $v->sqid }}" data-parent="{{ $m->sqid }}"
                        @selected($selectedVariant === $v->sqid && $selectedMaterial === $m->sqid)>{{ $variantLabel($v) }}</option>
            @endforeach
            @if ($kept !== null && $keptChoices->isNotEmpty() && (int) $m->id === (int) $keptMaterial->id)
                <option value="{{ $kept->sqid }}" data-parent="{{ $m->sqid }}"
                        @selected($selectedVariant === $kept->sqid && $selectedMaterial === $m->sqid)>{{ $variantLabel($kept) }} ({{ __('material.variant.not_selectable') }})</option>
            @endif
        @endforeach
    </x-select-field>
@endif
@if ($kept !== null && $keptChoices->isEmpty())
    <x-select-field span="2" name="article_variant_id" :id="$variantFieldId . '-kept'"
                    :label="__('validation.attributes.article_variant_id')" :hint="__('material.variant.kept_hint')"
                    disabled data-depends-on="material_id" data-depends-strict="1" data-depends-readonly="1">
        <option value="{{ $kept->sqid }}" data-parent="{{ $keptMaterial->sqid }}" selected>{{ $variantLabel($kept) }} ({{ __('material.variant.not_selectable') }})</option>
    </x-select-field>
@endif
