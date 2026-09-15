{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fragenkatalog (Feature 149, MVP-782): Fragen der Organisation mit Kategorie,
  Art und Verwendung; Kategorien verwalten. Prüfungen zeigen auf diese
  Fragen — löschen geht nur, was nirgends verwendet wird.
--}}
@extends('layouts.app')
@section('title', __('learning.title.questions'))
@section('nav-title', __('learning.title.questions'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('learning.subtitle.questions')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('learning.questions.create')"
                    show-label>{{ __('learning.action.new_question') }}</x-icon-btn>
        <x-help-button topic="learning.overview" />
    </x-slot:actions>

    {{-- Kategorien: anlegen, umbenennen, löschen (nur leer). --}}
    <details class="mb-3 rounded-box border border-base-300 px-4 py-2 text-sm">
        <summary class="cursor-pointer font-medium">{{ __('learning.field.categories') }} ({{ $categories->count() }})</summary>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            @foreach ($categories as $cat)
                <form method="POST" action="{{ route('learning.questions.categories.update', $cat) }}" class="flex items-center gap-1">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="cat-name-{{ $cat->sqid }}">{{ __('learning.field.category') }}</label>
                    <input type="text" id="cat-name-{{ $cat->sqid }}" name="name" value="{{ $cat->name }}" minlength="2" maxlength="120" required
                           class="input input-xs input-bordered w-40">
                    <span class="badge badge-ghost badge-sm">{{ $cat->questions_count }}</span>
                    <x-icon-btn icon="save" tone="ghost" size="xs" type="submit" :label="__('learning.action.rename_category')" />
                </form>
                @if ((int) $cat->questions_count === 0)
                    <form method="POST" action="{{ route('learning.questions.categories.destroy', $cat) }}"
                          data-confirm-dialog data-confirm-message="{{ __('learning.confirm.delete_category') }}">
                        @csrf
                        @method('DELETE')
                        <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('learning.action.delete_category')" />
                    </form>
                @endif
            @endforeach
            <form method="POST" action="{{ route('learning.questions.categories.store') }}" class="flex items-center gap-1">
                @csrf
                <label class="sr-only" for="cat-new">{{ __('learning.action.add_category') }}</label>
                <input type="text" id="cat-new" name="name" minlength="2" maxlength="120" required
                       placeholder="{{ __('learning.action.add_category') }}" class="input input-xs input-bordered w-40">
                <x-icon-btn icon="add" tone="outline" size="xs" type="submit" :label="__('learning.action.add_category')" />
            </form>
        </div>
    </details>

    <x-filter-bar :action="route('learning.questions.index')" :reset="route('learning.questions.index')">
        <x-filter-field :label="__('learning.field.search')" for="flt-q-search" class="flex-1 min-w-60">
            <input id="flt-q-search" type="search" name="q" value="{{ $search }}"
                   placeholder="{{ __('learning.field.prompt') }}" class="input input-sm input-bordered w-full">
        </x-filter-field>
        <x-filter-field :label="__('learning.field.category')" for="flt-q-category">
            <select id="flt-q-category" name="category" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->sqid }}" @selected($category === $cat->id)>{{ $cat->name }} ({{ $cat->questions_count }})</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.question_kind')" for="flt-q-kind">
            <select id="flt-q-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_kinds') }}</option>
                @foreach (\App\Enums\Learning\LearningQuestionKind::cases() as $case)
                    <option value="{{ $case->value }}" @selected($kind === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.quiz')" for="flt-q-quiz">
            <select id="flt-q-quiz" name="quiz" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('learning.filter.all_quizzes') }}</option>
                @foreach ($quizzes as $quiz)
                    <option value="{{ $quiz->sqid }}" @selected($quizId === $quiz->id)>{{ $quiz->title }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('learning.field.questions')" for="flt-q-total">
            <span id="flt-q-total" class="badge badge-ghost badge-sm">{{ $total }}</span>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('learning.field.question') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.category') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.question_kind') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('learning.field.score') }}</x-table.th>
                <x-table.th sort type="string">{{ __('learning.field.used_in') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($questions as $question)
            <tr class="hover">
                <td>
                    <span class="font-medium">{{ $question->title ?? \Illuminate\Support\Str::limit($question->prompt, 80) }}</span>
                    @if ($question->title)
                        <span class="block text-xs text-muted">{{ \Illuminate\Support\Str::limit($question->prompt, 100) }}</span>
                    @endif
                </td>
                <td class="text-sm">{{ $question->category?->name ?? '–' }}</td>
                <td class="text-sm"><x-status-badge :tone="$question->kind->tone()" size="sm" outline>{{ $question->kind->label() }}</x-status-badge></td>
                <td class="text-center text-sm">{{ $question->points }}</td>
                <td class="text-sm">
                    @forelse ($question->quizzes as $quiz)
                        <span class="badge badge-ghost badge-sm">{{ $quiz->title }}</span>
                    @empty
                        <span class="text-muted">–</span>
                    @endforelse
                </td>
                <td class="text-right whitespace-nowrap">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="edit" tone="ghost" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('learning.questions.edit', $question)"
                                    :label="__('learning.action.edit_question')" />
                        <form method="POST" action="{{ route('learning.questions.duplicate', $question) }}">
                            @csrf
                            <x-icon-btn icon="content_copy" tone="ghost" size="xs" type="submit" :label="__('learning.action.duplicate_question')" />
                        </form>
                        @if ($question->quizzes->isEmpty())
                            <form method="POST" action="{{ route('learning.questions.destroy', $question) }}"
                                  data-confirm-dialog data-confirm-message="{{ __('learning.confirm.delete_question') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('learning.action.delete_question')" />
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="quiz" :colspan="6" :title="__('learning.empty.questions_catalog')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$questions" standing />
</x-index-page>
@endsection
