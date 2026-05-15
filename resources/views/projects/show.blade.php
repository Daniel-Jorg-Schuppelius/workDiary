@extends('layouts.app')
@section('title', $project->name . ' — ' . __('Projekt'))
@section('nav-title', $project->name)

@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    {{-- Projekt-Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="flex min-w-0 items-start gap-3">
            <span class="mt-1 inline-block h-4 w-4 shrink-0 rounded-full" style="background:{{ $project->color ?: '#94a3b8' }}"></span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate font-['Space_Grotesk'] text-lg font-semibold">{{ $project->name }}</h1>
                    <span class="badge badge-sm badge-{{ $project->statusTone() }}">{{ $project->statusLabel() }}</span>
                </div>
                @if ($project->description)
                    <p class="mt-1 max-w-prose text-sm text-base-content/70">{{ $project->description }}</p>
                @endif
                <div class="mt-2 flex flex-wrap gap-3 text-xs text-base-content/60">
                    @if ($project->starts_on)
                        <span>{{ __('Start') }}: {{ $project->starts_on->format('d.m.Y') }}</span>
                    @endif
                    @if ($project->ends_on)
                        <span>{{ __('Ende') }}: {{ $project->ends_on->format('d.m.Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" data-entry-modal-trigger class="btn btn-sm btn-ghost">{{ __('Bearbeiten') }}</a>
            @endcan
            @can('delete', $project)
                <form method="POST" action="{{ route('projects.destroy', $project) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-title="{{ __('Projekt löschen') }}"
                      data-confirm-message="{{ __('Verknüpfungen zu Einträgen werden gelöst.') }}"
                      data-confirm-label="{{ __('Löschen') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-ghost text-error">{{ __('Löschen') }}</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="projectTabs()" class="flex min-h-0 flex-col gap-4">
        <div role="tablist" class="tabs tabs-box w-full sm:w-auto">
            <button role="tab" @click="setTab('overview')" :class="{ 'tab-active': tab === 'overview' }" class="tab">
                {{ __('Übersicht') }}
            </button>
            <button role="tab" @click="setTab('tasks')" :class="{ 'tab-active': tab === 'tasks' }" class="tab">
                {{ __('Aufgaben') }}
                @php $openCount = ($taskStats->get('open') ?? 0) + ($taskStats->get('in_progress') ?? 0); @endphp
                @if ($openCount > 0)
                    <span class="badge badge-xs badge-primary ml-1">{{ $openCount }}</span>
                @endif
            </button>
            <button role="tab" @click="setTab('time')" :class="{ 'tab-active': tab === 'time' }" class="tab">
                {{ __('Zeiterfassung') }}
            </button>
            <button role="tab" @click="setTab('timesheets')" :class="{ 'tab-active': tab === 'timesheets' }" class="tab">
                {{ __('Stundenzettel') }}
            </button>
            <button role="tab" @click="setTab('diary')" :class="{ 'tab-active': tab === 'diary' }" class="tab">
                {{ __('Tagebuch') }}
            </button>
            @if (auth()->user()?->canManageBilling())
                <button role="tab" @click="setTab('billing')" :class="{ 'tab-active': tab === 'billing' }" class="tab">
                    {{ __('Abrechnung') }}
                </button>
            @endif
        </div>

        <div x-show="tab === 'overview'" x-cloak>
            @include('projects._overview_tab')
        </div>
        <div x-show="tab === 'tasks'" x-cloak>
            @include('projects._tasks_tab')
        </div>
        <div x-show="tab === 'time'" x-cloak>
            @include('projects._time_tab')
        </div>
        <div x-show="tab === 'timesheets'" x-cloak>
            @include('projects._timesheets_tab')
        </div>
        <div x-show="tab === 'diary'" x-cloak>
            @include('projects._diary_tab')
        </div>
        @if (auth()->user()?->canManageBilling())
            <div x-show="tab === 'billing'" x-cloak>
                @include('projects._billing_tab')
            </div>
        @endif
    </div>
</div>

<script>
    function projectTabs() {
        const allowed = ['overview', 'tasks', 'time', 'timesheets', 'diary', 'billing'];
        const hash = window.location.hash.replace('#', '');
        return {
            tab: allowed.includes(hash) ? hash : 'overview',
            setTab(name) {
                this.tab = name;
                history.replaceState(null, '', '#' + name);
            }
        };
    }
</script>
@endsection
