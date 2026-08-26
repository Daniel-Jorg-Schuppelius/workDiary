{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Unterweisungs-Register (Feature 132): Nummer/Thema/Datum/Unterweisende:r/
  Bestätigungen, KPI Wiederholung fällig.
--}}
@extends('layouts.app')
@section('title', __('safety.register.title.instructions'))
@section('nav-title', __('safety.register.title.instructions'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.register.subtitle.instructions')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('safety.instructions.create')"
                        show-label>{{ __('safety.register.action.create_instruction') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('safety.instructions.index')" :reset="route('safety.instructions.index')">
        <x-filter-field :label="__('safety.register.kpi.instruction_due')" for="flt-due">
            <span id="flt-due" class="badge {{ $dueCount > 0 ? 'badge-error' : 'badge-ghost' }} badge-sm">{{ $dueCount }}</span>
        </x-filter-field>
        <x-filter-field :label="__('safety.register.filter.open_only')" for="flt-open" class="order-40">
            <input id="flt-open" type="checkbox" name="open" value="1" class="toggle toggle-sm" data-autosubmit @checked($onlyOpen)>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('safety.register.field.instruction_no') }}</th>
                <th>{{ __('safety.register.field.topic') }}</th>
                <th>{{ __('safety.register.field.held_on') }}</th>
                <th>{{ __('safety.register.field.instructor') }}</th>
                <th>{{ __('safety.register.field.assessment') }}</th>
                <th class="text-center">{{ __('safety.register.field.repeat_interval_months') }}</th>
                <th>{{ __('safety.register.field.signed') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($instructions as $instruction)
            @php $allSigned = $instruction->participants_count > 0 && $instruction->signed_participants_count === $instruction->participants_count; @endphp
            <tr class="hover">
                <td class="font-mono text-sm">{{ $instruction->displayNo() }}</td>
                <td class="font-medium">{{ $instruction->topic }}</td>
                <td class="text-sm">{{ $instruction->held_on->format('d.m.Y') }}</td>
                <td class="text-sm">{{ $instruction->instructor?->name ?? '–' }}</td>
                <td class="text-sm text-base-content/70">
                    @if ($instruction->assessment)
                        <a class="link link-hover font-mono" href="{{ route('safety.assessments.show', $instruction->assessment) }}">{{ $instruction->assessment->displayNo() }}</a>
                    @else
                        –
                    @endif
                </td>
                <td class="text-center text-sm">{{ $instruction->repeat_interval_months ?? '–' }}</td>
                <td>
                    <x-status-badge :tone="$allSigned ? 'success' : 'warning'" size="sm">
                        {{ __('safety.register.status_summary', ['signed' => $instruction->signed_participants_count, 'total' => $instruction->participants_count]) }}
                    </x-status-badge>
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('safety.instructions.show', $instruction)" :label="__('safety.register.action.show')" />
                        @if ($canManage)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('safety.instructions.edit', $instruction)"
                                        :label="__('safety.register.action.edit')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="school" :colspan="8" :title="__('safety.register.empty.instructions')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$instructions" standing />
</x-index-page>
@endsection
