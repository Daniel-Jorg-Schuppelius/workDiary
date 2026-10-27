{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter Reselling (MVP-969, Reihenfolge nach Ablauf Feature 185): Arbeiten │ Bestand │ Auswerten.
     Zähler nur, wo die Seite sie mitgibt ($summary); Einstellungen (Produkt-Einstufung, Abrechnung) im ⋯-Menü.
     Kurze Beschriftungen (resale.tab.*): zehn Reiter mit Seitentiteln wurden bei 1600 px abgeschnitten (Sonde 2026-10-26). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'finance.resale.index', 'routeIs' => 'finance.resale.index', 'icon' => 'dashboard', 'label' => __('resale.tab.overview')],
    ['route' => 'finance.resale.accounts.index', 'routeIs' => 'finance.resale.accounts.*', 'icon' => 'account_balance_wallet', 'label' => __('resale.tab.accounts')],
    ['route' => 'finance.resale.reconcile.index', 'routeIs' => 'finance.resale.reconcile.*', 'icon' => 'compare_arrows', 'label' => __('resale.tab.reconcile')],
    ['route' => 'finance.resale.periods.index', 'routeIs' => 'finance.resale.periods.index', 'icon' => 'fact_check', 'label' => __('resale.tab.periods'), 'count' => ($summary['open_periods'] ?? $summary['open'] ?? 0) ?: null],
    ['route' => 'finance.resale.inbox', 'routeIs' => 'finance.resale.inbox', 'icon' => 'inbox', 'label' => __('resale.tab.inbox'), 'count' => ($summary['unassigned'] ?? 0) ?: null,
     'when' => auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false],
    ['divider' => true],
    ['route' => 'finance.resale.subscriptions', 'routeIs' => ['finance.resale.subscriptions', 'finance.resale.show'], 'icon' => 'subscriptions', 'label' => __('resale.tab.subscriptions')],
    ['route' => 'finance.resale.licenses.index', 'routeIs' => 'finance.resale.licenses.*', 'icon' => 'key', 'label' => __('resale.tab.licenses')],
    ['route' => 'finance.resale.purchases.index', 'routeIs' => 'finance.resale.purchases.index', 'icon' => 'shopping_cart', 'label' => __('resale.tab.purchases')],
    ['divider' => true],
    ['route' => 'finance.resale.report.index', 'routeIs' => 'finance.resale.report.*', 'icon' => 'insights', 'label' => __('resale.tab.reports')],
    ['route' => 'finance.resale.prices', 'routeIs' => 'finance.resale.prices', 'icon' => 'price_check', 'label' => __('resale.tab.prices')],
]" />
