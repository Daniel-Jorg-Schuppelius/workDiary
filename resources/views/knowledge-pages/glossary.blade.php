{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : glossary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Glossar der Organisation (Feature 191, MVP-1667): Begriffe mit Kürzeln
  und Synonymen, die die Suche der Wissensseiten mitnimmt. Variablen:
  $terms (Paginator), $q, $canManage, $visiblePages (Seiten-ID → sichtbar).
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.glossary.title'))
@section('nav-title', __('knowledge_pages.glossary.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('knowledge_pages.glossary.subtitle')">
    @if ($canManage)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.glossary.create')">{{ __('knowledge_pages.glossary.create') }}</x-icon-btn>
        </x-slot:actions>
    @endif
    <x-knowledge-tabs />
    <x-filter-bar :action="route('knowledge.glossary.index')" :reset="route('knowledge.glossary.index')">
        <label for="kg-search" class="sr-only">{{ __('knowledge_pages.search.label') }}</label>
        <input id="kg-search" type="search" name="q" value="{{ $q }}" maxlength="120" class="input input-sm input-bordered w-64 shrink-0" placeholder="{{ __('knowledge_pages.glossary.search') }}">
    </x-filter-bar>
    @if ($terms->isEmpty())
        <x-empty-state framed icon="spellcheck" :title="__('knowledge_pages.glossary.empty')" :message="__('knowledge_pages.glossary.empty_hint')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('knowledge_pages.glossary.term') }}</th>
                    <th>{{ __('knowledge_pages.glossary.synonyms') }}</th>
                    <th>{{ __('knowledge_pages.glossary.description') }}</th>
                    <th>{{ __('knowledge_pages.glossary.page') }}</th>
                    @if ($canManage)<th class="w-px"></th>@endif
                </tr>
            </x-slot:head>
            @foreach ($terms as $entry)
                <tr class="hover">
                    <td class="font-medium">{{ $entry->term }}</td>
                    <td class="text-sm">{{ implode(', ', $entry->synonyms ?? []) }}</td>
                    <td class="whitespace-pre-line text-sm">{{ $entry->description }}</td>
                    <td class="text-sm">
                        @if ($entry->page !== null && isset($visiblePages[$entry->page->id]))
                            <a class="link link-hover" href="{{ route('knowledge.pages.show', $entry->page) }}">{{ $entry->page->title }}</a>
                        @else
                            —
                        @endif
                    </td>
                    @if ($canManage)
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.glossary.edit', $entry)" :label="__('knowledge_pages.action.edit')" />
                                <x-action-form :action="route('knowledge.glossary.destroy', $entry)" method="DELETE"
                                               :confirm="__('knowledge_pages.confirm.delete_term')" confirm-icon="delete" confirm-tone="error"
                                               :confirm-label="__('knowledge_pages.glossary.delete')">
                                    <x-icon-btn icon="delete" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.glossary.delete')" />
                                </x-action-form>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$terms" standing />
    @endif
</x-index-page>
@endsection
