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
<x-index-page overflow="clip" :subtitle="__('article.subtitle')">
    <x-slot:actions>
        @can('create', App\Models\Article::class)
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
    </x-filter-bar>

    {{-- Status-Tabs über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav :items="[
        ['label' => __('article.status.active'), 'route' => 'articles.index', 'params' => ['status' => 'active', 'q' => $search], 'active' => $status === 'active'],
        ['label' => __('article.status.draft'), 'route' => 'articles.index', 'params' => ['status' => 'draft', 'q' => $search], 'active' => $status === 'draft'],
        ['label' => __('article.status.retired'), 'route' => 'articles.index', 'params' => ['status' => 'retired', 'q' => $search], 'active' => $status === 'retired'],
        ['label' => __('Alle'), 'route' => 'articles.index', 'params' => ['status' => 'all', 'q' => $search], 'active' => $status === 'all'],
    ]" />

    @if ($articles->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                       :title="$search !== '' ? __('Keine Artikel für „:q“ gefunden.', ['q' => $search]) : __('article.empty')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table table-sort="server"
                     :route="route('articles.index')"
                     :current-sort="$sort"
                     :current-dir="$dir"
                     :sort-params="['status' => $status, 'q' => $search]"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                        <x-table.th sort="number">{{ __('article.field.sku') }}</x-table.th>
                        <th>{{ __('article.field.type') }}</th>
                        <th class="text-right">{{ __('article.variants') }}</th>
                        <th>{{ __('article.field.status') }}</th>
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
                        <td class="text-right">
                            @can('update', $article)
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                            :href="route('articles.edit', $article)" :title="__('Bearbeiten')" />
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$articles" standing />
    @endif
</x-index-page>
@endsection
