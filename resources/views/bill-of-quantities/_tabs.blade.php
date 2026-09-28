{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter eines Leistungsverzeichnisses (MVP-969); erwartet $bill. Kalkulationsdaten (X52) nur, wo es welche gibt. --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'bill-of-quantities.show', 'params' => $bill, 'routeIs' => 'bill-of-quantities.show', 'icon' => 'list_alt', 'label' => __('gaeb.show.positions')],
    ['route' => 'bill-of-quantities.price-comparison', 'params' => $bill, 'routeIs' => 'bill-of-quantities.price-comparison', 'icon' => 'table_chart', 'label' => __('gaeb.comparison.button')],
    ['route' => 'bill-of-quantities.cost-groups', 'params' => $bill, 'routeIs' => 'bill-of-quantities.cost-groups', 'icon' => 'category', 'label' => __('Kostengruppen')],
    ['route' => 'bill-of-quantities.calculation-data', 'params' => $bill, 'routeIs' => 'bill-of-quantities.calculation-data', 'icon' => 'calculate', 'label' => __('Kalkulationsdaten'), 'when' => $bill->costTypes()->exists()],
    ['route' => 'bill-of-quantities.catalog-assignment', 'params' => $bill, 'routeIs' => 'bill-of-quantities.catalog-assignment', 'icon' => 'playlist_add_check', 'label' => __('Zuordnen')],
    ['route' => 'bill-of-quantities.call-offs.index', 'params' => $bill, 'routeIs' => 'bill-of-quantities.call-offs.*', 'icon' => 'assignment', 'label' => __('gaeb.call_off.button')],
    ['route' => 'bill-of-quantities.billing', 'params' => $bill, 'routeIs' => 'bill-of-quantities.billing', 'icon' => 'receipt_long', 'label' => __('gaeb.billing.button'), 'when' => auth()->user()?->can(\App\Enums\User\Permission::InvoiceViewAny->value) ?? false],
]" />
