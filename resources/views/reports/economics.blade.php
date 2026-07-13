{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : economics.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Wirtschaftlichkeit'))
@section('nav-title', __('Wirtschaftlichkeit'))

@section('content')
@php
    $eur = fn($v): string => number_format((float) $v, 2, ',', '.') . ' €';
    $pct = fn($v): string => number_format((float) $v, 2, ',', '.') . ' %';
    $min = fn($v): string => $v === null ? '–' : (string) $v;
    $signEur = fn($v): string => ($v > 0 ? '+' : '') . number_format((float) $v, 2, ',', '.') . ' €';
    $contribTone = fn($v): string => $v < 0 ? 'text-error' : ($v > 0 ? 'text-success' : '');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Deckungsbeitrag, Ranking und Plan-vs-Ist je Kunde und Projekt.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.economics', array_filter(['project_id' => \App\Support\Sqid::encode(\App\Models\Project::class, $projectId), 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.economics', array_filter(['project_id' => \App\Support\Sqid::encode(\App\Models\Project::class, $projectId), 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
                <x-help-button topic="reports.economics" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.economics')" :reset="route('reports.economics')">
        <x-filter-field :label="__('Projekt')" for="rep-project">
            <select id="rep-project" name="project_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($projects as $project)
                    <option value="{{ $project->sqid }}" @selected(\App\Support\Sqid::encode(\App\Models\Project::class, $projectId) === $project->sqid)>{{ $project->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

    <div role="alert" class="alert alert-info mb-4 text-sm">
        <span class="material-symbols-outlined" aria-hidden="true">info</span>
        <div>
            {{ __('Erlös = abrechenbare Zeiten (Satz) + abgerechnetes Material + abrechenbare Spesen. Kosten = interner Zeit-Kostensatz + Material- und Beleg-Direktaufwand. Maßgebliche Rechnungen führt das externe Fakturierungssystem; hier dienen die erfassten Beträge als Projektion.') }}
            @if($costRateMissing)
                <span class="font-medium">{{ __('Hinweis: Für einen Teil der Zeiten ist kein interner Kostensatz gepflegt – diese fließen mit 0 € Kosten ein, der Deckungsbeitrag ist insoweit zu optimistisch.') }}</span>
            @endif
        </div>
    </div>

    {{-- Feature 002: Zielwert Deckungsbeitrags-Marge (Soll/Ist) --}}
    @if($marginTarget !== null)
        <x-card class="mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold">{{ __('reporting.target.metric.contributionMargin') }}</div>
                    <div class="text-xs text-base-content/60">{{ __('reporting.target.subtitle') }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-2xl font-semibold tabular-nums {{ $contribTone($marginTarget['met'] ? 1 : -1) }}">{{ $actualMargin === null ? '–' : $pct($actualMargin) }}</span>
                    <x-reports.target-badge :eval="$marginTarget" />
                </div>
            </div>
        </x-card>
    @endif

    {{-- Ranking Top/Flop --}}
    <div class="grid gap-4 lg:grid-cols-2 mb-4">
        <x-card>
            <div class="mb-2 text-sm font-semibold">{{ __('Top 5 Projekte (Deckungsbeitrag)') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr><x-table.th>{{ __('Projekt') }}</x-table.th><x-table.th align="right">{{ __('Deckungsbeitrag') }}</x-table.th><x-table.th align="right">{{ __('Marge') }}</x-table.th></tr>
                </x-slot:head>
                @forelse($topProjects as $row)
                    <tr><td>{{ $row['projectName'] }}</td><td class="text-right tabular-nums {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td><td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <div class="mb-2 text-sm font-semibold">{{ __('Flop 5 Projekte (Deckungsbeitrag)') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr><x-table.th>{{ __('Projekt') }}</x-table.th><x-table.th align="right">{{ __('Deckungsbeitrag') }}</x-table.th><x-table.th align="right">{{ __('Marge') }}</x-table.th></tr>
                </x-slot:head>
                @forelse($flopProjects as $row)
                    <tr><td>{{ $row['projectName'] }}</td><td class="text-right tabular-nums {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td><td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <div class="mb-2 text-sm font-semibold">{{ __('Top 5 Kunden (Deckungsbeitrag)') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr><x-table.th>{{ __('Kunde') }}</x-table.th><x-table.th align="right">{{ __('Deckungsbeitrag') }}</x-table.th><x-table.th align="right">{{ __('Marge') }}</x-table.th></tr>
                </x-slot:head>
                @forelse($topCustomers as $row)
                    <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td><td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card>
            <div class="mb-2 text-sm font-semibold">{{ __('Flop 5 Kunden (Deckungsbeitrag)') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr><x-table.th>{{ __('Kunde') }}</x-table.th><x-table.th align="right">{{ __('Deckungsbeitrag') }}</x-table.th><x-table.th align="right">{{ __('Marge') }}</x-table.th></tr>
                </x-slot:head>
                @forelse($flopCustomers as $row)
                    <tr><td>{{ $row['customerName'] }}</td><td class="text-right tabular-nums {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td><td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-base-content/60">{{ __('Keine Daten') }}</td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>

    {{-- Kunden-Übersicht --}}
    <x-card class="mb-4">
        <div class="mb-3 text-sm font-semibold">{{ __('Wirtschaftlichkeit je Kunde') }}</div>
        @if(count($byCustomer) === 0)
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Daten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abrechenbar (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Nicht abrechenbar (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anteil %') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erlös') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Kosten') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Deckungsbeitrag') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Marge') }}</x-table.th>
                        <x-table.th align="right">{{ __('reporting.target.metric_label') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach($byCustomer as $row)
                    <tr>
                        <td class="font-medium">{{ $row['customerName'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['billableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['nonBillableMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $pct($row['nonBillableShare']) }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['revenue']) }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['cost']) }}@if($row['costRateMissing'])<span class="text-warning" title="{{ __('Kostensätze nicht vollständig gepflegt') }}"> *</span>@endif</td>
                        <td class="text-right tabular-nums font-medium {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td>
                        <td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td>
                        <td class="text-right">
                            <x-reports.target-badge :eval="$customerMarginTargets[$row['customerId']] ?? null" compact />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Projekt-Übersicht inkl. Plan-vs-Ist --}}
    <x-card>
        <div class="mb-3 text-sm font-semibold">{{ __('Wirtschaftlichkeit & Plan-vs-Ist je Projekt') }}</div>
        @if(count($byProject) === 0)
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Daten im gewählten Zeitraum.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Projekt') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erlös') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Kosten') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Deckungsbeitrag') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Marge') }}</x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="nacharbeit">{{ __('Nacharbeit (Min.)') }}</x-term></x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="kulanz">{{ __('Kulanz (Min.)') }}</x-term></x-table.th>
                        <x-table.th sort type="number" align="right"><x-term glossary="nacharbeit">{{ __('Nacharbeit %') }}</x-term></x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ist (Min.)') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Δ Min.') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan-Budget') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Δ Budget') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach($byProject as $row)
                    @php
                        // MVP-332 Belegtiefe: signierte Drilldowns je Kostenblock (Zeit/Material/Belege).
                        $drill = fn(string $kind, float $expected): string => \Illuminate\Support\Facades\URL::temporarySignedRoute('reports.economics.drilldown', now()->addHours(2), [
                            'kind' => $kind,
                            'project' => \App\Support\Sqid::encode(\App\Models\Project::class, $row['projectId']),
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'expected' => number_format($expected, 2, '.', ''),
                        ]);
                    @endphp
                    <tr>
                        <td class="font-medium">{{ $row['projectName'] }}</td>
                        <td>{{ $row['customerName'] }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['revenue']) }}</td>
                        <td class="text-right tabular-nums">
                            {{ $eur($row['cost']) }}@if($row['costRateMissing'])<span class="text-warning" title="{{ __('Kostensätze nicht vollständig gepflegt') }}"> *</span>@endif
                            <div class="whitespace-nowrap text-xs text-base-content/60">
                                <a class="link link-hover" href="{{ $drill('time', (float) $row['costTime']) }}" title="{{ __('Belegtiefe: Zeiteinträge') }}">{{ __('Zeit') }} {{ $eur($row['costTime']) }}</a>
                                · <a class="link link-hover" href="{{ $drill('material', (float) $row['costMaterial']) }}" title="{{ __('Belegtiefe: Material') }}">{{ __('Material') }} {{ $eur($row['costMaterial']) }}</a>
                                · <a class="link link-hover" href="{{ $drill('expense', (float) $row['costExpense']) }}" title="{{ __('Belegtiefe: Spesen/Belege') }}">{{ __('Belege') }} {{ $eur($row['costExpense']) }}</a>
                            </div>
                        </td>
                        <td class="text-right tabular-nums font-medium {{ $contribTone($row['contribution']) }}">{{ $eur($row['contribution']) }}</td>
                        <td class="text-right tabular-nums">{{ $pct($row['margin']) }}</td>
                        <td class="text-right tabular-nums">
                            <a class="link link-hover" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('reports.economics.drilldown', now()->addHours(2), ['kind' => 'rework', 'project' => $row['projectId'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $row['reworkMinutes'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            <a class="link link-hover" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('reports.economics.drilldown', now()->addHours(2), ['kind' => 'goodwill', 'project' => $row['projectId'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $row['goodwillMinutes'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $pct($row['reworkShare']) }}</td>
                        <td class="text-right tabular-nums">{{ $min($row['planMinutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['actualMinutes'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['planMinutesDelta'] === null ? '–' : (($row['planMinutesDelta'] > 0 ? '+' : '') . $row['planMinutesDelta']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['planBudget'] === null ? '–' : $eur($row['planBudget']) }}</td>
                        <td class="text-right tabular-nums {{ $row['planBudgetDelta'] !== null && $row['planBudgetDelta'] > 0 ? 'text-error' : '' }}">{{ $row['planBudgetDelta'] === null ? '–' : $signEur($row['planBudgetDelta']) }}</td>
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-2 text-xs text-base-content/60">{{ __('Plan-Werte stammen aus dem Projekt-Zeitbudget (Minuten) und Projekt-Budget (€). Projekte ohne gepflegten Plan zeigen „–".') }}</div>
        @endif
    </x-card>

    {{-- MVP-332: LV-Dimension (Feature 014 × 049) — nur mit Projektfilter. --}}
    @if($boqDimension !== null)
        @php
            $u = $boqDimension['unassigned'];
            $hasUnassigned = $u['cost'] > 0.0 || $u['timeMinutes'] > 0;
            [$boqAddenda, $boqMain] = collect($boqDimension['positions'])->partition(fn(array $p): bool => $p['isAddendum']);
            $multiBill = collect($boqDimension['positions'])->pluck('billId')->unique()->count() > 1;
        @endphp
        <x-card class="mt-4">
            <div class="mb-3 text-sm font-semibold">{{ __('Nachkalkulation je LV-Position') }}</div>
            @if(!$boqDimension['hasBoq'])
                <x-empty-state icon="list_alt" :title="__('Dieses Projekt führt kein Leistungsverzeichnis.')" :message="__('Die LV-Dimension steht für Projekte mit GAEB-Leistungsverzeichnis (Feature 049) zur Verfügung.')" />
            @elseif(count($boqDimension['positions']) === 0 && !$hasUnassigned)
                <x-empty-state icon="list_alt" :title="__('Keine LV-Bewegungen im gewählten Zeitraum.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Ordnungszahl') }}</x-table.th>
                            <x-table.th>{{ __('Position') }}</x-table.th>
                            @if($multiBill)
                                <x-table.th>{{ __('LV') }}</x-table.th>
                            @endif
                            <x-table.th align="right">{{ __('Aufmaß (Menge)') }}</x-table.th>
                            <x-table.th>{{ __('Einheit') }}</x-table.th>
                            <x-table.th align="right">{{ __('Erlös (Aufmaß × EP)') }}</x-table.th>
                            <x-table.th align="right">{{ __('Zeit (Min.)') }}</x-table.th>
                            <x-table.th align="right">{{ __('Kosten Zeit') }}</x-table.th>
                            <x-table.th align="right">{{ __('Kosten Material') }}</x-table.th>
                            <x-table.th align="right">{{ __('Kosten') }}</x-table.th>
                            <x-table.th align="right">{{ __('Deckungsbeitrag') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach($boqMain as $p)
                        <tr>
                            <td class="font-medium tabular-nums">{{ $p['referenceNo'] }}</td>
                            <td class="max-w-md truncate text-sm">{{ $p['shortText'] ?? '—' }}</td>
                            @if($multiBill)
                                <td class="text-sm">{{ $p['billName'] }}</td>
                            @endif
                            <td class="text-right tabular-nums">{{ number_format($p['measuredQuantity'], 3, ',', '.') }}</td>
                            <td>{{ $p['unit'] ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $eur($p['revenue']) }}</td>
                            <td class="text-right tabular-nums">{{ $p['timeMinutes'] }}</td>
                            <td class="text-right tabular-nums">{{ $eur($p['costTime']) }}</td>
                            <td class="text-right tabular-nums">{{ $eur($p['costMaterial']) }}</td>
                            <td class="text-right tabular-nums">{{ $eur($p['cost']) }}</td>
                            <td class="text-right tabular-nums font-medium {{ $contribTone($p['contribution']) }}">{{ $eur($p['contribution']) }}</td>
                        </tr>
                    @endforeach
                    @if($boqAddenda->isNotEmpty())
                        <tr class="bg-base-200/60">
                            <td colspan="{{ $multiBill ? 11 : 10 }}" class="text-xs font-semibold uppercase tracking-wide">{{ __('Nachträge') }}</td>
                        </tr>
                        @foreach($boqAddenda as $p)
                            <tr>
                                <td class="font-medium tabular-nums">{{ $p['referenceNo'] }} <span class="badge badge-outline badge-xs align-middle">{{ __('Nachtrag') }}</span></td>
                                <td class="max-w-md truncate text-sm">{{ $p['shortText'] ?? '—' }}</td>
                                @if($multiBill)
                                    <td class="text-sm">{{ $p['billName'] }}</td>
                                @endif
                                <td class="text-right tabular-nums">{{ number_format($p['measuredQuantity'], 3, ',', '.') }}</td>
                                <td>{{ $p['unit'] ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $eur($p['revenue']) }}</td>
                                <td class="text-right tabular-nums">{{ $p['timeMinutes'] }}</td>
                                <td class="text-right tabular-nums">{{ $eur($p['costTime']) }}</td>
                                <td class="text-right tabular-nums">{{ $eur($p['costMaterial']) }}</td>
                                <td class="text-right tabular-nums">{{ $eur($p['cost']) }}</td>
                                <td class="text-right tabular-nums font-medium {{ $contribTone($p['contribution']) }}">{{ $eur($p['contribution']) }}</td>
                            </tr>
                        @endforeach
                    @endif
                    @if($hasUnassigned)
                        <tr class="text-base-content/70">
                            <td class="font-medium">{{ __('Ohne LV-Zuordnung') }}</td>
                            <td class="text-sm">{{ __('Quellposten ohne Positions-Verknüpfung (inkl. aller Spesen)') }}</td>
                            @if($multiBill)
                                <td>—</td>
                            @endif
                            <td class="text-right">—</td>
                            <td>—</td>
                            <td class="text-right">—</td>
                            <td class="text-right tabular-nums">{{ $u['timeMinutes'] }}</td>
                            <td class="text-right tabular-nums">{{ $eur($u['costTime']) }}</td>
                            <td class="text-right tabular-nums">{{ $eur($u['costMaterial']) }}</td>
                            <td class="text-right tabular-nums">{{ $eur($u['cost']) }}</td>
                            <td class="text-right">—</td>
                        </tr>
                    @endif
                </x-table>
                <div class="mt-2 text-xs text-base-content/60">{{ __('Erlös je Position = im Zeitraum aufgemessene Menge × Einheitspreis (Projektion der Abrechnung nach Aufmaß). Kostenzuordnung über Bautagebuch-/Material-Verknüpfungen der Aufmaß-Meldungen und Positions-Mappings; mehrdeutige oder fehlende Verknüpfungen sowie Spesen (ohne LV-Anker) erscheinen unter „Ohne LV-Zuordnung". Kosten der Positionen + „Ohne LV-Zuordnung" entsprechen den Projektkosten.') }}</div>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
