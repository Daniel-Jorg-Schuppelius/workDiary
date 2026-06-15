{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Liste ausgefüllter Formulare (Feature 032) mit Filter Vorlage/Zeitraum.
--}}

@extends('layouts.app')

@section('title', __('form.title.submissions'))
@section('nav-title', __('form.title.submissions'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('form.subtitle.submissions')">
        <x-slot:actions>
            @if ($activeTemplates->isNotEmpty())
                <div class="dropdown dropdown-end">
                    <x-icon-btn icon="edit_note" tone="primary" size="sm" type="button" tabindex="0" show-label>
                        {{ __('form.action.fill') }}
                    </x-icon-btn>
                    <ul tabindex="0" class="dropdown-content menu z-30 mt-1 w-64 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                        @foreach ($activeTemplates as $activeTemplate)
                            <li>
                                <a data-entry-modal-trigger
                                   href="{{ route('form-submissions.create', ['template' => $activeTemplate->sqid]) }}">
                                    {{ $activeTemplate->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @can('viewAny', \App\Models\FormTemplate::class)
                <x-icon-btn icon="assignment" tone="outline" size="sm"
                            :href="route('form-templates.index')"
                            show-label>{{ __('form.title.templates') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        @include('documents._tabs')

        <x-filter-bar :action="route('form-submissions.index')"
                      :reset="$hasActiveFilters ? route('form-submissions.index') : null">
            <x-filter-field :label="__('form.field.template')" for="form-sub-template" class="min-w-52">
                <select id="form-sub-template" name="template" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('form.filter.all') }}</option>
                    @foreach ($templates as $filterTemplate)
                        <option value="{{ $filterTemplate->sqid }}" @selected($filters['template'] === $filterTemplate->sqid)>{{ $filterTemplate->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('form.field.template') }}</th>
                    <th>{{ __('form.field.subject') }}</th>
                    <th>{{ __('form.field.submitted_by') }}</th>
                    <th>{{ __('form.field.submitted_at') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($submissions as $submission)
                <tr class="hover" id="form-submission-{{ $submission->id }}">
                    <td>
                        <a href="{{ route('form-submissions.show', $submission) }}" class="flex items-center gap-2 font-medium link-hover">
                            <x-icon name="edit_note" class="text-base-content/60" />
                            {{ optional($submission->template)->name ?? '—' }}
                        </a>
                    </td>
                    <td class="text-base-content/70">
                        @php $subjectModel = $submission->subject; @endphp
                        @if ($subjectModel !== null)
                            {{ $subjectModel->getAttribute('title') ?? $subjectModel->getAttribute('name') ?? ('#' . $subjectModel->getKey()) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ optional($submission->submitter)->name ?? '—' }}</td>
                    <td class="text-base-content/70">{{ $submission->submitted_at?->fdatetime() ?? '—' }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="visibility" tone="outline" size="xs"
                                    :href="route('form-submissions.show', $submission)"
                                    :label="__('form.action.show')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5"
                               :title="__('form.empty_submissions_title')"
                               :message="$hasActiveFilters ? __('form.empty_filtered') : __('form.empty_submissions')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$submissions" />
    </x-index-page>
@endsection
