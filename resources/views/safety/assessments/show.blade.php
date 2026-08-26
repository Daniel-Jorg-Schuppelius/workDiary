{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Detailseite Gefährdungsbeurteilung (Feature 132): Kopf, Positionen mit
  Risiko vor/nach (Modal-Dialoge), Statusmaschine, Versionskette.
--}}
@extends('layouts.app')
@section('title', $assessment->displayNo())
@section('nav-title', $assessment->displayNo())
@section('content')
@php
    $isApproved = $assessment->status === \App\Enums\Safety\HazardAssessmentStatus::Approved;
    $editable = $canManage && $assessment->isEditable();
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$assessment->area . ($assessment->activity ? ' · ' . $assessment->activity : '')"
                        :badge="$assessment->status->label()" :badgeTone="$assessment->status->tone()">
            <x-slot:actions>
                @if ($editable)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('safety.assessments.edit', $assessment)"
                                show-label>{{ __('safety.register.action.edit') }}</x-icon-btn>
                @endif
                @if ($canManage && $isApproved)
                    <x-action-form :action="route('safety.assessments.new-version', $assessment)">
                        <x-icon-btn type="submit" icon="difference" tone="primary" size="sm" show-label>{{ __('safety.register.action.new_version') }}</x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('safety.assessments.index')"
                            show-label>{{ __('safety.register.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $assessment->displayNo() }}</h2>
                    <x-status-badge :tone="$assessment->status->tone()" size="sm">{{ $assessment->status->label() }}</x-status-badge>
                    @if ($isApproved && $assessment->isReviewOverdue())
                        <x-status-badge tone="error" size="sm">{{ __('safety.register.kpi.review_due') }}</x-status-badge>
                    @endif
                </div>
                @if ($isApproved)
                    <p class="mt-2 text-xs text-muted">{{ __('safety.register.hint.frozen') }}</p>
                @endif
                <div class="divider my-3"></div>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('safety.register.field.area')" :value="$assessment->area" />
                    <x-detail-grid.row :label="__('safety.register.field.activity')" :value="$assessment->activity ?? '–'" />
                    <x-detail-grid.row :label="__('safety.register.field.description')" :value="$assessment->description ?? '–'" />
                    <x-detail-grid.row :label="__('safety.register.field.review_due_on')" :value="$assessment->review_due_on?->format('d.m.Y') ?? '–'" />
                    <x-detail-grid.row :label="__('safety.register.field.created_by')" :value="$assessment->createdBy?->name ?? '–'" />
                    @if ($assessment->approved_at)
                        <x-detail-grid.row :label="__('safety.register.field.approved_by')" :value="$assessment->approvedBy?->name ?? '–'" />
                        <x-detail-grid.row :label="__('safety.register.field.approved_at')" :value="$assessment->approved_at->format('d.m.Y H:i')" />
                    @endif
                    @if ($assessment->supersedes)
                        <x-detail-grid.row :label="__('safety.register.field.supersedes')">
                            <a class="link link-hover font-mono" href="{{ route('safety.assessments.show', $assessment->supersedes) }}">{{ $assessment->supersedes->displayNo() }}</a>
                        </x-detail-grid.row>
                    @endif
                    @if ($assessment->successors->isNotEmpty())
                        <x-detail-grid.row :label="__('safety.register.field.superseded_by')">
                            @foreach ($assessment->successors as $successor)
                                <a class="link link-hover font-mono" href="{{ route('safety.assessments.show', $successor) }}">{{ $successor->displayNo() }}</a>
                            @endforeach
                        </x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </x-card>

            {{-- Gefährdungs-Positionen --}}
            <x-card>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="warning_amber" class="text-muted" /> {{ __('safety.register.field.items') }}
                        <span class="font-normal text-muted">({{ $assessment->items->count() }})</span>
                    </h3>
                    @if ($editable)
                        <x-icon-btn icon="add" tone="primary" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('safety.assessments.items.create', $assessment)"
                                    show-label>{{ __('safety.register.action.add_item') }}</x-icon-btn>
                    @endif
                </div>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('safety.register.field.position') }}</th>
                            <th>{{ __('safety.register.field.hazard') }}</th>
                            <th>{{ __('safety.register.field.measure') }}</th>
                            <th class="text-center">{{ __('safety.register.field.risk_before') }}</th>
                            <th class="text-center">{{ __('safety.register.field.risk_after') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($assessment->items as $item)
                        <tr class="hover">
                            <td class="font-mono text-sm">{{ $item->position }}</td>
                            <td class="text-sm font-medium">{{ $item->hazard }}</td>
                            <td class="text-sm text-base-content/70">{{ $item->measure ?? '–' }}</td>
                            <td class="text-center">
                                <x-status-badge :tone="\App\Models\Safety\HazardAssessmentItem::riskTone($item->risk_before)" size="sm">
                                    {{ $item->risk_before }} <span class="opacity-60">({{ $item->severity_before }}×{{ $item->likelihood_before }})</span>
                                </x-status-badge>
                            </td>
                            <td class="text-center">
                                @if ($item->risk_after !== null)
                                    <x-status-badge :tone="\App\Models\Safety\HazardAssessmentItem::riskTone($item->risk_after)" size="sm">
                                        {{ $item->risk_after }} <span class="opacity-60">({{ $item->severity_after }}×{{ $item->likelihood_after }})</span>
                                    </x-status-badge>
                                @else
                                    <span class="text-muted">–</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($editable)
                                    <div class="flex justify-end gap-1">
                                        <x-icon-btn icon="edit" tone="outline" size="xs"
                                                    data-entry-modal-trigger
                                                    :href="route('safety.assessments.items.edit', [$assessment, $item])"
                                                    :label="__('safety.register.action.edit_item')" />
                                        <x-action-form :action="route('safety.assessments.items.destroy', [$assessment, $item])" method="DELETE"
                                                       :confirm="__('safety.register.confirm.delete_item')"
                                                       confirm-icon="delete" confirm-tone="error"
                                                       :confirm-label="__('safety.register.action.delete')">
                                            <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('safety.register.action.delete')" />
                                        </x-action-form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('safety.register.empty.items')" :message="$editable ? __('safety.register.hint.approve_requires_items') : null" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            @if ($canManage && $assessment->status->allowedTransitions() !== [])
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold">{{ __('safety.register.action.transition') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($assessment->status->allowedTransitions() as $target)
                            <x-action-form :action="route('safety.assessments.transition', $assessment)">
                                <input type="hidden" name="status" value="{{ $target->value }}">
                                <x-icon-btn type="submit" size="sm" tone="outline" show-label
                                            icon="{{ $target === \App\Enums\Safety\HazardAssessmentStatus::Approved ? 'verified' : ($target === \App\Enums\Safety\HazardAssessmentStatus::Archived ? 'inventory_2' : 'arrow_forward') }}">{{ $target->label() }}</x-icon-btn>
                            </x-action-form>
                        @endforeach
                    </div>
                    @if ($errors->has('status'))
                        <p class="mt-2 text-sm text-error">{{ $errors->first('status') }}</p>
                    @endif
                </x-card>
            @endif

            @if ($editable)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold">{{ __('safety.register.action.delete') }}</h3>
                    <x-action-form :action="route('safety.assessments.destroy', $assessment)" method="DELETE"
                                   :confirm="__('safety.register.confirm.delete_assessment')"
                                   confirm-icon="delete" confirm-tone="error"
                                   :confirm-label="__('safety.register.action.delete')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('safety.register.action.delete') }}</x-icon-btn>
                    </x-action-form>
                </x-card>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
