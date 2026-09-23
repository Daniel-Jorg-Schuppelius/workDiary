{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : grading.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Graduierung je Mitglied (MVP-846): Grad je Ordnung, Voraussetzungen für den nächsten Grad zum heutigen Stichtag, Historie, Nachweise. --}}
@extends('layouts.app')
@section('title', __('club.grading.title.member'))
@section('nav-title', __('club.grading.title.member'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$member->fullName() . ' · ' . $member->displayNo()">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="workspace_premium" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.members.grades.create', $member)" show-label>{{ __('club.grading.action.recognize') }}</x-icon-btn>
                    <x-icon-btn icon="task" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.members.proofs.create', $member)" show-label>{{ __('club.grading.action.add_proof') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.members.show', $member)" show-label>{{ __('club.action.back') }}</x-icon-btn>
                <x-help-button topic="club.grading" />
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
    @unless ($enabled)
        <div class="alert alert-info text-sm" role="status"><x-icon name="info" /><span>{{ __('club.grading.hint.disabled') }}</span></div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($overview as $row)
                <x-card :title="$row['system']->name . ' · ' . $row['system']->discipline" icon="military_tech">
                    <p class="text-sm">
                        <span class="text-muted">{{ __('club.grading.field.current_grade') }}:</span>
                        <strong>{{ $row['current']?->grade?->name ?? __('club.grading.label.no_grade') }}</strong>
                        @if ($row['current'])<span class="text-xs text-muted">· {{ __('club.grading.label.since', ['date' => $row['current']->obtained_on->format('d.m.Y')]) }}</span>@endif
                    </p>
                    @if ($row['requirement'] && $row['report'])
                        @php $report = $row['report']; @endphp
                        <p class="mt-2 text-sm">
                            <span class="text-muted">{{ __('club.grading.field.next_grade') }}:</span>
                            <strong>{{ $row['requirement']->grade?->name }}</strong>
                            <x-status-badge :tone="$report->met ? 'success' : 'warning'" size="xs" :label="$report->met ? __('club.grading.label.eligible') : __('club.grading.label.not_eligible')" />
                        </p>
                        <p class="text-xs text-muted">{{ __('club.grading.label.exam_day', ['date' => $report->examDay->format('d.m.Y')]) }}@if ($report->countingFrom) · {{ __('club.grading.label.counting_from', ['date' => $report->countingFrom->format('d.m.Y')]) }}@endif</p>
                        @if ($report->progressText())
                            <p class="mt-1 text-sm font-medium">{{ $report->progressText() }}</p>
                            @if ($report->unitsText())<p class="text-xs text-muted">{{ $report->unitsText() }}</p>@endif
                        @endif
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($report->items as $item)
                                <li class="flex flex-wrap items-center gap-2">
                                    <x-icon :name="$item['met'] ? 'check_circle' : 'radio_button_unchecked'" class="{{ $item['met'] ? 'text-success' : 'text-warning' }}" />
                                    <span>{{ __('club.grading.item.' . $item['key']) }}</span>
                                    <span class="text-xs text-muted">{{ __('club.grading.label.required_actual', ['required' => $item['required'], 'actual' => $item['actual']]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($row['current'])
                        <p class="mt-2 text-xs text-muted">{{ __('club.grading.label.top_grade') }}</p>
                    @else
                        <p class="mt-2 text-xs text-muted">{{ __('club.grading.label.no_active_version') }}</p>
                    @endif
                </x-card>
            @empty
                <x-empty-state framed icon="military_tech" :title="__('club.grading.empty.systems')" />
            @endforelse
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.grading.card.history')" icon="history" :count="$history->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($history as $item)
                        <li class="flex flex-wrap items-center gap-2 {{ $item->isRevoked() ? 'line-through opacity-60' : '' }}">
                            <span class="tabular-nums">{{ $item->obtained_on->format('d.m.Y') }}</span>
                            <span class="font-medium">{{ $item->grade?->name }}</span>
                            <span class="text-xs text-muted">{{ $item->system?->discipline }} · {{ $item->source->label() }}@if ($item->confirmedBy) · {{ $item->confirmedBy->name }}@endif</span>
                            @if ($item->evidence)<span class="text-xs text-muted">· {{ $item->evidence }}</span>@endif
                            @if ($item->isRevoked())
                                <x-status-badge tone="error" size="xs" :label="__('club.grading.label.revoked')" :title="$item->revoke_reason" />
                            @else
                                <span class="ml-auto flex gap-1">
                                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="xs" :href="route('club.members.grades.certificate', [$member, $item])" :label="__('club.exams.action.certificate')" />
                                    @if ($canManage)
                                        <x-icon-btn icon="undo" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.members.grades.revoke.edit', [$member, $item])" :label="__('club.grading.action.revoke')" />
                                    @endif
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.grading.empty.history') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('club.exams.card.member_exams')" icon="workspace_premium" :count="$candidates->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($candidates as $candidate)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $candidate->offer?->event?->started_at?->orgTz()->format('d.m.Y') }}</span>
                            <a href="{{ route('club.exams.show', $candidate->offer) }}" class="link link-hover font-medium">{{ $candidate->offer?->event?->title }}</a>
                            <span class="text-xs text-muted">{{ $candidate->targetGrade?->name }}</span>
                            <x-status-badge :tone="$candidate->status->tone()" size="xs">{{ $candidate->status->label() }}</x-status-badge>
                            @if ($candidate->needsReview())<x-status-badge tone="warning" size="xs" :label="__('club.exams.label.review_required')" :title="$candidate->review_note" />@endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.exams.empty.member_exams') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('club.grading.card.proofs')" icon="task" :count="$proofs->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($proofs as $proof)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{{ $proof->obtained_on->format('d.m.Y') }}</span>
                            <span class="font-medium">{{ $proof->label }}</span>
                            <x-status-badge tone="ghost" size="xs">{{ $proof->kind->label() }}</x-status-badge>
                            @if ($proof->minutes)<span class="text-xs text-muted">{{ \App\Services\Club\ClubEligibilityReport::hoursMinutes($proof->minutes) }}</span>@endif
                            @if ($proof->valid_until)<span class="text-xs text-muted">{{ __('club.grading.label.valid_until', ['date' => $proof->valid_until->format('d.m.Y')]) }}</span>@endif
                            @if ($proof->origin)<span class="text-xs text-muted">· {{ $proof->origin }}</span>@endif
                            @if ($canManage)
                                <span class="ml-auto flex gap-1">
                                    <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.members.proofs.edit', [$member, $proof])" :label="__('club.action.edit')" />
                                    <x-action-form :action="route('club.members.proofs.destroy', [$member, $proof])" method="DELETE" :confirm="__('club.grading.confirm.delete_proof')" confirm-icon="delete" confirm-tone="error">
                                        <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                    </x-action-form>
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.grading.empty.proofs') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
