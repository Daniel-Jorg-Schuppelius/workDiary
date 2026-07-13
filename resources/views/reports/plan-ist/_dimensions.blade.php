{{--
  Created on   : Sat Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dimensions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dimension-Umschalter der Plan/Ist-Auswertung (A14 · MVP-333): Anwesenheit
  (persönlich/Team/Org) plus Schicht/Projekt/Standort. Tabs erscheinen nur
  mit dem jeweiligen Bestandsrecht; der gewählte Zeitraum wird mitgenommen.
--}}

@php
    $u = auth()->user();
    $canTeam = (bool) ($u?->isAdmin() || $u?->can(\App\Enums\User\Permission::ReportPresenceTeam->value));
    $canOrg = (bool) ($u?->isAdmin() || $u?->can(\App\Enums\User\Permission::ReportPresenceOrganization->value));
    $rangeQuery = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $dimensionTabs = array_values(array_filter([
        ['route' => 'reports.plan-ist.presence', 'label' => __('Anwesenheit')],
        $canTeam ? ['route' => 'reports.plan-ist.team', 'label' => __('Team')] : null,
        $canOrg ? ['route' => 'reports.plan-ist.organization', 'label' => __('Organisation')] : null,
        $canOrg ? ['route' => 'reports.plan-ist.shifts', 'label' => __('Schichten')] : null,
        $canOrg ? ['route' => 'reports.plan-ist.projects', 'label' => __('Projekte')] : null,
        $canOrg ? ['route' => 'reports.plan-ist.sites', 'label' => __('Standorte')] : null,
    ]));
@endphp

@if (count($dimensionTabs) > 1)
    <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
        @foreach ($dimensionTabs as $tab)
            <a role="tab"
               href="{{ route($tab['route'], $rangeQuery) }}"
               class="tab whitespace-nowrap {{ request()->routeIs($tab['route']) ? 'tab-active' : '' }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>
@endif
