@extends('layouts.app')
@section('title', $project->name . ' — ' . __('Projekt'))
@section('nav-title', $project->name)

@section('content')
<x-page-shell>

    <x-page-toolbar :title="$project->name" :badge="$project->statusLabel()" :badge-tone="$project->statusTone()">
        @if ($project->description)
            <p class="max-w-prose">{{ $project->description }}</p>
        @endif
        <div class="mt-1 flex flex-wrap gap-3 text-xs text-base-content/60">
            @if ($project->starts_on)
                <span>{{ __('Start') }}: {{ $project->starts_on->fdate() }}</span>
            @endif
            @if ($project->ends_on)
                <span>{{ __('Ende') }}: {{ $project->ends_on->fdate() }}</span>
            @endif
        </div>
        <x-slot:actions>
            <x-icon-btn icon="timeline" size="sm" :href="route('projects.planning', $project)" show-label>{{ __('Projektplanung') }}</x-icon-btn>
            {{-- Einstieg Feature 064: auch ohne Board sichtbar (Erst-Aktivierung liegt auf der Board-Seite). --}}
            @if (app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.agile_projects')
                && \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::AgileView->value))
                <x-icon-btn icon="view_kanban" size="sm" :href="route('agile.board', $project)" show-label>{{ __('Projektboard') }}</x-icon-btn>
            @endif
            @can('update', $project)
                <x-icon-btn icon="edit" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.edit', $project)"
                            show-label>{{ __('Bearbeiten') }}</x-icon-btn>
            @endcan
            @can('delete', $project)
                <x-action-form :action="route('projects.destroy', $project)" method="DELETE"
                      :confirm="__('Verknüpfungen zu Einträgen werden gelöst.')"
                      :confirm-label="__('Löschen')"
                      data-confirm-title="{{ __('Projekt löschen') }}">
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </x-action-form>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Tabs --}}
    <div x-data="tabs({{ Js::from(request('tab', 'overview')) }})" data-tab-url-sync
         data-tab-allowed="overview,tasks,time,timesheets,diary,recurrence,billing"
         class="flex min-h-0 flex-col gap-4">
        {{-- sticky: bei langen Tab-Inhalten (z. B. große Zeiten-Tabelle) bleibt
             die Tab-Leiste beim Scrollen erreichbar. --}}
        <div role="tablist" class="tabs tabs-box sticky top-0 z-20 w-full shadow-xs sm:w-auto">
            <button role="tab" @click="setTab('overview')" :class="tabClass('overview')" class="tab">
                {{ __('Übersicht') }}
            </button>
            <button role="tab" @click="setTab('tasks')" :class="tabClass('tasks')" class="tab">
                {{ __('Aufgaben') }}
                @php $openCount = ($taskStats->get('open') ?? 0) + ($taskStats->get('in_progress') ?? 0); @endphp
                @if ($openCount > 0)
                    <x-status-badge tone="primary" size="xs" class="ml-1">{{ $openCount }}</x-status-badge>
                @endif
            </button>
            <button role="tab" @click="setTab('time')" :class="tabClass('time')" class="tab">
                {{ __('Zeiterfassung') }}
            </button>
            <button role="tab" @click="setTab('timesheets')" :class="tabClass('timesheets')" class="tab">
                {{ __('Stundenzettel') }}
            </button>
            <button role="tab" @click="setTab('diary')" :class="tabClass('diary')" class="tab">
                {{ __('Aufträge') }}
                @if ($entries->isNotEmpty())
                    <x-status-badge tone="ghost" size="xs" class="ml-1">{{ $entries->count() }}</x-status-badge>
                @endif
            </button>
            <button role="tab" @click="setTab('timeline')" :class="tabClass('timeline')" class="tab">
                <span class="material-symbols-outlined text-base" aria-hidden="true">timeline</span>
                {{ __('Timeline') }}
            </button>
            <button role="tab" @click="setTab('recurrence')" :class="tabClass('recurrence')" class="tab">
                {{ __('Wiederkehr') }}
                @if ($recurrenceRules->isNotEmpty())
                    <x-status-badge tone="ghost" size="xs" class="ml-1">{{ $recurrenceRules->count() }}</x-status-badge>
                @endif
            </button>
            @if (auth()->user()?->canManageBilling())
                <button role="tab" @click="setTab('billing')" :class="tabClass('billing')" class="tab">
                    {{ __('Abrechnung') }}
                    @if ($billingRules->isNotEmpty())
                        <x-status-badge tone="ghost" size="xs" class="ml-1">{{ $billingRules->count() }}</x-status-badge>
                    @endif
                </button>
            @endif
        </div>

        <div x-show="isTab('overview')" x-cloak>
            @include('projects._overview_tab')
        </div>
        <div x-show="isTab('tasks')" x-cloak>
            @include('projects._tasks_tab')
        </div>
        <div x-show="isTab('time')" x-cloak>
            @include('projects._time_tab')
        </div>
        <div x-show="isTab('timesheets')" x-cloak>
            @include('projects._timesheets_tab')
        </div>
        <div x-show="isTab('diary')" x-cloak>
            @include('projects._diary_tab')
        </div>
        <div x-show="isTab('timeline')" x-cloak>
            @include('projects._timeline_tab')
        </div>
        <div x-show="isTab('recurrence')" x-cloak>
            @include('projects._recurrence_tab')
        </div>
        @if (auth()->user()?->canManageBilling())
            <div x-show="isTab('billing')" x-cloak>
                @include('projects._billing_tab')
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
