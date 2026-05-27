@extends('layouts.app')

@section('title', $site->name)
@section('nav-title', $site->name)

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="$site->customer
                ? __('Standort von :customer.', ['customer' => $site->customer->name])
                : __('Standort ohne Kundenbindung.')">
                <x-slot:actions>
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('sites.edit', $site)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    <x-icon-btn icon="arrow_back" size="sm"
                                :href="route('sites.index')"
                                show-label>{{ __('Zurück') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-kpi-tile :label="__('Gebäude')" :value="$kpis['buildings']" tone="primary"
                        :href="route('buildings.index', ['site' => $site->id])" />
            <x-kpi-tile :label="__('Geschosse')" :value="$kpis['floors']" tone="info" />
            <x-kpi-tile :label="__('Räume')" :value="$kpis['rooms']" tone="success" />
            <x-kpi-tile :label="__('BGF (m²)')" :value="$kpis['gross_area']" tone="secondary" format="decimal" />
        </div>

        <x-card :title="__('Adresse & Lage')">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @if ($site->address_street || $site->address_zip || $site->address_city)
                    <div>
                        <dt class="text-base-content/60">{{ __('Adresse') }}</dt>
                        <dd>
                            @if ($site->address_street){{ $site->address_street }}<br>@endif
                            {{ trim(($site->address_zip ?? '').' '.($site->address_city ?? '')) }}
                            @if ($site->country) · {{ $site->country }}@endif
                        </dd>
                    </div>
                @endif
                @if ($site->geo_lat !== null && $site->geo_lng !== null)
                    <div>
                        <dt class="text-base-content/60">{{ __('Geo') }}</dt>
                        <dd class="font-mono">{{ $site->geo_lat }}, {{ $site->geo_lng }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-base-content/60">{{ __('Status') }}</dt>
                    <dd>
                        @if ($site->is_active)
                            <span class="badge badge-success badge-sm">{{ __('aktiv') }}</span>
                        @else
                            <span class="badge badge-ghost badge-sm">{{ __('inaktiv') }}</span>
                        @endif
                    </dd>
                </div>
                @if ($site->notes)
                    <div class="md:col-span-2">
                        <dt class="text-base-content/60">{{ __('Notizen') }}</dt>
                        <dd class="whitespace-pre-line">{{ $site->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card :title="__('Gebäude') . ' (' . $buildings->count() . ')'" padding="p-0">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('buildings.create', ['site' => $site->id])"
                            show-label>{{ __('Gebäude anlegen') }}</x-icon-btn>
            </x-slot:actions>

            @if ($buildings->isEmpty())
                <div class="p-4">
                    <x-empty-state framed
                        icon='<span class="material-symbols-outlined" aria-hidden="true">apartment</span>' />
                </div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th class="text-end">{{ __('Baujahr') }}</th>
                            <th class="text-end">{{ __('BGF (m²)') }}</th>
                            <th class="text-end">{{ __('Geschosse') }}</th>
                            <th class="text-end">{{ __('Räume') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($buildings as $b)
                        <tr>
                            <td>
                                <a class="link link-hover" href="{{ route('buildings.show', $b) }}">{{ $b->name }}</a>
                                @if ($b->code)<span class="text-base-content/60 ms-1">({{ $b->code }})</span>@endif
                            </td>
                            <td class="text-end">{{ $b->year_built ?? '—' }}</td>
                            <td class="text-end">{{ $b->gross_area_m2 !== null ? number_format((float) $b->gross_area_m2, 1, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ $b->floors_count }}</td>
                            <td class="text-end">{{ $b->rooms_count }}</td>
                            <td class="text-right">
                                <x-icon-btn icon="edit" size="sm"
                                            data-entry-modal-trigger
                                            :href="route('buildings.edit', $b)"
                                            :label="__('Bearbeiten')" />
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </x-page-shell>
@endsection

