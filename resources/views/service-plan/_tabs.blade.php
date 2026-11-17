{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Umschalter Jahressicht · Periodensicht des Leistungsplans (Feature 199); Filter ohne Leistungsauswahl und Stand bleiben erhalten. --}}
@php
    $tabParams = array_intersect_key($linkParams ?? [], array_flip(['responsible', 'group', 'q', 'customer']));
@endphp
<x-tab-nav class="w-fit" :items="[
    ['route' => 'service-plan.index', 'params' => $tabParams, 'routeIs' => 'service-plan.index', 'icon' => 'calendar_view_month', 'label' => __('service_plan.view.year')],
    ['route' => 'service-plan.periods.index', 'params' => $tabParams, 'routeIs' => 'service-plan.periods.index', 'icon' => 'view_week', 'label' => __('service_plan.view.periods')],
]" />
