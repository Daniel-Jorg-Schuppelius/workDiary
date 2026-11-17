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
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('form.subtitle.submissions')">
        <x-slot:actions>
            @if ($activeTemplates->isNotEmpty())
                <x-action-menu icon="edit_note" tone="primary" :label="__('form.action.fill')" placement="bar">
                    @foreach ($activeTemplates as $activeTemplate)
                        <x-button tone="ghost" data-entry-modal-trigger
                                  :href="route('form-submissions.create', ['template' => $activeTemplate->sqid])">{{ $activeTemplate->name }}</x-button>
                    @endforeach
                </x-action-menu>
            @endif
            @can('viewAny', \App\Models\Form\FormTemplate::class)
                <x-icon-btn icon="assignment" tone="outline" size="sm"
                            :href="route('form-templates.index')"
                            show-label>{{ __('form.title.templates') }}</x-icon-btn>
            @endcan
        </x-slot:actions>


        {{-- Eigene Entwürfe (Feature 203): je Feld gespeichert, noch nicht abgesendet. --}}
        @if ($drafts->isNotEmpty())
            <x-card as="section" class="mb-4" :title="__('form.title.drafts')" :count="$drafts->count()">
                <ul class="divide-y divide-base-300">
                    @foreach ($drafts as $draft)
                        <li id="form-draft-{{ $draft->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <div class="min-w-0">
                                <span class="flex items-center gap-2 font-medium">
                                    <x-icon name="edit_note" class="text-muted" />
                                    {{ optional($draft->template)->name ?? '—' }}
                                </span>
                                <span class="block text-xs text-muted">
                                    <x-subject-link :for="$draft" />
                                    · {{ __('form.draft.saved_at', ['date' => $draft->updated_at?->fdatetime() ?? '—']) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-button tone="primary" size="xs" icon="edit" data-entry-modal-trigger
                                          :href="route('form-submissions.edit', $draft)">{{ __('form.action.continue') }}</x-button>
                                <x-action-form :action="route('form-submissions.destroy', $draft)" method="DELETE"
                                               :confirm="__('form.draft.confirm_discard')" confirm-icon="delete"
                                               confirm-tone="error" :confirm-label="__('form.action.discard')">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('form.action.discard')" />
                                </x-action-form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

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
                            <x-icon name="edit_note" class="text-muted" />
                            {{ optional($submission->template)->name ?? '—' }}
                        </a>
                    </td>
                    <td class="text-base-content/70"><x-subject-link :for="$submission" /></td>
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

        <x-pagination :paginator="$submissions" standing />
    </x-index-page>
@endsection
