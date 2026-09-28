{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Krisenmanagement (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'crisis.index', 'routeIs' => 'crisis.index', 'icon' => 'emergency_home', 'label' => __('Krisenmanagement')],
    ['route' => 'crisis.bia.index', 'routeIs' => 'crisis.bia.index', 'icon' => 'account_tree', 'label' => __('crisis.bia.title')],
    ['route' => 'crisis.bcm-report', 'routeIs' => 'crisis.bcm-report', 'icon' => 'insights', 'label' => __('crisis.bcm_report.title')],
]" />
