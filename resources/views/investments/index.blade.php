{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Investitionen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Investitionen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Investitionsakten mit Varianten, Budgetantrag, Freigabe und Soll-Ist-Verfolgung.')">
    <x-slot:actions>
        <x-icon-btn icon="analytics" size="sm" :href="route('investments.report')" show-label>{{ __('Bericht') }}</x-icon-btn>
        @can('create', \App\Models\Investments\InvestmentCase::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('investments.create')"
                        show-label>{{ __('Investition erfassen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('investments.index')" :reset="route('investments.index')">
        <x-filter-field :label="__('Status')" for="inv-status" class="shrink-0">
            <select id="inv-status" name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Kategorie')" for="inv-category" class="shrink-0">
            <select id="inv-category" name="category" class="select select-sm select-bordered w-44" aria-label="{{ __('Kategorie') }}">
                <option value="">{{ __('Alle Kategorien') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ __("values.$c") }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
             :route="route('investments.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'desc'"
             :sort-params="request()->except(['sort', 'dir', 'page'])">
        <x-slot:head>
            <tr>
                <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                <x-table.th sort="category">{{ __('Kategorie') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <x-table.th>{{ __('Kostenstelle') }}</x-table.th>
                <x-table.th>{{ __('Verantwortlich') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr class="hover">
                <td><a href="{{ route('investments.show', $case) }}" class="link link-hover font-medium">{{ $case->title }}</a></td>
                <td>{{ __("values.{$case->category}") }}</td>
                <td><x-status-badge :tone="$case->statusTone()" size="sm">{{ __("values.{$case->status}") }}</x-status-badge></td>
                <td>{{ $case->costCenterDisplay() ?? '—' }}</td>
                <td>{{ $case->responsible->name ?? '—' }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('investments.show', $case)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon="trending_up" :colspan="6" :title="__('Keine Investitionen — „Investition erfassen“ startet die erste Akte.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
