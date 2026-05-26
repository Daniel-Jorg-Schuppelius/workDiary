@extends('layouts.app')
@section('title', __('Inaktive Projekte'))
@section('nav-title', __('Inaktive Projekte'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Projekte ohne Zeiteinträge im Zeitraum — optional in einem Schritt archivieren.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.project-inactive', ['export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.project-inactive', ['export' => 'xlsx'])"
                            show-label>XLSX</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">
                {{ __('Keine Aktivität im Zeitraum') }}
                <span class="text-xs uppercase tracking-[0.18em] text-base-content/60 ml-2">
                    {{ $rangeFrom->format('d.m.Y') }} – {{ $rangeTo->format('d.m.Y') }}
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
                                <input type="checkbox" id="rep-inactive-all" class="checkbox checkbox-xs" onclick="document.querySelectorAll('[name=&quot;project_ids[]&quot;]').forEach(c=>c.checked=this.checked)">
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
                                <input type="checkbox" class="checkbox checkbox-xs" name="project_ids[]" value="{{ $project->id }}">
                            </td>
                            <td class="font-medium">{{ $project->name }}</td>
                            <td>{{ $project->customer?->name }}</td>
                            <td>{{ $project->status?->value }}</td>
                            <td class="text-sm tabular-nums">
                                @php($last = $lastByProject[$project->id] ?? null)
                                {{ $last !== null ? \Illuminate\Support\Carbon::parse($last)->format('d.m.Y') : '–' }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn btn-sm btn-warning gap-2"
                            data-confirm-dialog="{{ __('Ausgewählte Projekte wirklich archivieren?') }}">
                        <span class="material-symbols-outlined" aria-hidden="true">archive</span>
                        {{ __('Ausgewählte archivieren') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection
