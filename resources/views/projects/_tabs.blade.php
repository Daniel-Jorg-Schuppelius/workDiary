{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter eines Projekts (MVP-969); erwartet $project. Das Projektboard gehört zum Agil-Modul mit eigener Navigation und bleibt Aktion. --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'projects.show', 'params' => $project, 'routeIs' => 'projects.show', 'icon' => 'folder', 'label' => __('Projekt')],
    ['route' => 'projects.planning', 'params' => $project, 'routeIs' => 'projects.planning', 'icon' => 'timeline', 'label' => __('Projektplanung')],
]" />
