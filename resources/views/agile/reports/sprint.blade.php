{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sprint.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Sprint-Cockpit (Feature 064, P8/MVP-146): Burndown/Burnup, Velocity mit
     Commitment-Erfüllung, Scope-Zusammensetzung, Qualitätsreihe und
     unveränderlicher Abschlussbericht — alles aus Events/Snapshots
     (metric_version im Datenstand). --}}

@extends('layouts.app')

@section('title', __('Sprint-Cockpit') . ' — ' . $project->name)
@section('nav-title', __('Sprint-Cockpit'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Sprint-Cockpit') }} — {{ $project->name }}</x-slot:title>
        <x-slot:subtitle>{{ __('Kennzahlen aus Ereignissen und Snapshots (Definition v:version).', ['version' => $velocity->metricVersion]) }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm" :href="route('agile.reports.export.pdf', $project)" show-label>PDF</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('agile.reports.export.csv', [$project, 'velocity'])" show-label>{{ __('CSV Velocity') }}</x-icon-btn>
            <x-icon-btn icon="view_kanban" tone="ghost" size="sm" :href="route('agile.board', $project)" show-label>{{ __('Zum Board') }}</x-icon-btn>
            <x-icon-btn icon="sprint" tone="ghost" size="sm" :href="route('agile.sprints', $project)" show-label>{{ __('Sprints') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($sprints->isNotEmpty())
        <form method="GET" action="{{ route('agile.reports.sprint', $project) }}" class="flex items-center gap-2">
            <select name="sprint" class="select select-sm select-bordered" aria-label="{{ __('Sprint wählen') }}" data-autosubmit>
                @foreach ($sprints as $candidate)
                    <option value="{{ $candidate->sqid }}" @selected($sprint?->id === $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </select>
            <noscript><x-icon-btn icon="filter_alt" tone="ghost" size="sm" type="submit" :label="__('Anzeigen')" /></noscript>
        </form>
    @endif

    @if ($sprint === null)
        <x-empty-state icon="monitoring" framed
                       :title="__('Noch kein gestarteter Sprint vorhanden.')"
                       :message="__('Das Cockpit füllt sich, sobald ein Sprint gestartet wurde.')" />
    @else
        <div class="grid gap-3 xl:grid-cols-2">
            @if ($burndown !== null)
                <x-charts.line :title="__('Burndown — :name', ['name' => $sprint->name])"
                               :unit="__('Story Points (verbleibend)')"
                               :computed-at="$burndown->computedAt"
                               :ideal="true"
                               :x-label="__('Datum')"
                               :series="collect($burndown->data['series'])->map(fn($row) => ['x' => $row['date'], 'y' => $row['remaining']])->all()" />
                <x-charts.line :title="__('Burnup — :name', ['name' => $sprint->name])"
                               :unit="__('Story Points (erledigt/Umfang)')"
                               :computed-at="$burndown->computedAt"
                               :x-label="__('Datum')"
                               :y-label="__('Erledigt')"
                               :series="$burnup" />
            @endif

            <x-charts.bar :title="__('Velocity je Sprint')"
                          :unit="__('Story Points')"
                          :computed-at="$velocity->computedAt"
                          :median="$velocity->data['median'] > 0 ? $velocity->data['median'] : null"
                          :x-label="__('Sprint')"
                          :y-label="__('Erledigt')"
                          :y2-label="__('Zugesagt')"
                          :series="collect($velocity->data['sprints'])->map(fn($row) => ['x' => $row['sprint'], 'y' => $row['done_points'], 'y2' => $row['committed_points']])->all()" />

            <x-charts.bar :title="__('Qualitätsreihe (Wiederöffnungen und Übersteuerungen)')"
                          :unit="__('Ereignisse je Woche')"
                          :computed-at="$quality->computedAt"
                          :x-label="__('Woche')"
                          :y-label="__('Wiederöffnungen')"
                          :y2-label="__('Übersteuerungen')"
                          :series="collect($quality->data['weeks'])->map(fn($row, $week) => ['x' => $week, 'y' => $row['reopened'], 'y2' => $row['overrides']])->values()->all()" />
        </div>

        @if ($scope !== null)
            <x-card :title="__('Scope-Zusammensetzung — :name', ['name' => $sprint->name])">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Zugesagt (bei Start)')">
                        {{ __(':items Elemente, :points Punkte', ['items' => $scope['committed_items'], 'points' => $scope['committed_points']]) }}
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('Nach Start hinzugefügt')">
                        {{ __(':items Elemente, :points Punkte', ['items' => $scope['added_items'], 'points' => $scope['added_points']]) }}
                    </x-detail-grid.row>
                </x-detail-grid>
            </x-card>
        @endif

        @if ($sprint->capacity_snapshot !== null)
            <x-card :title="__('Kapazität beim Start — :name', ['name' => $sprint->name])">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Basis (Arbeitszeitmodelle)')">{{ $sprint->capacity_snapshot['base_hours'] }} h</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Abwesenheiten (genehmigt)')">−{{ $sprint->capacity_snapshot['absence_hours'] }} h</x-detail-grid.row>
                    @if (($sprint->capacity_snapshot['adjustment_hours'] ?? 0) != 0)
                        <x-detail-grid.row :label="__('Manuelle Korrektur')">
                            {{ $sprint->capacity_snapshot['adjustment_hours'] }} h — {{ $sprint->capacity_snapshot['adjustment_reason'] }}
                        </x-detail-grid.row>
                    @endif
                    <x-detail-grid.row :label="__('Verfügbar gesamt')">{{ $sprint->capacity_snapshot['total_hours'] }} h</x-detail-grid.row>
                </x-detail-grid>
            </x-card>
        @endif

        @if ($sprint->completion_snapshot !== null)
            <x-card :title="__('Sprintabschlussbericht — :name (unveränderlich)', ['name' => $sprint->name])">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Zugesagte Punkte')">{{ $sprint->completion_snapshot['committed_points'] ?? 0 }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Erledigte Punkte')">{{ $sprint->completion_snapshot['done_points'] ?? 0 }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Offen übergeben')">{{ __(':items Elemente, :points Punkte', ['items' => $sprint->completion_snapshot['open_items'] ?? 0, 'points' => $sprint->completion_snapshot['open_points'] ?? 0]) }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Scope-Zugänge nach Start')">{{ $sprint->completion_snapshot['scope_added'] ?? 0 }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Abgeschlossen am')">{{ $sprint->completed_at?->isoFormat('L LT') ?? '—' }}</x-detail-grid.row>
                </x-detail-grid>
            </x-card>
        @endif

        {{-- Epic-Fortschritt (Vollaudit 2026-07, M25 / MVP-146): Kinder über
             task.parent_task_id, erledigt = Spalte mit Kategorie done. --}}
        @if ($epicProgress !== [])
            <x-card :title="__('Epic-Fortschritt')">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Epic') }}</th>
                            <th class="text-right">{{ __('Elemente erledigt') }}</th>
                            <th class="text-right">{{ __('Punkte erledigt') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($epicProgress as $row)
                        <tr>
                            <td>{{ $row['epic']->task?->title }}</td>
                            <td class="text-right tabular-nums">{{ $row['done'] }}/{{ $row['total'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['points_done'] }}/{{ $row['points_total'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        @if ($milestones->isNotEmpty())
            <x-card :title="__('Meilenstein-Fortschritt')">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Meilenstein') }}</th>
                            <th>{{ __('Fällig') }}</th>
                            <th class="text-right">{{ __('Aufgaben erledigt') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($milestones as $milestone)
                        <tr>
                            <td>{{ $milestone->title }}</td>
                            <td>{{ $milestone->due_date?->isoFormat('L') ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $milestone->done_tasks_count }}/{{ $milestone->tasks_count }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
