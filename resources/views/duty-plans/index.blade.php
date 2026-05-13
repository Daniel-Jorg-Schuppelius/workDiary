@extends('layouts.app')
@section('title', __('Dienstpläne'))
@section('nav-title', __('Dienstpläne'))
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Dienstpläne') }}</h1>
        <div class="flex items-center gap-2">
            <form method="GET" class="contents">
                <select name="period" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="">{{ __('Alle Zeiträume') }}</option>
                    @foreach (\App\Models\DutyPlan::$periodTypes as $pt)
                        <option value="{{ $pt }}" @selected($period === $pt)>{{ __('duty_plan.period.' . $pt) }}</option>
                    @endforeach
                </select>
                <select name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="">{{ __('Alle Status') }}</option>
                    @foreach (\App\Models\DutyPlan::$statuses as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ __('duty_plan.status.' . $st) }}</option>
                    @endforeach
                </select>
            </form>
            @can('create', \App\Models\DutyPlan::class)
                <a href="{{ route('duty-plans.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    + {{ __('Dienstplan anlegen') }}
                </a>
            @endcan
        </div>
    </div>

    <x-table>
        <thead>
            <tr>
                <th>{{ __('Titel') }}</th>
                <th>{{ __('Zeitraum') }}</th>
                <th>{{ __('Typ') }}</th>
                <th class="text-center">{{ __('Schichten') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plans as $plan)
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('duty-plans.show', $plan) }}" class="link link-hover">{{ $plan->title }}</a>
                    </td>
                    <td class="text-sm text-base-content/70">
                        {{ $plan->from_date->format('d.m.Y') }} – {{ $plan->to_date->format('d.m.Y') }}
                    </td>
                    <td>
                        <span class="badge badge-sm badge-outline">{{ __('duty_plan.period.' . $plan->period_type) }}</span>
                    </td>
                    <td class="text-center">{{ $plan->shifts_count }}</td>
                    <td>
                        @if ($plan->isPublished())
                            <span class="badge badge-success badge-sm">{{ __('duty_plan.status.published') }}</span>
                        @else
                            <span class="badge badge-ghost badge-sm">{{ __('duty_plan.status.draft') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('duty-plans.show', $plan) }}" class="btn btn-ghost btn-xs">{{ __('Ansehen') }}</a>
                            @can('update', $plan)
                            <a href="{{ route('duty-plans.edit', $plan) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            @endcan
                            @can('delete', $plan)
                            <form method="POST" action="{{ route('duty-plans.destroy', $plan) }}"
                                  onsubmit="return confirm('{{ __('Dienstplan wirklich löschen?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-base-content/50">{{ __('Noch keine Dienstpläne vorhanden.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
    <div>{{ $plans->links() }}</div>
</div>
@endsection
