{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Vereinsbeiträge (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'club.fees.accounts.index', 'routeIs' => 'club.fees.accounts.index', 'icon' => 'account_balance_wallet', 'label' => __('club.fees.title.accounts')],
    ['route' => 'club.fees.claims.index', 'routeIs' => 'club.fees.claims.index', 'icon' => 'receipt_long', 'label' => __('club.fees.title.claims')],
    ['route' => 'club.fees.runs.index', 'routeIs' => 'club.fees.runs.index', 'icon' => 'play_circle', 'label' => __('club.fees.title.runs')],
    ['route' => 'club.fees.collections.index', 'routeIs' => 'club.fees.collections.index', 'icon' => 'account_balance', 'label' => __('club.fees.title.collections')],
    ['route' => 'club.fees.tariffs.index', 'routeIs' => 'club.fees.tariffs.index', 'icon' => 'payments', 'label' => __('club.fees.title.tariffs')],
    ['route' => 'club.fees.preview', 'routeIs' => 'club.fees.preview', 'icon' => 'calculate', 'label' => __('club.fees.action.preview')],
]" />
