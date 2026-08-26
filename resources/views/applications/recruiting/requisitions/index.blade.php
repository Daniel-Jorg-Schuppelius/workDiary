{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Stellen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Stellen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Stellenbedarf, Veröffentlichungen und Bewerbungsstände.')">
    <x-slot:actions>
        @can('create', \App\Models\Applications\JobRequisition::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('recruiting.requisitions.create')"
                        show-label>{{ __('Stelle anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @include('applications.recruiting._tabs')

    <x-filter-bar :action="route('recruiting.requisitions.index')" :reset="route('recruiting.requisitions.index')">
        <x-filter-field :label="__('Status')" for="req-status" class="shrink-0">
            <select id="req-status" name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
             :route="route('recruiting.requisitions.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'desc'"
             :sort-params="request()->except(['sort', 'dir', 'page'])">
        <x-slot:head>
            <tr>
                <x-table.th sort="title">{{ __('Stelle') }}</x-table.th>
                <x-table.th sort="department">{{ __('Abteilung') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <x-table.th sort="applications" align="right">{{ __('Bewerbungen') }}</x-table.th>
                <x-table.th sort="target_start">{{ __('Zielstart') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requisitions as $requisition)
            <tr class="hover">
                <td><a href="{{ route('recruiting.requisitions.show', $requisition) }}" class="link link-hover font-medium">{{ $requisition->title }}</a></td>
                <td>{{ $requisition->department ?? '—' }}</td>
                <td><x-status-badge :tone="$requisition->statusTone()" size="sm">{{ __("values.{$requisition->status}") }}</x-status-badge></td>
                <td class="text-right tabular-nums">{{ $requisition->applications_count }}</td>
                <td class="tabular-nums">{{ optional($requisition->target_start_on)->fdate() ?? '—' }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('recruiting.requisitions.show', $requisition)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon="work" :colspan="6" :title="__('Keine Stellen — „Stelle anlegen“ startet den ersten Bedarf.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$requisitions" standing />
</x-index-page>
@endsection
