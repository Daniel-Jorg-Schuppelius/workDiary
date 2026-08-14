@extends('layouts.app')

@section('title', __('Auditbericht Prüfwesen'))
@section('nav-title', __('Auditbericht'))

@section('content')
<x-index-page :subtitle="__('Fällige und überfällige Prüfungen, Sperren, Abweichungen und Prüfquote mit Drilldown.')">
    <x-slot:actions>
        {{-- CSV-Export (MVP-292; Vollaudit 2026-07, M33). --}}
        <x-icon-btn icon="download" tone="ghost" size="sm"
                    :href="route('asset-compliance.reports.index', ['export' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString()])"
                    show-label>{{ __('CSV') }}</x-icon-btn>
        <x-icon-btn icon="table_view" tone="ghost" size="sm"
                    :href="route('asset-compliance.reports.index', ['export' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString()])"
                    show-label>Excel</x-icon-btn>
        <form method="POST" action="{{ route('asset-compliance.reports.snapshot', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
            @csrf
            <button type="submit" class="btn btn-sm">{{ __('Snapshot einfrieren') }}</button>
        </form>
    </x-slot:actions>

    <x-filter-bar :action="route('asset-compliance.reports.index')" :reset="route('asset-compliance.reports.index')">
        <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" />
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Prüfpflichten')" :value="$assignmentCount" />
        <x-kpi-tile :label="__('Überfällig')" :value="$overdueCount" />
        <x-kpi-tile :label="__('Gesperrt (Prüfwesen)')" :value="$blockedCount" />
        <x-kpi-tile :label="__('Prüfquote')" :value="$passRate !== null ? $passRate . ' %' : '—'" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Prüfungen im Zeitraum')" :value="$inspectionCount" />
        <x-kpi-tile :label="__('Nicht bestanden')" :value="$failedCount" />
        <x-kpi-tile :label="__('Zertifikate')" :value="$certificateCount" />
        <x-kpi-tile :label="__('Bald fällig')" :value="$dueSoonCount" />
    </div>

    {{-- Prüfkosten (MVP-291; Vollaudit 2026-07, M33). --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Prüfkosten im Zeitraum')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $totalCost, 2, withThousandsSeparator: true) . ' €'" />
        @foreach (collect($costByKind)->take(3) as $kind => $kindCost)
            <x-kpi-tile :label="__('Kosten') . ' · ' . $kind" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $kindCost, 2, withThousandsSeparator: true) . ' €'" />
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Prüfpflichten nach Prüfart')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Prüfart') }}</th><th class="text-right">{{ __('Pflichten') }}</th></tr></x-slot:head>
                @forelse ($byKind as $kind => $count)
                    <tr>
                        <td>{{ \App\Enums\AssetCompliance\AssetInspectionKind::tryFrom($kind)?->label() ?? $kind }}</td>
                        <td class="text-right font-mono">{{ $count }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" :title="__('Keine Prüfpflichten.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Prüfungen nach Prüfer (Top 10)')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Prüfer') }}</th><th class="text-right">{{ __('Prüfungen') }}</th></tr></x-slot:head>
                @forelse ($byInspector as $inspector => $count)
                    <tr>
                        <td>{{ $inspector }}</td>
                        <td class="text-right font-mono">{{ $count }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" :title="__('Keine Prüfungen im Zeitraum.')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    <x-card :title="__('Abweichungen (nicht bestanden)')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Asset') }}</th><th>{{ __('Zeitpunkt') }}</th><th>{{ __('Bemerkung') }}</th></tr></x-slot:head>
            @forelse ($deviations as $row)
                <tr>
                    <td>{{ $row['asset'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['performed_at'])->fdatetime() }}</td>
                    <td class="text-sm">{{ $row['note'] ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="3" :title="__('Keine Abweichungen im Zeitraum.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Eingefrorene Snapshots (P2)')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Zeitraum') }}</th><th>{{ __('Erstellt') }}</th><th class="text-right">{{ __('Überfällig') }}</th><th class="text-right">{{ __('Prüfquote') }}</th></tr></x-slot:head>
            @forelse ($snapshots as $snapshot)
                <tr>
                    <td>{{ $snapshot->period_start->fdate() }} – {{ $snapshot->period_end->fdate() }}</td>
                    <td>{{ $snapshot->created_at?->fdatetime() }}</td>
                    <td class="text-right font-mono">{{ data_get($snapshot->payload, 'overdueCount', 0) }}</td>
                    <td class="text-right font-mono">{{ data_get($snapshot->payload, 'passRate') !== null ? data_get($snapshot->payload, 'passRate') . ' %' : '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Noch keine Snapshots eingefroren.')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
