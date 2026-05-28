@extends('layouts.app')

@section('title', $floor->label)
@section('nav-title', $floor->label)

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="$floor->building
                ? __('Geschoss :level im Gebäude :building.', ['level' => $floor->level, 'building' => $floor->building->name])
                : __('Geschoss ohne Gebäudebindung.')">
                <x-slot:actions>
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('floors.edit', $floor)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if ($floor->building)
                        <x-icon-btn icon="apartment" size="sm"
                                    :href="route('buildings.show', $floor->building)"
                                    show-label>{{ __('Gebäude') }}</x-icon-btn>
                    @endif
                    <x-icon-btn icon="arrow_back" size="sm"
                                :href="route('floors.index')"
                                show-label>{{ __('Zurück') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-kpi-tile :label="__('Räume')" :value="$kpis['rooms']" tone="primary" />
            <x-kpi-tile :label="__('Aktiv')" :value="$kpis['active_rooms']" tone="success" />
            <x-kpi-tile :label="__('NGF (m²)')" :value="$kpis['net_area']" tone="info" format="decimal" />
            <x-kpi-tile :label="__('Kapazität Σ')" :value="$kpis['capacity']" tone="secondary" />
        </div>

        <x-card :title="__('Lage')">
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                @if ($floor->building?->site?->customer)
                    <div>
                        <dt class="text-base-content/60">{{ __('Kunde') }}</dt>
                        <dd>{{ $floor->building->site->customer->name }}</dd>
                    </div>
                @endif
                @if ($floor->building?->site)
                    <div>
                        <dt class="text-base-content/60">{{ __('Standort') }}</dt>
                        <dd><a class="link link-hover" href="{{ route('sites.show', $floor->building->site) }}">{{ $floor->building->site->name }}</a></dd>
                    </div>
                @endif
                <div>
                    <dt class="text-base-content/60">{{ __('BGF (m²)') }}</dt>
                    <dd>{{ $floor->gross_area_m2 !== null ? number_format((float) $floor->gross_area_m2, 1, ',', '.') : '—' }}</dd>
                </div>
                @if ($floor->notes)
                    <div class="md:col-span-3">
                        <dt class="text-base-content/60">{{ __('Notizen') }}</dt>
                        <dd class="whitespace-pre-line">{{ $floor->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card :title="__('Räume') . ' (' . $rooms->count() . ')'" padding="p-0">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('rooms.create', ['floor' => $floor->sqid])"
                            show-label>{{ __('Raum anlegen') }}</x-icon-btn>
            </x-slot:actions>

            @if ($rooms->isEmpty())
                <div class="p-4">
                    <x-empty-state framed
                        icon='<span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>' />
                </div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Nutzung') }}</th>
                            <th>{{ __('Reinigung') }}</th>
                            <th class="text-end">{{ __('Kapazität') }}</th>
                            <th class="text-end">{{ __('NGF (m²)') }}</th>
                            <th class="text-end">{{ __('Geräte') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rooms as $r)
                        <tr class="{{ $r->is_active ? '' : 'opacity-60' }}">
                            <td>
                                {{ $r->name }}
                                @if ($r->code)<span class="text-base-content/60 ms-1 font-mono">({{ $r->code }})</span>@endif
                            </td>
                            <td>
                                @if ($r->usage_type)
                                    <span class="badge badge-ghost badge-sm">{{ $r->usage_type->label() }}</span>
                                @endif
                            </td>
                            <td>{{ $r->cleaningProfile?->label ?? '—' }}</td>
                            <td class="text-end">{{ $r->capacity ?? '—' }}</td>
                            <td class="text-end">{{ $r->net_area_m2 !== null ? number_format((float) $r->net_area_m2, 1, ',', '.') : '—' }}</td>
                            <td class="text-end tabular-nums">{{ $r->assets_count ?? 0 }}</td>
                            <td class="text-right">
                                @can('create', \App\Models\Asset::class)
                                    <x-icon-btn icon="add" size="sm"
                                                data-entry-modal-trigger
                                                :href="route('assets.create', ['room' => $r->sqid])"
                                                :label="__('Gerät zuordnen')" />
                                @endcan
                                <x-icon-btn icon="edit" size="sm"
                                            data-entry-modal-trigger
                                            :href="route('rooms.edit', $r)"
                                            :label="__('Bearbeiten')" />
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </x-page-shell>
@endsection

