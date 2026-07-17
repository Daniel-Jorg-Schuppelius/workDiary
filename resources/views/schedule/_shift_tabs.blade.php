{{--
    Gemeinsame Tab-Leiste „Schichtplan ↔ Schichttausch" (zusammengelegt).
    Aktiver Tab über routeIs. Der Schichttausch-Tab wird nur bei entsprechender
    Berechtigung gezeigt (ShiftExchange::viewAny).
--}}
<x-tab-nav :items="[
    ['route' => 'schedule.index', 'icon' => 'schedule', 'label' => __('Schichtplan'),
     'routeIs' => ['schedule.index', 'schedule.shifts.*', 'schedule.types.*', 'schedule.import.*']],
    ['route' => 'schedule.exchanges.index', 'routeIs' => 'schedule.exchanges.*', 'icon' => 'swap_horiz', 'label' => __('schedule.exchange.title'),
     'when' => \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\ShiftExchange::class)],
]" />
