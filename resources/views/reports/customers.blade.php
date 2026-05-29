@extends('layouts.app')
@section('title', __('Kundenanalyse'))
@section('nav-title', __('Kundenanalyse'))

@section('content')
@php
    $fmt = function (int $min): string {
        $sign = $min < 0 ? '-' : '';
        $abs = abs($min);

        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Aufwand, Nacharbeit und Nicht-Abrechenbares je Kunde.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customers', array_filter(['min_minutes' => $minMinutes > 0 ? $minMinutes : null, 'project_id' => $projectId, 'user_id' => sqid(\App\Models\User::class, $userId), 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customers', array_filter(['min_minutes' => $minMinutes > 0 ? $minMinutes : null, 'project_id' => $projectId, 'user_id' => sqid(\App\Models\User::class, $userId), 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.customer-analysis" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.customers')" :reset="route('reports.customers')">
        <x-filter-field :label="__('Mindest-Aufwand (Minuten)')" for="rep-min-minutes">
            <input id="rep-min-minutes" type="number" name="min_minutes" value="{{ $minMinutes }}" min="0" class="input input-sm input-bordered w-36" />
        </x-filter-field>

        <x-filter-field :label="__('Projekt')" for="rep-project">
            <select id="rep-project" name="project_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Mitarbeiter')" for="rep-user">
            <select id="rep-user" name="user_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($reportUsers as $reportUser)
                    <option value="{{ $reportUser->sqid }}" @selected(sqid(\App\Models\User::class, $userId) === $reportUser->sqid)>{{ $reportUser->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-table bare>
            <x-slot:head>
                <tr><th>{{ __('Top 5 Aufwand') }}</th><th class="text-right">{{ __('Min.') }}</th></tr>
            </x-slot:head>
            @forelse($topByMinutes as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['totalMinutes'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>

        <x-table bare>
            <x-slot:head>
                <tr><th>{{ __('Top 5 Nacharbeit') }}</th><th class="text-right">{{ __('Einträge') }}</th></tr>
            </x-slot:head>
            @forelse($topByRework as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['reworkEntryCount'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>

        <x-table bare>
            <x-slot:head>
                <tr><th>{{ __('Top 5 nicht abrechenbar') }}</th><th class="text-right">{{ __('Min.') }}</th></tr>
            </x-slot:head>
            @forelse($topByNonBillable as $row)
                <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums">{{ $row['nonBillableMinutes'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </x-table>
    </div>

    <x-card class="mt-4">
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if($rows->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Kundendaten im gewählten Zeitraum.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kunde') }}</th>
                        <th class="text-right">{{ __('Aufträge') }}</th>
                        <th class="text-right">{{ __('Gesamt') }}</th>
                        <th class="text-right">{{ __('Abrechenbar') }}</th>
                        <th class="text-right">{{ __('Nicht abrechenbar') }}</th>
                        <th class="text-right">{{ __('Anteil %') }}</th>
                        <th class="text-right">{{ __('Nacharbeit') }}</th>
                        <th class="text-right">{{ __('Offene Punkte') }}</th>
                        <th class="text-right">{{ __('Eskaliert') }}</th>
                        <th class="text-right">{{ __('Ø Min./Auftrag') }}</th>
                        <th class="text-right">{{ __('Trend 30d') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($rows as $row)
                    @php
                        $drilldownBase = [
                            'customer' => sqid(\App\Models\Customer::class, $row['customerId']),
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'project' => sqid(\App\Models\Project::class, $projectId),
                            'user' => $userId,
                        ];
                        $reportDrilldownBase = array_filter([
                            'customer_id' => $row['customerId'],
                            'project_id' => $projectId,
                            'user_id' => $userId,
                        ]);
                    @endphp
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">
                                {{ $row['customerName'] }}
                            </a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">{{ $row['entryCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums" title="{{ $fmt($row['totalMinutes']) }}">
                            <a href="{{ route('diary.index', $drilldownBase) }}" class="link link-hover">{{ $row['totalMinutes'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['billableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['nonBillableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ number_format((float) $row['nonBillableShare'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.protocols', $reportDrilldownBase) }}" class="link link-hover">{{ $row['reworkEntryCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.open-issues', $reportDrilldownBase) }}" class="link link-hover">{{ $row['openIssueCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.customers.drilldown.open-issues', array_merge($reportDrilldownBase, ['escalated' => 1])) }}" class="link link-hover">{{ $row['escalationCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['avgEntryMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['trend30d'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
