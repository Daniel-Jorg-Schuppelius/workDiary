{{--
  Created on   : Fri Jun 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dashboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auditbereitschafts-Dashboard (Feature 044, MVP 1): KPI-Kacheln mit
  Warn-Tones + kompakte Drill-down-Listen je Geltungsbereich (Scope-
  Wechsler, Muster Anforderungen-/Zertifizierungen-Seite). Reines
  Blade/CSS — keine Charts, kein zusätzliches JS. Kennzahl „ungeprüfte
  Lieferanten" entfällt bewusst (kein Lieferantenmodul, MVP 2).
  Variablen: $scope, $scopes, $readiness (ReadinessService::forScope())
--}}

@extends('layouts.app')

@section('title', __('isms.title.dashboard'))
@section('nav-title', __('isms.title.dashboard'))

@section('content')
    <x-index-page :subtitle="$scope !== null ? __('isms.subtitle.dashboard_scope', ['scope' => $scope->name]) : __('isms.subtitle.dashboard')">
        <x-slot:actions>
            <x-icon-btn icon="inventory_2" tone="outline" size="sm"
                        :href="route('isms.packages.index')"
                        show-label>{{ __('isms.title.packages') }}</x-icon-btn>
        </x-slot:actions>

        @if ($scopes->count() > 1)
            <x-filter-bar :action="route('isms.dashboard')" :reset="null">
                <x-filter-field :label="__('isms.field.scope')" for="isms-dash-scope" class="min-w-44">
                    <select id="isms-dash-scope" name="scope" class="select select-sm select-bordered w-full">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($scope !== null && $scope->is($scopeOption))>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        @endif

        @if ($scope === null || $readiness === null)
            <x-empty-state framed
                           :title="__('isms.empty_scopes_title')"
                           :message="__('isms.empty_scopes')" />
        @else
            @php
                $certificateAlerts = $readiness['certificates']
                    ->filter(fn(array $row): bool => $row['expiring'] || $row['surveillance_soon'])
                    ->count();
            @endphp

            {{-- KPI-Kacheln: Zähler mit Warn-Tones + Drill-down-Links --}}
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-kpi-tile :label="__('isms.dashboard.kpi_high_risks')"
                            :value="$readiness['high_risks']['count']"
                            :tone="$readiness['high_risks']['count'] > 0 ? 'error' : 'success'"
                            :hint="__('isms.dashboard.kpi_high_risks_hint')"
                            :href="route('isms.risks.index', ['sort' => 'score'])" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_overdue_reviews')"
                            :value="$readiness['reviews']['overdue_count']"
                            :tone="$readiness['reviews']['overdue_count'] > 0 ? 'warning' : 'success'"
                            :hint="__('isms.dashboard.kpi_overdue_reviews_hint')"
                            :href="route('isms.risks.index', ['sort' => 'review'])" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_unassessed')"
                            :value="$readiness['reviews']['unassessed_count']"
                            :tone="$readiness['reviews']['unassessed_count'] > 0 ? 'warning' : 'success'"
                            :hint="__('isms.dashboard.kpi_unassessed_hint')"
                            :href="route('isms.risks.index')" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_evidence_gaps')"
                            :value="$readiness['evidence_gaps']['count']"
                            :tone="$readiness['evidence_gaps']['count'] > 0 ? 'warning' : 'success'"
                            :hint="__('isms.dashboard.kpi_evidence_gaps_hint')"
                            :href="route('isms.requirements.index', ['scope' => $scope->sqid])" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_overdue_actions')"
                            :value="$readiness['actions']['overdue_count']"
                            :tone="$readiness['actions']['overdue_count'] > 0 ? 'error' : 'success'"
                            :hint="__('isms.dashboard.kpi_overdue_actions_hint')"
                            :href="route('isms.audits.index')" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_nonconformities')"
                            :value="$readiness['nonconformities']['open_count']"
                            :tone="$readiness['nonconformities']['open_count'] > 0 ? 'error' : 'success'"
                            :hint="__('isms.dashboard.kpi_nonconformities_hint')"
                            :href="route('isms.audits.index')" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_certificates')"
                            :value="$certificateAlerts"
                            :tone="$certificateAlerts > 0 ? 'warning' : 'success'"
                            :hint="__('isms.dashboard.kpi_certificates_hint')"
                            :href="route('isms.conformity.index', ['scope' => $scope->sqid])" />
                <x-kpi-tile :label="__('isms.dashboard.kpi_software_eol')"
                            :value="$readiness['software']['eol_count']"
                            :tone="$readiness['software']['eol_count'] > 0 ? 'error' : 'success'"
                            :hint="__('isms.dashboard.kpi_software_eol_hint')"
                            :href="route('isms.software.index')" />
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                {{-- SoA-Fortschritt je Norm --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_soa') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.requirements.index', ['scope' => $scope->sqid]) }}">{{ __('isms.dashboard.open_register') }}</a>
                    </div>
                    @if ($readiness['soa']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_soa') }}</p>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('isms.field.norm') }}</th>
                                    <th class="text-center">{{ __('isms.dashboard.soa_total') }}</th>
                                    <th class="text-center">{{ __('isms.dashboard.soa_applicable') }}</th>
                                    <th class="w-44">{{ __('isms.dashboard.soa_quote') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($readiness['soa'] as $norm)
                                    <tr>
                                        <td class="font-medium">{{ $norm['norm'] }}</td>
                                        <td class="text-center text-base-content/70">{{ $norm['total'] }}</td>
                                        <td class="text-center text-base-content/70">{{ $norm['applicable'] }}</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <progress class="progress {{ $norm['quote'] >= 80 ? 'progress-success' : ($norm['quote'] >= 40 ? 'progress-warning' : 'progress-error') }} w-24"
                                                          value="{{ $norm['quote'] }}" max="100"></progress>
                                                <span class="text-xs text-base-content/70">{{ $norm['quote'] }}&nbsp;% ({{ $norm['covered'] }}/{{ $norm['applicable'] }})</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-card>

                {{-- Hohe Risiken (Top 5) --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_high_risks') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.risks.index', ['sort' => 'score']) }}">{{ __('isms.dashboard.open_register') }}</a>
                    </div>
                    @if ($readiness['high_risks']['top']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_block') }}</p>
                    @else
                        <ul class="divide-y divide-base-200 text-sm">
                            @foreach ($readiness['high_risks']['top'] as $risk)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $risk->displayNo() }}</span>
                                        {{ $risk->title }}
                                        @if ($risk->owner !== null)
                                            <span class="text-xs text-base-content/50">· {{ $risk->owner->name }}</span>
                                        @endif
                                    </span>
                                    <x-status-badge :tone="\App\Models\Isms\IsmsRisk::scoreTone($risk->score)">{{ $risk->score }}</x-status-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- Bewertungs-Reviews: überfällig + unbewertet --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_reviews') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.risks.index', ['sort' => 'review']) }}">{{ __('isms.dashboard.open_register') }}</a>
                    </div>
                    @if ($readiness['reviews']['overdue']->isEmpty() && $readiness['reviews']['unassessed']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_block') }}</p>
                    @else
                        <ul class="divide-y divide-base-200 text-sm">
                            @foreach ($readiness['reviews']['overdue'] as $risk)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $risk->displayNo() }}</span>
                                        {{ $risk->title }}
                                    </span>
                                    <x-status-badge tone="warning" outline>{{ __('isms.dashboard.review_overdue') }}</x-status-badge>
                                </li>
                            @endforeach
                            @foreach ($readiness['reviews']['unassessed'] as $risk)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $risk->displayNo() }}</span>
                                        {{ $risk->title }}
                                    </span>
                                    <x-status-badge tone="ghost" outline>{{ __('isms.dashboard.review_unassessed') }}</x-status-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- Nachweislücken (Top 10) --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_evidence_gaps') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.requirements.index', ['scope' => $scope->sqid, 'applicable' => 'yes']) }}">{{ __('isms.dashboard.open_register') }}</a>
                    </div>
                    @if ($readiness['evidence_gaps']['top']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_block') }}</p>
                    @else
                        <ul class="divide-y divide-base-200 text-sm">
                            @foreach ($readiness['evidence_gaps']['top'] as $statement)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $statement->requirement?->ref_no }}</span>
                                        {{ $statement->requirement?->title }}
                                        <span class="text-xs text-base-content/50">· {{ $statement->requirement?->normLabel() }}</span>
                                    </span>
                                    <x-status-badge :tone="$statement->implementation_status->tone()" outline>{{ $statement->implementation_status->label() }}</x-status-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- Überfällige Korrekturmaßnahmen + offene Nichtkonformitäten --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_actions') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.audits.index') }}">{{ __('isms.dashboard.open_audits') }}</a>
                    </div>
                    @if ($readiness['actions']['overdue']->isEmpty() && $readiness['nonconformities']['open']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_block') }}</p>
                    @else
                        <ul class="divide-y divide-base-200 text-sm">
                            @foreach ($readiness['actions']['overdue'] as $action)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $action->finding?->ismsAudit?->displayNo() }}</span>
                                        {{ $action->title }}
                                    </span>
                                    <x-status-badge tone="error" outline>{{ __('isms.dashboard.due_since', ['date' => $action->due_on?->format('d.m.Y')]) }}</x-status-badge>
                                </li>
                            @endforeach
                            @foreach ($readiness['nonconformities']['open'] as $finding)
                                <li class="flex items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs text-base-content/60">{{ $finding->ismsAudit?->displayNo() }} {{ $finding->displayNo() }}</span>
                                        {{ $finding->title }}
                                    </span>
                                    <x-status-badge :tone="$finding->kind->tone()" outline>{{ $finding->kind->label() }}</x-status-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- Zertifikate & Termine --}}
                <x-card>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">{{ __('isms.dashboard.section_certificates') }}</h3>
                        <a class="link text-xs" href="{{ route('isms.conformity.index', ['scope' => $scope->sqid]) }}">{{ __('isms.dashboard.open_register') }}</a>
                    </div>
                    @if ($readiness['certificates']->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('isms.dashboard.empty_certificates') }}</p>
                    @else
                        <ul class="divide-y divide-base-200 text-sm">
                            @foreach ($readiness['certificates'] as $row)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span class="font-medium">{{ $row['norm'] }}</span>
                                    <span class="flex flex-wrap items-center gap-2">
                                        <x-status-badge :tone="$row['status']->tone()">{{ $row['status']->label() }}</x-status-badge>
                                        @if ($row['valid_until'] !== null)
                                            <span class="text-xs {{ $row['expiring'] ? 'font-semibold text-warning' : 'text-base-content/60' }}">
                                                {{ __('isms.dashboard.valid_until_short', ['date' => $row['valid_until']->format('d.m.Y')]) }}
                                            </span>
                                        @endif
                                        @if ($row['next_surveillance'] !== null)
                                            <span class="text-xs {{ $row['surveillance_soon'] ? 'font-semibold text-warning' : 'text-base-content/60' }}">
                                                {{ __('isms.dashboard.surveillance_short', ['date' => $row['next_surveillance']->format('d.m.Y')]) }}
                                            </span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        @endif
    </x-index-page>
@endsection
