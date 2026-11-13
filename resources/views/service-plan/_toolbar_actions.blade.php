{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _toolbar_actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aktionen im Seitenkopf des Leistungsplans (Feature 199). Variablen: $route (Routenname der Sicht), $linkParams, $canManage. --}}
<x-report-export :formats="['csv', 'xlsx']" :url="fn (string $format) => route($route, array_merge($linkParams, ['export' => $format]))" />
@if ($canManage)
    <x-action-menu icon="more_horiz" tone="ghost" :label="__('service_plan.action.more')">
        <x-icon-btn icon="upload_file" tone="ghost" size="sm" :href="route('service-plan.import.create')" show-label>{{ __('service_plan.import.title') }}</x-icon-btn>
        <x-icon-btn icon="compare_arrows" tone="ghost" size="sm" :href="route('service-plan.reconcile.create')" show-label>{{ __('service_plan.reconcile.title') }}</x-icon-btn>
    </x-action-menu>
@endif
