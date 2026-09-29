{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : article-catalog-select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Artikelauswahl aus dem Artikelkatalog (Phase 125, MVP-1025): eine Gruppe je
  Quelle (Artikelstamm, Lexoffice, …), Wert = Formularschlüssel `quelle:sqid`.
  Erwartet :articles (list<CatalogArticle>) und :selected (Formularschlüssel).
--}}
@props([
    'name' => 'article',
    'articles' => [],
    'selected' => null,
    'label' => null,
    'hint' => null,
    'span' => null,
    'empty' => null,
    'error' => null,
])

@php
    $oldKey = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
@endphp
<x-select-field :name="$name" :error="$error ?? $oldKey" :label="$label ?? __('article.catalog.field')" :hint="$hint" :span="$span" {{ $attributes }}>
    <option value="">{{ $empty ?? __('article.catalog.none') }}</option>
    @foreach (collect($articles)->groupBy('sourceLabel') as $sourceLabel => $group)
        <optgroup label="{{ $sourceLabel }}">
            @foreach ($group as $article)
                <option value="{{ $article->formKey }}" @selected((string) old($oldKey, $selected) === $article->formKey)>{{ $article->label() }}{{ $article->netPrice !== null ? ' — ' . $article->netPrice->withScale(2)->format() . ($article->unitName ? '/' . $article->unitName : '') : '' }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-select-field>
