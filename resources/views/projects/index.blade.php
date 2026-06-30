{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Projekte') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Projekte'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $statusOptions = ['' => __('Alle')] + \App\Enums\Project\ProjectStatus::options();
@endphp
<x-index-page overflow="clip" :subtitle="__('Projekte und ihre Zuordnungen verwalten.')">
    <x-slot:actions>
        @if (auth()->user()?->canManageBilling())
            <x-icon-btn icon="merge" size="sm"
                        :href="route('projects.duplicates.index')"
                        show-label>{{ __('Projekt-Abgleich') }}</x-icon-btn>
        @endif
        @can('create', App\Models\Project::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('projects.create')"
                        show-label>{{ __('Projekt anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('projects.index')" method="GET" :reset="route('projects.index')">
        <input type="text" name="q" value="{{ $search ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <div class="join">
            @foreach ($statusOptions as $value => $label)
                <a href="{{ route('projects.index', array_filter(['status' => $value === '' ? null : $value, 'q' => $search ?: null])) }}"
                   class="join-item btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </x-filter-bar>

    @if ($rows->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">folder_open</span>' :title="__('Noch keine Projekte angelegt')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true" table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string" default="asc">{{ __('Projekt') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Offen') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Problem') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Bestätigt') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erledigt') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Mitarb.') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('Letzte Aktivität') }}</x-table.th>
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
                                <td class="text-sm text-base-content/80">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $project->customer?->name ?? '—' }}</span>
                                        @if ($project->foreignCustomer)
                                            <span class="badge badge-sm badge-outline gap-1"
                                                  title="{{ __('Fremdkunde') }}">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">handshake</span>
                                                {{ $project->foreignCustomer->name }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <x-status-badge size="sm" :tone="$project->statusTone()">{{ $project->statusLabel() }}</x-status-badge>
                                </td>
                                <td class="text-right tabular-nums">{{ $cOpen }}</td>
                                <td class="text-right tabular-nums {{ $cAlert > 0 ? 'text-error font-semibold' : '' }}">{{ $cAlert }}</td>
                                <td class="text-right tabular-nums">{{ $cProgress }}</td>
                                <td class="text-right tabular-nums text-base-content/60">{{ $cDone }}</td>
                                <td class="text-right tabular-nums">{{ $users }}</td>
                                <td class="text-xs text-base-content/60" data-sort-value="{{ $last ? \Carbon\CarbonImmutable::parse($last)->format('Y-m-d H:i:s') : '' }}">
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

        <x-pagination :paginator="$projects" standing />
    @endif
</x-index-page>
@endsection
