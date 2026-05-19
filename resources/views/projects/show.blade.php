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
                <span>{{ __('Start') }}: {{ $project->starts_on->format('d.m.Y') }}</span>
            @endif
            @if ($project->ends_on)
                <span>{{ __('Ende') }}: {{ $project->ends_on->format('d.m.Y') }}</span>
            @endif
        </div>
        <x-slot:actions>
            @can('update', $project)
                <x-icon-btn icon="edit" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.edit', $project)"
                            show-label>{{ __('Bearbeiten') }}</x-icon-btn>
            @endcan
            @can('delete', $project)
                <form method="POST" action="{{ route('projects.destroy', $project) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-title="{{ __('Projekt löschen') }}"
                      data-confirm-message="{{ __('Verknüpfungen zu Einträgen werden gelöst.') }}"
                      data-confirm-label="{{ __('Löschen') }}">
                    @csrf @method('DELETE')
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Tabs --}}
    <div x-data="projectTabs({{ Js::from(request('tab', 'overview')) }})" class="flex min-h-0 flex-col gap-4">
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
</x-page-shell>

<script>
    function projectTabs(initial) {
        const allowed = ['overview', 'tasks', 'time', 'timesheets', 'diary', 'billing'];
        const fromQuery = new URLSearchParams(window.location.search).get('tab');
        const fromHash = window.location.hash.replace('#', '');
        const start = allowed.includes(fromQuery) ? fromQuery
                    : allowed.includes(initial) ? initial
                    : allowed.includes(fromHash) ? fromHash
                    : 'overview';
        return {
            tab: start,
            setTab(name) {
                this.tab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                url.hash = '';
                history.replaceState(null, '', url.toString());
            }
        };
    }
</script>
@endsection
