@extends('layouts.app')
@section('title', __('Projekte') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Projekte'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $statusOptions = ['' => __('Alle')] + \App\Enums\Project\ProjectStatus::options();
@endphp
<x-index-page overflow="clip" :subtitle="__('Projekte und ihre Zuordnungen verwalten.')">
    <x-slot:actions>
        @can('create', App\Models\Project::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.create')"
                        show-label>{{ __('Projekt anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('projects.index')" method="GET" :reset="route('projects.index')">
        <div class="join">
            @foreach ($statusOptions as $value => $label)
                <a href="{{ route('projects.index', $value === '' ? [] : ['status' => $value]) }}"
                   class="join-item btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </x-filter-bar>

    @if ($rows->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">folder_open</span>' :title="__('Noch keine Projekte angelegt')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Projekt') }}</th>
                        <th>{{ __('Kunde') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Offen') }}</th>
                        <th class="text-right">{{ __('Problem') }}</th>
                        <th class="text-right">{{ __('Bestätigt') }}</th>
                        <th class="text-right">{{ __('Erledigt') }}</th>
                        <th class="text-right">{{ __('Mitarb.') }}</th>
                        <th>{{ __('Letzte Aktivität') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $row)
                            @php
                                $project = $row['project'];
                                $depth = $row['depth'];
                                $isOrphan = $depth === 0 && $project->parent_id !== null;
                                $rowsForProject = $stats->get($project->id, collect());
                                $byStatus = $rowsForProject->keyBy('status');
                                $cOpen = (int) ($byStatus->get(2)->cnt ?? 0);
                                $cAlert = (int) ($byStatus->get(3)->cnt ?? 0);
                                $cProgress = (int) ($byStatus->get(1)->cnt ?? 0);
                                $cDone = (int) ($byStatus->get(-1)->cnt ?? 0);
                                $last = $lastEntries->get($project->id);
                                $users = (int) ($userCounts->get($project->id) ?? 0);
                                $indentClass = ['', 'pl-6', 'pl-12'][$depth] ?? 'pl-12';
                            @endphp
                            <tr class="hover">
                                <td>
                                    <div class="flex items-center gap-2 {{ $indentClass }}">
                                        @if ($depth > 0)
                                            <x-icon name="subdirectory_arrow_right" class="text-base-content/40" />
                                        @endif
                                        <span class="inline-block h-3 w-3 shrink-0 rounded-full"
                                              style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                                        <a href="{{ route('projects.show', $project) }}"
                                           class="font-['Space_Grotesk'] font-semibold hover:text-primary">{{ $project->name }}</a>
                                        @if ($isOrphan && $project->parent)
                                            <span class="badge badge-xs badge-ghost"
                                                  title="{{ __('Sub-Projekt von :name', ['name' => $project->parent->name]) }}">
                                                ↳ {{ $project->parent->name }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($project->description)
                                        <div class="line-clamp-1 text-xs text-base-content/60 {{ $indentClass }} mt-0.5">
                                            {{ $project->description }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-sm text-base-content/80">{{ $project->customer?->name ?? '—' }}</td>
                                <td>
                                    <x-status-badge size="sm" :tone="$project->statusTone()">{{ $project->statusLabel() }}</x-status-badge>
                                </td>
                                <td class="text-right tabular-nums">{{ $cOpen }}</td>
                                <td class="text-right tabular-nums {{ $cAlert > 0 ? 'text-error font-semibold' : '' }}">{{ $cAlert }}</td>
                                <td class="text-right tabular-nums">{{ $cProgress }}</td>
                                <td class="text-right tabular-nums text-base-content/60">{{ $cDone }}</td>
                                <td class="text-right tabular-nums">{{ $users }}</td>
                                <td class="text-xs text-base-content/60">
                                    @if ($last)
                                        {{ \Carbon\CarbonImmutable::parse($last)->diffForHumans() }}
                                    @else
                                        {{ __('keine Aktivität') }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-icon-btn icon="open_in_new"
                                                :href="route('projects.show', $project)"
                                                :label="__('Öffnen')" />
                                </td>
                            </tr>
                        @endforeach
            </x-table>
        </x-card>

        <x-pagination :paginator="$projects" />
    @endif
</x-index-page>
@endsection
