{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : submissions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Offene Einreichungen zur Personalakte (MVP-987) für den Akten-Kreis. Variablen: $submissions
--}}
@extends('layouts.app')
@section('title', __('hr.personnel_file.submission.title'))
@section('nav-title', __('hr.personnel_file.submission.title'))
@include('partials.page-fill')
@section('content')
<x-index-page overflow="clip" :subtitle="__('hr.personnel_file.submission.subtitle')" back-route="org.members.index" :back-label="__('hr.personnel_file.back')">
    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('hr.personnel_file.submission.person') }}</th>
                <th>{{ __('hr.personnel_file.field.title') }}</th>
                <th>{{ __('hr.personnel_file.field.category') }}</th>
                <th>{{ __('hr.personnel_file.submission.submitted_at') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($submissions as $submission)
            <tr class="hover">
                <td class="font-medium">
                    @if ($submission->user)
                        <a class="link link-hover" href="{{ route('org.members.personnel-file.index', $submission->user) }}">{{ $submission->user->name }}</a>
                    @endif
                </td>
                <td>{{ $submission->title }}@if ($submission->note)<span class="block text-xs text-muted">{{ $submission->note }}</span>@endif</td>
                <td><x-status-badge tone="ghost" outline>{{ $submission->hr_category->label() }}</x-status-badge></td>
                <td class="text-sm">{{ $submission->created_at?->fdate() }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="download" tone="outline" size="xs" :href="route('personnel-file.submissions.download', $submission)" :label="__('hr.personnel_file.action.download')" />
                        <x-icon-btn icon="check" tone="primary" size="xs" data-entry-modal-trigger :href="route('personnel-file.submissions.accept-form', $submission)" :label="__('hr.personnel_file.action.accept')" />
                        <x-icon-btn icon="block" tone="error" size="xs" data-entry-modal-trigger :href="route('personnel-file.submissions.reject-form', $submission)" :label="__('hr.personnel_file.action.reject')" />
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="outbox" :colspan="5" :title="__('hr.personnel_file.submission.empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
