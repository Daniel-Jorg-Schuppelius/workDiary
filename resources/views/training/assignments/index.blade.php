{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schulungs-Soll (Feature 145): wer schuldet welchen Kurs bis wann und
  womit ist er nachgewiesen (Link ins Arbeitsschutz-Register).
--}}
@extends('layouts.app')
@section('title', __('training.title.assignments'))
@section('nav-title', __('training.title.assignments'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('training.subtitle.assignments')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('training.assignments.create')"
                        show-label>{{ __('training.action.create_assignment') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('training.assignments.index')" :reset="route('training.assignments.index')">
        <x-filter-field :label="__('training.filter.state')" for="flt-state">
            <select id="flt-state" name="state" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('training.filter.all') }}</option>
                @foreach (\App\Enums\Training\TrainingAssignmentState::cases() as $option)
                    <option value="{{ $option->value }}" @selected($state === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('training.kpi.overdue')" for="flt-overdue">
            <span id="flt-overdue" class="badge {{ $overdueCount > 0 ? 'badge-error' : 'badge-ghost' }} badge-sm">{{ $overdueCount }}</span>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('training.field.user') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.course') }}</x-table.th>
                <x-table.th sort type="date">{{ __('training.field.due_at') }}</x-table.th>
                <x-table.th sort type="date">{{ __('training.field.fulfilled_at') }}</x-table.th>
                <x-table.th sort type="string">{{ __('training.field.state') }}</x-table.th>
                <th>{{ __('training.field.proof') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($assignments as $assignment)
            @php $assignmentState = $assignment->state(); @endphp
            <tr class="hover">
                <td class="font-medium">{{ $assignment->user?->name ?? '–' }}</td>
                <td class="text-sm">{{ $assignment->course?->title ?? '–' }}</td>
                <td class="text-sm">{{ $assignment->due_at?->format('d.m.Y') ?? '–' }}</td>
                <td class="text-sm text-base-content/70">{{ $assignment->fulfilled_at?->format('d.m.Y') ?? '–' }}</td>
                <td>
                    <x-status-badge :tone="$assignmentState->tone()" size="sm">{{ $assignmentState->label() }}</x-status-badge>
                </td>
                <td class="text-sm text-base-content/70">
                    @if ($assignment->instruction)
                        <a class="link link-hover font-mono" href="{{ route('safety.instructions.show', $assignment->instruction) }}">{{ $assignment->instruction->displayNo() }}</a>
                        <span>{{ $assignment->instruction->topic }}</span>
                    @else
                        –
                    @endif
                </td>
                <td class="text-right">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-action-form :action="route('training.assignments.destroy', $assignment)" method="DELETE"
                                           :confirm="__('training.confirm.delete_assignment')"
                                           confirm-icon="delete" confirm-tone="error"
                                           :confirm-label="__('training.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('training.action.delete')" />
                            </x-action-form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="fact_check" :colspan="7" :title="__('training.empty.assignments')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$assignments" standing />
</x-index-page>
@endsection
