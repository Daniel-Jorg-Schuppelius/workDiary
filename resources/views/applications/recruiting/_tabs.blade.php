{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tab-Navigation Personalbereich (Feature 068): Stellen · Bewerbungen --}}
<x-tab-nav :items="[
    ['route' => 'recruiting.requisitions.index', 'routeIs' => 'recruiting.requisitions.*', 'icon' => 'work', 'label' => __('Stellen')],
    ['route' => 'recruiting.applications.index', 'routeIs' => 'recruiting.applications.*', 'icon' => 'person_search', 'label' => __('Bewerbungen')],
]" />
