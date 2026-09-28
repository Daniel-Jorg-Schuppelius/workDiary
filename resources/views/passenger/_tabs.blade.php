{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Personenbeförderung (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'passenger-rides.index', 'routeIs' => 'passenger-rides.index', 'icon' => 'local_taxi', 'label' => __('passenger.rides.title')],
    ['route' => 'passenger-settlements.index', 'routeIs' => 'passenger-settlements.index', 'icon' => 'payments', 'label' => __('passenger.settlements.title')],
    ['route' => 'passenger-masterdata.index', 'routeIs' => 'passenger-masterdata.index', 'icon' => 'tune', 'label' => __('passenger.masterdata.title')],
]" />
