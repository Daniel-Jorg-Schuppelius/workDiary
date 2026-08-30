{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Audits (Feature 046, Inkrement C): Liste mit Filtern (Scope/Status/Art/
  Jahr), Modal-CRUD, Statuswechsel-Dropdown und Aufklappbereich je Audit
  (Muster conformity/index): Feststellungen mit Art-Badge, Anforderungs-
  Referenz, Statuskette und verschachtelten Korrekturmaßnahmen inkl.
  Wirksamkeitsprüfung (Pflicht-Notiz bei effective/ineffective — Regeln
  serverseitig im AuditService).
  Variablen: $audits, $filters, $years, $scopes, $canManage
--}}

@extends('layouts.app')

@section('title', __('isms.title.audits'))
@section('nav-title', __('isms.title.audits'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.audits')">
        <x-slot:actions>
            <x-icon-btn icon="event_repeat" tone="ghost" size="sm"
                        :href="route('isms.audit-programs.index')"
                        show-label>{{ __('Auditprogramme') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.audits.create')"
                            show-label>{{ __('isms.action.create_audit') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        @php
            $hasActiveFilters = $filters['scope'] !== 'all' || $filters['status'] !== 'all'
                || $filters['kind'] !== 'all' || $filters['year'] !== 'all';
        @endphp

        <x-filter-bar :action="route('isms.audits.index')"
                      :reset="$hasActiveFilters ? route('isms.audits.index') : null">
            @if ($scopes->count() > 1)
                <x-filter-field :label="__('isms.field.scope')" for="isms-audit-scope" class="min-w-44">
                    <select id="isms-audit-scope" name="scope" class="select select-sm select-bordered w-full">
                        <option value="all">{{ __('isms.filter.all') }}</option>
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($filters['scope'] === $scopeOption->sqid)>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif

            <x-filter-field :label="__('isms.field.status')" for="isms-audit-status" class="min-w-40">
                <select id="isms-audit-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\AuditStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.audit_kind')" for="isms-audit-kind" class="min-w-40">
                <select id="isms-audit-kind" name="kind" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\AuditKind::cases() as $kind)
                        <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.year')" for="isms-audit-year" class="min-w-32">
                <select id="isms-audit-year" name="year" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected($filters['year'] === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.audit_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.norm') }}</th>
                    <th>{{ __('isms.field.audit_kind') }}</th>
                    <th>{{ __('isms.field.period') }}</th>
                    <th>{{ __('isms.field.lead_auditor') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th class="text-center">{{ __('isms.field.findings') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($audits as $audit)
                @php /** @var \App\Models\Isms\IsmsAudit $audit */ @endphp
                <tr class="hover" id="isms-audit-{{ $audit->id }}">
                    <td class="font-mono text-sm align-top">{{ $audit->displayNo() }}</td>
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $audit->title }}</summary>
                            <div class="mt-2 space-y-2 text-xs text-base-content/70">
                                @if ($audit->criteria)
                                    <p><span class="font-semibold">{{ __('isms.field.criteria') }}:</span> {{ $audit->criteria }}</p>
                                @endif
                                @if ($audit->auditors)
                                    <p><span class="font-semibold">{{ __('isms.field.auditors') }}:</span> {{ $audit->auditors }}</p>
                                @endif
                                @if ($audit->independence_note)
                                    <p><span class="font-semibold">{{ __('isms.field.independence_note') }}:</span> {{ $audit->independence_note }}</p>
                                @endif
                                @if ($audit->summary)
                                    <p><span class="font-semibold">{{ __('isms.field.summary') }}:</span> {{ $audit->summary }}</p>
                                @endif

                                {{-- Feststellungen inkl. Korrekturmaßnahmen --}}
                                <p class="font-semibold">{{ __('isms.field.findings') }}:</p>
                                @forelse ($audit->findings as $finding)
                                    <div class="rounded border border-base-300 p-2 space-y-1" id="isms-finding-{{ $finding->id }}">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono">{{ $finding->displayNo() }}</span>
                                            <x-status-badge :tone="$finding->kind->tone()" outline>{{ $finding->kind->label() }}</x-status-badge>
                                            <span class="font-medium">{{ $finding->title }}</span>
                                            @if ($finding->requirement !== null)
                                                <span class="text-muted">{{ $finding->requirement->normLabel() }} · {{ $finding->requirement->ref_no }}</span>
                                            @endif
                                            <x-status-badge :tone="$finding->status->tone()">{{ $finding->status->label() }}</x-status-badge>
                                            <span class="text-muted">{{ __('isms.audit.actions_count', ['count' => $finding->correctiveActions->count()]) }}</span>
                                            @can('manageFindings', $audit)
                                                <span class="ml-auto flex items-center gap-1">
                                                    <x-icon-btn icon="edit" tone="outline" size="xs"
                                                                data-entry-modal-trigger
                                                                :href="route('isms.audits.findings.edit', $finding)"
                                                                :label="__('isms.action.edit_finding')" />
                                                    @if ($finding->status->allowedTransitions() !== [])
                                                        <details class="dropdown dropdown-end">
                                                            <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                                                <x-icon name="swap_horiz" />
                                                            </summary>
                                                            <ul class="menu dropdown-content z-10 w-64 rounded-box bg-base-100 p-2 shadow">
                                                                @foreach ($finding->status->allowedTransitions() as $target)
                                                                    <li>
                                                                        <form method="POST" action="{{ route('isms.audits.findings.transition', $finding) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="{{ $target->value }}">
                                                                            <button type="submit" class="w-full text-left">{{ $target->label() }}</button>
                                                                        </form>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </details>
                                                    @endif
                                                    <x-action-form :action="route('isms.audits.findings.destroy', $finding)" method="DELETE"
                                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                                          :confirm="__('isms.confirm_delete_finding')"
                                                          confirm-icon="delete"
                                                          confirm-tone="error"
                                                          :confirm-label="__('isms.action.delete')">
                                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                                    :label="__('isms.action.delete')" />
                                                    </x-action-form>
                                                </span>
                                            @endcan
                                        </div>
                                        @if ($finding->description)
                                            <p>{{ $finding->description }}</p>
                                        @endif

                                        {{-- Korrekturmaßnahmen der Feststellung --}}
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-base-content/80">{{ __('isms.field.corrective_actions') }} ({{ $finding->correctiveActions->count() }})</summary>
                                            <div class="mt-1 space-y-1">
                                                @forelse ($finding->correctiveActions as $action)
                                                    <div class="rounded border border-base-200 bg-base-200/40 p-2 space-y-1" id="isms-action-{{ $action->id }}">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-medium">{{ $action->title }}</span>
                                                            <x-status-badge :tone="$action->status->tone()">{{ $action->status->label() }}</x-status-badge>
                                                            @if ($action->owner !== null)
                                                                <span class="text-muted">{{ $action->owner->name }}</span>
                                                            @endif
                                                            @if ($action->due_on !== null)
                                                                <span class="{{ $action->due_on->isPast() && $action->status->isPending() ? 'text-error font-semibold' : 'text-muted' }}">
                                                                    {{ __('isms.field.due_on') }}: {{ $action->due_on->format('d.m.Y') }}
                                                                </span>
                                                            @endif
                                                            @if ($action->completed_on !== null)
                                                                <span class="text-muted">{{ __('isms.field.completed_on') }}: {{ $action->completed_on->format('d.m.Y') }}</span>
                                                            @endif
                                                            @can('manageFindings', $audit)
                                                                <span class="ml-auto flex items-center gap-1">
                                                                    <x-icon-btn icon="edit" tone="outline" size="xs"
                                                                                data-entry-modal-trigger
                                                                                :href="route('isms.audits.actions.edit', $action)"
                                                                                :label="__('isms.action.edit_action')" />
                                                                    @if ($action->status->allowedTransitions() !== [])
                                                                        <details class="dropdown dropdown-end">
                                                                            <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                                                                <x-icon name="swap_horiz" />
                                                                            </summary>
                                                                            <ul class="menu dropdown-content z-10 w-72 rounded-box bg-base-100 p-2 shadow">
                                                                                @foreach ($action->status->allowedTransitions() as $target)
                                                                                    <li>
                                                                                        <form method="POST" action="{{ route('isms.audits.actions.transition', $action) }}" class="space-y-1">
                                                                                            @csrf
                                                                                            <input type="hidden" name="status" value="{{ $target->value }}">
                                                                                            @if (in_array($target, [\App\Enums\Isms\CorrectiveActionStatus::Effective, \App\Enums\Isms\CorrectiveActionStatus::Ineffective], true))
                                                                                                {{-- Wirksamkeitsprüfung: Pflicht-Notiz (serverseitig erzwungen). --}}
                                                                                                <textarea aria-label="{{ __('isms.field.effectiveness_note') }}" name="effectiveness_note" rows="2" required maxlength="5000"
                                                                                                          class="textarea textarea-bordered textarea-xs w-full"
                                                                                                          placeholder="{{ __('isms.field.effectiveness_note') }} *"></textarea>
                                                                                            @endif
                                                                                            <button type="submit" class="w-full text-left">{{ $target->label() }}</button>
                                                                                        </form>
                                                                                    </li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </details>
                                                                    @endif
                                                                    <x-action-form :action="route('isms.audits.actions.destroy', $action)" method="DELETE"
                                                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                                                          :confirm="__('isms.confirm_delete_action')"
                                                                          confirm-icon="delete"
                                                                          confirm-tone="error"
                                                                          :confirm-label="__('isms.action.delete')">
                                                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                                                    :label="__('isms.action.delete')" />
                                                                    </x-action-form>
                                                                </span>
                                                            @endcan
                                                        </div>
                                                        @if ($action->root_cause)
                                                            <p><span class="font-semibold">{{ __('isms.field.root_cause') }}:</span> {{ $action->root_cause }}</p>
                                                        @endif
                                                        @if ($action->action_plan)
                                                            <p><span class="font-semibold">{{ __('isms.field.action_plan') }}:</span> {{ $action->action_plan }}</p>
                                                        @endif
                                                        @if ($action->effectiveness_note)
                                                            <p><span class="font-semibold">{{ __('isms.field.effectiveness_note') }}:</span> {{ $action->effectiveness_note }}</p>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <p class="text-muted">{{ __('isms.audit.empty_actions') }}</p>
                                                @endforelse
                                                @can('manageFindings', $audit)
                                                    @if ($finding->status !== \App\Enums\Isms\FindingStatus::Closed)
                                                        <x-icon-btn icon="add" tone="outline" size="xs"
                                                                    data-entry-modal-trigger
                                                                    :href="route('isms.audits.actions.create', $finding)"
                                                                    show-label>{{ __('isms.action.create_action') }}</x-icon-btn>
                                                    @endif
                                                @endcan
                                            </div>
                                        </details>
                                    </div>
                                @empty
                                    <p>{{ __('isms.audit.empty_findings') }}</p>
                                @endforelse
                                @can('manageFindings', $audit)
                                    @if ($audit->status->allowsFindings())
                                        <x-icon-btn icon="add" tone="outline" size="xs"
                                                    data-entry-modal-trigger
                                                    :href="route('isms.audits.findings.create', $audit)"
                                                    show-label>{{ __('isms.action.create_finding') }}</x-icon-btn>
                                    @else
                                        <p class="text-muted">{{ __('isms.audit.findings_require_running') }}</p>
                                    @endif
                                @endcan
                            </div>
                        </details>
                    </td>
                    <td class="text-base-content/70">{{ $audit->normLabel() ?? '—' }}</td>
                    <td><x-status-badge :tone="$audit->kind->tone()" outline>{{ $audit->kind->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">
                        @if ($audit->performed_from !== null)
                            {{ $audit->performed_from->format('d.m.Y') }} – {{ $audit->performed_to?->format('d.m.Y') ?? '…' }}
                        @elseif ($audit->planned_on !== null)
                            {{ __('isms.audit.planned_short') }}: {{ $audit->planned_on->format('d.m.Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ optional($audit->leadAuditor)->name ?? '—' }}</td>
                    <td><x-status-badge :tone="$audit->status->tone()">{{ $audit->status->label() }}</x-status-badge></td>
                    <td class="text-center text-base-content/70">
                        <span class="{{ $audit->open_findings_count > 0 ? 'text-warning font-semibold' : '' }}">{{ $audit->open_findings_count }}</span>
                        / {{ $audit->findings_count }}
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $audit)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.audits.edit', $audit)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('transition', $audit)
                                @if ($audit->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-64 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($audit->status->allowedTransitions() as $target)
                                                <li>
                                                    <form method="POST" action="{{ route('isms.audits.transition', $audit) }}">
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
                            @can('delete', $audit)
                                <x-action-form :action="route('isms.audits.destroy', $audit)" method="DELETE"
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      :confirm="__('isms.confirm_delete_audit')"
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
                               :title="__('isms.empty_audits_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_audits')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$audits" standing />
    </x-index-page>
@endsection
