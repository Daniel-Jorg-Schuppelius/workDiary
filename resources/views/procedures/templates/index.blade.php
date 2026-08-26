{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Prozedurvorlagen-Designer (Feature 026): Listenseite + Modal-Anlage.
  Bearbeitung der Schritte erfolgt im Voll-Seiten-Designer (edit).
--}}

@extends('layouts.app')

@section('title', __('procedure.title.templates'))
@section('nav-title', __('procedure.title.templates'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('procedure.subtitle.templates')">
        <x-slot:actions>
            <x-help-button topic="procedures.designer" :label="__('procedure.help.designer')" />
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('procedures.create')"
                            show-label>{{ __('procedure.action.createTemplate') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('procedures.index')"
                      :reset="$hasActiveFilters ? route('procedures.index') : null">
            <x-filter-field :label="__('procedure.filter.search')" for="proc-q" class="flex-1 min-w-60">
                <input id="proc-q" type="search" name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="{{ __('procedure.filter.searchPlaceholder') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>

            <x-filter-field :label="__('procedure.field.status')" for="proc-status" class="min-w-40">
                <select id="proc-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('procedure.filter.all') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('procedure.status.active') }}</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>{{ __('procedure.status.archived') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procedure.field.name') }}</th>
                    <th>{{ __('procedure.field.code') }}</th>
                    <th>{{ __('procedure.field.status') }}</th>
                    <th>{{ __('procedure.field.currentVersion') }}</th>
                    <th>{{ __('procedure.field.steps') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                @php
                    $published = $template->versions->whereNotNull('published_at')->sortByDesc('version')->first();
                    $draft = $template->versions->whereNull('published_at')->sortByDesc('version')->first();
                    $stepCount = ($draft ?? $published)?->steps->count() ?? 0;
                @endphp
                <tr class="hover" id="procedure-template-{{ $template->id }}">
                    <td>
                        <span class="flex items-center gap-2 font-medium">
                            <x-icon name="rule" class="text-muted" />
                            {{ $template->name }}
                        </span>
                        @if ($template->description)
                            <span class="block max-w-md truncate text-xs text-muted">{{ $template->description }}</span>
                        @endif
                    </td>
                    <td><code class="text-xs">{{ $template->code }}</code></td>
                    <td>
                        @if ($template->active)
                            <x-status-badge tone="success">{{ __('procedure.status.active') }}</x-status-badge>
                        @else
                            <x-status-badge tone="neutral">{{ __('procedure.status.archived') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-base-content/70">
                        @if ($published)
                            v{{ $published->version }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if ($draft)
                            <x-status-badge tone="warning" class="ml-1">{{ __('procedure.status.draft') }} v{{ $draft->version }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $stepCount }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $template)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            :href="route('procedures.edit', $template)"
                                            :label="__('procedure.action.edit')" />
                            @endcan
                            @can('update', $template)
                                @if ($template->active)
                                    <x-action-form :action="route('procedures.archive', $template)"
                                          :confirm="__('procedure.confirm.archive')"
                                          confirm-icon="archive"
                                          confirm-tone="warning"
                                          :confirm-label="__('procedure.action.archive')"
                                          data-confirm-title="{{ __('procedure.action.archive') }}">
                                        <x-icon-btn icon="archive" tone="warning" size="xs" type="submit"
                                                    :label="__('procedure.action.archive')" />
                                    </x-action-form>
                                @else
                                    <x-action-form :action="route('procedures.activate', $template)">
                                        <x-icon-btn icon="play_arrow" tone="success" size="xs" type="submit"
                                                    :label="__('procedure.action.activate')" />
                                    </x-action-form>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6"
                               :title="__('procedure.empty.title')"
                               :message="$hasActiveFilters ? __('procedure.empty.filtered') : __('procedure.empty.message')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$templates" standing />
    </x-index-page>
@endsection
