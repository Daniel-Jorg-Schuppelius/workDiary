{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Pferde (Feature 159, MVP-854): Schul- und Privatpferde mit Reitgruppen, Einsatzgrenze, Ruhepuffer und Sperrhinweis. --}}
@extends('layouts.app')
@section('title', __('club.horses.title.index'))
@section('nav-title', __('club.horses.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.horses.subtitle.index')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.horses.create')" show-label>{{ __('club.horses.action.create') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.horses" />
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.horses.field.kind') }}</th>
                <th>{{ __('club.horses.field.owner') }}</th>
                <th>{{ __('club.horses.field.groups') }}</th>
                <th>{{ __('club.horses.field.suitable_for') }}</th>
                <th class="text-center">{{ __('club.horses.field.max_uses_per_day') }}</th>
                <th class="text-center">{{ __('club.horses.field.rest_minutes') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($horses as $horse)
            <tr class="hover {{ $horse->is_active ? '' : 'opacity-60' }}">
                <td class="font-medium">
                    <a href="{{ route('club.horses.show', $horse) }}" class="link link-hover">{{ $horse->name }}</a>
                    @if ($closedResourceIds->contains($horse->club_resource_id))<span class="badge badge-warning badge-xs">{{ __('club.resources.label.closed_now') }}</span>@endif
                    @if ($horse->resource?->requires_clearance)<x-icon name="verified_user" class="text-info" />@endif
                </td>
                <td class="text-sm">{{ $horse->kind->label() }}</td>
                <td class="text-sm">{{ $horse->owner?->fullName() ?? '–' }}</td>
                <td class="text-sm">{{ $horse->groups->pluck('name')->implode(', ') ?: __('club.horses.label.all_groups') }}</td>
                <td class="text-sm">{{ $horse->suitable_for ?? '–' }}</td>
                <td class="text-center text-sm tabular-nums">{{ $horse->max_uses_per_day ?? '–' }}</td>
                <td class="text-center text-sm tabular-nums">{{ $horse->resource?->teardown_minutes ?? 0 }}</td>
                <td><x-status-badge :tone="$horse->is_active ? 'success' : 'ghost'" size="sm">{{ $horse->is_active ? __('club.grading.label.active') : __('club.label.inactive') }}</x-status-badge></td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.horses.show', $horse)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="bedroom_baby" :colspan="9" :title="__('club.horses.empty.index')" :message="__('club.horses.hint.index_empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
