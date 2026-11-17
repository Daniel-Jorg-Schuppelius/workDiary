{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : gaps.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Suchlücken (Feature 191, MVP-1667): Suchbegriffe ohne Treffer, gezählt
  ohne Person. Von hier aus Seite oder Glossarbegriff anlegen. Variablen:
  $gaps (Paginator), $sort, $showDismissed.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.gaps.title'))
@section('nav-title', __('knowledge_pages.gaps.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('knowledge_pages.gaps.subtitle')">
    <x-knowledge-tabs />
    <x-filter-bar :action="route('knowledge.gaps.index')" :reset="route('knowledge.gaps.index')">
        <select name="sort" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('knowledge_pages.gaps.sort') }}">
            <option value="searches" @selected($sort === 'searches')>{{ __('knowledge_pages.gaps.sort_searches') }}</option>
            <option value="recent" @selected($sort === 'recent')>{{ __('knowledge_pages.gaps.sort_recent') }}</option>
        </select>
        <x-filter-toggle name="dismissed" :label="__('knowledge_pages.gaps.show_dismissed')" :checked="$showDismissed" />
    </x-filter-bar>
    @if ($gaps->isEmpty())
        <x-empty-state framed icon="search_off" :title="__('knowledge_pages.gaps.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('knowledge_pages.gaps.term') }}</th>
                    <th class="text-right">{{ __('knowledge_pages.gaps.searches') }}</th>
                    <th>{{ __('knowledge_pages.gaps.last') }}</th>
                    <th class="w-px"></th>
                </tr>
            </x-slot:head>
            @foreach ($gaps as $gap)
                <tr class="hover">
                    <td class="font-medium">{{ $gap->term }}@if ($gap->dismissed_at) <x-status-badge tone="ghost" size="xs">{{ __('knowledge_pages.gaps.dismissed') }}</x-status-badge>@endif</td>
                    <td class="text-right tabular-nums">{{ $gap->searches }}</td>
                    <td class="text-sm">{{ $gap->last_searched_at->fdate() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="note_add" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.pages.create', ['title' => $gap->term])" :label="__('knowledge_pages.gaps.create_page')" />
                            <x-icon-btn icon="spellcheck" tone="outline" size="xs" data-entry-modal-trigger :href="route('knowledge.glossary.create', ['term' => $gap->term])" :label="__('knowledge_pages.gaps.create_term')" />
                            @unless ($gap->dismissed_at)
                                <form method="POST" action="{{ route('knowledge.gaps.dismiss', $gap) }}">
                                    @csrf
                                    <x-icon-btn icon="done" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.gaps.dismiss')" />
                                </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$gaps" standing />
    @endif
</x-index-page>
@endsection
