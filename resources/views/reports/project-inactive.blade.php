{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : project-inactive.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Inaktive Projekte'))
@section('nav-title', __('Inaktive Projekte'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Projekte ohne Zeiteinträge im Zeitraum — optional in einem Schritt archivieren.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.project-inactive', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.project-inactive', array_merge($standardFilters->toQueryParams(), ['export' => 'xlsx']))"
                            show-label>XLSX</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.project-inactive')" :reset="route('reports.project-inactive')">
        @include('reports._standard_filters', ['idPrefix' => 'project-inactive'])
    </x-filter-bar>

    <x-charts.bar :title="__('Projekte je Inaktivitätsdauer')" :unit="__('Projekte')" :series="$inactivitySeries" :x-label="__('Monate seit letzter Buchung')" :y-label="__('Projekte')" />

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">
                {{ __('Keine Aktivität im Zeitraum') }}
                <span class="text-xs uppercase tracking-[0.18em] text-base-content/60 ml-2">
                    {{ $rangeFrom->fdate() }} – {{ $rangeTo->fdate() }}
                </span>
            </h2>
            <div class="text-xs uppercase tracking-[0.18em] text-base-content/60">
                {{ trans_choice('{0}Keine Projekte|{1}1 Projekt|[2,*]:count Projekte', $projects->count(), ['count' => $projects->count()]) }}
            </div>
        </div>

        @if ($projects->count() === 0)
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">folder_off</span>' :title="__('Keine inaktiven Projekte im gewählten Zeitraum.')" />
        @else
            <form method="POST" action="{{ route('reports.project-inactive.archive') }}">
                @csrf
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>
                                <input type="checkbox" id="rep-inactive-all" class="checkbox checkbox-xs" data-check-all='[name="project_ids[]"]' data-check-all-scope="document">
                            </x-table.th>
                            <x-table.th sort type="string">{{ __('Projekt') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Letzte Aktivität') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($projects as $project)
                        <tr>
                            <td>
                                <input type="checkbox" class="checkbox checkbox-xs" name="project_ids[]" value="{{ \App\Support\Sqid::encode(\App\Models\Project::class, $project->id) }}">
                            </td>
                            <td class="font-medium">{{ $project->name }}</td>
                            <td>{{ $project->customer?->name }}</td>
                            <td>{{ $project->status?->label() }}</td>
                            <td class="text-sm tabular-nums">
                                @php($last = $lastByProject[$project->id] ?? null)
                                {{ $last !== null ? \Illuminate\Support\Carbon::parse($last)->fdate() : '–' }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4 flex justify-end">
                    <x-button type="submit" tone="warning" class="gap-2"
                            data-confirm-dialog
                            data-confirm-message="{{ __('Ausgewählte Projekte wirklich archivieren?') }}"
                            data-confirm-icon="archive"
                            data-confirm-tone="warning"
                            data-confirm-label="{{ __('Archivieren') }}" icon="archive">{{ __('Ausgewählte archivieren') }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-page-shell>
@endsection
