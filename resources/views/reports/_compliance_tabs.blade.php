{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _compliance_tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter des Arbeitszeit-Compliance-Bereichs (MVP-969). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'reports.compliance.dashboard', 'routeIs' => 'reports.compliance.dashboard', 'icon' => 'insights', 'label' => __('compliance.history.to_dashboard')],
    ['route' => 'reports.arbzg-compliance', 'routeIs' => 'reports.arbzg-compliance', 'icon' => 'table_view', 'label' => __('compliance.history.to_report')],
    ['route' => 'reports.compliance.history', 'routeIs' => 'reports.compliance.history', 'icon' => 'fact_check', 'label' => __('compliance.history.nav')],
]" />
