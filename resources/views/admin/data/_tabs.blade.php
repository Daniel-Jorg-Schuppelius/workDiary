{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Tab-Navigation für den Datentransfer-Bereich: Import · Export · Verlauf.
    Der Import nutzt den bestehenden MVP-049-Wizard (admin.imports.*),
    Export und Verlauf liegen unter admin.data.*.
--}}
<x-tab-nav :items="[
    ['route' => 'admin.imports.index', 'routeIs' => 'admin.imports.*', 'icon' => 'upload_file', 'label' => __('Import')],
    ['route' => 'admin.data.index', 'routeIs' => 'admin.data.index', 'icon' => 'download', 'label' => __('Export')],
    ['route' => 'admin.data.history', 'routeIs' => 'admin.data.history', 'icon' => 'history', 'label' => __('Verlauf')],
]" />
