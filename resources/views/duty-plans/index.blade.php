@extends('layouts.app')
@section('title', __('Dienstpläne'))
@section('nav-title', __('Dienstpläne'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('Dienstpläne des Mandanten verwalten.')">
    <x-slot:actions>
        @can('create', \App\Models\DutyPlan::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('duty-plans.create')"
                        show-label>{{ __('Dienstplan anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('duty-plans.index')" :reset="route('duty-plans.index')">
        <x-filter-field :label="__('Status')" for="dp-status">
            <select id="dp-status" name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach (\App\Enums\Shift\DutyPlanStatus::values() as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ __('duty_plan.status.' . $st) }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('duty-plans.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'desc'"
             :sort-params="['status' => $status]">
        <x-slot:head>
            <tr>
                <x-table.th sort="name">{{ __('Titel') }}</x-table.th>
                <x-table.th sort="from_date" default>{{ __('Zeitraum') }}</x-table.th>
                <x-table.th sort="period_type">{{ __('Typ') }}</x-table.th>
                <x-table.th sort="shifts" align="center">{{ __('Schichten') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
            @forelse ($plans as $plan)
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('duty-plans.show', $plan) }}" class="link link-hover">{{ $plan->title }}</a>
                    </td>
                    <td class="text-sm text-base-content/70">
                        {{ $plan->from_date->fdate() }} – {{ $plan->to_date->fdate() }}
                    </td>
                    <td>
                        <x-status-badge size="sm" outline>{{ $plan->period_type->label() }}</x-status-badge>
                    </td>
                    <td class="text-center">{{ $plan->shifts_count }}</td>
                    <td>
                        @if ($plan->isPublished())
                            <x-status-badge tone="success" size="sm">{{ __('duty_plan.status.published') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('duty_plan.status.draft') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility"
                                        :href="route('duty-plans.show', $plan)"
                                        :label="__('Ansehen')" />
                            @can('update', $plan)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('duty-plans.edit', $plan)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $plan)
                                <form method="POST" action="{{ route('duty-plans.destroy', $plan) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Dienstplan wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>' :colspan="6" :title="__('Noch keine Dienstpläne vorhanden')" compact />
            @endforelse
    </x-table>
    <x-pagination :paginator="$plans" />
</x-index-page>
@endsection
