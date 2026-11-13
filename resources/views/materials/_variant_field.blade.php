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
     (data-depends-strict) zeigt das Feld nur bei solcher Materialwahl; leer bucht die
     Standardvariante. Erwartet $materials, $variantChoices; optional $variantFieldId,
     $selectedMaterial und $selectedVariant (Sqids). --}}
@php
    $variantChoices ??= [];
    $selectedMaterial = (string) old('material_id', $selectedMaterial ?? '');
    $selectedVariant = (string) old('article_variant_id', $selectedVariant ?? '');
@endphp
@if ($variantChoices !== [])
    <x-select-field span="2" name="article_variant_id" :id="$variantFieldId ?? 'article_variant_id'"
                    :label="__('validation.attributes.article_variant_id')" :hint="__('material.variant.hint')"
                    data-depends-on="material_id" data-depends-strict="1" data-depends-autoselect="off">
        <option value="">{{ __('material.variant.default') }}</option>
        @foreach ($materials as $m)
            @foreach ($variantChoices[$m->id] ?? [] as $v)
                <option value="{{ $v->sqid }}" data-parent="{{ $m->sqid }}"
                        @selected($selectedVariant === $v->sqid && $selectedMaterial === $m->sqid)>{{ trim(($v->sku ? $v->sku . ' · ' : '') . ($v->name ?? '')) ?: $v->sqid }}</option>
            @endforeach
        @endforeach
    </x-select-field>
@endif
