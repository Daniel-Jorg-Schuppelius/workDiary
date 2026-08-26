{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anforderungen + SoA je Geltungsbereich (Feature 044/046): Scope-Auswahl
  (Query-Param scope={sqid}, Default = Default-Scope) und Norm-Filter
  (norm+edition aus den vorhandenen Requirements); Ref-Nr., Titel,
  Anwendbarkeit, Begründung, Umsetzungsstatus, Maßnahmen-Zahl; Modal-
  Bearbeitung der SoA-Aussage; Normprofil-Auswahl + „Normkatalog laden"
  (Registry, idempotent, mit Bestätigung); fehlende SoA-Aussagen je Scope
  nachziehbar; eigene Anforderungen als Modal-CRUD.
  Variablen: $requirements, $statements (keyBy requirement_id), $scope,
             $scopes, $filters, $normOptions, $profiles, $hasActiveFilters,
             $missingStatements, $catalogLoaded, $canManage
--}}

@extends('layouts.app')

@section('title', __('isms.title.requirements'))
@section('nav-title', __('isms.title.requirements'))

@section('content')
    <x-index-page :subtitle="$scope !== null ? __('isms.subtitle.requirements_scope', ['scope' => $scope->name]) : __('isms.subtitle.requirements')">
        <x-slot:actions>
            {{-- Direkt-Exporte (Feature 044, MVP 1): SoA-Stand des gewählten Scopes; „versioniert" leistet das Auditpaket. --}}
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.requirements.export', array_filter(['scope' => $scope?->sqid, 'format' => 'json']))"
                        show-label>{{ __('isms.action.export_json') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.requirements.export', array_filter(['scope' => $scope?->sqid, 'format' => 'csv']))"
                        show-label>{{ __('isms.action.export_csv') }}</x-icon-btn>
            <x-icon-btn icon="rule_folder" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('isms.soa', array_filter(['scope' => $scope?->sqid, 'norm' => $filters['norm'] !== 'all' ? $filters['norm'] : null]))"
                        show-label>{{ __('isms.title.soa') }}</x-icon-btn>
            @if ($canManage)
                <form method="POST" action="{{ route('isms.requirements.import') }}"
                      class="flex items-center gap-2"
                      data-confirm-dialog
                      data-confirm-title="{{ __('isms.action.import_catalog') }}"
                      data-confirm-message="{{ __('isms.confirm_import_catalog') }}"
                      data-confirm-icon="library_add"
                      data-confirm-tone="info"
                      data-confirm-label="{{ __('isms.action.import_catalog') }}">
                    @csrf
                    @if ($scope !== null)
                        <input type="hidden" name="scope" value="{{ $scope->sqid }}">
                    @endif
                    <label class="sr-only" for="isms-req-profile">{{ __('isms.field.norm_profile') }}</label>
                    <select id="isms-req-profile" name="profile" class="select select-sm select-bordered max-w-72">
                        @foreach ($profiles as $profile)
                            <option value="{{ $profile['key'] }}">{{ $profile['label'] }} ({{ $profile['requirements_count'] }})</option>
                        @endforeach
                    </select>
                    <x-icon-btn icon="library_add" tone="outline" size="sm" type="submit"
                                show-label>{{ __('isms.action.import_catalog') }}</x-icon-btn>
                </form>
                {{-- OSCAL-Katalog-Upload (Nachtrag 044a): NIST/BSI-SdT-JSON mit Volltext. --}}
                <form method="POST" action="{{ route('isms.requirements.import-oscal') }}"
                      enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    @if ($scope !== null)
                        <input type="hidden" name="scope" value="{{ $scope->sqid }}">
                    @endif
                    <input type="file" name="file" accept="application/json,.json"
                           class="file-input file-input-bordered file-input-sm max-w-56" required
                           aria-label="{{ __('OSCAL-Katalog (JSON)') }}">
                    <x-icon-btn icon="upload_file" tone="outline" size="sm" type="submit"
                                show-label>{{ __('OSCAL importieren') }}</x-icon-btn>
                </form>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.requirements.create')"
                            show-label>{{ __('isms.action.create_requirement') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('isms.requirements.index')"
                      :reset="$hasActiveFilters ? route('isms.requirements.index', array_filter(['scope' => $scope?->sqid])) : null">
            @if ($scopes->count() > 1)
                <x-filter-field :label="__('isms.field.scope')" for="isms-req-scope" class="min-w-44">
                    <select id="isms-req-scope" name="scope" class="select select-sm select-bordered w-full">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($scope !== null && $scope->is($scopeOption))>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif

            @if ($normOptions->isNotEmpty())
                <x-filter-field :label="__('isms.field.norm')" for="isms-req-norm" class="min-w-44">
                    <select id="isms-req-norm" name="norm" class="select select-sm select-bordered w-full">
                        <option value="all">{{ __('isms.filter.all') }}</option>
                        @foreach ($normOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['norm'] === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif

            <x-filter-field :label="__('isms.field.source')" for="isms-req-source" class="min-w-40">
                <select id="isms-req-source" name="source" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\RequirementSource::cases() as $source)
                        <option value="{{ $source->value }}" @selected($filters['source'] === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.applicable')" for="isms-req-applicable" class="min-w-40">
                <select id="isms-req-applicable" name="applicable" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    <option value="yes" @selected($filters['applicable'] === 'yes')>{{ __('isms.filter.applicable_yes') }}</option>
                    <option value="no" @selected($filters['applicable'] === 'no')>{{ __('isms.filter.applicable_no') }}</option>
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.implementation_status')" for="isms-req-status" class="min-w-44">
                <select id="isms-req-status" name="implementation_status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['implementation_status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        @if ($missingStatements && $canManage && $scope !== null)
            {{-- Scope ohne SoA-Aussagen zu den angezeigten Anforderungen:
                 fehlende Statements idempotent nachziehen (gewählte Norm
                 oder alle — der aktuelle Norm-Filter wird übernommen). --}}
            <div class="alert alert-info">
                <x-icon name="rule" />
                <span>{{ __('isms.statements_missing_for_scope', ['scope' => $scope->name]) }}</span>
                <x-action-form :action="route('isms.statements.ensure', $scope)"
                      data-confirm-title="{{ __('isms.action.ensure_statements') }}"
                      :confirm="__('isms.confirm_ensure_statements')"
                      confirm-icon="rule"
                      confirm-tone="info"
                      :confirm-label="__('isms.action.ensure_statements')">
                    <input type="hidden" name="norm" value="{{ $filters['norm'] }}">
                    <x-icon-btn icon="rule" tone="outline" size="sm" type="submit"
                                show-label>{{ __('isms.action.ensure_statements') }}</x-icon-btn>
                </x-action-form>
            </div>
        @endif

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.ref_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.norm') }}</th>
                    <th>{{ __('isms.field.applicable') }}</th>
                    <th>{{ __('isms.field.implementation_status') }}</th>
                    <th class="text-center">{{ __('isms.field.controls') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($requirements as $requirement)
                @php($statement = $statements[$requirement->id] ?? null)
                <tr class="hover {{ $statement !== null && ! $statement->applicable ? 'opacity-60' : '' }}" id="isms-requirement-{{ $requirement->id }}">
                    <td class="font-mono text-sm">{{ $requirement->ref_no }}</td>
                    <td>
                        <span class="font-medium">{{ $requirement->title }}</span>
                        @if ($statement?->justification)
                            <span class="block max-w-md truncate text-xs text-muted"
                                  title="{{ $statement->justification }}">{{ $statement->justification }}</span>
                        @endif
                        @if ($statement?->evidence_note)
                            <span class="block max-w-md truncate text-xs text-muted"
                                  title="{{ $statement->evidence_note }}"><x-icon name="attachment" /> {{ $statement->evidence_note }}</span>
                        @endif
                    </td>
                    <td>
                        <x-status-badge :tone="$requirement->source->tone()" outline>{{ $requirement->normLabel() }}</x-status-badge>
                    </td>
                    <td>
                        @if ($statement === null)
                            <span class="text-muted">—</span>
                        @elseif ($statement->applicable)
                            <x-status-badge tone="success">{{ __('isms.filter.applicable_yes') }}</x-status-badge>
                        @else
                            <x-status-badge tone="neutral">{{ __('isms.filter.applicable_no') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        @if ($statement === null)
                            <span class="text-muted">—</span>
                        @else
                            <x-status-badge :tone="$statement->implementation_status->tone()">{{ $statement->implementation_status->label() }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-center text-base-content/70">{{ $requirement->controls_count }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($canManage && $statement !== null)
                                <x-icon-btn icon="rule" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.statements.edit', $statement)"
                                            :label="__('isms.action.edit_statement')" />
                            @endif
                            @if ($requirement->source === \App\Enums\Isms\RequirementSource::Custom)
                                @can('update', $requirement)
                                    <x-icon-btn icon="edit" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.requirements.edit', $requirement)"
                                                :label="__('isms.action.edit')" />
                                @endcan
                                @can('delete', $requirement)
                                    <x-action-form :action="route('isms.requirements.destroy', $requirement)" method="DELETE"
                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                          :confirm="__('isms.confirm_delete_requirement')"
                                          confirm-icon="delete"
                                          confirm-tone="error"
                                          :confirm-label="__('isms.action.delete')">
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                    :label="__('isms.action.delete')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               :title="__('isms.empty_requirements_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : ($catalogLoaded ? __('isms.empty_requirements') : __('isms.empty_requirements_hint_catalog'))" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
