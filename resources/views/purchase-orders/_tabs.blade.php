{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Bestellungen (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'purchase-orders.index', 'routeIs' => 'purchase-orders.index', 'icon' => 'shopping_cart', 'label' => __('procurement.title')],
    ['route' => 'purchase-orders.incoming', 'routeIs' => 'purchase-orders.incoming', 'icon' => 'local_shipping', 'label' => __('procurement.action.incoming')],
    ['route' => 'purchase-orders.suggestions', 'routeIs' => 'purchase-orders.suggestions', 'icon' => 'lightbulb', 'label' => __('procurement.action.suggestions')],
]" />
