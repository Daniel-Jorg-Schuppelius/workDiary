{{--
  Created on   : Sat Sep 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Reiter des Reselling-Registers. Vier Reiter (Phase 163, MVP-1435):
  Überblick · Abos · Abrechnung · Berichte — die übrigen Flächen
  (Lizenzkonten, Abgleich, Inbox, Lizenzbestand, Einkauf, Preise) im Menü
  „Weitere Bereiche“, erreichbar auch aus Aufgaben und Abo-Akten.
  Zähler nur, wo die Seite sie mitgibt ($summary); Einstellungen
  (Produkt-Einstufung, Abrechnung) im ⋯-Menü der Seite.
--}}
<div class="flex flex-wrap items-start gap-2 flex-none">
    <x-tab-nav class="flex-1 min-w-0" :items="[
        ['route' => 'finance.resale.index', 'routeIs' => 'finance.resale.index', 'icon' => 'dashboard', 'label' => __('resale.tab.overview')],
        ['route' => 'finance.resale.subscriptions', 'routeIs' => ['finance.resale.subscriptions', 'finance.resale.show'], 'icon' => 'subscriptions', 'label' => __('resale.tab.subscriptions'), 'count' => ($summary['unassigned'] ?? 0) ?: null],
        ['route' => 'finance.resale.periods.index', 'routeIs' => 'finance.resale.periods.index', 'icon' => 'fact_check', 'label' => __('resale.tab.billing'), 'count' => ($summary['open_periods'] ?? $summary['open'] ?? 0) ?: null],
        ['route' => 'finance.resale.report.index', 'routeIs' => 'finance.resale.report.*', 'icon' => 'insights', 'label' => __('resale.tab.reports')],
    ]" />
    <x-action-menu icon="more_horiz" :label="__('resale.tab.more')">
        <x-icon-btn icon="account_balance_wallet" tone="ghost" size="sm" :href="route('finance.resale.accounts.index')" show-label>{{ __('resale.tab.accounts') }}</x-icon-btn>
        <x-icon-btn icon="compare_arrows" tone="ghost" size="sm" :href="route('finance.resale.reconcile.index')" show-label>{{ __('resale.tab.reconcile') }}</x-icon-btn>
        @can(\App\Enums\User\Permission::ResellingManage->value)
            <x-icon-btn icon="inbox" tone="ghost" size="sm" :href="route('finance.resale.inbox')" show-label>{{ __('resale.tab.inbox') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="key" tone="ghost" size="sm" :href="route('finance.resale.licenses.index')" show-label>{{ __('resale.tab.licenses') }}</x-icon-btn>
        <x-icon-btn icon="shopping_cart" tone="ghost" size="sm" :href="route('finance.resale.purchases.index')" show-label>{{ __('resale.tab.purchases') }}</x-icon-btn>
        <x-icon-btn icon="price_check" tone="ghost" size="sm" :href="route('finance.resale.prices')" show-label>{{ __('resale.tab.prices') }}</x-icon-btn>
    </x-action-menu>
</div>
