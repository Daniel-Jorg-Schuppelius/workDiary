{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _commission_tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Provisionen (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'commissions.index', 'routeIs' => 'commissions.index', 'icon' => 'receipt_long', 'label' => __('commission.action.to_commissions')],
    ['route' => 'commission-runs.index', 'routeIs' => 'commission-runs.index', 'icon' => 'event_repeat', 'label' => __('commission.action.to_runs')],
    ['route' => 'commission-rules.index', 'routeIs' => 'commission-rules.index', 'icon' => 'percent', 'label' => __('commission.action.to_rules')],
]" />
