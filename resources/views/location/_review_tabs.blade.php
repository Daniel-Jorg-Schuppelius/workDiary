{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _review_tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüf-Eingang der Standorterfassung: Zeitvorschläge und Fahrtvorschläge (MVP-1185). --}}
<x-tab-nav class="w-fit" :items="[
    ['route' => 'location.review.index', 'routeIs' => 'location.review.index', 'icon' => 'where_to_vote', 'label' => __('Zeitvorschläge')],
    ['route' => 'location.review.trips.index', 'routeIs' => 'location.review.trips.*', 'icon' => 'route', 'label' => __('Fahrtvorschläge')],
]" />
