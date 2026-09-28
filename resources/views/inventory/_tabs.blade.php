{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Lager (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'inventory.stock', 'routeIs' => 'inventory.stock', 'icon' => 'inventory', 'label' => __('inventory.stock')],
    ['route' => 'inventory.counts.index', 'routeIs' => 'inventory.counts.index', 'icon' => 'fact_check', 'label' => __('inventory.count_ui.title')],
    ['route' => 'warehouses.index', 'routeIs' => 'warehouses.index', 'icon' => 'warehouse', 'label' => __('inventory.warehouses')],
]" />
