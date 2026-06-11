{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('knowledge.title.index'))
@section('nav-title', __('knowledge.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('knowledge.subtitle')">
        <x-slot:actions>
            @if ($canCreate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('knowledge.create')"
                            show-label>{{ __('knowledge.action.create') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('knowledge.index')"
                      :reset="$hasActiveFilters ? route('knowledge.index') : null">
            <x-filter-field :label="__('knowledge.filter.search')" for="knowledge-q" class="flex-1 min-w-60">
                <input id="knowledge-q" type="search" name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="{{ __('knowledge.filter.search_placeholder') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>

            <x-filter-field :label="__('knowledge.field.category')" for="knowledge-category" class="min-w-40">
                <select id="knowledge-category" name="category" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('knowledge.filter.all') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            @if ($canModerate)
                <x-filter-field :label="__('knowledge.field.status')" for="knowledge-status" class="min-w-40">
                    <select id="knowledge-status" name="status" class="select select-sm select-bordered w-full">
                        <option value="all">{{ __('knowledge.filter.all') }}</option>
                        @foreach (\App\Enums\Knowledge\ArticleStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif

            <x-filter-field :label="__('knowledge.filter.sort')" for="knowledge-sort" class="min-w-40">
                <select id="knowledge-sort" name="sort" class="select select-sm select-bordered w-full">
                    <option value="newest" @selected($filters['sort'] === 'newest')>{{ __('knowledge.filter.sort_newest') }}</option>
                    <option value="helpful" @selected($filters['sort'] === 'helpful')>{{ __('knowledge.filter.sort_helpful') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('knowledge.field.title') }}</th>
                    <th>{{ __('knowledge.field.category') }}</th>
                    <th>{{ __('knowledge.field.status') }}</th>
                    <th>{{ __('knowledge.field.helpful') }}</th>
                    <th>{{ __('knowledge.field.creator') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($articles as $article)
                <tr class="hover" id="knowledge-article-{{ $article->id }}">
                    <td>
                        <a href="{{ route('knowledge.show', $article) }}" class="flex items-center gap-2 font-medium link-hover">
                            <x-icon name="school" class="text-base-content/60" />
                            {{ $article->title }}
                        </a>
                        <span class="block max-w-md truncate text-xs text-base-content/60">{{ $article->problem }}</span>
                        @if ($article->tags->isNotEmpty())
                            <span class="mt-1 flex flex-wrap gap-1">
                                @foreach ($article->tags as $tag)
                                    <x-status-badge tone="ghost" outline>{{ $tag->name }}</x-status-badge>
                                @endforeach
                            </span>
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $article->category ?? '—' }}</td>
                    <td><x-status-badge :tone="$article->status->tone()">{{ $article->status->label() }}</x-status-badge></td>
                    <td>
                        <span class="flex items-center gap-2 text-sm">
                            <span class="flex items-center gap-1 text-success"><x-icon name="thumb_up" /> {{ $article->helpful_count }}</span>
                            <span class="flex items-center gap-1 text-base-content/50"><x-icon name="thumb_down" /> {{ $article->not_helpful_count }}</span>
                        </span>
                    </td>
                    <td class="text-base-content/70">{{ optional($article->creator)->name ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" tone="outline" size="xs"
                                        :href="route('knowledge.show', $article)"
                                        :label="__('knowledge.action.show')" />
                            @can('update', $article)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('knowledge.edit', $article)"
                                            :label="__('knowledge.action.edit')" />
                            @endcan
                            @if ($article->status !== \App\Enums\Knowledge\ArticleStatus::Published)
                                @can('publish', $article)
                                    <form method="POST" action="{{ route('knowledge.publish', $article) }}">
                                        @csrf
                                        <x-icon-btn icon="publish" tone="success" size="xs" type="submit"
                                                    :label="__('knowledge.action.publish')" />
                                    </form>
                                @endcan
                            @endif
                            @if ($article->status !== \App\Enums\Knowledge\ArticleStatus::Archived)
                                @can('archive', $article)
                                    <form method="POST" action="{{ route('knowledge.archive', $article) }}"
                                          data-confirm-dialog
                                          data-confirm-title="{{ __('knowledge.action.archive') }}"
                                          data-confirm-message="{{ __('knowledge.confirm_archive') }}"
                                          data-confirm-icon="archive"
                                          data-confirm-tone="warning"
                                          data-confirm-label="{{ __('knowledge.action.archive') }}">
                                        @csrf
                                        <x-icon-btn icon="archive" tone="warning" size="xs" type="submit"
                                                    :label="__('knowledge.action.archive')" />
                                    </form>
                                @endcan
                            @endif
                            @can('delete', $article)
                                <form method="POST" action="{{ route('knowledge.destroy', $article) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('knowledge.action.delete') }}"
                                      data-confirm-message="{{ __('knowledge.confirm_delete') }}"
                                      data-confirm-icon="delete"
                                      data-confirm-tone="error"
                                      data-confirm-label="{{ __('knowledge.action.delete') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('knowledge.action.delete')" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6"
                               :title="__('knowledge.empty_title')"
                               :message="$hasActiveFilters ? __('knowledge.empty_filtered') : __('knowledge.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$articles" />
    </x-index-page>
@endsection
