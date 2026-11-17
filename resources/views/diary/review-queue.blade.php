{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : review-queue.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Prüf-Warteschlange (Feature 198, MVP-1636). Variablen: $entries (Paginator von DiaryEntry, Fälligkeit, dann Vorlagedatum). --}}

@extends('layouts.app')

@section('title', __('diary.review.queue.title'))
@section('nav-title', __('diary.review.queue.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('diary.review.queue.subtitle')">
    <div class="flex justify-end">
        <x-help-button topic="diary-entries.review" />
    </div>
    @if ($entries->isEmpty())
        <x-empty-state framed icon="fact_check" :title="__('diary.review.queue.empty_title')" :message="__('diary.review.queue.empty_message')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('diary.review.queue.column.order') }}</th>
                    <th>{{ __('diary.period.label') }}</th>
                    <th>{{ __('diary.review.queue.column.submitted') }}</th>
                    <th>{{ __('Fällig bis') }}</th>
                    <th class="text-right">{{ __('diary.review.queue.column.actions') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($entries as $entry)
                <tr class="hover">
                    <td>
                        <x-order-link :entry="$entry" link-class="link link-hover font-medium">{{ \App\Services\Diary\Approvals\OrderReviewInboxSubject::title($entry) }}</x-order-link>
                        <div class="text-xs text-muted">{{ implode(' · ', array_filter([$entry->customer?->name, $entry->entryType?->label, $entry->assignedUser?->name])) }}</div>
                    </td>
                    <td class="whitespace-nowrap text-sm">{{ $entry->period_key?->label() ?? '—' }}</td>
                    <td class="whitespace-nowrap text-sm">
                        {{ $entry->reviewSubmitter?->name ?? '—' }}
                        <div class="text-xs text-muted tabular-nums">{{ $entry->review_submitted_at?->fdatetime() ?? '—' }}</div>
                    </td>
                    <td class="whitespace-nowrap text-sm tabular-nums {{ $entry->due_date?->isPast() ? 'text-error font-semibold' : '' }}">{{ $entry->due_date?->fdate() ?? '—' }}</td>
                    <td class="text-right">
                        @can('approveReview', $entry)
                            <form method="POST" action="{{ route('diary.lifecycle', [$entry, 'action' => 'approveReview']) }}" class="inline">
                                @csrf
                                <x-icon-btn type="submit" icon="verified" tone="success" size="xs" show-label>{{ __('diary.lifecycle.action.approve_review') }}</x-icon-btn>
                            </form>
                            {{-- Rückfrage mit Pflichttext im Auftrag (Dialog dort). --}}
                            <x-order-link :entry="$entry" link-class="btn btn-xs btn-warning">{{ __('diary.lifecycle.action.query_review') }}</x-order-link>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$entries" standing />
    @endif
</x-index-page>
@endsection
