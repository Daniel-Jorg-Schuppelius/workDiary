@extends('layouts.app')
@section('title', __('Projekte') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Projekte'))

@section('content')
@php
    $statusOptions = [
        '' => __('Alle'),
        \App\Models\Project::STATUS_ACTIVE => __('Aktiv'),
        \App\Models\Project::STATUS_PAUSED => __('Pausiert'),
        \App\Models\Project::STATUS_ARCHIVED => __('Archiviert'),
    ];
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="join">
                @foreach ($statusOptions as $value => $label)
                    <a href="{{ route('projects.index', $value === '' ? [] : ['status' => $value]) }}"
                       class="join-item btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
                @endforeach
            </div>
            <x-slot:actions>
                @can('create', App\Models\Project::class)
                    <a href="{{ route('projects.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                        <x-icon name="add" />
                        <span>{{ __('Projekt') }}</span>
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($projects->isEmpty())
        <x-card>
            <x-empty-state :title="__('Noch keine Projekte angelegt')" />
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @foreach ($projects as $project)
                @php
                    $rows = $stats->get($project->id, collect());
                    $byStatus = $rows->keyBy('status');
                    $cOpen = (int) ($byStatus->get(2)->cnt ?? 0);
                    $cAlert = (int) ($byStatus->get(3)->cnt ?? 0);
                    $cProgress = (int) ($byStatus->get(1)->cnt ?? 0);
                    $cDone = (int) ($byStatus->get(-1)->cnt ?? 0);
                    $total = $cOpen + $cAlert + $cProgress + $cDone;
                    $last = $lastEntries->get($project->id);
                    $users = (int) ($userCounts->get($project->id) ?? 0);
                @endphp
                <a href="{{ route('projects.show', $project) }}"
                   class="group flex flex-col gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs transition hover:border-primary hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-block h-3 w-3 shrink-0 rounded-full"
                                  style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                            <h2 class="truncate font-['Space_Grotesk'] text-base font-semibold group-hover:text-primary">{{ $project->name }}</h2>
                        </div>
                        <span class="badge badge-sm badge-{{ $project->statusTone() }}">{{ $project->statusLabel() }}</span>
                    </div>

                    @if ($project->description)
                        <p class="line-clamp-2 text-xs text-base-content/70">{{ $project->description }}</p>
                    @endif

                    @if ($project->parent)
                        <div class="text-xs text-base-content/60">
                            ↳ {{ __('Sub-Projekt von') }} <span class="font-medium">{{ $project->parent->name }}</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div class="rounded border border-base-300 bg-base-200/40 p-1.5">
                            <div class="font-semibold">{{ $cOpen }}</div>
                            <div class="text-base-content/60">{{ __('Offen') }}</div>
                        </div>
                        <div class="rounded border border-base-300 bg-base-200/40 p-1.5">
                            <div class="font-semibold text-error">{{ $cAlert }}</div>
                            <div class="text-base-content/60">{{ __('Problem') }}</div>
                        </div>
                        <div class="rounded border border-base-300 bg-base-200/40 p-1.5">
                            <div class="font-semibold">{{ $cProgress }}</div>
                            <div class="text-base-content/60">{{ __('Bestätigt') }}</div>
                        </div>
                        <div class="rounded border border-base-300 bg-base-200/40 p-1.5">
                            <div class="font-semibold">{{ $cDone }}</div>
                            <div class="text-base-content/60">{{ __('Erledigt') }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-base-content/60">
                        <span>{{ trans_choice(':n Eintrag|:n Einträge', $total, ['n' => $total]) }} · {{ trans_choice(':n Mitarbeitende|:n Mitarbeitende', $users, ['n' => $users]) }}</span>
                        <span>
                            @if ($last)
                                {{ \Carbon\CarbonImmutable::parse($last)->diffForHumans() }}
                            @else
                                {{ __('keine Aktivität') }}
                            @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-page-shell>
@endsection
