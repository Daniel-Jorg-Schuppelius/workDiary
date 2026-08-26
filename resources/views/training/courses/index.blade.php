{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schulungskatalog (Feature 145): Kurs, Anbieter, Gültigkeit, Pflicht,
  Rechtsgrundlage — Nachweise liegen im Arbeitsschutz-Register (132).
--}}
@extends('layouts.app')
@section('title', __('training.title.courses'))
@section('nav-title', __('training.title.courses'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('training.subtitle.courses')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('training.courses.create')"
                        show-label>{{ __('training.action.create_course') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('training.courses.index')" :reset="route('training.courses.index')">
        <x-filter-field :label="__('training.kpi.mandatory')" for="flt-mandatory-count">
            <span id="flt-mandatory-count" class="badge badge-ghost badge-sm">{{ $mandatoryCount }}</span>
        </x-filter-field>
        <x-filter-field :label="__('training.filter.mandatory_only')" for="flt-mandatory" class="order-40">
            <input id="flt-mandatory" type="checkbox" name="mandatory" value="1" class="toggle toggle-sm" data-autosubmit @checked($onlyMandatory)>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('training.field.title') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.provider_kind') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('training.field.validity_months') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('training.field.lead_days') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.legal_basis') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('training.field.requirements_count') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('training.field.assignments_count') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($courses as $course)
            <tr class="hover">
                <td class="font-medium">
                    <a class="link link-hover" href="{{ route('training.courses.show', $course) }}">{{ $course->title }}</a>
                    @if ($course->is_mandatory)
                        <x-status-badge tone="error" size="sm" outline>{{ __('training.field.is_mandatory') }}</x-status-badge>
                    @endif
                    @unless ($course->is_active)
                        <x-status-badge tone="ghost" size="sm">{{ __('training.field.is_active') }}: {{ __('Nein') }}</x-status-badge>
                    @endunless
                </td>
                <td class="text-sm">
                    <x-status-badge :tone="$course->provider_kind->tone()" size="sm" outline>{{ $course->provider_kind->label() }}</x-status-badge>
                    <span class="text-base-content/70">{{ $course->provider_name ?? '' }}</span>
                </td>
                <td class="text-center text-sm">{{ $course->validity_months ?? '–' }}</td>
                <td class="text-center text-sm">{{ $course->lead_days }}</td>
                <td class="text-sm text-base-content/70">{{ $course->legal_basis ?? '–' }}</td>
                <td class="text-center text-sm">{{ $course->requirements_count }}</td>
                <td class="text-center text-sm">{{ $course->assignments_count }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('training.courses.show', $course)" :label="__('training.action.show')" />
                        @if ($canManage)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('training.courses.edit', $course)"
                                        :label="__('training.action.edit')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="school" :colspan="8" :title="__('training.empty.courses')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$courses" standing />
</x-index-page>
@endsection
