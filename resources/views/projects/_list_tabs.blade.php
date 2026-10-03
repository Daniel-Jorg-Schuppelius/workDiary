{{--
  Created on   : Sat Oct 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _list_tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereichsreiter der Projektliste (MVP-1073); die Reiter eines einzelnen Projekts stehen in _tabs. --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'projects.index', 'routeIs' => 'projects.index', 'icon' => 'folder_special', 'label' => __('Projekte')],
    ['route' => 'projects.times', 'routeIs' => 'projects.times', 'icon' => 'schedule', 'label' => __('Zeiten')],
]" />
