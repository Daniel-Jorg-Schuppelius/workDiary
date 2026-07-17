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
