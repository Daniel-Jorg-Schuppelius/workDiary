{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter eines agilen Projekts (MVP-970); erwartet $project. Ohne Board nur das Board (Einrichtung). --}}
@php $agileHasBoard = isset($board) || \App\Models\Agile\AgileBoard::query()->where('project_id', $project->id)->exists(); @endphp
<x-tab-nav class="flex-none" :items="[
    ['route' => 'agile.board', 'params' => $project, 'routeIs' => 'agile.board', 'icon' => 'view_kanban', 'label' => __('Projektboard')],
    ['route' => 'agile.backlog', 'params' => $project, 'routeIs' => 'agile.backlog', 'icon' => 'low_priority', 'label' => __('Produkt-Backlog'),
     'when' => $agileHasBoard],
    ['route' => 'agile.sprints', 'params' => $project, 'routeIs' => 'agile.sprints', 'icon' => 'sprint', 'label' => __('Sprints'),
     'when' => $agileHasBoard],
    ['route' => 'agile.reports.sprint', 'params' => $project, 'routeIs' => 'agile.reports.sprint', 'icon' => 'monitoring', 'label' => __('Sprint-Cockpit'),
     'when' => $agileHasBoard],
    ['route' => 'agile.reports.flow', 'params' => $project, 'routeIs' => 'agile.reports.flow', 'icon' => 'insights', 'label' => __('Fluss-Bericht'),
     'when' => $agileHasBoard],
]" />
