{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tiles.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zählkacheln des Leistungsplans (Feature 199): zählen Zellen, filtern Zeilen. Variablen: $route, $result, $filters, $linkParams. --}}
@php
    $tileTones = ['missing' => 'error', 'open' => 'info', 'review' => 'secondary', 'done' => 'success', 'waived' => 'neutral', 'overdue' => 'warning'];
@endphp
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
    @foreach (\App\Enums\ServicePlan\ServicePlanCellState::FILTERS as $state)
        <x-kpi-tile :label="__('service_plan.count.' . $state)" :value="$result->counts[$state] ?? 0" :tone="$tileTones[$state]"
                    :href="route($route, array_merge($linkParams, ['state' => $filters['state'] === $state ? null : $state]))"
                    :active="$filters['state'] === $state" />
    @endforeach
</div>
