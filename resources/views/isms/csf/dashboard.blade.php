{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dashboard.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  NIST-CSF-2.0-Funktionsabdeckung (Nachtrag NIST): Abdeckung der sechs
  CSF-Funktionen je Geltungsbereich — direkt aus der NIST-SoA oder
  abgeleitet aus der ISO/IEC-27001-SoA via Crosswalk. Reines Blade/CSS,
  keine Charts. Variablen: $scope, $scopes, $readiness (CsfReadinessService).
--}}

@extends('layouts.app')

@section('title', __('isms.title.csf'))
@section('nav-title', __('isms.title.csf'))

@section('content')
    <x-index-page :subtitle="$scope !== null ? __('isms.subtitle.csf_scope', ['scope' => $scope->name]) : __('isms.subtitle.csf')">
        <x-slot:actions>
            <x-icon-btn icon="compare_arrows" tone="outline" size="sm"
                        :href="route('isms.csf.crosswalk', $scope !== null ? ['scope' => $scope->sqid] : [])"
                        show-label>{{ __('isms.csf.action_crosswalk') }}</x-icon-btn>
        </x-slot:actions>

        @if ($scopes->count() > 1)
            <x-filter-bar :action="route('isms.csf')" :reset="null">
                <x-filter-field :label="__('isms.field.scope')" for="isms-csf-scope" class="min-w-44">
                    <select id="isms-csf-scope" name="scope" class="select select-sm select-bordered w-full">
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
            {{-- 046-Prinzip: ausschließlich Selbsteinschätzung, keine Konformitätszusage. --}}
            <div role="note" class="alert alert-info alert-soft text-sm">
                <x-icon name="info" class="shrink-0" />
                <span>{{ __('isms.csf.disclaimer') }}</span>
            </div>

            @unless ($readiness['has_nist'])
                @if ($readiness['has_crosswalk'])
                    <div role="note" class="alert alert-warning alert-soft text-sm">
                        <x-icon name="link" class="shrink-0" />
                        <span>{{ __('isms.csf.no_nist_notice', ['label' => $readiness['source_label']]) }}</span>
                    </div>
                @else
                    <div role="note" class="alert alert-error alert-soft text-sm">
                        <x-icon name="warning" class="shrink-0" />
                        <span>{{ __('isms.csf.no_crosswalk_notice') }}</span>
                    </div>
                @endif
            @endunless

            {{-- Gesamtabdeckung --}}
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold">{{ __('isms.csf.overall') }}</h3>
                        <p class="text-xs text-muted">{{ __('isms.csf.overall_hint') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <progress class="progress {{ $readiness['overall_tone'] === 'success' ? 'progress-success' : ($readiness['overall_tone'] === 'warning' ? 'progress-warning' : ($readiness['overall_tone'] === 'error' ? 'progress-error' : '')) }} w-40"
                                  value="{{ $readiness['overall_quote'] }}" max="100"></progress>
                        <span class="font-['Space_Grotesk'] text-2xl font-semibold {{ $readiness['overall_tone'] === 'success' ? 'text-success' : ($readiness['overall_tone'] === 'warning' ? 'text-warning' : ($readiness['overall_tone'] === 'error' ? 'text-error' : 'text-muted')) }}">{{ $readiness['overall_quote'] }}&nbsp;%</span>
                    </div>
                </div>
            </x-card>

            {{-- Abdeckung je CSF-Funktion --}}
            <x-card>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">{{ __('isms.csf.section_functions') }}</h3>
                    <a class="link text-xs" href="{{ route('isms.csf.crosswalk', ['scope' => $scope->sqid]) }}">{{ __('isms.csf.action_crosswalk') }}</a>
                </div>
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('isms.csf.col_function') }}</th>
                                <th>{{ __('isms.csf.col_source') }}</th>
                                <th class="w-56">{{ __('isms.csf.col_coverage') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($readiness['functions'] as $function)
                                @php
                                    $chosen = $function['mode'] === 'direct' ? $function['direct'] : $function['mapped'];
                                    $sourceLabel = match ($function['mode']) {
                                        'direct' => __('isms.csf.source_direct'),
                                        'mapped' => __('isms.csf.source_mapped'),
                                        default => __('isms.csf.source_none'),
                                    };
                                    $sourceTone = match ($function['mode']) {
                                        'direct' => 'badge-info',
                                        'mapped' => 'badge-ghost',
                                        default => 'badge-ghost',
                                    };
                                    $sourceHint = match ($function['mode']) {
                                        'direct' => __('isms.csf.source_direct_hint'),
                                        'mapped' => __('isms.csf.source_mapped_hint'),
                                        default => '',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <span class="font-mono text-xs text-muted">{{ $function['ref'] }}</span>
                                        <span class="font-medium">{{ $function['title'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm {{ $sourceTone }}" @if ($sourceHint !== '') title="{{ $sourceHint }}" @endif>{{ $sourceLabel }}</span>
                                    </td>
                                    <td>
                                        @if ($function['mode'] === 'none')
                                            <span class="text-xs text-muted">{{ __('isms.csf.source_none') }}</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <progress class="progress {{ $function['tone'] === 'success' ? 'progress-success' : ($function['tone'] === 'warning' ? 'progress-warning' : 'progress-error') }} w-24"
                                                          value="{{ $function['quote'] }}" max="100"></progress>
                                                <span class="text-xs text-base-content/70">{{ $function['quote'] }}&nbsp;% ({{ $chosen['covered'] }}/{{ $chosen['applicable'] }})</span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            </x-card>
        @endif
    </x-index-page>
@endsection
