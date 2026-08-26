{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kursdetail (Feature 145): Stammdaten, Kursversionen, Pflichtzuordnungen.
--}}
@extends('layouts.app')
@section('title', $course->title)
@section('nav-title', $course->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->legal_basis ?? __('training.subtitle.courses')"
                        :badge="$course->is_mandatory ? __('training.field.is_mandatory') : null"
                        badgeTone="error">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('training.courses.versions.create', $course)"
                                show-label>{{ __('training.action.create_version') }}</x-icon-btn>
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('training.courses.edit', $course)"
                                show-label>{{ __('training.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('training.courses.index')"
                            show-label>{{ __('training.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="history_edu" class="text-muted" /> {{ __('training.field.versions') }}
                </h3>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('training.field.version') }}</th>
                            <th>{{ __('training.field.valid_from') }}</th>
                            <th>{{ __('training.field.content_summary') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($course->versions as $version)
                        <tr class="hover">
                            <td class="font-mono text-sm">
                                {{ $version->displayLabel() }}
                                @if ($version->is_current)
                                    <x-status-badge tone="success" size="sm">{{ __('training.field.is_active') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-sm">{{ $version->valid_from?->format('d.m.Y') ?? '–' }}</td>
                            <td class="text-sm text-base-content/70">{{ \Illuminate\Support\Str::limit((string) $version->content_summary, 80) }}</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <div class="flex justify-end gap-1">
                                        <x-action-form :action="route('training.courses.versions.destroy', [$course, $version])" method="DELETE"
                                                       :confirm="__('training.confirm.delete_version')"
                                                       confirm-icon="delete" confirm-tone="error"
                                                       :confirm-label="__('training.action.delete')">
                                            <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('training.action.delete')" />
                                        </x-action-form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('training.empty.versions')" compact />
                    @endforelse
                </x-table>
                @if ($errors->has('version'))
                    <p class="mt-2 text-sm text-error">{{ $errors->first('version') }}</p>
                @endif
            </x-card>

            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="grid_view" class="text-muted" /> {{ __('training.title.requirements') }}
                </h3>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('training.field.subject_kind') }}</th>
                            <th>{{ __('training.field.subject') }}</th>
                            <th>{{ __('training.field.first_due_days') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($course->requirements as $requirement)
                        <tr class="hover">
                            <td class="text-sm">{{ $requirement->subject_kind->label() }}</td>
                            <td class="text-sm font-medium">{{ $requirement->subjectLabel() }}</td>
                            <td class="text-sm">{{ $requirement->first_due_days }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3" :title="__('training.empty.requirements')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('training.field.code')" :value="$course->code" />
                    <x-detail-grid.row :label="__('training.field.provider_kind')" :value="$course->provider_kind->label()" />
                    <x-detail-grid.row :label="__('training.field.provider_name')" :value="$course->provider_name ?? '–'" />
                    <x-detail-grid.row :label="__('training.field.duration_minutes')" :value="$course->duration_minutes ?? '–'" />
                    <x-detail-grid.row :label="__('training.field.validity_months')" :value="$course->validity_months ?? '–'" />
                    <x-detail-grid.row :label="__('training.field.lead_days')" :value="$course->lead_days" />
                    <x-detail-grid.row :label="__('training.field.legal_basis')" :value="$course->legal_basis ?? '–'" />
                    <x-detail-grid.row :label="__('training.field.cost_amount')" :value="$course->cost_amount !== null ? $course->cost_amount . ' ' . ($course->cost_currency?->value ?? '') : '–'" />
                    <x-detail-grid.row :label="__('training.field.assignments_count')" :value="$assignmentCount" />
                    <x-detail-grid.row :label="__('training.field.notes')" :value="$course->notes ?? '–'" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('training.hint.proof_in_register') }}</p>
            </x-card>

            @if ($canManage)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold">{{ __('training.action.delete') }}</h3>
                    <x-action-form :action="route('training.courses.destroy', $course)" method="DELETE"
                                   :confirm="__('training.confirm.delete_course')"
                                   confirm-icon="delete" confirm-tone="error"
                                   :confirm-label="__('training.action.delete')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('training.action.delete') }}</x-icon-btn>
                    </x-action-form>
                    @if ($errors->has('course'))
                        <p class="mt-2 text-sm text-error">{{ $errors->first('course') }}</p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
