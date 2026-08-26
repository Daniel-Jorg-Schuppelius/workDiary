{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Register der Gefährdungsbeurteilungen (Feature 132): Voll-Höhe-Tabelle
  Nummer/Bereich/Status/Version/Wiedervorlage, KPI Wiedervorlage fällig.
--}}
@extends('layouts.app')
@section('title', __('safety.register.title.assessments'))
@section('nav-title', __('safety.register.title.assessments'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.register.subtitle.assessments')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('safety.assessments.create')"
                        show-label>{{ __('safety.register.action.create_assessment') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('safety.assessments.index')" :reset="route('safety.assessments.index')">
        <x-filter-field :label="__('safety.register.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('safety.register.filter.current_only') }}</option>
                @foreach (\App\Enums\Safety\HazardAssessmentStatus::cases() as $st)
                    <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('safety.register.kpi.review_due')" for="flt-review">
            <span id="flt-review" class="badge {{ $reviewDueCount > 0 ? 'badge-error' : 'badge-ghost' }} badge-sm">{{ $reviewDueCount }}</span>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('safety.register.field.assessment_no') }}</th>
                <th>{{ __('safety.register.field.area') }}</th>
                <th>{{ __('safety.register.field.activity') }}</th>
                <th>{{ __('safety.register.field.status') }}</th>
                <th class="text-center">{{ __('safety.register.field.version') }}</th>
                <th class="text-center">{{ __('safety.register.field.items') }}</th>
                <th>{{ __('safety.register.field.review_due_on') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($assessments as $assessment)
            <tr class="hover">
                <td class="font-mono text-sm">GB-{{ $assessment->assessment_no }}</td>
                <td class="font-medium">{{ $assessment->area }}</td>
                <td class="text-sm text-base-content/70">{{ $assessment->activity ?? '–' }}</td>
                <td><x-status-badge :tone="$assessment->status->tone()" size="sm">{{ $assessment->status->label() }}</x-status-badge></td>
                <td class="text-center font-mono text-sm">v{{ $assessment->version }}</td>
                <td class="text-center text-sm">{{ $assessment->items_count }}</td>
                <td class="text-sm {{ $assessment->status === \App\Enums\Safety\HazardAssessmentStatus::Approved && $assessment->isReviewOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">
                    {{ $assessment->review_due_on?->format('d.m.Y') ?? '–' }}
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('safety.assessments.show', $assessment)" :label="__('safety.register.action.show')" />
                        @if ($canManage && $assessment->isEditable())
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('safety.assessments.edit', $assessment)"
                                        :label="__('safety.register.action.edit')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="checklist" :colspan="8" :title="__('safety.register.empty.assessments')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$assessments" standing />
</x-index-page>
@endsection
