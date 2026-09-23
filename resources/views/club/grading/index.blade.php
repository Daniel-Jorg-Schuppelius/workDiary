{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Graduierungsordnungen (Feature 159, MVP-846): Liste je Disziplin; Modul je Organisation zuschaltbar. --}}
@extends('layouts.app')
@section('title', __('club.grading.title.index'))
@section('nav-title', __('club.grading.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.grading.subtitle.index')">
    <x-slot:actions>
        @if ($canSettings)
            <x-icon-btn icon="settings" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.settings.edit')" show-label>{{ __('club.grading.action.settings') }}</x-icon-btn>
        @endif
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.grading.create')" show-label>{{ __('club.grading.action.create_system') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.grading" />
    </x-slot:actions>

    @unless ($enabled)
        <div class="alert alert-info mb-3 text-sm" role="status">
            <x-icon name="info" />
            <span>{{ __('club.grading.hint.disabled') }}</span>
        </div>
    @endunless

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.grading.field.name') }}</th>
                <th>{{ __('club.field.discipline') }}</th>
                <th class="text-right">{{ __('club.grading.field.grades') }}</th>
                <th class="text-right">{{ __('club.grading.field.versions') }}</th>
                <th class="text-right">{{ __('club.grading.field.member_grades') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($systems as $system)
            <tr class="hover">
                <td class="font-medium"><a href="{{ route('club.grading.show', $system) }}" class="link link-hover">{{ $system->name }}</a></td>
                <td class="text-sm">{{ $system->discipline }}</td>
                <td class="text-right tabular-nums">{{ $system->grades_count }}</td>
                <td class="text-right tabular-nums">{{ $system->versions_count }}</td>
                <td class="text-right tabular-nums">{{ $system->member_grades_count }}</td>
                <td>
                    <x-status-badge :tone="$system->is_active ? 'success' : 'ghost'" size="sm">{{ $system->is_active ? __('club.grading.label.active') : __('club.label.inactive') }}</x-status-badge>
                </td>
                <td class="text-right">
                    <x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.grading.show', $system)" :label="__('club.action.show')" />
                </td>
            </tr>
        @empty
            <x-table.empty icon="military_tech" :colspan="7" :title="__('club.grading.empty.systems')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
