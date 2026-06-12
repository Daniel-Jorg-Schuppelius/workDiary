{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Normneutrale Maßnahmen (Feature 046; vormals Maßnahmenkatalog + SoA):
  Filter, Modal-CRUD (Titel, Status, Owner, Anforderungs-Mapping). Der
  Annex-A-Katalog-Import lebt jetzt auf der Anforderungen-Seite.
--}}

@extends('layouts.app')

@section('title', __('isms.title.controls'))
@section('nav-title', __('isms.title.controls'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.controls')">
        <x-slot:actions>
            {{-- Direkt-Exporte (Feature 044, MVP 1): Datenstand = jetzt; „versioniert" leistet das Auditpaket. --}}
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.controls.export', ['format' => 'json'])"
                        show-label>{{ __('isms.action.export_json') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.controls.export', ['format' => 'csv'])"
                        show-label>{{ __('isms.action.export_csv') }}</x-icon-btn>
            <x-icon-btn icon="checklist" tone="outline" size="sm"
                        :href="route('isms.requirements.index')"
                        show-label>{{ __('isms.title.requirements') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.controls.create')"
                            show-label>{{ __('isms.action.create_control') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('isms.controls.index')"
                      :reset="$hasActiveFilters ? route('isms.controls.index') : null">
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
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.implementation_status') }}</th>
                    <th>{{ __('isms.field.requirements') }}</th>
                    <th class="text-center">{{ __('isms.field.risks') }}</th>
                    <th>{{ __('isms.field.owner') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($controls as $control)
                <tr class="hover" id="isms-control-{{ $control->id }}">
                    <td>
                        <span class="font-medium">{{ $control->title }}</span>
                        @if ($control->description)
                            <span class="block max-w-md truncate text-xs text-base-content/60"
                                  title="{{ $control->description }}">{{ $control->description }}</span>
                        @endif
                        @if ($control->evidence_note)
                            <span class="block max-w-md truncate text-xs text-base-content/50"
                                  title="{{ $control->evidence_note }}"><x-icon name="attachment" /> {{ $control->evidence_note }}</span>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$control->implementation_status->tone()">{{ $control->implementation_status->label() }}</x-status-badge></td>
                    <td>
                        @if ($control->requirements->isEmpty())
                            <span class="text-base-content/50">—</span>
                        @else
                            <div class="flex max-w-xs flex-wrap gap-1">
                                @foreach ($control->requirements as $requirement)
                                    <x-status-badge :tone="$requirement->source->tone()" outline>
                                        <span class="font-mono" title="{{ $requirement->normLabel() }} — {{ $requirement->title }}">{{ $requirement->ref_no }}</span>
                                    </x-status-badge>
                                @endforeach
                            </div>
                        @endif
                    </td>
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
                <x-table.empty :colspan="6"
                               :title="__('isms.empty_controls_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_controls')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
