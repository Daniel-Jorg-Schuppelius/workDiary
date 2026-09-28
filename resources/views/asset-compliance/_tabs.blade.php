{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Prüfmittel (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'asset-compliance.index', 'routeIs' => 'asset-compliance.index', 'icon' => 'dashboard', 'label' => __('Übersicht')],
    ['route' => 'asset-compliance.profiles.index', 'routeIs' => 'asset-compliance.profiles.index', 'icon' => 'checklist', 'label' => __('Prüfprofile')],
    ['route' => 'asset-compliance.schedules.index', 'routeIs' => 'asset-compliance.schedules.index', 'icon' => 'event_available', 'label' => __('Prüfkalender')],
    ['route' => 'asset-compliance.rounds.index', 'routeIs' => 'asset-compliance.rounds.index', 'icon' => 'qr_code_scanner', 'label' => __('inspection_round.nav')],
    ['route' => 'asset-compliance.reports.index', 'routeIs' => 'asset-compliance.reports.index', 'icon' => 'query_stats', 'label' => __('Auditbericht')],
]" />
