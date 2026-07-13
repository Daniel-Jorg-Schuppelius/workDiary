{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Sicherheitsvorfälle (Feature 044, MVP 2): KPI-Hinweise, Filter,
  Tabelle mit Aufklapp-Detail (Verlauf, verknüpfte Risiken/Maßnahmen),
  Modal-CRUD und Statusübergängen (closed erfordert Ursache + Lessons Learned).
--}}

@extends('layouts.app')

@section('title', __('isms.title.incidents'))
@section('nav-title', __('isms.title.incidents'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.incidents')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.incidents.create')"
                            show-label>{{ __('isms.action.create_incident') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-card>
                <div class="text-xs text-base-content/60">{{ __('isms.incidents.kpi_open') }}</div>
                <div class="text-2xl font-bold">{{ $openCount }}</div>
            </x-card>
            <x-card>
                <div class="text-xs text-base-content/60">{{ __('isms.incidents.kpi_open_critical') }}</div>
                <div class="text-2xl font-bold {{ $openCriticalCount > 0 ? 'text-error' : '' }}">{{ $openCriticalCount }}</div>
                <div class="text-xs text-base-content/50">{{ __('isms.incidents.kpi_open_critical_hint') }}</div>
            </x-card>
        </div>

        <x-filter-bar :action="route('isms.incidents.index')"
                      :reset="$hasActiveFilters ? route('isms.incidents.index') : null">
            <x-filter-field :label="__('isms.field.status')" for="isms-incident-status" class="min-w-40">
                <select id="isms-incident-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\SecurityIncidentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.severity')" for="isms-incident-severity" class="min-w-40">
                <select id="isms-incident-severity" name="severity" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected($filters['severity'] === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.category')" for="isms-incident-category" class="min-w-40">
                <select id="isms-incident-category" name="category" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\SecurityIncidentCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.incident_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.category') }}</th>
                    <th>{{ __('isms.field.severity') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.owner') }}</th>
                    <th>{{ __('isms.field.detected_at') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($incidents as $incident)
                <tr class="hover" id="isms-incident-{{ $incident->id }}">
                    <td class="font-mono text-sm">{{ $incident->displayNo() }}</td>
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $incident->title }}</summary>
                            <div class="mt-2 space-y-1 text-xs text-base-content/70">
                                @if ($incident->description)
                                    <p>{{ $incident->description }}</p>
                                @endif
                                @if ($incident->impact)
                                    <p><span class="font-semibold">{{ __('isms.field.impact') }}:</span> {{ $incident->impact }}</p>
                                @endif
                                @if ($incident->root_cause)
                                    <p><span class="font-semibold">{{ __('isms.field.root_cause') }}:</span> {{ $incident->root_cause }}</p>
                                @endif
                                @if ($incident->lessons_learned)
                                    <p><span class="font-semibold">{{ __('isms.field.lessons_learned') }}:</span> {{ $incident->lessons_learned }}</p>
                                @endif
                                @if ($incident->personal_data_affected)
                                    <p class="flex items-center gap-1 text-warning">
                                        <x-icon name="privacy_tip" />
                                        {{ __('isms.incidents.personal_data_notice') }}
                                        @if ($incident->privacy_incident_ref)
                                            <span class="font-mono">({{ $incident->privacy_incident_ref }})</span>
                                        @endif
                                    </p>
                                @endif
                                <p>
                                    <span class="font-semibold">{{ __('isms.field.risks') }}:</span>
                                    @forelse ($incident->risks as $risk)
                                        <x-status-badge tone="ghost" outline>{{ $risk->displayNo() }} {{ $risk->title }}</x-status-badge>
                                    @empty
                                        —
                                    @endforelse
                                </p>
                                <p>
                                    <span class="font-semibold">{{ __('isms.field.controls') }}:</span>
                                    @forelse ($incident->controls as $control)
                                        <x-status-badge tone="ghost" outline>{{ $control->title }}</x-status-badge>
                                    @empty
                                        —
                                    @endforelse
                                </p>
                            </div>
                        </details>
                    </td>
                    <td><x-status-badge :tone="$incident->category->tone()" outline>{{ $incident->category->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$incident->severity->tone()">{{ $incident->severity->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$incident->status->tone()">{{ $incident->status->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">{{ optional($incident->owner)->name ?? '—' }}</td>
                    <td class="text-base-content/70">{{ $incident->detected_at?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $incident)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.incidents.edit', $incident)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('transition', $incident)
                                @if ($incident->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-56 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($incident->status->allowedTransitions() as $target)
                                                <li>
                                                    <form method="POST" action="{{ route('isms.incidents.transition', $incident) }}">
                                                        @csrf
                                                        <input type="hidden" name="status" value="{{ $target->value }}">
                                                        <button type="submit" class="w-full text-left">{{ $target->label() }}</button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            @endcan
                            @can('delete', $incident)
                                <x-action-form :action="route('isms.incidents.destroy', $incident)" method="DELETE"
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      :confirm="__('isms.confirm_delete_incident')"
                                      confirm-icon="delete"
                                      confirm-tone="error"
                                      :confirm-label="__('isms.action.delete')">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('isms.action.delete')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8"
                               :title="__('isms.empty_incidents_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_incidents')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$incidents" standing />
    </x-index-page>
@endsection
