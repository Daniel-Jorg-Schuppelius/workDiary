{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Produkte & Leistungen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Produkte & Leistungen'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $articles */
    $q = $filters['q'] ?? '';
    $type = $filters['type'] ?? '';
    $status = $filters['status'] ?? 'active';
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Aus Lexoffice synchronisierte Produkte und Leistungen des Mandanten.')">
    <x-slot:actions>
        @if ($canSync)
            <form method="POST" action="{{ route('lexoffice.articles.sync') }}">
                @csrf
                <x-icon-btn icon="sync" tone="primary" size="sm" type="submit"
                            show-label>{{ __('Synchronisieren') }}</x-icon-btn>
            </form>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('lexoffice.articles.index')" :reset="($q !== '' || $type !== '' || $status !== 'active') ? route('lexoffice.articles.index') : null">
        <x-filter-field :label="__('Suche')" for="art-q" class="flex-1 min-w-60">
            <input id="art-q" type="text" name="q" value="{{ $q }}" placeholder="{{ __('Name, Artikelnr. …') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-filter-field :label="__('Typ')" for="art-type" class="w-40 shrink-0">
            <select id="art-type" name="type" class="select select-sm select-bordered">
                <option value="" @selected($type === '')>{{ __('Alle') }}</option>
                <option value="PRODUCT" @selected($type === 'PRODUCT')>{{ __('Produkt') }}</option>
                <option value="SERVICE" @selected($type === 'SERVICE')>{{ __('Leistung') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Status')" for="art-status" class="w-40 shrink-0">
            <select id="art-status" name="status" class="select select-sm select-bordered">
                <option value="active" @selected($status === 'active')>{{ __('Aktiv') }}</option>
                <option value="archived" @selected($status === 'archived')>{{ __('Archiviert') }}</option>
                <option value="all" @selected($status === 'all')>{{ __('Alle') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($articles->total() === 0)
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
            :title="$q !== '' ? __('Keine Treffer für „:q“.', ['q' => $q]) : __('Noch keine Produkte oder Leistungen synchronisiert')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('lexoffice.articles.index')" :current-sort="$sort" :current-dir="$dir"
                     :sort-params="array_filter(['q' => $q ?: null, 'type' => $type ?: null, 'status' => $status !== 'active' ? $status : null])">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                        <x-table.th sort="article_number">{{ __('Artikelnr.') }}</x-table.th>
                        <x-table.th sort="type">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort="unit_name">{{ __('Einheit') }}</x-table.th>
                        <x-table.th sort="net_unit_price" align="right">{{ __('Netto-Preis') }}</x-table.th>
                        <x-table.th sort="vat_rate" align="right">{{ __('USt') }}</x-table.th>
                        <x-table.th sort="archived_at">{{ __('Status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($articles as $article)
                    <tr class="hover">
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('lexoffice.articles.details', $article) }}"
                               data-entry-modal-trigger>{{ $article->name }}</a>
                        </td>
                        <td class="text-base-content/70 tabular-nums">{{ $article->article_number }}</td>
                        <td>
                            <x-status-badge :tone="$article->type === 'SERVICE' ? 'info' : 'neutral'" size="xs">
                                {{ $article->type === 'SERVICE' ? __('Leistung') : __('Produkt') }}
                            </x-status-badge>
                        </td>
                        <td class="text-base-content/70">{{ $article->unit_name }}</td>
                        <td class="text-right tabular-nums">
                            @if ($article->net_unit_price !== null)
                                {{ number_format((float) $article->net_unit_price, 2, ',', '.') }} {{ $article->currency }}
                            @else
                                <span class="text-base-content/40">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            @if ($article->vat_rate !== null)
                                {{ number_format((float) $article->vat_rate, 0, ',', '.') }} %
                            @else
                                <span class="text-base-content/40">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($article->archived_at)
                                <x-status-badge tone="ghost" size="xs">{{ __('archiviert') }}</x-status-badge>
                            @else
                                <x-status-badge tone="success" size="xs">{{ __('aktiv') }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-pagination :paginator="$articles" />
    @endif
</x-index-page>
@endsection
