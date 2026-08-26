{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter der Artikel-Detailseite (MVP-715): Stammdaten | Nachkalkulation.
     Nachkalkulation nur mit Recht auf Fertigungsaufträge — Kosten sind
     Fertigungs-, keine Stammdaten. --}}
@php
    /** @var \App\Models\Article $article */
    $costingVisible = \Illuminate\Support\Facades\Route::has('articles.costing')
        && (auth()->user()?->can('viewAny', \App\Models\ManufacturingOrder::class) ?? false);
@endphp
<x-tab-nav class="w-fit" :items="[
    ['label' => __('article.tabs.master'), 'route' => 'articles.show', 'params' => $article, 'routeIs' => 'articles.show', 'icon' => 'inventory_2'],
    ['label' => __('article.costing.title'), 'route' => 'articles.costing', 'params' => $article, 'routeIs' => 'articles.costing', 'icon' => 'calculate', 'when' => $costingVisible],
]" />
