{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _view-tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Gemeinsame Ansicht-Umschaltung für Touren: Liste <-> Karte.
    Übernimmt die aktuellen Filter-Query-Parameter (from/to/status/user …),
    damit der Zeitraum/Filter beim Wechsel erhalten bleibt.
--}}
@php
    $tabQuery = request()->query();
@endphp

<x-tab-nav :items="[
    ['route' => 'tours.index', 'params' => $tabQuery, 'routeIs' => 'tours.index', 'icon' => 'list', 'label' => __('Liste')],
    ['route' => 'tours.map', 'params' => $tabQuery, 'routeIs' => 'tours.map', 'icon' => 'map', 'label' => __('Karte')],
]" />
