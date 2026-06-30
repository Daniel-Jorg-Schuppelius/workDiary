{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Schwachstellenregister (Feature 044, MVP 2): Severity-KPIs, Filter,
  Tabelle mit Aufklapp-Detail (Komponente/Advisory), Modal-CRUD,
  Statusübergängen und der bewussten Ausnutzbarkeits-Entscheidung.
--}}

@extends('layouts.app')

@section('title', __('isms.title.vulnerabilities'))
@section('nav-title', __('isms.title.vulnerabilities'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.vulnerabilities')">
        <x-slot:actions>
            <x-icon-btn icon="upload_file" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('isms.advisories.create')"
                        show-label>{{ __('isms.action.import_advisory') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.vulnerabilities.create')"
                            show-label>{{ __('isms.action.create_vulnerability') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-5">
            @foreach (['critical', 'high', 'medium', 'low'] as $sev)
                <x-card>
                    <div class="text-xs text-base-content/60">{{ __('enums.isms.incident-severity.' . $sev) }}</div>
                    <div class="text-2xl font-bold {{ in_array($sev, ['critical', 'high']) && ($severityCounts[$sev] ?? 0) > 0 ? 'text-error' : '' }}">{{ $severityCounts[$sev] ?? 0 }}</div>
                </x-card>
            @endforeach
            <x-card>
                <div class="text-xs text-base-content/60">{{ __('isms.vulnerabilities.kpi_overdue') }}</div>
                <div class="text-2xl font-bold {{ $overdueCount > 0 ? 'text-error' : '' }}">{{ $overdueCount }}</div>
            </x-card>
        </div>

        <x-filter-bar :action="route('isms.vulnerabilities.index')"
                      :reset="$hasActiveFilters ? route('isms.vulnerabilities.index') : null">
            <x-filter-field :label="__('isms.field.status')" for="isms-vuln-status" class="min-w-40">
                <select id="isms-vuln-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\VulnerabilityStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.severity')" for="isms-vuln-severity" class="min-w-40">
                <select id="isms-vuln-severity" name="severity" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected($filters['severity'] === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.exploitability')" for="isms-vuln-exploit" class="min-w-44">
                <select id="isms-vuln-exploit" name="exploitability" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\Exploitability::cases() as $exp)
                        <option value="{{ $exp->value }}" @selected($filters['exploitability'] === $exp->value)>{{ $exp->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.filter.sort')" for="isms-vuln-sort" class="min-w-40">
                <select id="isms-vuln-sort" name="sort" class="select select-sm select-bordered w-full">
                    <option value="severity" @selected($filters['sort'] === 'severity')>{{ __('isms.filter.sort_severity') }}</option>
                    <option value="cvss" @selected($filters['sort'] === 'cvss')>{{ __('isms.filter.sort_cvss') }}</option>
                    <option value="due" @selected($filters['sort'] === 'due')>{{ __('isms.filter.sort_due') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.vuln_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.identifier') }}</th>
                    <th class="text-center">{{ __('isms.field.cvss') }}</th>
                    <th>{{ __('isms.field.severity') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.exploitability') }}</th>
                    <th>{{ __('isms.field.due_on') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($vulnerabilities as $vulnerability)
                <tr class="hover" id="isms-vuln-{{ $vulnerability->id }}">
                    <td class="font-mono text-sm">{{ $vulnerability->displayNo() }}</td>
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $vulnerability->title }}</summary>
                            <div class="mt-2 space-y-1 text-xs text-base-content/70">
                                @if ($vulnerability->affected_component)
                                    <p><span class="font-semibold">{{ __('isms.field.affected_component') }}:</span> <span class="font-mono">{{ $vulnerability->affected_component }}</span></p>
                                @endif
                                @if ($vulnerability->product)
                                    <p><span class="font-semibold">{{ __('isms.field.product') }}:</span> {{ $vulnerability->product->name }} {{ $vulnerability->product->product_version }}</p>
                                @endif
                                @if ($vulnerability->advisory_ref)
                                    <p><span class="font-semibold">{{ __('isms.field.advisory_ref') }}:</span> <span class="font-mono">{{ $vulnerability->advisory_ref }}</span></p>
                                @endif
                                @if ($vulnerability->exploitability_note)
                                    <p><span class="font-semibold">{{ __('isms.field.exploitability_note') }}:</span> {{ $vulnerability->exploitability_note }}</p>
                                @endif
                                <p><span class="font-semibold">{{ __('isms.field.source') }}:</span> {{ $vulnerability->source->label() }}</p>
                            </div>
                        </details>
                    </td>
                    <td class="font-mono text-xs">{{ $vulnerability->identifier ?? '—' }}</td>
                    <td class="text-center">{{ $vulnerability->cvss_score !== null ? number_format((float) $vulnerability->cvss_score, 1) : '—' }}</td>
                    <td><x-status-badge :tone="$vulnerability->severity->tone()">{{ $vulnerability->severity->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$vulnerability->status->tone()">{{ $vulnerability->status->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$vulnerability->exploitability->tone()" outline>{{ $vulnerability->exploitability->label() }}</x-status-badge></td>
                    <td class="{{ $vulnerability->isOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">
                        {{ $vulnerability->due_on?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $vulnerability)
                                <x-icon-btn icon="gavel" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.vulnerabilities.decision', $vulnerability)"
                                            :label="__('isms.action.decide_exploitability')" />
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.vulnerabilities.edit', $vulnerability)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('transition', $vulnerability)
                                @if ($vulnerability->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-56 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($vulnerability->status->allowedTransitions() as $target)
                                                <li>
                                                    <form method="POST" action="{{ route('isms.vulnerabilities.transition', $vulnerability) }}">
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
                            @can('delete', $vulnerability)
                                <x-action-form :action="route('isms.vulnerabilities.destroy', $vulnerability)" method="DELETE"
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      :confirm="__('isms.confirm_delete_vulnerability')"
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
                <x-table.empty :colspan="9"
                               :title="__('isms.empty_vulnerabilities_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_vulnerabilities')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$vulnerabilities" standing />
    </x-index-page>
@endsection
