{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Verleih (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'rental.index', 'routeIs' => 'rental.index', 'icon' => 'forklift', 'label' => __('Verleihakten')],
    ['route' => 'rental.calendar', 'routeIs' => 'rental.calendar', 'icon' => 'calendar_month', 'label' => __('Kalender')],
    ['route' => 'rental.requests.index', 'routeIs' => 'rental.requests.index', 'icon' => 'mark_email_unread', 'label' => __('Verleih-Anfragen')],
    ['route' => 'rental.reports.index', 'routeIs' => 'rental.reports.index', 'icon' => 'query_stats', 'label' => __('Verleihbericht')],
]" />
