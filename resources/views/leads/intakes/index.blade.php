{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Aufnahmen (Feature 200, MVP-1641): neueste Fassung je Akte mit Stufe. Variablen: $intakes (Paginator), $counts (Stufe → Anzahl), $stage, $status, $sort, $dir, $enabled, $canManage. --}}

@extends('layouts.app')

@section('title', __('lead_intake.index_title'))
@section('nav-title', __('lead_intake.index_title'))
@include('partials.page-fill')

@php
    use App\Enums\Sales\LeadIntakeStage;
    $linkParams = array_filter(['stage' => $stage?->value, 'status' => $status !== 'open' ? $status : null], static fn ($value) => $value !== null);
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('lead_intake.index_subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="tune" tone="ghost" size="sm" data-entry-modal-trigger
                        :href="route('leads.intakes.settings.edit') . '?dialog=1'" show-label>{{ __('lead_intake.settings.title') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($enabled === [])
        <div role="note" class="alert alert-info text-sm">
            <x-icon name="info" />
            <span>{{ __('lead_intake.hint.no_packages') }}</span>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-5">
        @foreach (LeadIntakeStage::pipeline() as $step)
            <x-kpi-tile :label="$step->label()" :value="(int) ($counts[$step->value] ?? 0)"
                        :href="route('leads.intakes.index', ['stage' => $step->value])" :active="$stage === $step" />
        @endforeach
    </div>

    <x-filter-bar :action="route('leads.intakes.index')" :reset="route('leads.intakes.index')">
        <select name="status" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('lead_intake.filter.status') }}">
            @foreach (\App\Http\Controllers\Sales\LeadIntakeController::STATUS_FILTERS as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ __('lead_intake.filter.status_' . $option) }}</option>
            @endforeach
        </select>
        <select name="stage" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('lead_intake.filter.stage') }}">
            <option value="">{{ __('lead_intake.filter.all_stages') }}</option>
            @foreach (LeadIntakeStage::cases() as $option)
                <option value="{{ $option->value }}" @selected($stage === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($intakes->isEmpty())
        <x-empty-state framed icon="assignment_ind" :title="__('lead_intake.empty.title')" :message="__('lead_intake.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('lead_intake.column.lead') }}</th>
                    <x-sort-th column="stage" :route="route('leads.intakes.index')" :params="$linkParams" :sort="$sort" :dir="$dir">{{ __('lead_intake.column.stage') }}</x-sort-th>
                    <th>{{ __('lead_intake.column.packages') }}</th>
                    <th>{{ __('lead_intake.column.decision') }}</th>
                    <x-sort-th column="refresh_due_on" :route="route('leads.intakes.index')" :params="$linkParams" :sort="$sort" :dir="$dir">{{ __('lead_intake.column.refresh_due_on') }}</x-sort-th>
                    <x-sort-th column="updated_at" :route="route('leads.intakes.index')" :params="$linkParams" :sort="$sort" :dir="$dir" default="updated_at">{{ __('lead_intake.column.updated_at') }}</x-sort-th>
                </tr>
            </x-slot:head>
            @foreach ($intakes as $intake)
                <tr class="hover" data-intake-row="{{ $intake->sqid }}">
                    <td>
                        @if ($intake->lead !== null)
                            <a class="link link-hover font-medium" href="{{ route('leads.intakes.show', [$intake->lead, $intake]) }}">{{ $intake->lead->displayName() }}</a>
                        @else
                            —
                        @endif
                        @if ($intake->revision > 1)
                            <div class="text-xs text-muted">{{ __('lead_intake.revision', ['revision' => $intake->revision]) }}</div>
                        @endif
                    </td>
                    <td><x-status-badge size="sm" :tone="$intake->stage->tone()">{{ $intake->stage->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ implode(', ', array_map(static fn ($p): string => $p->label, $intake->packageSnapshots())) ?: '—' }}</td>
                    <td class="text-sm">
                        @if ($intake->decision !== null)
                            <x-status-badge size="sm" :tone="$intake->decision->tone()">{{ $intake->decision->label() }}</x-status-badge>
                            <div class="text-xs text-muted">{{ $intake->decider?->name ?? '—' }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="whitespace-nowrap tabular-nums">{{ $intake->refresh_due_on?->fdate() ?? '—' }}</td>
                    <td class="whitespace-nowrap tabular-nums">{{ $intake->updated_at?->fdate() ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$intakes" standing />
    @endif
</x-index-page>
@endsection
