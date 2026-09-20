{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einstieg „Wissen“ (MVP-813, Feature 155): Sammlungsbaum links, Inhalte als
  Liste oder Kacheln rechts, Filter oben; Mehrfachauswahl legt Inhalte in eine
  Sammlung. Gezeigt wird nur, was die Person auch in der Fachliste sähe.
--}}
@extends('layouts.app')

@section('title', __('collections.hub.title'))
@section('nav-title', __('collections.hub.title'))

@section('content')
@php
    $hubQuery = static fn (array $overrides = []): array => array_filter(array_merge($filters, ['page' => null], $overrides), static fn ($value): bool => $value !== null && $value !== '');
@endphp
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="__('collections.hub.subtitle')">
        <x-slot:actions>
            <x-help-button topic="knowledge.collections" />
            <x-icon-btn :icon="$filters['view'] === 'tiles' ? 'view_list' : 'grid_view'" tone="ghost" size="sm" show-label
                        :href="route('knowledge-hub.index', $hubQuery(['view' => $filters['view'] === 'tiles' ? null : 'tiles']))">
                {{ $filters['view'] === 'tiles' ? __('collections.hub.view_list') : __('collections.hub.view_tiles') }}
            </x-icon-btn>
            @if ($mayImport)
                <x-icon-btn icon="folder_zip" tone="ghost" size="sm" show-label data-entry-modal-trigger
                            :href="route('knowledge-imports.create', ['source' => 'obsidian'])">{{ __('collections.import.obsidian.action') }}</x-icon-btn>
                @if ($oneNoteReady)
                    <x-icon-btn icon="book" tone="ghost" size="sm" show-label data-entry-modal-trigger
                                :href="route('knowledge-imports.create', ['source' => 'onenote'])">{{ __('collections.import.onenote.action') }}</x-icon-btn>
                @endif
            @endif
            @if ($tree !== [])
                <x-icon-btn icon="folder_special" tone="ghost" size="sm" show-label
                            :href="route('collections.index', array_filter(['collection' => $selected?->sqid]))">{{ __('collections.hub.manage') }}</x-icon-btn>
            @endif
        </x-slot:actions>
    </x-page-toolbar>
    </x-slot:toolbar>

    <x-knowledge-tabs />

    <x-filter-bar :action="route('knowledge-hub.index')" :reset="route('knowledge-hub.index')">
        @foreach (['collection', 'tag', 'view'] as $kept)
            @if ($filters[$kept] !== null && ! ($kept === 'view' && $filters['view'] === 'list'))
                <input type="hidden" name="{{ $kept }}" value="{{ $filters[$kept] }}">
            @endif
        @endforeach
        <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="120"
               placeholder="{{ __('collections.hub.search') }}" aria-label="{{ __('collections.hub.search') }}"
               class="input input-sm input-bordered w-64 shrink-0">
        <select name="type" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('collections.field.type') }}" data-autosubmit>
            <option value="">{{ __('collections.hub.all_types') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type['key'] }}" @selected($filters['type'] === $type['key'])>{{ $type['label'] }}</option>
            @endforeach
        </select>
        {{-- Kundenfilter über die Trägerkette (MVP-818). --}}
        <select name="customer" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('collections.field.customer') }}" data-autosubmit>
            <option value="">{{ __('collections.hub.all_customers') }}</option>
            @foreach ($customers as $hubCustomer)
                <option value="{{ $hubCustomer->sqid }}" @selected($filters['customer'] === $hubCustomer->sqid)>{{ $hubCustomer->name }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($activeTagId !== null || $tagFacets !== [])
        <div class="flex flex-wrap items-center gap-2">
            @if ($activeTagId !== null)
                <span class="badge badge-primary gap-1">
                    <x-icon name="sell" class="text-sm" />
                    {{ $activeTagName ?? __('collections.hub.tag_filter') }}
                    <a href="{{ route('knowledge-hub.index', $hubQuery(['tag' => null])) }}" class="inline-flex" aria-label="{{ __('search.filter.remove') }}">
                        <x-icon name="close" class="text-sm" />
                    </a>
                </span>
            @endif
            @foreach ($tagFacets as $facet)
                @continue($facet['id'] === $activeTagId)
                <a href="{{ route('knowledge-hub.index', $hubQuery(['tag' => \App\Support\Sqid::encode(\App\Models\Tag::class, $facet['id'])])) }}"
                   class="badge badge-outline gap-1">{{ $facet['name'] }} <span class="opacity-70">{{ $facet['hits'] }}</span></a>
            @endforeach
        </div>
    @endif

    <div @class(['grid grid-cols-1 gap-4', 'lg:grid-cols-[18rem_1fr]' => $tree !== []])>
        @if ($tree !== [])
            <x-card :title="__('collections.title.tree')" icon="account_tree" padding="p-3">
                <nav aria-label="{{ __('collections.title.tree') }}">
                    <ul class="space-y-0.5 text-sm" role="list">
                        <li>
                            <a href="{{ route('knowledge-hub.index', $hubQuery(['collection' => null])) }}"
                               @class(['flex items-center gap-2 rounded-box px-2 py-1 hover:bg-base-200', 'bg-base-200 font-semibold' => $selected === null])
                               @if ($selected === null) aria-current="page" @endif>
                                <x-icon name="apps" class="text-muted" />
                                <span class="min-w-0 flex-1 truncate">{{ __('collections.hub.all_contents') }}</span>
                            </a>
                        </li>
                        @foreach ($tree as $row)
                            @php $node = $row['collection']; $isSelected = $selected !== null && (int) $selected->id === (int) $node->id; @endphp
                            <li style="padding-inline-start: {{ ($row['depth'] - 1) * 0.9 }}rem">
                                <a href="{{ route('knowledge-hub.index', $hubQuery(['collection' => $node->sqid])) }}"
                                   @class(['flex items-center gap-2 rounded-box px-2 py-1 hover:bg-base-200', 'bg-base-200 font-semibold' => $isSelected])
                                   @if ($isSelected) aria-current="page" @endif>
                                    <x-icon :name="$node->isPrivate() ? 'folder_shared' : 'folder'" class="text-muted" />
                                    <span class="min-w-0 flex-1 truncate">{{ $node->title }}</span>
                                    <span class="text-xs text-muted">{{ $row['count'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </x-card>
        @endif

        <form method="POST" action="{{ route('collections.items.bulk') }}" data-bulk-form class="min-w-0 space-y-3">
            @csrf
            @if ($mayCollect && $tree !== [])
                <x-bulk-toolbar :label="__('collections.hub.selected')">
                    <x-slot:actions>
                        <select name="collection" class="select select-sm select-bordered" aria-label="{{ __('collections.field.collection') }}" required>
                            @foreach ($tree as $row)
                                @continue($row['collection']->isArchived())
                                <option value="{{ $row['collection']->sqid }}" @selected($selected !== null && (int) $selected->id === (int) $row['collection']->id)>{{ str_repeat('– ', $row['depth'] - 1) }}{{ $row['collection']->title }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" tone="primary" size="sm" icon="bookmark_add">{{ __('collections.action.add') }}</x-button>
                    </x-slot:actions>
                </x-bulk-toolbar>
            @endif

            @if ($filters['view'] === 'tiles')
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($items as $row)
                        <x-card>
                            <div class="flex items-start justify-between gap-2">
                                <span class="inline-flex items-center gap-1 text-xs text-muted"><x-icon :name="$row['icon']" /> {{ $row['label'] }}</span>
                                @if ($mayCollect && $tree !== [])
                                    <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="items[]"
                                           value="{{ $row['type'] }}:{{ \App\Support\Sqid::encode($row['model']::class, (int) $row['model']->getKey()) }}"
                                           aria-label="{{ __('collections.hub.select_item', ['title' => $row['title']]) }}">
                                @endif
                            </div>
                            <a class="link link-hover mt-1 block font-medium" href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                            @unless ($row['subject']->isEmpty())
                                <x-subject-link :subject="$row['subject']" class="mt-1 text-xs text-base-content/70" />
                            @endunless
                            <p class="mt-2 flex flex-wrap items-center gap-1 text-xs text-muted">
                                <span>{{ $row['updated_at']?->fdate() }}</span>
                                @foreach ($row['tags'] as $tag)
                                    <span class="badge badge-ghost badge-xs">{{ $tag['name'] }}</span>
                                @endforeach
                            </p>
                        </x-card>
                    @empty
                        <x-empty-state icon="menu_book" :title="__('collections.hub.empty')" :message="__('collections.hub.empty_hint')" class="sm:col-span-2 xl:col-span-3" />
                    @endforelse
                </div>
            @else
                <x-card padding="p-0">
                    <x-table :bare="true" :caption="__('collections.hub.title')">
                        <x-slot:head>
                            <tr>
                                @if ($mayCollect && $tree !== [])
                                    <th class="w-8">
                                        <input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all aria-label="{{ __('collections.hub.select_all') }}">
                                    </th>
                                @endif
                                <th>{{ __('collections.field.type') }}</th>
                                <th>{{ __('collections.field.title') }}</th>
                                <th>{{ __('collections.field.subject') }}</th>
                                <th>{{ __('collections.hub.updated') }}</th>
                            </tr>
                        </x-slot:head>
                        @forelse ($items as $row)
                            <tr class="hover">
                                @if ($mayCollect && $tree !== [])
                                    <td>
                                        <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="items[]"
                                               value="{{ $row['type'] }}:{{ \App\Support\Sqid::encode($row['model']::class, (int) $row['model']->getKey()) }}"
                                               aria-label="{{ __('collections.hub.select_item', ['title' => $row['title']]) }}">
                                    </td>
                                @endif
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1"><x-icon :name="$row['icon']" class="text-muted" /> {{ $row['label'] }}</span>
                                </td>
                                <td class="font-medium">
                                    <a class="link link-hover" href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                                    @foreach ($row['tags'] as $tag)
                                        <span class="badge badge-ghost badge-xs">{{ $tag['name'] }}</span>
                                    @endforeach
                                </td>
                                <td class="text-sm text-base-content/70"><x-subject-link :subject="$row['subject']" /></td>
                                <td class="whitespace-nowrap text-sm text-base-content/70">{{ $row['updated_at']?->fdate() ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table.empty icon="menu_book" :colspan="$mayCollect && $tree !== [] ? 5 : 4" :title="__('collections.hub.empty')" :message="__('collections.hub.empty_hint')" compact />
                        @endforelse
                    </x-table>
                </x-card>
            @endif
        </form>
    </div>

    <x-pagination :paginator="$items" standing />
</x-page-shell>
@endsection
