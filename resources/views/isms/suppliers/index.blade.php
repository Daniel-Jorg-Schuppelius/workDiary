{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Lieferantenbewertung (Feature 044, MVP 2/3): KPIs (überfällige
  Reviews / auffällige), Filter, Tabelle mit Aufklapp-Detail (Anforderungen/
  Vertragsmerkmale), Modal-CRUD und Statusübergängen.
--}}

@extends('layouts.app')

@section('title', __('isms.title.suppliers'))
@section('nav-title', __('isms.title.suppliers'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.suppliers')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.suppliers.create')"
                            show-label>{{ __('isms.action.create_supplier') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-card>
                <div class="text-xs text-base-content/60">{{ __('isms.suppliers.kpi_overdue') }}</div>
                <div class="text-2xl font-bold {{ $overdueCount > 0 ? 'text-warning' : '' }}">{{ $overdueCount }}</div>
                <div class="text-xs text-base-content/50">{{ __('isms.suppliers.kpi_overdue_hint') }}</div>
            </x-card>
            <x-card>
                <div class="text-xs text-base-content/60">{{ __('isms.suppliers.kpi_flagged') }}</div>
                <div class="text-2xl font-bold {{ $flaggedCount > 0 ? 'text-error' : '' }}">{{ $flaggedCount }}</div>
                <div class="text-xs text-base-content/50">{{ __('isms.suppliers.kpi_flagged_hint') }}</div>
            </x-card>
        </div>

        <x-filter-bar :action="route('isms.suppliers.index')"
                      :reset="$hasActiveFilters ? route('isms.suppliers.index') : null">
            <x-filter-field :label="__('isms.field.status')" for="isms-supplier-status" class="min-w-40">
                <select id="isms-supplier-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\SupplierAssessmentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.criticality')" for="isms-supplier-crit" class="min-w-40">
                <select id="isms-supplier-crit" name="criticality" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected($filters['criticality'] === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.risk_rating')" for="isms-supplier-risk" class="min-w-40">
                <select id="isms-supplier-risk" name="risk" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected($filters['risk'] === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.filter.sort')" for="isms-supplier-sort" class="min-w-40">
                <select id="isms-supplier-sort" name="sort" class="select select-sm select-bordered w-full">
                    <option value="criticality" @selected($filters['sort'] === 'criticality')>{{ __('isms.filter.sort_criticality') }}</option>
                    <option value="risk" @selected($filters['sort'] === 'risk')>{{ __('isms.filter.sort_risk') }}</option>
                    <option value="review" @selected($filters['sort'] === 'review')>{{ __('isms.filter.sort_review_date') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.assessment_no') }}</th>
                    <th>{{ __('isms.field.supplier') }}</th>
                    <th>{{ __('isms.field.criticality') }}</th>
                    <th>{{ __('isms.field.risk_rating') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.next_review_on') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($assessments as $assessment)
                <tr class="hover" id="isms-supplier-{{ $assessment->id }}">
                    <td class="font-mono text-sm">{{ $assessment->displayNo() }}</td>
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $assessment->displayName() }}</summary>
                            <div class="mt-2 space-y-1 text-xs text-base-content/70">
                                @if ($assessment->service_description)
                                    <p><span class="font-semibold">{{ __('isms.field.service_description') }}:</span> {{ $assessment->service_description }}</p>
                                @endif
                                @if ($assessment->scope)
                                    <p><span class="font-semibold">{{ __('isms.field.scope') }}:</span> {{ $assessment->scope->name }}</p>
                                @endif
                                @if ($assessment->security_requirements)
                                    <p><span class="font-semibold">{{ __('isms.field.security_requirements') }}:</span> {{ $assessment->security_requirements }}</p>
                                @endif
                                <p class="flex flex-wrap gap-1">
                                    <x-status-badge :tone="$assessment->has_nda ? 'success' : 'ghost'" outline>{{ __('isms.field.has_nda') }}: {{ $assessment->has_nda ? __('isms.soa.yes') : __('isms.soa.no') }}</x-status-badge>
                                    <x-status-badge :tone="$assessment->has_dpa ? 'success' : 'ghost'" outline>{{ __('isms.field.has_dpa') }}: {{ $assessment->has_dpa ? __('isms.soa.yes') : __('isms.soa.no') }}</x-status-badge>
                                    <x-status-badge :tone="$assessment->audit_right ? 'success' : 'ghost'" outline>{{ __('isms.field.audit_right') }}: {{ $assessment->audit_right ? __('isms.soa.yes') : __('isms.soa.no') }}</x-status-badge>
                                </p>
                                @if ($assessment->dpa_ref)
                                    <p><span class="font-semibold">{{ __('isms.field.dpa_ref') }}:</span> {{ $assessment->dpa_ref }}</p>
                                @endif
                                @if ($assessment->findings)
                                    <p><span class="font-semibold">{{ __('isms.field.findings') }}:</span> {{ $assessment->findings }}</p>
                                @endif
                                @if ($assessment->owner)
                                    <p><span class="font-semibold">{{ __('isms.field.owner') }}:</span> {{ $assessment->owner->name }}</p>
                                @endif
                            </div>
                        </details>
                    </td>
                    <td><x-status-badge :tone="$assessment->criticality->tone()">{{ $assessment->criticality->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$assessment->risk_rating->tone()" outline>{{ $assessment->risk_rating->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$assessment->status->tone()">{{ $assessment->status->label() }}</x-status-badge></td>
                    <td class="{{ $assessment->isReviewOverdue() ? 'text-warning font-semibold' : 'text-base-content/70' }}">
                        {{ $assessment->next_review_on?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $assessment)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.suppliers.edit', $assessment)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('transition', $assessment)
                                @if ($assessment->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-56 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($assessment->status->allowedTransitions() as $target)
                                                <li>
                                                    <form method="POST" action="{{ route('isms.suppliers.transition', $assessment) }}">
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
                            @can('delete', $assessment)
                                <form method="POST" action="{{ route('isms.suppliers.destroy', $assessment) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      data-confirm-message="{{ __('isms.confirm_delete_supplier') }}"
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
                <x-table.empty :colspan="7"
                               :title="__('isms.empty_suppliers_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_suppliers')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$assessments" />
    </x-index-page>
@endsection
