{{-- Tab-Navigation Personalbereich (Feature 068): Stellen · Bewerbungen --}}
<x-tab-nav :items="[
    ['route' => 'recruiting.requisitions.index', 'routeIs' => 'recruiting.requisitions.*', 'icon' => 'work', 'label' => __('Stellen')],
    ['route' => 'recruiting.applications.index', 'routeIs' => 'recruiting.applications.*', 'icon' => 'person_search', 'label' => __('Bewerbungen')],
]" />
