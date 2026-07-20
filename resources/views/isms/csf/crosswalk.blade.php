{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : crosswalk.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  NIST CSF 2.0 → ISO/IEC 27001:2022 Crosswalk (Nachtrag NIST): Zuordnung
  der CSF-Kategorien zu ISO-Referenzen mit Abdeckung aus der ISO-SoA des
  Geltungsbereichs. Variablen: $scope, $scopes, $crosswalk
  (CsfReadinessService::crosswalkForScope()).
--}}

@extends('layouts.app')

@section('title', __('isms.title.csf_crosswalk'))
@section('nav-title', __('isms.title.csf_crosswalk'))

@section('content')
    <x-index-page :subtitle="$scope !== null ? __('isms.subtitle.csf_crosswalk_scope', ['scope' => $scope->name]) : __('isms.subtitle.csf_crosswalk')">
        <x-slot:actions>
            <x-icon-btn icon="radar" tone="outline" size="sm"
                        :href="route('isms.csf', $scope !== null ? ['scope' => $scope->sqid] : [])"
                        show-label>{{ __('isms.csf.action_dashboard') }}</x-icon-btn>
        </x-slot:actions>

        @if ($scopes->count() > 1)
            <x-filter-bar :action="route('isms.csf.crosswalk')" :reset="null">
                <x-filter-field :label="__('isms.field.scope')" for="isms-csf-cw-scope" class="min-w-44">
                    <select id="isms-csf-cw-scope" name="scope" class="select select-sm select-bordered w-full">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($scope !== null && $scope->is($scopeOption))>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        @endif

        @if ($scope === null || $crosswalk === null)
            <x-empty-state framed
                           icon='<span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>'
                           :title="__('isms.csf.no_crosswalk_notice')"
                           :message="__('isms.csf.crosswalk_empty')" />
        @else
            <div role="note" class="alert alert-info alert-soft text-sm">
                <x-icon name="info" class="shrink-0" />
                <span>
                    {{ __('isms.csf.crosswalk_intro') }}
                    @if ($crosswalk['as_of'] !== null)
                        {{ __('isms.csf.crosswalk_version', ['version' => $crosswalk['version'], 'as_of' => $crosswalk['as_of']]) }}
                    @else
                        {{ __('isms.csf.crosswalk_version_no_date', ['version' => $crosswalk['version']]) }}
                    @endif
                </span>
            </div>

            <x-card>
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th class="w-64">{{ __('isms.csf.crosswalk_source') }}</th>
                                <th>{{ __('isms.csf.crosswalk_targets') }}</th>
                                <th class="w-56">{{ __('isms.csf.crosswalk_coverage') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($crosswalk['rows'] as $row)
                                @php
                                    $cov = $row['coverage'];
                                    $tone = $cov['applicable'] === 0
                                        ? 'ghost'
                                        : ($cov['quote'] >= 80 ? 'success' : ($cov['quote'] >= 40 ? 'warning' : 'error'));
                                @endphp
                                <tr>
                                    <td class="align-top">
                                        <span class="font-mono text-xs text-base-content/60">{{ $row['source_ref'] }}</span>
                                        <span class="font-medium">{{ $row['source_title'] }}</span>
                                    </td>
                                    <td class="align-top">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row['targets'] as $target)
                                                <span class="badge badge-sm badge-ghost font-mono" title="{{ $target['title'] }}">{{ $target['ref'] }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        @if ($cov['applicable'] === 0)
                                            <span class="text-xs text-base-content/50">{{ __('isms.csf.source_none') }}</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <progress class="progress {{ $tone === 'success' ? 'progress-success' : ($tone === 'warning' ? 'progress-warning' : 'progress-error') }} w-24"
                                                          value="{{ $cov['quote'] }}" max="100"></progress>
                                                <span class="text-xs text-base-content/70">{{ $cov['quote'] }}&nbsp;% ({{ $cov['covered'] }}/{{ $cov['applicable'] }})</span>
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
