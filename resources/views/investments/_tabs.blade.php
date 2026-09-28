{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Investitionen (MVP-969). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'investments.index', 'routeIs' => 'investments.index', 'icon' => 'savings', 'label' => __('Übersicht')],
    ['route' => 'investments.report', 'routeIs' => 'investments.report', 'icon' => 'analytics', 'label' => __('Bericht')],
    ['route' => 'investments.programs.index', 'routeIs' => 'investments.programs.index', 'icon' => 'account_tree', 'label' => __('investment.program.title')],
    ['route' => 'investments.objectives.index', 'routeIs' => 'investments.objectives.index', 'icon' => 'flag', 'label' => __('investment.objective.title')],
    ['route' => 'investments.supplier-ratings.index', 'routeIs' => 'investments.supplier-ratings.index', 'icon' => 'star_rate', 'label' => __('investment.supplier_rating.overview')],
]" />
