{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfungsangebote (Feature 159, MVP-847): Termine der Art Prüfung mit Ordnung, Regelversion, Zielgraden und Kandidatenstand. --}}
@extends('layouts.app')
@section('title', __('club.exams.title.index'))
@section('nav-title', __('club.exams.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.exams.subtitle.index')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.exams.create')" show-label>{{ __('club.exams.action.create_offer') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.exams" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.exams.index')" :reset="route('club.exams.index')">
        <div class="join" role="group" aria-label="{{ __('club.events.filter.period') }}">
            @foreach (['upcoming', 'range', 'past'] as $option)
                <a href="{{ route('club.exams.index', ['period' => $option]) }}" class="btn btn-sm join-item {{ $period === $option ? 'btn-primary' : 'btn-outline' }}">{{ __('club.events.filter.period_' . $option) }}</a>
            @endforeach
        </div>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.events.field.title') }}</th>
                <th>{{ __('club.grading.field.system') }}</th>
                <th>{{ __('club.exams.field.target_grades') }}</th>
                <th class="text-right">{{ __('club.exams.field.candidates') }}</th>
                <th class="text-right">{{ __('club.exams.field.admitted') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($offers as $offer)
            <tr class="hover">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $offer->event?->started_at?->orgTz()->format('d.m.Y H:i') }}</td>
                <td class="font-medium">
                    <a href="{{ route('club.exams.show', $offer) }}" class="link link-hover">{{ $offer->event?->title }}</a>
                    @if ($offer->event?->isCancelled())<x-status-badge tone="error" size="xs" :label="__('club.events.label.cancelled')" />@endif
                </td>
                <td class="text-sm">{{ $offer->system?->name }} <span class="text-xs text-muted">v{{ $offer->version?->version_no }}</span></td>
                <td class="text-sm">{{ $offer->targetGrades->pluck('name')->join(', ') }}</td>
                <td class="text-right tabular-nums">{{ $offer->candidates_count }}</td>
                <td class="text-right tabular-nums">{{ $offer->admitted_count }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.exams.show', $offer)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="workspace_premium" :colspan="7" :title="__('club.exams.empty.offers')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
