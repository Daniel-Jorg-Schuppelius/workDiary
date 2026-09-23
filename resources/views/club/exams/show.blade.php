{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfungsangebot (MVP-847): Termin, Regelversion, Zielgrade, Prüfer; Kandidaten mit Zulassungsbericht, Freigabe, Zulassung/Ausnahme, Ablehnung, Ergebnis. --}}
@extends('layouts.app')
@section('title', $event?->title ?? __('club.exams.title.index'))
@section('nav-title', __('club.exams.title.index'))
@section('content')
@php
    $start = $event?->started_at?->orgTz();
    $isCancelled = $event?->isCancelled() ?? false;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="($event?->title ?? '') . ($start ? ' · ' . $start->format('d.m.Y H:i') : '')"
                        :badge="$isCancelled ? __('club.events.label.cancelled') : $offer->system?->name . ' v' . $offer->version?->version_no"
                        :badgeTone="$isCancelled ? 'error' : 'primary'">
            <x-slot:actions>
                @if ($canCandidates && ! $isCancelled)
                    <x-icon-btn icon="person_add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.exams.candidates.create', $offer)" show-label>{{ __('club.exams.action.add_candidate') }}</x-icon-btn>
                    <x-action-form :action="route('club.exams.recheck', $offer)">
                        <x-icon-btn type="submit" icon="rule" tone="outline" size="sm" show-label>{{ __('club.exams.action.recheck') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.exams.edit', $offer)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                @if ($event)
                    <x-icon-btn icon="event" tone="ghost" size="sm" :href="route('club.events.show', $event)" show-label>{{ __('club.exams.action.open_event') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.exams.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
                <x-help-button topic="club.exams" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.exams.card.candidates')" icon="groups" :count="$candidates->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.exams.hint.candidates') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.exams.field.target_grade') }}</th>
                            <th>{{ __('club.exams.field.eligibility') }}</th>
                            <th>{{ __('club.field.status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($candidates as $candidate)
                        @php $report = (array) ($candidate->eligibility_report ?? []); @endphp
                        <tr class="align-top">
                            <td class="font-medium">
                                @if ($candidate->member)
                                    <a href="{{ route('club.members.grading', $candidate->member) }}" class="link link-hover">{{ $candidate->member->fullName() }}</a>
                                @endif
                                @if ($candidate->hasException())<x-status-badge tone="warning" size="xs" :label="__('club.exams.label.exception')" :title="$candidate->exception_reason" />@endif
                                @if ($candidate->needsReview())<x-status-badge tone="warning" size="xs" :label="__('club.exams.label.review_required')" :title="$candidate->review_note" />@endif
                            </td>
                            <td class="text-sm">{{ $candidate->targetGrade?->name }}</td>
                            <td class="text-xs">
                                @if ($candidate->checked_at)
                                    <x-status-badge :tone="$candidate->eligibility_met ? 'success' : 'warning'" size="xs" :label="$candidate->eligibility_met ? __('club.grading.label.eligible') : __('club.grading.label.not_eligible')" />
                                    <span class="text-muted">{{ __('club.exams.label.checked_at', ['date' => $candidate->checked_at->orgTz()->format('d.m.Y H:i')]) }}</span>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ((array) ($report['items'] ?? []) as $item)
                                            <li class="flex items-center gap-1">
                                                <x-icon :name="($item['met'] ?? false) ? 'check_circle' : 'radio_button_unchecked'" class="{{ ($item['met'] ?? false) ? 'text-success' : 'text-warning' }}" />
                                                {{ __('club.grading.item.' . ($item['key'] ?? 'minutes')) }}: {{ $item['actual'] ?? '' }} / {{ $item['required'] ?? '' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted">–</span>
                                @endif
                            </td>
                            <td>
                                <x-status-badge :tone="$candidate->status->tone()" size="sm">{{ $candidate->status->label() }}</x-status-badge>
                                @if ($candidate->result_recorded_at)
                                    <span class="block text-xs text-muted">{{ $candidate->result_recorded_at->orgTz()->format('d.m.Y H:i') }}@if ($candidate->resultBy) · {{ $candidate->resultBy->name }}@endif</span>
                                    @if ($candidate->result_note)<span class="block text-xs text-muted">{{ $candidate->result_note }}</span>@endif
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($canCandidates && $candidate->status->isOpen() && ! $isCancelled)
                                        @if ($candidate->status === \App\Enums\Club\ClubExamCandidateStatus::Requested)
                                            @if ($candidate->approved_at === null)
                                                <x-action-form :action="route('club.exams.candidates.approve', [$offer, $candidate])">
                                                    <x-icon-btn type="submit" icon="thumb_up" tone="outline" size="xs" :label="__('club.exams.action.approve')" />
                                                </x-action-form>
                                            @endif
                                            @if ($candidate->eligibility_met)
                                                <x-action-form :action="route('club.exams.candidates.admit', [$offer, $candidate])">
                                                    <x-icon-btn type="submit" icon="how_to_reg" tone="primary" size="xs" :label="__('club.exams.action.admit')" />
                                                </x-action-form>
                                            @elseif ($canException)
                                                <x-icon-btn icon="how_to_reg" tone="outline" size="xs" class="btn-warning" data-entry-modal-trigger :href="route('club.exams.candidates.exception.edit', [$offer, $candidate])" :label="__('club.exams.action.admit_exception')" />
                                            @endif
                                        @endif
                                        @if ($candidate->needsReview())
                                            <x-action-form :action="route('club.exams.candidates.review', [$offer, $candidate])" :confirm="__('club.exams.confirm.clear_review')" confirm-icon="task_alt" confirm-tone="primary">
                                                <input type="hidden" name="note" value="{{ __('club.exams.label.review_manual') }}">
                                                <x-icon-btn type="submit" icon="task_alt" tone="outline" size="xs" :label="__('club.exams.action.clear_review')" />
                                            </x-action-form>
                                        @endif
                                        <x-action-form :action="route('club.exams.candidates.reject', [$offer, $candidate])" :confirm="__('club.exams.confirm.reject')" confirm-icon="person_remove" confirm-tone="warning">
                                            <x-icon-btn type="submit" icon="person_remove" tone="outline" size="xs" class="btn-warning" :label="__('club.exams.action.reject')" />
                                        </x-action-form>
                                    @endif
                                    @if ($canResult && $candidate->status === \App\Enums\Club\ClubExamCandidateStatus::Admitted)
                                        <x-icon-btn icon="grading" tone="primary" size="xs" data-entry-modal-trigger :href="route('club.exams.candidates.result.edit', [$offer, $candidate])" :label="__('club.exams.action.result')" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="groups" :colspan="5" :title="__('club.exams.empty.candidates')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.exams.card.offer')" icon="info">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('club.grading.field.system') }}</dt><dd>{{ $offer->system?->name }} · {{ $offer->system?->discipline }}</dd>
                    <dt class="text-muted">{{ __('club.exams.field.version') }}</dt><dd>v{{ $offer->version?->version_no }} <span class="text-xs text-muted">({{ __('club.exams.hint.version_frozen') }})</span></dd>
                    <dt class="text-muted">{{ __('club.exams.field.target_grades') }}</dt><dd>{{ $offer->targetGrades->pluck('name')->join(', ') }}</dd>
                    <dt class="text-muted">{{ __('club.exams.field.examiners') }}</dt><dd>{{ $examiners->pluck('name')->join(', ') ?: '–' }}</dd>
                    @if ($event)
                        <dt class="text-muted">{{ __('club.events.field.groups') }}</dt><dd>{{ $event->clubGroups->pluck('name')->join(', ') ?: '–' }}</dd>
                        <dt class="text-muted">{{ __('club.events.field.ends') }}</dt><dd>{{ $event->ended_at->orgTz()->format('d.m.Y H:i') }}</dd>
                    @endif
                </dl>
                @if ($offer->notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $offer->notes }}</p>@endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
