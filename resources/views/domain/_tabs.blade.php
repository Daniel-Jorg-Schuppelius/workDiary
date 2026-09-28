{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Domains (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'domains.index', 'routeIs' => 'domains.index', 'icon' => 'dns', 'label' => __('domain.title.index')],
    ['route' => 'domain-reseller.index', 'routeIs' => 'domain-reseller.index', 'icon' => 'account_tree', 'label' => __('domain.title.reseller')],
    ['route' => 'domains.reports', 'routeIs' => 'domains.reports', 'icon' => 'analytics', 'label' => __('domain.title.reports')],
    ['route' => 'admin.domain-provider.index', 'routeIs' => 'admin.domain-provider.index', 'icon' => 'settings_ethernet', 'label' => __('domain.title.connections')],
]" />
