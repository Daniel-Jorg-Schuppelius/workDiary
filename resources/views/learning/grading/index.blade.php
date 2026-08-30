{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Betreuer-Cockpit (Feature 149, MVP-739): offene Bewertungen an einer
  Stelle — Aufgaben-Abgaben und Aufsätze aus Prüfungen.
--}}
@extends('layouts.app')
@section('title', __('learning.title.grading'))
@section('nav-title', __('learning.title.grading'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.grading')">
    <x-slot:actions>
        <x-help-button topic="learning.overview" />
    </x-slot:actions>

    @if ($essays->isNotEmpty())
        <x-card class="mt-4">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                <x-icon name="edit_note" class="text-muted" /> {{ __('learning.field.open_essays') }}
            </h3>
            @foreach ($essays as $answer)
                <div class="mb-3 rounded-box border border-base-300 p-3">
                    <p class="text-sm font-medium">{{ $answer->question?->prompt }}</p>
                    <p class="mt-1 text-xs text-muted">
                        {{ $answer->attempt?->enrollment?->learnerName() }} ·
                        {{ $answer->attempt?->quiz?->title }} ·
                        {{ __('learning.field.attempt') }} {{ $answer->attempt?->attempt_no }}
                    </p>
                    <p class="mt-2 whitespace-pre-line text-sm text-base-content/80">{{ $answer->payload['text'] ?? '' }}</p>

                    <form method="POST" action="{{ route('learning.grading.essay', $answer) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <x-input-field name="points" type="number" min="0" max="1000" class="w-28"
                                       :label="__('learning.field.score')" :value="0" required />
                        <x-input-field name="note" :label="__('learning.field.correction')" maxlength="500" class="w-64" />
                        <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.grade') }}</x-icon-btn>
                    </form>
                </div>
            @endforeach
        </x-card>
    @endif

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.learner') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.course') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.assignment') }}</x-table.th>
                <x-table.th sort type="date">{{ __('learning.field.submitted_at') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($submissions as $submission)
            <tr class="hover">
                <td class="font-medium">{{ $submission->enrollment?->learnerName() }}</td>
                <td class="text-sm text-base-content/70">{{ $submission->assignment?->unit?->course?->title }}</td>
                <td class="text-sm">{{ $submission->assignment?->title }}</td>
                <td class="text-sm">{{ $submission->submitted_at?->translatedFormat('d.m.Y H:i') }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="grading" tone="primary" size="xs"
                                    :href="route('learning.grading.submission', $submission)"
                                    :label="__('learning.action.grade')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="grading" :colspan="5" :title="__('learning.empty.grading')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$submissions" standing />

</x-index-page>
@endsection
