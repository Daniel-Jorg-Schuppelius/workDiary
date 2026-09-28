{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter KI-Verwaltung (MVP-970). --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'admin.ai.index', 'routeIs' => 'admin.ai.index', 'icon' => 'smart_toy', 'label' => __('ai.title.connections')],
    ['route' => 'admin.ai.usage', 'routeIs' => 'admin.ai.usage', 'icon' => 'monitoring', 'label' => __('ai.usage.title')],
    ['route' => 'admin.ai.memory', 'routeIs' => 'admin.ai.memory', 'icon' => 'psychology', 'label' => __('ai.title.memory')],
]" />
