{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Verträge (MVP-970); Verwaltungsseiten nur mit Anlegerecht. --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'contracts.index', 'routeIs' => 'contracts.index', 'icon' => 'contract', 'label' => __('Verträge')],
    ['route' => 'contracts.templates.index', 'routeIs' => 'contracts.templates.index', 'icon' => 'library_books', 'label' => __('contract.template.title'),
     'when' => auth()->user()?->can('create', \App\Models\Contract\Contract::class) ?? false],
    ['route' => 'contracts.cost-centers', 'routeIs' => 'contracts.cost-centers', 'icon' => 'account_tree', 'label' => __('contract.cost_center.title'),
     'when' => auth()->user()?->can('create', \App\Models\Contract\Contract::class) ?? false],
    ['route' => 'contracts.price-index.index', 'routeIs' => 'contracts.price-index.index', 'icon' => 'trending_up', 'label' => __('contract.price_index.title'),
     'when' => auth()->user()?->can('create', \App\Models\Contract\Contract::class) ?? false],
]" />
