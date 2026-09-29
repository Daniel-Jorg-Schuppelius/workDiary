{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Reselling (MVP-969). Zähler nur, wo die Übersicht sie mitgibt ($summary). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'finance.resale.index', 'routeIs' => 'finance.resale.index', 'icon' => 'dashboard', 'label' => __('Übersicht')],
    ['route' => 'finance.resale.report.index', 'routeIs' => 'finance.resale.report.*', 'icon' => 'insights', 'label' => __('resale.report.title')],
    ['route' => 'finance.resale.prices', 'routeIs' => 'finance.resale.prices', 'icon' => 'price_check', 'label' => __('resale.prices.title')],
    ['route' => 'finance.resale.products', 'routeIs' => 'finance.resale.products', 'icon' => 'inventory_2', 'label' => __('resale.products.title')],
    ['route' => 'finance.resale.licenses.index', 'routeIs' => 'finance.resale.licenses.*', 'icon' => 'key', 'label' => __('resale.license.title')],
    ['route' => 'finance.resale.purchases.index', 'routeIs' => 'finance.resale.purchases.index', 'icon' => 'shopping_cart', 'label' => __('resale.purchase.title')],
    ['route' => 'finance.resale.reconcile.index', 'routeIs' => 'finance.resale.reconcile.index', 'icon' => 'compare_arrows', 'label' => __('resale.reconcile.title')],
    ['route' => 'finance.resale.periods.index', 'routeIs' => 'finance.resale.periods.index', 'icon' => 'fact_check', 'label' => __('resale.periods.title'), 'count' => ($summary['open_periods'] ?? 0) ?: null],
    ['route' => 'finance.resale.inbox', 'routeIs' => 'finance.resale.inbox', 'icon' => 'inbox', 'label' => __('resale.inbox.title'), 'count' => ($summary['unassigned'] ?? 0) ?: null,
     'when' => auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false],
]" />
