{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Maßnahmenkatalog (Feature 044, MVP 1): Filter, Modal-Bearbeitung
  (SoA-Felder), Button „Annex-A-Katalog laden" (idempotent, mit Bestätigung).
--}}

@extends('layouts.app')

@section('title', __('isms.title.controls'))
@section('nav-title', __('isms.title.controls'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.controls')">
        <x-slot:actions>
            <x-icon-btn icon="rule_folder" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('isms.soa')"
                        show-label>{{ __('isms.title.soa') }}</x-icon-btn>
            @if ($canManage)
                <form method="POST" action="{{ route('isms.controls.import') }}"
                      data-confirm-dialog
                      data-confirm-title="{{ __('isms.action.import_catalog') }}"
                      data-confirm-message="{{ __('isms.confirm_import_catalog') }}"
                      data-confirm-icon="library_add"
                      data-confirm-tone="info"
                      data-confirm-label="{{ __('isms.action.import_catalog') }}">
                    @csrf
                    <x-icon-btn icon="library_add" tone="outline" size="sm" type="submit"
                                show-label>{{ __('isms.action.import_catalog') }}</x-icon-btn>
                </form>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.controls.create')"
                            show-label>{{ __('isms.action.create_control') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('isms.controls.index')"
                      :reset="$hasActiveFilters ? route('isms.controls.index') : null">
            <x-filter-field :label="__('isms.field.source')" for="isms-control-source" class="min-w-40">
                <select id="isms-control-source" name="source" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\ControlSource::cases() as $source)
                        <option value="{{ $source->value }}" @selected($filters['source'] === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.applicable')" for="isms-control-applicable" class="min-w-40">
                <select id="isms-control-applicable" name="applicable" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    <option value="yes" @selected($filters['applicable'] === 'yes')>{{ __('isms.filter.applicable_yes') }}</option>
                    <option value="no" @selected($filters['applicable'] === 'no')>{{ __('isms.filter.applicable_no') }}</option>
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.implementation_status')" for="isms-control-status" class="min-w-44">
                <select id="isms-control-status" name="implementation_status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['implementation_status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.code') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.source') }}</th>
                    <th>{{ __('isms.field.applicable') }}</th>
                    <th>{{ __('isms.field.implementation_status') }}</th>
                    <th class="text-center">{{ __('isms.field.risks') }}</th>
                    <th>{{ __('isms.field.owner') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($controls as $control)
                <tr class="hover {{ $control->applicable ? '' : 'opacity-60' }}" id="isms-control-{{ $control->id }}">
                    <td class="font-mono text-sm">{{ $control->code }}</td>
                    <td>
                        <span class="font-medium">{{ $control->title }}</span>
                        @if ($control->justification)
                            <span class="block max-w-md truncate text-xs text-base-content/60"
                                  title="{{ $control->justification }}">{{ $control->justification }}</span>
                        @endif
                        @if ($control->evidence_note)
                            <span class="block max-w-md truncate text-xs text-base-content/50"
                                  title="{{ $control->evidence_note }}"><x-icon name="attachment" /> {{ $control->evidence_note }}</span>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$control->source->tone()" outline>{{ $control->source->label() }}</x-status-badge></td>
                    <td>
                        @if ($control->applicable)
                            <x-status-badge tone="success">{{ __('isms.filter.applicable_yes') }}</x-status-badge>
                        @else
                            <x-status-badge tone="neutral">{{ __('isms.filter.applicable_no') }}</x-status-badge>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$control->implementation_status->tone()">{{ $control->implementation_status->label() }}</x-status-badge></td>
                    <td class="text-center text-base-content/70">{{ $control->risks_count }}</td>
                    <td class="text-base-content/70">{{ optional($control->owner)->name ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $control)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.controls.edit', $control)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('delete', $control)
                                <form method="POST" action="{{ route('isms.controls.destroy', $control) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      data-confirm-message="{{ __('isms.confirm_delete_control') }}"
                                      data-confirm-icon="delete"
                                      data-confirm-tone="error"
                                      data-confirm-label="{{ __('isms.action.delete') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('isms.action.delete')" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8"
                               :title="__('isms.empty_controls_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : ($catalogLoaded ? __('isms.empty_controls') : __('isms.empty_controls_hint_catalog'))" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
