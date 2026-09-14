{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('search.synonyms.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('search.synonyms.title'))

@section('content')
<x-index-page :subtitle="__('search.synonyms.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            @foreach ($presets as $preset)
                <x-action-form :action="route('admin.search-synonyms.preset')">
                    <input type="hidden" name="preset" value="{{ $preset }}">
                    <x-icon-btn icon="library_add" size="sm" type="submit" show-label>{{ __('search.synonyms.action.preset_' . $preset) }}</x-icon-btn>
                </x-action-form>
            @endforeach
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.search-synonyms.create')"
                        show-label>{{ __('search.synonyms.action.new') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="manage_search" />
        <span>{{ __('search.synonyms.notice') }}</span>
    </div>

    <x-table :caption="__('search.synonyms.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('search.synonyms.field.terms') }}</x-table.th>
                <x-table.th>{{ __('search.synonyms.field.creator') }}</x-table.th>
                <x-table.th>{{ __('search.synonyms.field.active') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($groups as $group)
            <tr class="{{ $group->active ? '' : 'opacity-50' }}">
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ((array) $group->terms as $term)
                            <span class="badge badge-sm badge-outline font-mono">{{ $term }}</span>
                        @endforeach
                    </div>
                </td>
                <td class="text-sm text-muted">{{ $group->creator?->name ?? '—' }}</td>
                <td>{{ $group->active ? __('search.synonyms.field.enabled_yes') : __('search.synonyms.field.enabled_no') }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('admin.search-synonyms.edit', $group)"
                                        :title="__('search.synonyms.action.edit')" />
                            <x-action-form :action="route('admin.search-synonyms.toggle', $group)">
                                <x-icon-btn :icon="$group->active ? 'toggle_on' : 'toggle_off'" size="xs" type="submit"
                                            :title="$group->active ? __('search.synonyms.action.deactivate') : __('search.synonyms.action.activate')" />
                            </x-action-form>
                            <x-action-form :action="route('admin.search-synonyms.destroy', $group)" method="DELETE"
                                           :confirm="__('search.synonyms.delete_confirm')" confirm-tone="error">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('search.synonyms.action.delete')" />
                            </x-action-form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('search.synonyms.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$groups" standing />
</x-index-page>
@endsection
