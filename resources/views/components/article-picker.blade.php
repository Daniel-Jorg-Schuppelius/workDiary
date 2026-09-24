{{--
  Created on   : Sat May 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : article-picker.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Optionaler Artikel-/Variantenbezug einer Belegposition (Feature 140, Feature 160).
     Erwartet: $articles (Collection<Article>, optional mit geladenen `variants`),
     $selected (int|null article_id), $selectedVariant (int|null), $currency (Belegwährung).
     Auswahl belegt description/unit/unit_price vor (Alpine articleItemPicker);
     ein Preis in fremder Währung wird nie still übernommen. --}}
@props(['articles', 'selected' => null, 'selectedVariant' => null, 'currency' => null, 'span' => 2])
@php
    $map = $articles->mapWithKeys(fn(\App\Models\Article\Article $a): array => [$a->sqid => [
        'description' => $a->name,
        'unit' => $a->base_unit,
        'unit_price' => $a->default_sale_price?->getAmount(),
        'currency' => $a->currency?->value ?? $a->currency,
        'variants' => $a->relationLoaded('variants') ? $a->variants->map(fn(\App\Models\Article\ArticleVariant $v): array => [
            'id' => $v->sqid,
            'label' => trim(($v->sku ? $v->sku . ' · ' : '') . ($v->name ?? '')) ?: $v->sqid,
            'name' => $v->name,
            'unit_price' => $v->effectiveSalePrice()?->getAmount(),
            'currency' => $v->currency?->value ?? $a->currency?->value ?? $a->currency,
        ])->values()->all() : [],
    ]])->all();
    $selectedSqid = (string) old('article_id', \App\Support\Sqid::encode(\App\Models\Article\Article::class, $selected));
    $selectedVariantSqid = (string) old('article_variant_id', \App\Support\Sqid::encode(\App\Models\Article\ArticleVariant::class, $selectedVariant));
    $groups = $articles->groupBy(fn(\App\Models\Article\Article $a): string => $a->type?->label() ?? '');
@endphp
<div class="contents" x-data="articleItemPicker" x-on:change="onChange($event)"
     data-articles="{{ json_encode($map, JSON_THROW_ON_ERROR) }}" data-currency="{{ $currency ?? '' }}">
    <x-select-field name="article_id" :label="__('Artikel')" :span="$span"
                    :hint="__('Optional — belegt Beschreibung, Einheit und Einzelpreis vor.')">
        <option value="">{{ __('— ohne Artikelbezug —') }}</option>
        @foreach ($groups as $groupLabel => $groupArticles)
            <optgroup label="{{ $groupLabel !== '' ? $groupLabel : __('Artikel') }}">
                @foreach ($groupArticles as $article)
                    <option value="{{ $article->sqid }}" @selected($selectedSqid === $article->sqid)>{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </x-select-field>
    <x-select-field name="article_variant_id" :label="__('invoicing.free.field.variant')" :span="$span"
                    :hint="__('invoicing.free.hint.variant')">
        <option value="">{{ __('invoicing.free.option.no_variant') }}</option>
        @foreach ($articles as $article)
            @if ($article->relationLoaded('variants') && $article->sqid === $selectedSqid)
                @foreach ($article->variants as $variant)
                    <option value="{{ $variant->sqid }}" @selected($selectedVariantSqid === $variant->sqid)>{{ $variant->sku ? $variant->sku . ' · ' : '' }}{{ $variant->name }}</option>
                @endforeach
            @endif
        @endforeach
    </x-select-field>
    <p class="hidden md:col-span-2 text-xs text-warning" data-currency-note>{{ __('invoicing.free.hint.currency_mismatch') }}</p>
</div>
