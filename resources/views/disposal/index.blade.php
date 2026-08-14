{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('disposal.index.title'))
@section('nav-title', __('disposal.index.title'))

@section('content')
<x-index-page :subtitle="__('disposal.index.subtitle')">
    <x-slot:actions>
        @can('create', \App\Models\Disposal\DisposalJob::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('disposal.create')"
                        show-label>{{ __('disposal.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('disposal.index.kpi.open')" :value="$openCount" />
        <x-kpi-tile :label="__('disposal.index.kpi.hazardous_open')" :value="$hazardousOpenCount" tone="warning" />
        <x-kpi-tile :label="__('disposal.index.kpi.completed_year')" :value="$completedCount" tone="success" />
    </div>

    <x-filter-bar :action="route('disposal.index')" :reset="route('disposal.index')">
        <select name="status" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Disposal\DisposalJobStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="customer_id" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Kunde') }}">
            <option value="">{{ __('Alle Kunden') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected(request('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
        <label class="label cursor-pointer gap-2 shrink-0">
            <input type="checkbox" name="hazardous" value="1" class="checkbox checkbox-sm" @checked(request()->boolean('hazardous'))>
            <span class="label-text text-sm">{{ __('disposal.index.filter.hazardous_only') }}</span>
        </label>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('disposal.field.site') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('disposal.index.col.items') }}</th>
                    <th>{{ __('disposal.index.col.picked_up') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($jobs as $job)
                <tr>
                    <td><a href="{{ route('disposal.show', $job) }}" class="link font-mono">{{ $job->number }}</a></td>
                    <td>{{ $job->customer->name ?? '—' }}</td>
                    <td>{{ $job->site?->name ?? '—' }}</td>
                    <td><x-status-badge size="md" :tone="$job->status->tone()" outline>{{ $job->status->label() }}</x-status-badge></td>
                    <td class="text-right font-mono">{{ $job->items_count }}</td>
                    <td>{{ $job->picked_up_on?->fdate() ?? '—' }}</td>
                    <td>{{ $job->responsible->name ?? '—' }}</td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('disposal.show', $job)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon="recycling" :colspan="8" :title="__('disposal.index.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$jobs" standing />
</x-index-page>
@endsection
