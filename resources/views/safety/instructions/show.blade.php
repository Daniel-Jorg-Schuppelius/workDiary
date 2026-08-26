{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachweis-Ansicht einer Unterweisung (Feature 132): Kopf, Teilnehmerliste
  mit Signaturstatus, Bestätigungs-Klick der eigenen Zeile.
--}}
@extends('layouts.app')
@section('title', $instruction->displayNo())
@section('nav-title', $instruction->displayNo())
@section('content')
@php
    $signed = $instruction->participants->whereNotNull('signed_at')->count();
    $total = $instruction->participants->count();
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$instruction->topic . ' · ' . $instruction->held_on->format('d.m.Y')"
                        :badge="__('safety.register.status_summary', ['signed' => $signed, 'total' => $total])"
                        :badgeTone="$total > 0 && $signed === $total ? 'success' : 'warning'">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('safety.instructions.edit', $instruction)"
                                show-label>{{ __('safety.register.action.edit') }}</x-icon-btn>
                @endif
                @can('viewAny', \App\Models\Safety\SafetyInstruction::class)
                    <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                                :href="route('safety.instructions.index')"
                                show-label>{{ __('safety.register.action.back') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="groups" class="text-muted" /> {{ __('safety.register.field.participants') }}
                    <span class="font-normal text-muted">({{ $total }})</span>
                </h3>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('safety.register.field.user') }}</th>
                            <th>{{ __('safety.register.field.signed') }}</th>
                            <th>{{ __('safety.register.field.signed_at') }}</th>
                            <th>{{ __('safety.register.field.method') }}</th>
                            <th>{{ __('safety.register.field.next_due_on') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($instruction->participants as $participant)
                        <tr class="hover">
                            <td class="text-sm font-medium">{{ $participant->user?->name ?? '–' }}</td>
                            <td>
                                <x-status-badge :tone="$participant->isSigned() ? 'success' : 'warning'" size="sm">
                                    {{ $participant->isSigned() ? __('Ja') : __('Nein') }}
                                </x-status-badge>
                            </td>
                            <td class="text-sm text-base-content/70">{{ $participant->signed_at?->format('d.m.Y H:i') ?? '–' }}</td>
                            <td class="text-sm text-base-content/70">{{ $participant->method?->label() ?? '–' }}</td>
                            <td class="text-sm {{ $participant->isDueOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">{{ $participant->next_due_on?->format('d.m.Y') ?? '–' }}</td>
                            <td class="text-right">
                                @can('sign', $participant)
                                    <x-action-form :action="route('safety.instructions.participants.sign', [$instruction, $participant])"
                                                   :confirm="__('safety.register.confirm.sign')"
                                                   confirm-icon="draw" confirm-tone="primary"
                                                   :confirm-label="__('safety.register.action.sign')">
                                        <x-icon-btn icon="draw" tone="primary" size="xs" type="submit" show-label>{{ __('safety.register.action.sign') }}</x-icon-btn>
                                    </x-action-form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('safety.register.empty.participants')" compact />
                    @endforelse
                </x-table>
                @if ($ownParticipant !== null && ! $ownParticipant->isSigned())
                    <p class="mt-3 text-xs text-muted">{{ __('safety.register.hint.sign_self') }}</p>
                @endif
                @if ($errors->has('participant'))
                    <p class="mt-2 text-sm text-error">{{ $errors->first('participant') }}</p>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('safety.register.field.instruction_no')" :value="$instruction->displayNo()" />
                    <x-detail-grid.row :label="__('safety.register.field.topic')" :value="$instruction->topic" />
                    <x-detail-grid.row :label="__('safety.register.field.held_on')" :value="$instruction->held_on->format('d.m.Y')" />
                    <x-detail-grid.row :label="__('safety.register.field.instructor')" :value="$instruction->instructor?->name ?? '–'" />
                    <x-detail-grid.row :label="__('safety.register.field.repeat_interval_months')" :value="$instruction->repeat_interval_months ?? '–'" />
                    @if ($instruction->assessment)
                        <x-detail-grid.row :label="__('safety.register.field.assessment')">
                            <a class="link link-hover font-mono" href="{{ route('safety.assessments.show', $instruction->assessment) }}">{{ $instruction->assessment->displayNo() }}</a>
                            <span class="text-muted">{{ $instruction->assessment->area }}</span>
                        </x-detail-grid.row>
                    @endif
                    @if ($instruction->trainingCourse)
                        {{-- Feature 145: dieser Nachweis erfüllt das Schulungs-Soll des Kurses. --}}
                        <x-detail-grid.row :label="__('training.field.course')">
                            <a class="link link-hover" href="{{ route('training.courses.show', $instruction->trainingCourse) }}">{{ $instruction->trainingCourse->title }}</a>
                            <span class="text-muted">{{ $instruction->trainingCourseVersion?->displayLabel() }}</span>
                        </x-detail-grid.row>
                    @endif
                    <x-detail-grid.row :label="__('safety.register.field.notes')" :value="$instruction->notes ?? '–'" />
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('safety.register.hint.pdf_not_in_mvp') }}</p>
            </x-card>

            @if ($canManage)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold">{{ __('safety.register.action.delete') }}</h3>
                    <x-action-form :action="route('safety.instructions.destroy', $instruction)" method="DELETE"
                                   :confirm="__('safety.register.confirm.delete_instruction')"
                                   confirm-icon="delete" confirm-tone="error"
                                   :confirm-label="__('safety.register.action.delete')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('safety.register.action.delete') }}</x-icon-btn>
                    </x-action-form>
                    @if ($errors->has('instruction'))
                        <p class="mt-2 text-sm text-error">{{ $errors->first('instruction') }}</p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
