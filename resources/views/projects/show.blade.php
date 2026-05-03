@extends('layouts.app')
@section('title', $project->name . ' — ' . __('Projekt'))
@section('nav-title', $project->name)

@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
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
            <a href="{{ route('diary.index', ['project' => $project->id]) }}" class="btn btn-sm">{{ __('Im Tagebuch öffnen') }}</a>
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

    {{-- Letzte Einträge --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-sm">
        <header class="border-b border-base-300 px-4 py-3 font-['Space_Grotesk'] text-sm font-semibold">{{ __('Letzte Einträge') }}</header>
        <ul class="divide-y divide-base-300">
            @forelse ($entries as $entry)
                <li class="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                    <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                            <span>{{ optional($entry->start_at)->format('d.m.Y H:i') }}</span>
                            <span>· {{ $entry->user->name ?? '—' }}</span>
                            <span class="badge badge-xs badge-{{ $entry->statusTone() === 'open' ? 'neutral' : ($entry->statusTone() === 'alert' ? 'error' : ($entry->statusTone() === 'progress' ? 'info' : 'success')) }}">{{ $entry->statusLabel() }}</span>
                        </div>
                        <div class="line-clamp-2 text-sm">{{ \Illuminate\Support\Str::limit($entry->content, 200) }}</div>
                    </a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-base-content/60">{{ __('Keine Einträge zugeordnet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
