{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorlagen-Verwaltung (Feature 032): Listenseite + Modal-CRUD.
--}}

@extends('layouts.app')

@section('title', __('form.title.templates'))
@section('nav-title', __('form.title.templates'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('form.subtitle.templates')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('form-templates.create')"
                            show-label>{{ __('form.action.create_template') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('form-templates.index')"
                      :reset="$hasActiveFilters ? route('form-templates.index') : null">
            <x-filter-field :label="__('form.filter.search')" for="form-tpl-q" class="flex-1 min-w-60">
                <input id="form-tpl-q" type="search" name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="{{ __('form.filter.search_placeholder') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>

            <x-filter-field :label="__('form.field.status')" for="form-tpl-status" class="min-w-40">
                <select id="form-tpl-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('form.filter.all') }}</option>
                    @foreach (\App\Enums\Form\FormTemplateStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('form.field.name') }}</th>
                    <th>{{ __('form.field.status') }}</th>
                    <th>{{ __('form.field.fields') }}</th>
                    <th>{{ __('form.field.submissions') }}</th>
                    <th>{{ __('form.field.creator') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr class="hover" id="form-template-{{ $template->id }}">
                    <td>
                        <span class="flex items-center gap-2 font-medium">
                            <x-icon name="assignment" class="text-muted" />
                            {{ $template->name }}
                        </span>
                        @if ($template->description)
                            <span class="block max-w-md truncate text-xs text-muted">{{ $template->description }}</span>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$template->status->tone()">{{ $template->status->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">{{ count($template->fields ?? []) }}</td>
                    <td class="text-base-content/70">{{ $template->submissions_count }}</td>
                    <td class="text-base-content/70">{{ optional($template->creator)->name ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $template)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('form-templates.edit', $template)"
                                            :label="__('form.action.edit')" />
                            @endcan
                            @if ($template->status !== \App\Enums\Form\FormTemplateStatus::Active)
                                @can('activate', $template)
                                    <form method="POST" action="{{ route('form-templates.activate', $template) }}">
                                        @csrf
                                        <x-icon-btn icon="play_arrow" tone="success" size="xs" type="submit"
                                                    :label="__('form.action.activate')" />
                                    </form>
                                @endcan
                            @endif
                            @if ($template->status !== \App\Enums\Form\FormTemplateStatus::Archived)
                                @can('archive', $template)
                                    <x-action-form :action="route('form-templates.archive', $template)"
                                          data-confirm-title="{{ __('form.action.archive') }}"
                                          :confirm="__('form.confirm_archive')"
                                          confirm-icon="archive"
                                          confirm-tone="warning"
                                          :confirm-label="__('form.action.archive')">
                                        <x-icon-btn icon="archive" tone="warning" size="xs" type="submit"
                                                    :label="__('form.action.archive')" />
                                    </x-action-form>
                                @endcan
                            @endif
                            @can('delete', $template)
                                <x-action-form :action="route('form-templates.destroy', $template)" method="DELETE"
                                      data-confirm-title="{{ __('form.action.delete') }}"
                                      :confirm="__('form.confirm_delete')"
                                      confirm-icon="delete"
                                      confirm-tone="error"
                                      :confirm-label="__('form.action.delete')">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('form.action.delete')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6"
                               :title="__('form.empty_templates_title')"
                               :message="$hasActiveFilters ? __('form.empty_filtered') : __('form.empty_templates')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$templates" standing />
    </x-index-page>
@endsection
