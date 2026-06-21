{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Risikoregister (Feature 044, MVP 1): Filter, 5x5-Risikomatrix
  (reines Blade/CSS), Tabelle mit Aufklapp-Detail (verknüpfte Controls),
  Modal-CRUD und Statusübergängen.
--}}

@extends('layouts.app')

@section('title', __('isms.title.risks'))
@section('nav-title', __('isms.title.risks'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.risks')">
        <x-slot:actions>
            {{-- Direkt-Exporte (Feature 044, MVP 1): Datenstand = jetzt; „versioniert" leistet das Auditpaket. --}}
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.risks.export', ['format' => 'json'])"
                        show-label>{{ __('isms.action.export_json') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('isms.risks.export', ['format' => 'csv'])"
                        show-label>{{ __('isms.action.export_csv') }}</x-icon-btn>
            <x-icon-btn icon="rule_folder" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('isms.soa')"
                        show-label>{{ __('isms.title.soa') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.risks.create')"
                            show-label>{{ __('isms.action.create_risk') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        {{-- 5x5-Risikomatrix (offene Risiken): Zeilen = Eintrittswahrscheinlichkeit (5 oben), Spalten = Auswirkung --}}
        <x-card>
            <div class="flex flex-wrap items-start gap-6">
                <div>
                    <h3 class="mb-2 text-sm font-semibold">{{ __('isms.matrix.title') }}</h3>
                    <table class="border-separate" style="border-spacing: 2px;">
                        <tbody>
                            @for ($likelihood = 5; $likelihood >= 1; $likelihood--)
                                <tr>
                                    <th class="pr-2 text-right text-xs font-normal text-base-content/60">{{ $likelihood }}</th>
                                    @for ($impact = 1; $impact <= 5; $impact++)
                                        @php
                                            $count = $matrix[$likelihood][$impact] ?? 0;
                                            $tone = \App\Models\Isms\IsmsRisk::scoreTone($likelihood * $impact);
                                            $bg = ['success' => 'bg-success/20 text-success-content', 'warning' => 'bg-warning/30', 'error' => 'bg-error/30'][$tone];
                                        @endphp
                                        <td class="h-10 w-10 rounded text-center align-middle text-sm {{ $bg }} {{ $count > 0 ? 'font-bold' : 'text-base-content/40' }}"
                                            title="{{ __('isms.matrix.cell', ['likelihood' => $likelihood, 'impact' => $impact, 'count' => $count]) }}">
                                            {{ $count > 0 ? $count : '·' }}
                                        </td>
                                    @endfor
                                </tr>
                            @endfor
                            <tr>
                                <th></th>
                                @for ($impact = 1; $impact <= 5; $impact++)
                                    <th class="pt-1 text-center text-xs font-normal text-base-content/60">{{ $impact }}</th>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-1 text-xs text-base-content/60">
                        {{ __('isms.matrix.axes') }}
                    </p>
                </div>
                <div class="text-sm">
                    <h3 class="mb-2 text-sm font-semibold">{{ __('isms.matrix.legend') }}</h3>
                    <ul class="space-y-1">
                        <li class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-success/40"></span>{{ __('isms.matrix.low') }}</li>
                        <li class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-warning/50"></span>{{ __('isms.matrix.medium') }}</li>
                        <li class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-error/50"></span>{{ __('isms.matrix.high') }}</li>
                    </ul>
                    @if ($reviewDueCount > 0)
                        <p class="mt-3 flex items-center gap-1 text-warning">
                            <x-icon name="schedule" />
                            {{ trans_choice('isms.matrix.review_due', $reviewDueCount, ['count' => $reviewDueCount]) }}
                        </p>
                    @endif
                </div>
            </div>
        </x-card>

        <x-filter-bar :action="route('isms.risks.index')"
                      :reset="$hasActiveFilters ? route('isms.risks.index') : null">
            <x-filter-field :label="__('isms.field.status')" for="isms-risk-status" class="min-w-40">
                <select id="isms-risk-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\RiskStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.category')" for="isms-risk-category" class="min-w-40">
                <select id="isms-risk-category" name="category" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\RiskCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.treatment')" for="isms-risk-treatment" class="min-w-40">
                <select id="isms-risk-treatment" name="treatment" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\RiskTreatment::cases() as $treatment)
                        <option value="{{ $treatment->value }}" @selected($filters['treatment'] === $treatment->value)>{{ $treatment->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.filter.sort')" for="isms-risk-sort" class="min-w-40">
                <select id="isms-risk-sort" name="sort" class="select select-sm select-bordered w-full">
                    <option value="score" @selected($filters['sort'] === 'score')>{{ __('isms.filter.sort_score') }}</option>
                    <option value="review" @selected($filters['sort'] === 'review')>{{ __('isms.filter.sort_review') }}</option>
                    <option value="newest" @selected($filters['sort'] === 'newest')>{{ __('isms.filter.sort_newest') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.risk_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.category') }}</th>
                    <th class="text-center">{{ __('isms.field.score') }}</th>
                    <th>{{ __('isms.field.treatment') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.owner') }}</th>
                    <th>{{ __('isms.field.review_due_on') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($risks as $risk)
                <tr class="hover" id="isms-risk-{{ $risk->id }}">
                    <td class="font-mono text-sm">{{ $risk->displayNo() }}</td>
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $risk->title }}</summary>
                            <div class="mt-2 space-y-1 text-xs text-base-content/70">
                                @if ($risk->asset_ref)
                                    <p><span class="font-semibold">{{ __('isms.field.asset_ref') }}:</span> {{ $risk->asset_ref }}</p>
                                @endif
                                @if ($risk->threat)
                                    <p><span class="font-semibold">{{ __('isms.field.threat') }}:</span> {{ $risk->threat }}</p>
                                @endif
                                @if ($risk->description)
                                    <p>{{ $risk->description }}</p>
                                @endif
                                <p>
                                    <span class="font-semibold">{{ __('isms.field.controls') }}:</span>
                                    @forelse ($risk->controls as $control)
                                        <x-status-badge tone="ghost" outline>{{ $control->title }}</x-status-badge>
                                    @empty
                                        {{ __('isms.empty_controls_linked') }}
                                    @endforelse
                                </p>

                                {{-- Bewertungshistorie (046-D): freigegebene Stände statt Überschreiben --}}
                                <p class="font-semibold">{{ __('isms.assessment.history_title') }}:</p>
                                @if ($risk->assessments->isNotEmpty())
                                    <table class="table table-xs">
                                        <thead>
                                            <tr>
                                                <th>{{ __('isms.field.risk_no') }}</th>
                                                <th>{{ __('isms.field.assessment_kind') }}</th>
                                                <th>{{ __('isms.field.score') }}</th>
                                                <th>{{ __('isms.field.rationale') }}</th>
                                                <th>{{ __('isms.field.status') }}</th>
                                                <th>{{ __('isms.field.approved_by') }}</th>
                                                <th>{{ __('isms.field.valid_until') }}</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($risk->assessments as $assessment)
                                                <tr id="isms-assessment-{{ $assessment->id }}">
                                                    <td class="font-mono">{{ $assessment->displayNo() }}</td>
                                                    <td><x-status-badge :tone="$assessment->kind->tone()" outline>{{ $assessment->kind->label() }}</x-status-badge></td>
                                                    <td class="whitespace-nowrap">
                                                        {{ $assessment->likelihood }}×{{ $assessment->impact }} =
                                                        <x-status-badge :tone="\App\Models\Isms\IsmsRisk::scoreTone($assessment->score)">{{ $assessment->score }}</x-status-badge>
                                                    </td>
                                                    <td class="max-w-60">{{ $assessment->rationale !== null ? \Illuminate\Support\Str::limit($assessment->rationale, 80) : '—' }}</td>
                                                    <td><x-status-badge :tone="$assessment->status->tone()">{{ $assessment->status->label() }}</x-status-badge></td>
                                                    <td class="whitespace-nowrap">
                                                        @if ($assessment->isApproved())
                                                            {{ optional($assessment->approvedBy)->name ?? '—' }} · {{ $assessment->approved_at?->format('d.m.Y') }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap">
                                                        @if ($assessment->valid_until !== null)
                                                            {{ $assessment->valid_until->format('d.m.Y') }}
                                                            @if ($assessment->isReviewOverdue())
                                                                <x-status-badge tone="warning">{{ __('isms.assessment.review_overdue') }}</x-status-badge>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="text-right">
                                                        @can('update', $risk)
                                                            @unless ($assessment->isApproved())
                                                                <span class="flex justify-end gap-1">
                                                                    <x-action-form :action="route('isms.risks.assessments.approve', $assessment)"
                                                                          data-confirm-title="{{ __('isms.action.approve_assessment') }}"
                                                                          :confirm="__('isms.confirm_approve_assessment')"
                                                                          confirm-icon="task_alt"
                                                                          confirm-tone="primary"
                                                                          :confirm-label="__('isms.action.approve_assessment')">
                                                                        <x-icon-btn icon="task_alt" tone="primary" size="xs" type="submit"
                                                                                    :label="__('isms.action.approve_assessment')" />
                                                                    </x-action-form>
                                                                    <x-action-form :action="route('isms.risks.assessments.destroy', $assessment)" method="DELETE"
                                                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                                                          :confirm="__('isms.confirm_delete_assessment')"
                                                                          confirm-icon="delete"
                                                                          confirm-tone="error"
                                                                          :confirm-label="__('isms.action.delete')">
                                                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                                                    :label="__('isms.action.delete')" />
                                                                    </x-action-form>
                                                                </span>
                                                            @endunless
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p>{{ __('isms.assessment.empty') }}</p>
                                @endif
                                @can('update', $risk)
                                    <x-icon-btn icon="add" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.risks.assessments.create', $risk)"
                                                show-label>{{ __('isms.action.create_assessment') }}</x-icon-btn>
                                @endcan
                            </div>
                        </details>
                    </td>
                    <td><x-status-badge :tone="$risk->category->tone()" outline>{{ $risk->category->label() }}</x-status-badge></td>
                    <td class="text-center">
                        <x-status-badge :tone="\App\Models\Isms\IsmsRisk::scoreTone($risk->score)">{{ $risk->score }}</x-status-badge>
                        <span class="block text-xs text-base-content/50">{{ $risk->likelihood }}×{{ $risk->impact }}</span>
                    </td>
                    <td><x-status-badge :tone="$risk->treatment->tone()" outline>{{ $risk->treatment->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$risk->status->tone()">{{ $risk->status->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">{{ optional($risk->owner)->name ?? '—' }}</td>
                    <td class="{{ $risk->review_due_on !== null && $risk->review_due_on->isPast() && $risk->status->isOpen() ? 'text-error font-semibold' : 'text-base-content/70' }}">
                        {{ $risk->review_due_on?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $risk)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.risks.edit', $risk)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('transition', $risk)
                                @if ($risk->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-56 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($risk->status->allowedTransitions() as $target)
                                                <li>
                                                    <form method="POST" action="{{ route('isms.risks.transition', $risk) }}">
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
                            @can('delete', $risk)
                                <x-action-form :action="route('isms.risks.destroy', $risk)" method="DELETE"
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      :confirm="__('isms.confirm_delete_risk')"
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
                               :title="__('isms.empty_risks_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_risks')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$risks" />
    </x-index-page>
@endsection
