{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _duty_tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Gemeinsame Tab-Leiste „Dienstpläne ↔ Verfügbarkeit & Wunschdienste"
    (zusammengelegt). Aktiver Tab über routeIs. Der Verfügbarkeits-Tab wird
    nur bei entsprechender Berechtigung gezeigt (AvailabilityWindow::viewAny).
--}}
<x-tab-nav :items="[
    ['route' => 'duty-plans.index', 'routeIs' => 'duty-plans.*', 'icon' => 'event_available', 'label' => __('Dienstpläne')],
    ['route' => 'schedule.availability.index', 'routeIs' => 'schedule.availability.*', 'icon' => 'fact_check', 'label' => __('schedule.availability.title'),
     'when' => \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\AvailabilityWindow::class)],
]" />
