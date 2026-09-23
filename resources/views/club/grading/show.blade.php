{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ordnung (MVP-846): Grade in Reihenfolge, Regelversionen, Voraussetzungen der gewählten Version je Zielgrad. --}}
@extends('layouts.app')
@section('title', $system->name)
@section('nav-title', __('club.grading.title.index'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$system->name . ' · ' . $system->discipline"
                        :badge="$system->is_active ? __('club.grading.label.active') : __('club.label.inactive')"
                        :badgeTone="$system->is_active ? 'success' : 'ghost'">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.grading.grades.create', $system)" show-label>{{ __('club.grading.action.create_grade') }}</x-icon-btn>
                    <x-action-form :action="route('club.grading.versions.store', $system)">
                        <x-icon-btn type="submit" icon="library_add" tone="outline" size="sm" show-label>{{ __('club.grading.action.create_version') }}</x-icon-btn>
                    </x-action-form>
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.grading.edit', $system)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.grading.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
                <x-help-button topic="club.grading" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.grading.card.grades')" icon="military_tech" :count="$system->grades->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.grading.hint.grades') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th class="w-16">{{ __('club.grading.field.rank') }}</th>
                            <th>{{ __('club.grading.field.grade') }}</th>
                            <th>{{ __('club.grading.field.requirements') }}</th>
                            <th class="text-right">{{ __('club.grading.field.members') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($system->grades as $grade)
                        @php $requirement = $requirements->get($grade->id); @endphp
                        <tr class="align-top">
                            <td class="tabular-nums">{{ $grade->rank }}</td>
                            <td class="font-medium">
                                @if ($grade->color)<span class="mr-1 inline-block h-3 w-3 rounded-full border border-base-300 align-middle" style="background: {{ $grade->color }}"></span>@endif
                                {{ $grade->name }}
                                @unless ($grade->is_active)<x-status-badge tone="ghost" size="xs" :label="__('club.label.inactive')" />@endunless
                            </td>
                            <td class="text-xs">
                                @if ($requirement)
                                    <ul class="space-y-0.5">
                                        @if ($requirement->previousGrade)<li>{{ __('club.grading.field.previous_grade') }}: {{ $requirement->previousGrade->name }}</li>@endif
                                        @if ($requirement->min_minutes !== null)<li>{{ __('club.grading.field.min_minutes') }}: {{ \App\Services\Club\ClubEligibilityReport::hoursMinutes($requirement->min_minutes) }}</li>@endif
                                        @if ($requirement->min_sessions !== null)<li>{{ __('club.grading.field.min_sessions') }}: {{ $requirement->min_sessions }}@if ($requirement->min_minutes_per_session) ({{ __('club.grading.label.per_session', ['minutes' => $requirement->min_minutes_per_session]) }})@endif</li>@endif
                                        <li>{{ __('club.grading.field.counting_basis') }}: {{ $requirement->counting_basis->label() }}@if ($requirement->window_months) ({{ trans_choice('club.grading.months', $requirement->window_months, ['count' => $requirement->window_months]) }})@endif</li>
                                        @if ($requirement->wait_months !== null)<li>{{ __('club.grading.field.wait_months') }}: {{ trans_choice('club.grading.months', $requirement->wait_months, ['count' => $requirement->wait_months]) }}</li>@endif
                                        @if ($requirement->min_age !== null)<li>{{ __('club.field.min_age') }}: {{ $requirement->min_age }}</li>@endif
                                        @if ($requirement->required_proof_label)<li>{{ __('club.grading.field.required_proof_label') }}: {{ $requirement->required_proof_label }}</li>@endif
                                        @if ($requirement->counted_group_ids)<li>{{ __('club.grading.field.counted_group_ids') }}: {{ collect($requirement->counted_group_ids)->map(fn($id) => $groupNames[$id] ?? $id)->join(', ') }}</li>@endif
                                        @if ($requirement->requires_approval)<li>{{ __('club.grading.field.requires_approval') }}</li>@endif
                                        @if ($requirement->allows_exception)<li>{{ __('club.grading.field.allows_exception') }}</li>@endif
                                    </ul>
                                @elseif ($version)
                                    <span class="text-muted">{{ __('club.grading.label.no_requirement') }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $grade->member_grades_count }}</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <div class="flex justify-end gap-1">
                                        @if ($version)
                                            <x-icon-btn icon="rule" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.grading.requirements.edit', [$version, $grade])" :label="__('club.grading.action.edit_requirement')" />
                                            @if ($requirement)
                                                <x-action-form :action="route('club.grading.requirements.destroy', $requirement)" method="DELETE" :confirm="__('club.grading.confirm.delete_requirement')" confirm-icon="delete" confirm-tone="error">
                                                    <x-icon-btn type="submit" icon="rule_folder" tone="outline" size="xs" class="btn-error" :label="__('club.grading.action.delete_requirement')" />
                                                </x-action-form>
                                            @endif
                                        @endif
                                        <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.grading.grades.edit', $grade)" :label="__('club.action.edit')" />
                                        @if ($grade->member_grades_count === 0)
                                            <x-action-form :action="route('club.grading.grades.destroy', $grade)" method="DELETE" :confirm="__('club.grading.confirm.delete_grade')" confirm-icon="delete" confirm-tone="error">
                                                <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                            </x-action-form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="military_tech" :colspan="5" :title="__('club.grading.empty.grades')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.grading.card.versions')" icon="history" :count="$system->versions->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.grading.hint.versions') }}</p>
                <ul class="space-y-2 text-sm">
                    @forelse ($system->versions as $item)
                        <li class="rounded-box border px-3 py-2 {{ $version && $item->id === $version->id ? 'border-primary' : 'border-base-300' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('club.grading.show', [$system, 'version' => $item->sqid]) }}" class="link link-hover font-medium">v{{ $item->version_no }}</a>
                                <x-status-badge :tone="$item->isActive() ? 'success' : ($item->isDraft() ? 'warning' : 'ghost')" size="xs">{{ $item->status->label() }}</x-status-badge>
                                @if ($item->unit_minutes)<span class="text-xs text-muted">{{ __('club.grading.label.unit', ['minutes' => $item->unit_minutes]) }}</span>@endif
                                @if ($item->accepts_external_credits)<span class="text-xs text-muted">{{ __('club.grading.label.external_ok') }}</span>@endif
                            </div>
                            @if ($item->notes)<p class="mt-1 text-xs text-muted">{{ $item->notes }}</p>@endif
                            @if ($canManage)
                                <div class="mt-1 flex gap-1">
                                    <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.grading.versions.edit', $item)" :label="__('club.action.edit')" />
                                    @unless ($item->isActive())
                                        <x-action-form :action="route('club.grading.versions.activate', $item)" :confirm="__('club.grading.confirm.activate_version', ['no' => $item->version_no])" confirm-icon="verified" confirm-tone="primary">
                                            <x-icon-btn type="submit" icon="verified" tone="primary" size="xs" show-label>{{ __('club.grading.action.activate') }}</x-icon-btn>
                                        </x-action-form>
                                    @endunless
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-muted">{{ __('club.grading.empty.versions') }}</li>
                    @endforelse
                </ul>
            </x-card>

            @if ($system->description)
                <x-card :title="__('club.field.description')" icon="info">
                    <p class="whitespace-pre-line text-sm">{{ $system->description }}</p>
                </x-card>
            @endif

            @if ($canManage && $system->member_grades_count === 0 && $system->memberGrades()->doesntExist())
                <x-action-form :action="route('club.grading.destroy', $system)" method="DELETE" :confirm="__('club.grading.confirm.delete_system')" confirm-icon="delete" confirm-tone="error">
                    <x-icon-btn type="submit" icon="delete" tone="error" size="sm" show-label>{{ __('club.action.delete') }}</x-icon-btn>
                </x-action-form>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
