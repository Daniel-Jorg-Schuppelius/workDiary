{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Nachhaltigkeit (MVP-969). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'sustainability.index', 'routeIs' => 'sustainability.index', 'icon' => 'eco', 'label' => __('Übersicht')],
    ['route' => 'sustainability.sites.benchmark', 'routeIs' => 'sustainability.sites.benchmark', 'icon' => 'location_city', 'label' => __('sustainability.site.benchmark')],
    ['route' => 'sustainability.offsets.index', 'routeIs' => 'sustainability.offsets.index', 'icon' => 'forest', 'label' => __('sustainability.offset.title')],
]" />
