{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : readiness.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): begründete
  SELBSTEINSCHÄTZUNG je Geltungsbereich — Ampel/Score je Domäne +
  Gesamteinschätzung „intern auditbereit?" mit Begründung. NIE eine
  automatische Konformitätsbehauptung (046-Prinzip): der Disclaimer macht
  den Selbsteinschätzungs-Charakter unmissverständlich.
  Variablen: $scope, $scopes, $assessment (ReadinessAssessmentService::forScope())
--}}

@extends('layouts.app')

@section('title', __('isms.title.readiness'))
@section('nav-title', __('isms.title.readiness'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.readiness')">
        <x-slot:actions>
            <x-icon-btn icon="monitoring" tone="outline" size="sm"
                        :href="route('isms.dashboard', $scope !== null ? ['scope' => $scope->sqid] : [])"
                        show-label>{{ __('isms.title.dashboard') }}</x-icon-btn>
        </x-slot:actions>

        @if ($scopes->count() > 1)
            <x-filter-bar :action="route('isms.readiness')" :reset="null">
                <x-filter-field :label="__('isms.field.scope')" for="isms-readiness-scope" class="min-w-44">
                    <select id="isms-readiness-scope" name="scope" class="select select-sm select-bordered w-full">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($scope !== null && $scope->is($scopeOption))>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        @endif

        {{-- Selbsteinschätzungs-Disclaimer: prominent und immer sichtbar
             (046-Prinzip: keine automatische Konformitätsbehauptung). --}}
        <div class="alert alert-info bg-info/10 border-info/30 text-sm" role="note">
            <x-icon name="info" />
            <span>{{ __('isms.readiness.disclaimer') }}</span>
        </div>

        @if ($scope === null || $assessment === null)
            <x-empty-state framed
                           :title="__('isms.empty_scopes_title')"
                           :message="__('isms.empty_scopes')" />
        @else
            {{-- Gesamteinschätzung: Ampel + „intern auditbereit?" mit Begründung. --}}
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-base-content/50">{{ __('isms.readiness.overall_label') }}</div>
                        <div class="mt-1 flex items-center gap-3">
                            <span class="text-3xl font-bold text-{{ $assessment['overall_tone'] }}">{{ $assessment['overall_score'] }}<span class="text-base text-base-content/50">/100</span></span>
                            @if ($assessment['audit_ready'])
                                <x-status-badge tone="success">{{ __('isms.readiness.ready_yes') }}</x-status-badge>
                            @else
                                <x-status-badge tone="error">{{ __('isms.readiness.ready_no') }}</x-status-badge>
                            @endif
                        </div>
                    </div>
                    <p class="max-w-xl text-sm text-base-content/70">
                        {{ $assessment['audit_ready'] ? __('isms.readiness.verdict_ready') : __('isms.readiness.verdict_not_ready') }}
                    </p>
                </div>

                @if ($assessment['blockers'] !== [])
                    <div class="mt-4">
                        <h3 class="mb-2 text-sm font-semibold">{{ __('isms.readiness.blockers') }}</h3>
                        <ul class="space-y-1 text-sm">
                            @foreach ($assessment['blockers'] as $blocker)
                                <li class="flex items-start gap-2">
                                    <x-icon name="block" class="mt-0.5 text-error" />
                                    <span>
                                        <span class="font-medium">{{ __('isms.readiness.domain.' . $blocker['domain']) }}:</span>
                                        {{ $blocker['reason'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-card>

            {{-- Reifegrad je Domäne: Ampel, Score-Balken und begründende Signale. --}}
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($assessment['domains'] as $domain)
                    <x-card>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">{{ __('isms.readiness.domain.' . $domain['key']) }}</h3>
                            <x-status-badge :tone="$domain['tone']">{{ __('isms.readiness.level.' . $domain['level']) }}</x-status-badge>
                        </div>
                        <div class="mb-3 flex items-center gap-2">
                            <progress class="progress progress-{{ $domain['tone'] }} w-full" value="{{ $domain['score'] }}" max="100"></progress>
                            <span class="w-12 text-right text-xs text-base-content/70">{{ $domain['score'] }}&nbsp;%</span>
                        </div>
                        <ul class="space-y-1 text-xs text-base-content/70">
                            @foreach ($domain['signals'] as $signal)
                                <li class="flex items-start gap-1.5">
                                    <x-icon name="chevron_right" class="mt-0.5 text-base-content/40" />
                                    <span>{{ $signal }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endforeach
            </div>
        @endif
    </x-index-page>
@endsection
