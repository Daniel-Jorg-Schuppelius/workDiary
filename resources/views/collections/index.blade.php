{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sammlungen (MVP-809, Feature 155): Baum links, Inhalte der gewählten
  Sammlung rechts. Gezeigt wird nur, was die Person ohnehin sehen darf.
--}}
@extends('layouts.app')

@section('title', __('collections.title.index'))
@section('nav-title', __('collections.title.index'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="__('collections.subtitle')">
        <x-slot:actions>
            <x-icon-btn :icon="$withArchived ? 'visibility_off' : 'inventory_2'" size="sm" show-label
                        :href="route('collections.index', array_filter(['archived' => $withArchived ? null : 1, 'collection' => $selected?->sqid]))">
                {{ $withArchived ? __('collections.action.hide_archived') : __('collections.action.show_archived') }}
            </x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="create_new_folder" tone="primary" size="sm" show-label data-entry-modal-trigger
                            :href="route('collections.create')">{{ __('collections.action.create') }}</x-icon-btn>
            @endif
        </x-slot:actions>
    </x-page-toolbar>
    </x-slot:toolbar>

    <x-knowledge-tabs />

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[18rem_1fr]">
        <x-card :title="__('collections.title.tree')" icon="account_tree" padding="p-3">
            @if ($tree === [])
                <x-empty-state icon="folder_special" :title="__('collections.empty.tree')" compact />
            @else
                <nav aria-label="{{ __('collections.title.tree') }}">
                    <ul class="space-y-0.5 text-sm" role="list">
                        @foreach ($tree as $row)
                            @php $node = $row['collection']; $isSelected = $selected !== null && (int) $selected->id === (int) $node->id; @endphp
                            <li style="padding-inline-start: {{ ($row['depth'] - 1) * 0.9 }}rem">
                                <a href="{{ route('collections.index', array_filter(['collection' => $node->sqid, 'archived' => $withArchived ? 1 : null])) }}"
                                   @class(['flex items-center gap-2 rounded-box px-2 py-1 hover:bg-base-200', 'bg-base-200 font-semibold' => $isSelected, 'text-muted' => $node->isArchived()])
                                   @if ($isSelected) aria-current="page" @endif>
                                    <x-icon :name="$node->isPrivate() ? 'folder_shared' : 'folder'" class="text-muted" />
                                    <span class="min-w-0 flex-1 truncate">{{ $node->title }}</span>
                                    <span class="text-xs text-muted">{{ $row['count'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </x-card>

        @if ($selected === null)
            <x-card>
                <x-empty-state icon="folder_special" :title="__('collections.empty.selection')" :message="__('collections.help.intro')" />
            </x-card>
        @else
            <x-card :title="$selected->title" :icon="$selected->isPrivate() ? 'folder_shared' : 'folder_open'" :count="count($items)" padding="p-0">
                <x-slot:actions>
                    @if ($selected->isPrivate())
                        <x-status-badge tone="info" size="sm">{{ __('collections.visibility.private') }}</x-status-badge>
                    @endif
                    @if ($selected->isArchived())
                        <x-status-badge tone="warning" size="sm">{{ __('collections.badge.archived') }}</x-status-badge>
                    @endif
                    @can('update', $selected)
                        @unless ($selected->isArchived())
                            <x-icon-btn icon="create_new_folder" tone="outline" size="xs" data-entry-modal-trigger
                                        :href="route('collections.create', ['parent' => $selected->sqid])"
                                        :label="__('collections.action.create_child')" />
                        @endunless
                        <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger
                                    :href="route('collections.edit', $selected)" :label="__('collections.action.edit')" />
                        <form method="POST" action="{{ $selected->isArchived() ? route('collections.restore', $selected) : route('collections.archive', $selected) }}">
                            @csrf
                            <x-icon-btn :icon="$selected->isArchived() ? 'unarchive' : 'archive'" tone="outline" size="xs" type="submit"
                                        :label="$selected->isArchived() ? __('collections.action.restore') : __('collections.action.archive')" />
                        </form>
                    @endcan
                </x-slot:actions>

                @if ($selected->description)
                    <p class="whitespace-pre-line border-b border-base-300 px-4 py-3 text-sm text-base-content/80">{{ $selected->description }}</p>
                @endif

                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('collections.field.type') }}</th>
                            <th>{{ __('collections.field.title') }}</th>
                            <th>{{ __('collections.field.subject') }}</th>
                            <th>{{ __('collections.field.added_by') }}</th>
                            <th class="text-right">{{ __('collections.field.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($items as $row)
                        <tr class="hover">
                            <td class="whitespace-nowrap">
                                <span class="inline-flex items-center gap-1"><x-icon :name="$row['icon']" class="text-muted" /> {{ $row['label'] }}</span>
                            </td>
                            <td class="font-medium"><a class="link link-hover" href="{{ $row['url'] }}">{{ $row['title'] }}</a></td>
                            <td class="text-sm text-base-content/70"><x-subject-link :subject="$row['subject']" /></td>
                            <td class="text-sm text-base-content/70">
                                {{ $row['entry']->adder?->name ?? '—' }} · {{ $row['entry']->created_at?->fdate() }}
                            </td>
                            <td class="text-right">
                                @can('update', $selected)
                                    <form method="POST" action="{{ route('collections.items.destroy', [$selected, $row['entry']]) }}" class="inline-flex justify-end">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="remove_circle_outline" tone="outline" size="xs" type="submit" :label="__('collections.action.remove_item')" />
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="inventory_2" :colspan="5" :title="__('collections.empty.items')" :message="__('collections.help.add_from_detail')" compact />
                    @endforelse
                </x-table>
            </x-card>
        @endif
    </div>
</x-page-shell>
@endsection
