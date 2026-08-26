{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : article-picker.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Optionaler Artikelbezug einer Belegposition (Feature 140). Erwartet:
     $articles (Collection<Article>), $selected (int|null article_id). Auswahl
     belegt description/unit/unit_price vor (Alpine articleItemPicker). --}}
@props(['articles', 'selected' => null, 'span' => 2])
@php
    $map = $articles->mapWithKeys(fn(\App\Models\Article $a): array => [$a->sqid => [
        'description' => $a->name,
        'unit' => $a->base_unit,
        'unit_price' => $a->default_sale_price?->getAmount(),
    ]])->all();
    $selectedSqid = (string) old('article_id', \App\Support\Sqid::encode(\App\Models\Article::class, $selected));
@endphp
<x-select-field name="article_id" :label="__('Artikel')" :span="$span"
                :hint="__('Optional — belegt Beschreibung, Einheit und Einzelpreis vor.')"
                x-data="articleItemPicker"
                x-on:change="applyArticle()"
                :data-articles="json_encode($map, JSON_THROW_ON_ERROR)">
    <option value="">{{ __('— ohne Artikelbezug —') }}</option>
    @foreach ($articles as $article)
        <option value="{{ $article->sqid }}" @selected($selectedSqid === $article->sqid)>{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
    @endforeach
</x-select-field>
