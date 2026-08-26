{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', $building->name)
@section('nav-title', $building->name)

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="$building->site
                ? __('Gebäude am Standort :site.', ['site' => $building->site->name])
                : __('Gebäude ohne Standortbindung.')">
                <x-slot:actions>
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('buildings.edit', $building)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if ($building->site)
                        <x-icon-btn icon="location_on" size="sm"
                                    :href="route('sites.show', $building->site)"
                                    show-label>{{ __('Standort') }}</x-icon-btn>
                    @endif
                    <x-icon-btn icon="arrow_back" size="sm"
                                :href="route('buildings.index')"
                                show-label>{{ __('Zurück') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-kpi-tile :label="__('Geschosse')" :value="$kpis['floors']" tone="primary"
                        :href="route('floors.index', ['building' => $building->sqid])" />
            <x-kpi-tile :label="__('Räume')" :value="$kpis['rooms']" tone="info" />
            <x-kpi-tile :label="__('BGF (m²)')" :value="$kpis['gross_area']" tone="secondary" format="decimal" />
            <x-kpi-tile :label="__('NGF Räume (m²)')" :value="$kpis['net_area']" tone="success" format="decimal" />
        </div>

        <x-card :title="__('Stammdaten')">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @if ($building->code)
                    <div>
                        <dt class="text-muted">{{ __('Code') }}</dt>
                        <dd class="font-mono">{{ $building->code }}</dd>
                    </div>
                @endif
                @if ($building->year_built)
                    <div>
                        <dt class="text-muted">{{ __('Baujahr') }}</dt>
                        <dd>{{ $building->year_built }}</dd>
                    </div>
                @endif
                @if ($building->notes)
                    <div class="md:col-span-2">
                        <dt class="text-muted">{{ __('Notizen') }}</dt>
                        <dd class="whitespace-pre-line">{{ $building->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card :title="__('Geschosse') . ' (' . $floors->count() . ')'" padding="p-0">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('floors.create', ['building' => $building->sqid])"
                            show-label>{{ __('Geschoss anlegen') }}</x-icon-btn>
            </x-slot:actions>

            @if ($floors->isEmpty())
                <div class="p-4">
                    <x-empty-state framed
                        icon="layers" />
                </div>
            @else
                <x-table bare table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="number" class="text-end">{{ __('Ebene') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Bezeichnung') }}</x-table.th>
                            <x-table.th sort type="number" class="text-end">{{ __('BGF (m²)') }}</x-table.th>
                            <x-table.th sort type="number" class="text-end">{{ __('Räume') }}</x-table.th>
                            <x-table.th sort type="number" class="text-end">{{ __('NGF Σ (m²)') }}</x-table.th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($floors as $f)
                        <tr>
                            <td class="text-end font-mono">{{ $f->level }}</td>
                            <td><a class="link link-hover" href="{{ route('floors.show', $f) }}">{{ $f->label }}</a></td>
                            <td class="text-end" data-sort-value="{{ $f->gross_area_m2 !== null ? (float) $f->gross_area_m2 : -1 }}">{{ $f->gross_area_m2 !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $f->gross_area_m2, 1, withThousandsSeparator: true) : '—' }}</td>
                            <td class="text-end">{{ $f->rooms_count }}</td>
                            <td class="text-end" data-sort-value="{{ $f->net_area_sum !== null ? (float) $f->net_area_sum : -1 }}">{{ $f->net_area_sum !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $f->net_area_sum, 1, withThousandsSeparator: true) : '—' }}</td>
                            <td class="text-right">
                                <x-icon-btn icon="edit" size="sm"
                                            data-entry-modal-trigger
                                            :href="route('floors.edit', $f)"
                                            :label="__('Bearbeiten')" />
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </x-page-shell>
@endsection

