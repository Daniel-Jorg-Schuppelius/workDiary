{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.catalog.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.catalog.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('procurement.catalog.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="paid" size="sm" :href="route('supplier-catalogs.metal-quotations.index')" show-label>{{ __('procurement.metal.title') }}</x-icon-btn>
        <x-icon-btn icon="warning" size="sm" :href="route('supplier-catalogs.alerts')" show-label
                    :tone="($openAlerts ?? 0) > 0 ? 'warning' : null">{{ __('procurement.alert.title') }}@if (($openAlerts ?? 0) > 0) ({{ $openAlerts }})@endif</x-icon-btn>
        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                    :href="route('supplier-catalogs.create')" show-label>{{ __('procurement.catalog.action.new_source') }}</x-icon-btn>
    </x-slot:actions>

    @if ($sources->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">import_export</span>'
                       :title="__('procurement.catalog.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.catalog.col.source') }}</th>
                    <th>{{ __('procurement.field.supplier') }}</th>
                    <th>{{ __('procurement.catalog.col.format') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.items') }}</th>
                    <th>{{ __('procurement.catalog.col.last_import') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>
                </tr>
            </x-slot:head>
                @foreach ($sources as $source)
                    <tr @class(['hover', 'opacity-50' => ! $source->active])>
                        <td><a href="{{ route('supplier-catalogs.show', $source) }}" class="link link-hover font-medium">{{ $source->name }}</a></td>
                        <td>{{ $source->supplier?->name }}</td>
                        <td><span class="badge badge-sm badge-ghost">{{ $source->format->label() }}</span></td>
                        <td class="text-right tabular-nums">{{ $source->items_count }}</td>
                        <td class="text-sm opacity-70">{{ optional($source->last_imported_at)->format('d.m.Y H:i') ?: '—' }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                            :href="route('supplier-catalogs.edit', $source)" :title="__('Bearbeiten')" />
                                <form method="POST" action="{{ route('supplier-catalogs.toggle', $source) }}">@csrf
                                    <x-icon-btn :icon="$source->active ? 'toggle_on' : 'toggle_off'" size="xs"
                                                :tone="$source->active ? 'success' : null" type="submit"
                                                :title="$source->active ? __('procurement.catalog.action.deactivate') : __('procurement.catalog.action.activate')" />
                                </form>
                                <form method="POST" action="{{ route('supplier-catalogs.destroy', $source) }}"
                                      data-confirm-dialog data-confirm-message="{{ __('procurement.catalog.confirm_delete') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Löschen')" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
        </x-table>
        <x-pagination :paginator="$sources" standing />
    @endif
</x-index-page>
@endsection
