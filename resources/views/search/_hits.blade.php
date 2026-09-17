{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _hits.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Trefferliste der Tätigkeitsrecherche: was, wann, bei wem, wer, wie lange.
     Notizen, Artikel und Kurse lassen sich markieren und in eine Sammlung legen
     (MVP-813). Erwartet: $result, $mayCollect, $collectionOptions, $collectableSources. --}}
@php
    $collectHits = $mayCollect && $collectionOptions !== [] && collect($result->hits->items())->contains(static fn ($hit): bool => isset($collectableSources[$hit->type->value]));
@endphp
<form method="POST" action="{{ route('collections.items.bulk') }}" data-bulk-form class="space-y-3">
@csrf
@if ($collectHits)
    <x-bulk-toolbar :label="__('collections.hub.selected')">
        <x-slot:actions>
            <select name="collection" class="select select-sm select-bordered" aria-label="{{ __('collections.field.collection') }}" required>
                @foreach ($collectionOptions as $row)
                    <option value="{{ $row['collection']->sqid }}">{{ str_repeat('– ', $row['depth'] - 1) }}{{ $row['collection']->title }}</option>
                @endforeach
            </select>
            <x-button type="submit" tone="primary" size="sm" icon="bookmark_add">{{ __('collections.hub.add_hits') }}</x-button>
        </x-slot:actions>
    </x-bulk-toolbar>
@endif
<x-card :title="__('search.hits.title')" icon="manage_search" :count="$result->hits->total()">
    <x-table :caption="__('search.hits.title')" bare>
        <x-slot:head>
            <tr>
                @if ($collectHits)
                    <th class="w-8"><input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all aria-label="{{ __('collections.hub.select_all') }}"></th>
                @endif
                <x-table.th>{{ __('search.column.date') }}</x-table.th>
                <x-table.th>{{ __('search.column.activity') }}</x-table.th>
                <x-table.th>{{ __('search.column.customer') }}</x-table.th>
                <x-table.th>{{ __('search.column.person') }}</x-table.th>
                <x-table.th align="right">{{ __('search.column.duration') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($result->hits as $hit)
            <tr class="hover align-top">
                @if ($collectHits)
                    <td>
                        @isset($collectableSources[$hit->type->value])
                            <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="items[]"
                                   value="{{ $collectableSources[$hit->type->value] }}:{{ \App\Support\Sqid::encode($hit->type->modelClass(), $hit->sourceId) }}"
                                   aria-label="{{ __('collections.hub.select_item', ['title' => $hit->title]) }}">
                        @endisset
                    </td>
                @endif
                <td class="whitespace-nowrap text-sm">
                    {{ $hit->dateLabel() ?? '—' }}
                    @if ($hit->timeLabel() !== null)
                        <span class="block text-xs text-muted">{{ $hit->timeLabel() }}</span>
                    @endif
                </td>
                <td class="min-w-64">
                    <div class="flex items-start gap-2">
                        <x-icon name="{{ $hit->type->icon() }}" class="mt-0.5 text-base text-muted" />
                        <div class="min-w-0">
                            @if ($hit->showsTitle())
                                <div class="text-sm font-medium">@include('search._segments', ['segments' => $hit->titleSegments])</div>
                            @endif
                            @if ($hit->snippet !== [])
                                <div @class(['text-sm', 'text-muted' => $hit->showsTitle()])>@include('search._segments', ['segments' => $hit->snippet])</div>
                            @endif
                            <div class="text-xs text-muted">{{ $hit->type->label() }}</div>
                        </div>
                    </div>
                </td>
                <td class="text-sm">
                    {{ $hit->customerLabel() ?? '—' }}
                    @if ($hit->projectName !== null)
                        <span class="block text-xs text-muted">{{ $hit->projectName }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-sm">{{ $hit->userName ?? '—' }}</td>
                <td class="whitespace-nowrap text-right text-sm">{{ $hit->durationLabel() ?? '' }}</td>
                <td class="text-right">
                    @if ($hit->url !== null)
                        <div class="flex justify-end">
                            <x-icon-btn icon="open_in_new" size="xs" :href="$hit->url" :title="__('search.hits.open')" />
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="$collectHits ? 7 : 6" :title="__('search.empty.none')" compact />
        @endforelse
    </x-table>
    @if ($result->hits->total() === 0)
        <p class="mt-2 text-sm text-muted">{{ __('search.empty.none_hint') }}</p>
    @endif
</x-card>
</form>

<x-pagination :paginator="$result->hits" standing />
