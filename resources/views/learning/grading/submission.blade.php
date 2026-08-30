{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : submission.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bewertung einer Aufgaben-Abgabe (Feature 149, MVP-739). Die Rubrik macht
  die Bewertung erklärbar; ohne Rubrik zählt die Gesamtpunktzahl.
--}}
@extends('layouts.app')
@section('title', __('learning.action.grade'))
@section('nav-title', __('learning.action.grade'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$submission->assignment?->title"
                        :badge="$submission->status->label()"
                        :badgeTone="$submission->status->tone()">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.grading.index')"
                            show-label>{{ __('learning.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-2 text-sm font-semibold">{{ __('learning.field.submission') }}</h3>
                <p class="whitespace-pre-line text-sm text-base-content/80">{{ $submission->body }}</p>

                @if ($submission->attachments->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($submission->attachments as $attachment)
                            <li class="flex items-center gap-2">
                                <x-icon name="attach_file" class="text-muted" />
                                {{-- Ohne Download sieht die bewertende Person nur den
                                     Dateinamen — das wäre keine Bewertung. --}}
                                <a class="link" href="{{ route('learning.grading.submission.file', [$submission->sqid, $attachment->sqid]) }}">
                                    {{ $attachment->original_name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.action.grade') }}</h3>
                <form method="POST" action="{{ route('learning.grading.submission.grade', $submission) }}">
                    @csrf
                    @if ($criteria !== [])
                        <x-form-group :legend="__('learning.field.rubric')" icon="checklist" tone="primary" cols="1">
                            @foreach ($criteria as $criterion)
                                <x-input-field type="number" min="0" :max="$criterion['max_points'] ?? 10"
                                               :name="'rubric_scores[' . ($criterion['key'] ?? '') . ']'"
                                               :label="($criterion['label'] ?? '') . ' (max. ' . ($criterion['max_points'] ?? 0) . ')'"
                                               :value="0" />
                            @endforeach
                        </x-form-group>
                    @else
                        <x-form-group :legend="__('learning.field.score')" icon="grading" tone="primary" cols="1">
                            <x-input-field name="points" type="number" min="0" :max="$submission->assignment?->points ?? 100"
                                           :label="__('learning.field.score')" :value="0" required />
                        </x-form-group>
                    @endif
                    <x-form-group :legend="__('learning.field.feedback')" icon="comment" tone="info" cols="1">
                        <x-textarea-field name="feedback" :label="__('learning.field.feedback')" rows="4" maxlength="5000" />
                    </x-form-group>
                    <div class="mt-3 flex justify-end gap-2">
                        <x-icon-btn icon="grading" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.grade') }}</x-icon-btn>
                    </div>
                </form>

                <form method="POST" action="{{ route('learning.grading.submission.return', $submission) }}" class="mt-4 border-t border-base-300 pt-4">
                    @csrf
                    <x-textarea-field name="feedback" :label="__('learning.action.return_for_revision')" rows="2" maxlength="5000" required />
                    <div class="mt-2 flex justify-end">
                        <x-icon-btn icon="undo" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.return_for_revision') }}</x-icon-btn>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.learner') }}</h3>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.learner')" :value="$submission->enrollment?->learnerName() ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.course')" :value="$submission->assignment?->unit?->course?->title ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.submitted_at')" :value="$submission->submitted_at?->translatedFormat('d.m.Y H:i') ?? '–'" />
                    <x-detail-grid.row :label="__('learning.field.attempt')" :value="$submission->attempt_no" />
                    <x-detail-grid.row :label="__('learning.field.pass_percent')" :value="($submission->assignment?->pass_percent ?? 0) . ' %'" />
                </x-detail-grid>

                @if ($submission->assignment?->requires_second_opinion)
                    <p class="mt-3 text-xs text-muted">{{ __('learning.help.second_opinion') }}</p>
                    @if ($submission->status === \App\Enums\Learning\LearningSubmissionStatus::Graded && ! $submission->second_opinion_at)
                        <form method="POST" action="{{ route('learning.grading.submission.confirm', $submission) }}" class="mt-2">
                            @csrf
                            <x-icon-btn icon="how_to_reg" tone="outline" size="sm" type="submit" show-label>{{ __('learning.action.second_opinion') }}</x-icon-btn>
                        </form>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
