{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Interne Wissensdatenbank (Feature 191): Baum aus Bereichen, Rubriken und
  Seiten links, Liste bzw. Suchtreffer rechts. Variablen: $spaces, $space,
  $section, $tree, $pages (Paginator), $filter, $term, $forms (Suchformen mit
  Glossar), $snippets (Seiten-ID → Textauszug der sichtbaren Fassung),
  $changed (Seiten-ID → geändert seit letztem Besuch),
  $openReadings, $canEdit, $canManage, $canForceDelete.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.title'))
@section('nav-title', __('knowledge_pages.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="__('knowledge_pages.subtitle')">
        <x-slot:actions>
            @if ($canEdit && $spaces->isNotEmpty())
                <x-icon-btn icon="note_add" tone="primary" size="sm" show-label data-entry-modal-trigger
                            :href="route('knowledge.pages.create', array_filter(['space' => $space?->sqid, 'section' => $section?->sqid]))">{{ __('knowledge_pages.action.create') }}</x-icon-btn>
            @endif
            <x-action-menu icon="more_horiz" :label="__('knowledge_pages.action.more')">
                <x-icon-btn icon="menu_book" size="sm" show-label :href="route('knowledge.readings.index')">{{ __('knowledge_pages.reading.mine') }}</x-icon-btn>
                <x-icon-btn icon="spellcheck" size="sm" show-label :href="route('knowledge.glossary.index')">{{ __('knowledge_pages.glossary.title') }}</x-icon-btn>
                <x-icon-btn icon="emergency" size="sm" show-label :href="route('knowledge.pages.emergency-pdf')">{{ __('knowledge_pages.action.emergency_pdf') }}</x-icon-btn>
                @if ($canEdit && $spaces->isNotEmpty())
                    <x-icon-btn icon="photo_library" size="sm" show-label data-entry-modal-trigger
                                :href="route('knowledge.pages.create', array_filter(['space' => $space?->sqid, 'section' => $section?->sqid, 'kind' => 'guide']))">{{ __('knowledge_pages.action.create_guide') }}</x-icon-btn>
                    <x-icon-btn icon="upload_file" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.import')">{{ __('knowledge_pages.action.import') }}</x-icon-btn>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="create_new_folder" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.spaces.create')">{{ __('knowledge_pages.action.create_space') }}</x-icon-btn>
                    <x-icon-btn icon="search_off" size="sm" show-label :href="route('knowledge.gaps.index')">{{ __('knowledge_pages.gaps.title') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="delete_sweep" size="sm" show-label :href="route('knowledge.pages.trash')">{{ __('knowledge_pages.trash.title') }}</x-icon-btn>
            </x-action-menu>
        </x-slot:actions>
    </x-page-toolbar>
    </x-slot:toolbar>

    <x-knowledge-tabs />

    @if ($openReadings > 0)
        <div class="alert alert-info text-sm" role="status">
            <x-icon name="menu_book" />
            <span>{{ trans_choice('knowledge_pages.reading.open_banner', $openReadings, ['count' => $openReadings]) }}</span>
            <x-icon-btn icon="arrow_forward" size="xs" tone="info" show-label :href="route('knowledge.readings.index')">{{ __('knowledge_pages.reading.open_action') }}</x-icon-btn>
        </div>
    @endif

    <x-filter-bar :action="route('knowledge.pages.index')" :reset="route('knowledge.pages.index')">
        @if ($space !== null)
            <input type="hidden" name="space" value="{{ $space->sqid }}">
        @endif
        @if ($section !== null)
            <input type="hidden" name="section" value="{{ $section->sqid }}">
        @endif
        <label for="kp-search" class="sr-only">{{ __('knowledge_pages.search.label') }}</label>
        <input id="kp-search" type="search" name="q" value="{{ $term }}" maxlength="120" class="input input-sm input-bordered w-64 shrink-0"
               placeholder="{{ __('knowledge_pages.search.placeholder') }}">
        <select name="filter" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('knowledge_pages.filter.label') }}">
            <option value="">{{ __('knowledge_pages.filter.all') }}</option>
            @foreach (\App\Http\Controllers\Knowledge\KnowledgePageController::FILTERS as $option)
                <option value="{{ $option }}" @selected($filter === $option)>{{ __('knowledge_pages.filter.' . $option) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[19rem_1fr]">
        <x-card :title="__('knowledge_pages.tree.title')" icon="account_tree" padding="p-3">
            @if ($tree === [])
                <x-empty-state compact icon="auto_stories" :title="__('knowledge_pages.empty.spaces')"
                               :message="$canManage ? __('knowledge_pages.help.first_space') : __('knowledge_pages.help.no_space')" />
                @if ($canManage)
                    <div class="mt-2 flex justify-center">
                        <x-icon-btn icon="create_new_folder" tone="primary" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.spaces.create')">{{ __('knowledge_pages.action.create_space') }}</x-icon-btn>
                    </div>
                @endif
            @else
                <nav aria-label="{{ __('knowledge_pages.tree.title') }}" class="space-y-3 text-sm">
                    @foreach ($tree as $branch)
                        @php $treeSpace = $branch['space']; $isSpace = $space !== null && (int) $space->id === (int) $treeSpace->id && $section === null; @endphp
                        <div>
                            <a href="{{ route('knowledge.pages.index', ['space' => $treeSpace->sqid]) }}"
                               @class(['flex items-center gap-2 rounded-box px-2 py-1 font-semibold hover:bg-base-200', 'bg-base-200' => $isSpace])
                               @if ($isSpace) aria-current="page" @endif>
                                <x-icon :name="$treeSpace->is_restricted ? 'lock' : 'menu_book'" class="text-muted" />
                                <span class="min-w-0 flex-1 truncate">{{ $treeSpace->title }}</span>
                            </a>
                            <ul class="mt-0.5 space-y-0.5" role="list">
                                @include('knowledge-pages._tree', ['nodes' => $branch['loose'], 'depth' => 1])
                                @foreach ($branch['sections'] as $sectionBranch)
                                    @php $treeSection = $sectionBranch['section']; $isSection = $section !== null && (int) $section->id === (int) $treeSection->id; @endphp
                                    <li>
                                        <a href="{{ route('knowledge.pages.index', ['space' => $treeSpace->sqid, 'section' => $treeSection->sqid]) }}"
                                           @class(['flex items-center gap-2 rounded-box px-2 py-1 hover:bg-base-200', 'bg-base-200 font-semibold' => $isSection])
                                           style="padding-inline-start: 0.9rem" @if ($isSection) aria-current="page" @endif>
                                            <x-icon name="bookmark" class="text-muted" />
                                            <span class="min-w-0 flex-1 truncate">{{ $treeSection->title }}</span>
                                        </a>
                                        <ul class="space-y-0.5" role="list">
                                            @include('knowledge-pages._tree', ['nodes' => $sectionBranch['pages'], 'depth' => 2])
                                        </ul>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            @endif
        </x-card>

        <div class="space-y-4">
            @if ($space !== null)
                <x-card :title="$section?->title ?? $space->title" :icon="$section !== null ? 'bookmark' : ($space->is_restricted ? 'lock' : 'menu_book')">
                    <x-slot:actions>
                        @if ($space->is_restricted)
                            <x-status-badge tone="info" size="sm">{{ __('knowledge_pages.space.restricted') }}</x-status-badge>
                        @endif
                        @can('update', $space)
                            @if ($section === null)
                                <x-icon-btn icon="bookmark_add" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.sections.create', $space)" :label="__('knowledge_pages.action.create_section')" />
                                <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.spaces.edit', $space)" :label="__('knowledge_pages.action.edit_space')" />
                                <x-action-form :action="route('knowledge.spaces.destroy', $space)" method="DELETE"
                                               :confirm="__('knowledge_pages.confirm.delete_space')" confirm-icon="delete" confirm-tone="error"
                                               :confirm-label="__('knowledge_pages.action.delete_space')">
                                    <x-icon-btn icon="delete" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.action.delete_space')" />
                                </x-action-form>
                            @else
                                <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.sections.edit', $section)" :label="__('knowledge_pages.action.edit_section')" />
                                <x-action-form :action="route('knowledge.sections.destroy', $section)" method="DELETE"
                                               :confirm="__('knowledge_pages.confirm.delete_section')" confirm-icon="delete" confirm-tone="warning"
                                               :confirm-label="__('knowledge_pages.action.delete_section')">
                                    <x-icon-btn icon="delete" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.action.delete_section')" />
                                </x-action-form>
                            @endif
                        @endcan
                    </x-slot:actions>
                    @if ($section === null && $space->description)
                        <p class="whitespace-pre-line text-sm text-muted">{{ $space->description }}</p>
                    @endif
                </x-card>
            @endif

            @if ($term !== '' && count($forms) > 1)
                <p class="text-xs text-muted">{{ __('knowledge_pages.search.expanded', ['forms' => implode(', ', $forms)]) }}</p>
            @endif

            <x-card :title="$term !== '' ? __('knowledge_pages.search.results') : ($filter !== '' ? __('knowledge_pages.filter.' . $filter) : ($space !== null ? __('knowledge_pages.list.pages') : __('knowledge_pages.list.recent')))"
                    icon="article" :count="$pages->total()" padding="p-0">
                @if ($pages->isEmpty())
                    <div class="p-4">
                        <x-empty-state compact icon="search_off"
                                       :title="$term !== '' ? __('knowledge_pages.search.no_results', ['term' => $term]) : __('knowledge_pages.empty.pages')"
                                       :message="$term !== '' ? __('knowledge_pages.search.gap_hint') : null" />
                        @if ($term !== '' && $canEdit && $spaces->isNotEmpty())
                            <div class="mt-2 flex justify-center">
                                <x-icon-btn icon="note_add" tone="primary" size="sm" show-label data-entry-modal-trigger
                                            :href="route('knowledge.pages.create', array_filter(['space' => $space?->sqid, 'title' => $term]))">{{ __('knowledge_pages.search.create_from_term') }}</x-icon-btn>
                            </div>
                        @endif
                    </div>
                @else
                    <ul class="divide-y divide-base-300" role="list" data-knowledge-page-list>
                        @foreach ($pages as $listPage)
                            <li class="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('knowledge.pages.show', $listPage) }}" class="link link-hover inline-flex items-center gap-1 font-medium">
                                        <x-icon :name="$listPage->kind->icon()" class="text-muted" /> {{ $listPage->title }}
                                    </a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                        <span>{{ $listPage->space?->title }}@if ($listPage->section) › {{ $listPage->section->title }}@endif</span>
                                        <span>· {{ __('knowledge_pages.field.updated_at') }} {{ $listPage->updated_at?->fdate() }}</span>
                                        @if ($listPage->owner)
                                            <span>· {{ $listPage->owner->name }}</span>
                                        @endif
                                        @foreach ($listPage->tags as $tag)
                                            <x-status-badge tone="ghost" size="xs" outline>{{ $tag->displayName() }}</x-status-badge>
                                        @endforeach
                                    </div>
                                    @if (($snippets[$listPage->id] ?? '') !== '')
                                        <p class="mt-1 text-sm text-base-content/80">{{ $snippets[$listPage->id] }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-1">
                                    @isset($changed[$listPage->id])
                                        <x-status-badge tone="info" size="sm" icon="update">{{ __('knowledge_pages.badge.changed') }}</x-status-badge>
                                    @endisset
                                    @if ($listPage->is_emergency)
                                        <x-status-badge tone="error" size="sm" outline icon="emergency">{{ __('knowledge_pages.badge.emergency') }}</x-status-badge>
                                    @endif
                                    <x-status-badge :tone="$listPage->status->tone()" size="sm">{{ $listPage->status->label() }}</x-status-badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-pagination :paginator="$pages" standing />
        </div>
    </div>
</x-page-shell>
@endsection
