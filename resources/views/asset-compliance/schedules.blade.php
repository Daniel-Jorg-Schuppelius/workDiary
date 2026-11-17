{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : schedules.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Prüfkalender'))
@section('nav-title', __('Prüfkalender'))

@section('content')
<x-index-page :subtitle="__('Prüftermine mit internen Prüfern oder externen Prüfstellen; Prüfungen werden als unveränderbare Protokolle erfasst.')">
    <x-slot:actions>
        @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
            <x-icon-btn icon="route" tone="ghost" size="sm" :href="route('asset-compliance.tours.index')" show-label>{{ __('inspection_tour.title') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @include('asset-compliance._tabs')
    <x-validation-errors />

    <x-filter-bar :action="route('asset-compliance.schedules.index')" :reset="route('asset-compliance.schedules.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Offen (Standard)') }}</option>
            @foreach (\App\Enums\AssetCompliance\AssetInspectionScheduleStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Fällig') }}</th>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Prüfprofil') }}</th>
                    <th>{{ __('Prüfer / Prüfstelle') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->due_on->fdate() }}{{ $schedule->planned_on !== null ? ' (' . __('geplant') . ': ' . $schedule->planned_on->fdate() . ')' : '' }}</td>
                    <td>{{ $schedule->asset->name ?? '—' }}</td>
                    <td>{{ $schedule->assignment?->profile?->name ?? '—' }}</td>
                    <td>
                        {{ $schedule->inspector->name ?? $schedule->externalContact->name ?? '—' }}
                        @if ($schedule->external_contact_id !== null)
                            <x-status-badge tone="plain" outline>{{ __('extern') }}</x-status-badge>
                            @can('update', $schedule)
                                <x-button :href="route('external.create', ['type' => 'inspection', 'id' => $schedule->sqid])" tone="ghost"
                                        size="xs" data-entry-modal-trigger>
                                    {{ __('Zugang einladen') }}
                                </x-button>
                            @endcan
                        @endif
                    </td>
                    <td>
                        <x-status-badge :tone="$schedule->status->tone()" size="md" outline>{{ $schedule->status->label() }}</x-status-badge>
                        @if ($schedule->cancel_reason)
                            <span class="block text-xs text-muted">{{ $schedule->cancel_reason }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @can('inspect', \App\Models\AssetCompliance\AssetComplianceProfile::class)
                            @if ($schedule->status->isOpen() && $schedule->assignment !== null)
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs btn-primary">{{ __('Prüfung erfassen') }}</summary>
                                    {{-- Merker aus früheren Prüfungen dieser Pflicht und für die nächste (Feature 201, MVP-1646). --}}
                                    <x-carry-forward-notes :subject="$schedule" :anchor="'carry-forward-' . $schedule->sqid" />
                                    @include('asset-compliance._inspection_form', ['assignment' => $schedule->assignment, 'schedule' => $schedule])
                                </details>
                            @endif
                        @endcan
                        @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
                            @if ($schedule->status->canTransitionTo(\App\Enums\AssetCompliance\AssetInspectionScheduleStatus::Cancelled))
                                <x-icon-btn icon="event_busy" tone="error" size="xs" data-entry-modal-trigger
                                            :href="route('asset-compliance.schedules.cancel-form', $schedule)" :label="__('asset_compliance.schedule.cancel_action')" />
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon="event_available" :colspan="6" :title="request('status') ? __('asset_compliance.schedule.none_for_status') : __('Keine offenen Prüftermine.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$schedules" standing />

    {{-- Prüfprotokolle mit Ergebniszeilen und Roh-Messwerten (MVP-1575). --}}
    <x-card :title="__('asset_compliance.protocol.title')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('asset_compliance.protocol.performed_at') }}</th>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Prüfprofil') }}</th>
                    <th>{{ __('Ergebnis') }}</th>
                    <th>{{ __('asset_compliance.protocol.values') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($events as $event)
                <tr>
                    <td>
                        {{ $event->performed_at->fdatetime() }}
                        <span class="block text-xs text-muted">{{ $event->performer->name ?? $event->external_inspector_name ?? '—' }}</span>
                    </td>
                    <td>{{ $event->asset->name ?? '—' }}</td>
                    <td>{{ $event->assignment?->profile?->name ?? '—' }}</td>
                    <td>
                        <x-status-badge :tone="$event->result->isPassed() ? 'success' : 'error'" size="md" outline>{{ $event->result->label() }}</x-status-badge>
                        @if ($event->certificate)
                            <span class="block text-xs text-muted">{{ __('Zertifikatsnummer') }}: {{ $event->certificate->certificate_no }}</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        @forelse ($event->results as $line)
                            <div>
                                {{ $line->label }}:
                                <span class="font-mono">{{ $line->value !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line->value, 4) : '—' }} {{ $line->unit }}</span>
                                @if (! $line->passed)
                                    <x-status-badge tone="error" size="sm">{{ __('asset_compliance.protocol.out_of_limit') }}</x-status-badge>
                                @endif
                            </div>
                        @empty
                        @endforelse
                        @foreach ($event->measurements as $measurement)
                            <div class="text-base-content/70">
                                {{ $measurement->label }}:
                                <span class="font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $measurement->value, 4) }} {{ $measurement->unit }}</span>
                            </div>
                        @endforeach
                        @if ($event->results->isEmpty() && $event->measurements->isEmpty())
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="fact_check" :colspan="5" :title="__('asset_compliance.protocol.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
        <x-card :title="__('Prüftermin planen')">
            <form method="POST" action="{{ route('asset-compliance.schedules.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-select-field name="assignment_id" :label="__('Prüfpflicht')" required>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->sqid }}">
                            {{ $assignment->asset->name ?? '—' }} — {{ $assignment->profile->name ?? '—' }} ({{ __('fällig') }} {{ optional($assignment->next_due_on)->fdate() ?? '—' }})
                        </option>
                    @endforeach
                </x-select-field>
                <x-input-field name="due_on" type="date" :label="__('Fällig am')" required />
                <x-input-field name="planned_on" type="date" :label="__('Geplant am')" />
                <x-select-field name="inspector_user_id" :label="__('Interner Prüfer')">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="external_contact_id" :label="__('Externe Prüfstelle')">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($externalContacts as $contact)
                        <option value="{{ $contact->sqid }}">{{ $contact->name }}</option>
                    @endforeach
                </x-select-field>
                <x-button type="submit">{{ __('Termin planen') }}</x-button>
            </form>
        </x-card>
    @endcan
</x-index-page>
@endsection
