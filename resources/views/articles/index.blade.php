{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('article.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('article.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $articles */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
@endphp

@section('content')
@if (($datanormOversized ?? 0) > 0)
    <div class="alert alert-warning mb-3 text-sm">
        {{ trans_choice('article.datanorm_oversized', $datanormOversized, ['count' => $datanormOversized]) }}
    </div>
@endif
<x-index-page overflow="clip" :subtitle="__('article.subtitle')">
    <x-slot:actions>
        @if (auth()->user()?->canManageBilling())
            <x-icon-btn icon="merge" size="sm"
                        :href="route('articles.duplicates.index')"
                        show-label>{{ __('Artikel-Abgleich') }}</x-icon-btn>
        @endif
        <x-action-menu icon="download" :label="__('article.action.export_datanorm')">
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['version' => 5, 'prices' => 'list'])">{{ __('article.action.export_datanorm_v5_list') }}</x-button>
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['version' => 5, 'prices' => 'net'])">{{ __('article.action.export_datanorm_v5_net') }}</x-button>
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['version' => 4, 'prices' => 'list'])">{{ __('article.action.export_datanorm_v4_list') }}</x-button>
            <x-button tone="ghost" :href="route('articles.sales-discount-groups.index')">{{ __('article.discount_group.title') }}</x-button>
            <p class="wd-menu-heading">{{ __('article.action.export_datpreis_title') }}</p>
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['type' => 'prices', 'version' => 5, 'prices' => 'list'])">{{ __('article.action.export_datpreis_v5') }}</x-button>
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['type' => 'prices', 'version' => 4, 'prices' => 'list'])">{{ __('article.action.export_datpreis_v4') }}</x-button>
            <x-button tone="ghost" :href="route('articles.export.datanorm', ['type' => 'prices', 'version' => 5, 'prices' => 'list', 'since_days' => 30])">{{ __('article.action.export_datpreis_since') }}</x-button>
            {{-- MVP-566: frei wählbares Änderungsdatum --}}
            <div class="px-2 py-1">
                <form method="GET" action="{{ route('articles.export.datanorm') }}" class="flex items-center gap-1">
                    <input type="hidden" name="type" value="prices">
                    <input type="hidden" name="version" value="5">
                    <input type="hidden" name="prices" value="list">
                    <input type="date" name="since" required max="{{ now()->toDateString() }}"
                           class="input input-bordered input-xs" aria-label="{{ __('article.action.export_datpreis_custom') }}">
                    <x-button type="submit" tone="ghost" size="xs">{{ __('article.action.export_datpreis_custom') }}</x-button>
                </form>
            </div>
        </x-action-menu>
        @can('create', App\Models\Article\Article::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('articles.create')"
                        show-label>{{ __('article.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('articles.index')" :reset="$search !== '' ? route('articles.index', ['status' => $status]) : null">
        <input type="hidden" name="status" value="{{ $status }}">
        <x-filter-field :label="__('Suche')" for="art-q" class="flex-1 min-w-60">
            <input id="art-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        {{-- MVP-604: Kategorie-Filter --}}
        @if (($categories ?? collect())->isNotEmpty())
            <x-filter-field :label="__('article.field.category')" for="art-category">
                <select id="art-category" name="category" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('Alle') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected(($category ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
    </x-filter-bar>

    {{-- Status-Tabs über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav :items="[
        ['label' => __('article.status.active'), 'route' => 'articles.index', 'params' => ['status' => 'active', 'q' => $search], 'active' => $status === 'active'],
        ['label' => __('article.status.draft'), 'route' => 'articles.index', 'params' => ['status' => 'draft', 'q' => $search], 'active' => $status === 'draft'],
        ['label' => __('article.status.retired'), 'route' => 'articles.index', 'params' => ['status' => 'retired', 'q' => $search], 'active' => $status === 'retired'],
        ['label' => __('Alle'), 'route' => 'articles.index', 'params' => ['status' => 'all', 'q' => $search], 'active' => $status === 'all'],
    ]" />

    @if ($articles->total() === 0)
        <x-empty-state framed icon="inventory_2"
                       :title="$search !== '' ? __('Keine Artikel für „:q“ gefunden.', ['q' => $search]) : __('article.empty')" />
    @else
        <x-table :zebra="true" table-sort="server"
                 :route="route('articles.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="['status' => $status, 'q' => $search]"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="number">{{ __('article.field.sku') }}</x-table.th>
                    <th>{{ __('article.field.type') }}</th>
                    <th class="text-right">{{ __('article.variants') }}</th>
                    <th>{{ __('article.field.status') }}</th>
                    <x-custom-field-heads :columns="$customColumns" />
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($articles as $article)
                <tr>
                    <td>
                        <a href="{{ route('articles.show', $article) }}" class="link link-hover font-medium">{{ $article->name }}</a>
                    </td>
                    <td class="font-mono text-sm">{{ $article->number ?? '—' }}</td>
                    <td>{{ $article->type->label() }}</td>
                    <td class="text-right tabular-nums">{{ $article->variants_count }}</td>
                    <td>
                        <span class="badge badge-sm {{ $article->status->value === 'active' ? 'badge-success' : ($article->status->value === 'retired' ? 'badge-ghost' : 'badge-warning') }}">
                            {{ $article->status->label() }}
                        </span>
                    </td>
                    <x-custom-field-cells :columns="$customColumns" :model="$article" />
                    <td class="text-right">
                        @can('update', $article)
                            <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                        :href="route('articles.edit', $article)" :title="__('Bearbeiten')" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$articles" standing />
    @endif
</x-index-page>
@endsection
