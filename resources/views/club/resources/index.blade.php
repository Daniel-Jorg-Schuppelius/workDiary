{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sportstätten und Ressourcen (Feature 159, MVP-853): Baum aus Hallen, Teilflächen, Tischen, Plätzen, Bahnen, Booten/Geräten. --}}
@extends('layouts.app')
@section('title', __('club.resources.title.index'))
@section('nav-title', __('club.resources.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.resources.subtitle.index')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.resources.create')" show-label>{{ __('club.resources.action.create') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.resources" />
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.resources.field.kind') }}</th>
                <th class="text-center">{{ __('club.resources.field.capacity') }}</th>
                <th>{{ __('club.resources.field.room') }}</th>
                <th>{{ __('club.resources.field.asset') }}</th>
                <th class="text-center">{{ __('club.resources.field.requires_clearance') }}</th>
                <th class="text-right">{{ __('club.resources.field.bookings') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($tree as $row)
            @php($resource = $row['resource'])
            <tr class="hover {{ $resource->is_active ? '' : 'opacity-60' }}">
                <td class="font-medium" style="padding-left: {{ 0.75 + $row['depth'] * 1.25 }}rem;">
                    <x-icon :name="$resource->kind->icon()" class="mr-1 text-muted" />
                    <a href="{{ route('club.resources.show', $resource) }}" class="link link-hover">{{ $resource->name }}</a>
                    @if ($closedIds->contains($resource->id))<span class="badge badge-warning badge-xs">{{ __('club.resources.label.closed_now') }}</span>@endif
                </td>
                <td class="text-sm">{{ $resource->kind->label() }}</td>
                <td class="text-center text-sm tabular-nums">{{ $resource->capacity }}</td>
                <td class="text-sm">{{ $resource->room?->name ?? '–' }}</td>
                <td class="text-sm">{{ $resource->asset?->name ?? '–' }}</td>
                <td class="text-center">@if ($resource->requires_clearance)<x-icon name="verified_user" class="text-info" />@else<span class="text-muted">–</span>@endif</td>
                <td class="text-right text-sm tabular-nums">{{ $resource->bookings_count }}</td>
                <td><x-status-badge :tone="$resource->is_active ? 'success' : 'ghost'" size="sm">{{ $resource->is_active ? __('club.grading.label.active') : __('club.label.inactive') }}</x-status-badge></td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.resources.show', $resource)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="stadium" :colspan="9" :title="__('club.resources.empty.index')" :message="__('club.resources.hint.index_empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
