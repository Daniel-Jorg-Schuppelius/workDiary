{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Leasing (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'asset-finance.index', 'routeIs' => 'asset-finance.index', 'icon' => 'contract', 'label' => __('Leasing')],
    ['route' => 'asset-finance.deadlines.index', 'routeIs' => 'asset-finance.deadlines.index', 'icon' => 'event_upcoming', 'label' => __('Fristen')],
    ['route' => 'asset-finance.reports.index', 'routeIs' => 'asset-finance.reports.index', 'icon' => 'query_stats', 'label' => __('Bericht')],
]" />
