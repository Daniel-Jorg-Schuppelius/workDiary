{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Pflichtmatrix (Feature 145): je Zeile eine Zuordnung Rolle bzw.
  Tätigkeitsbereich × Kurs — Quelle der Soll-Einträge.
--}}
@extends('layouts.app')
@section('title', __('training.title.requirements'))
@section('nav-title', __('training.title.requirements'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('training.subtitle.requirements')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('training.requirements.create')"
                        show-label>{{ __('training.action.create_requirement') }}</x-icon-btn>
            <x-action-form :action="route('training.requirements.sync')"
                           :confirm="__('training.hint.sync')"
                           confirm-icon="sync" confirm-tone="primary"
                           :confirm-label="__('training.action.sync_assignments')">
                <x-icon-btn icon="sync" tone="outline" size="sm" type="submit" show-label>{{ __('training.action.sync_assignments') }}</x-icon-btn>
            </x-action-form>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('training.requirements.index')" :reset="route('training.requirements.index')">
        <x-filter-field :label="__('training.filter.subject_kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('training.filter.all') }}</option>
                @foreach (\App\Enums\Training\TrainingRequirementSubject::cases() as $subject)
                    <option value="{{ $subject->value }}" @selected($kind === $subject->value)>{{ $subject->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('training.kpi.active_requirements')" for="flt-active-count">
            <span id="flt-active-count" class="badge badge-ghost badge-sm">{{ $activeCount }}</span>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('training.field.subject') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.subject_kind') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.course') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('training.field.first_due_days') }}</x-table.th>
                <x-table.th sort type="string" align="center">{{ __('training.field.is_active') }}</x-table.th>
                <th>{{ __('training.field.source') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($requirements as $requirement)
            <tr class="hover">
                <td class="font-medium">{{ $requirement->subjectLabel() }}</td>
                <td class="text-sm">{{ $requirement->subject_kind->label() }}</td>
                <td class="text-sm">
                    @if ($requirement->course)
                        <a class="link link-hover" href="{{ route('training.courses.show', $requirement->course) }}">{{ $requirement->course->title }}</a>
                    @else
                        –
                    @endif
                </td>
                <td class="text-center text-sm">{{ $requirement->first_due_days }}</td>
                <td class="text-center">
                    <x-status-badge :tone="$requirement->is_active ? 'success' : 'ghost'" size="sm">
                        {{ $requirement->is_active ? __('Ja') : __('Nein') }}
                    </x-status-badge>
                </td>
                <td class="text-sm text-base-content/70">{{ $requirement->source }}</td>
                <td class="text-right">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('training.requirements.edit', $requirement)"
                                        :label="__('training.action.edit')" />
                            <x-action-form :action="route('training.requirements.destroy', $requirement)" method="DELETE"
                                           :confirm="__('training.confirm.delete_requirement')"
                                           confirm-icon="delete" confirm-tone="error"
                                           :confirm-label="__('training.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('training.action.delete')" />
                            </x-action-form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="grid_view" :colspan="7" :title="__('training.empty.requirements')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$requirements" standing />
</x-index-page>
@endsection
