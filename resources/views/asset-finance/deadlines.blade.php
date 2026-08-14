{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : deadlines.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Leasing-Fristen'))
@section('nav-title', __('Fristenkalender'))

@section('content')
<x-index-page :subtitle="__('Kündigungs-, Verlängerungs-, Kaufoptions-, Rückgabe- und Prüffristen aller Verträge.')">
    <x-filter-bar :action="route('asset-finance.deadlines.index')" :reset="route('asset-finance.deadlines.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Offen (Standard)') }}</option>
            @foreach (\App\Models\AssetFinance\AssetFinanceDeadline::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __("values.$status") }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Fällig') }}</th>
                    <th>{{ __('Art') }}</th>
                    <th>{{ __('Vertrag') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($deadlines as $deadline)
                <tr>
                    <td>
                        @if ($deadline->status === 'open' && $deadline->isDueForWarning())
                            <span class="text-error font-medium">{{ $deadline->due_on->fdate() }}</span>
                        @else
                            {{ $deadline->due_on->fdate() }}
                        @endif
                    </td>
                    <td>{{ $deadline->kind->label() }}</td>
                    <td>
                        @if ($deadline->contract !== null)
                            <a class="link font-mono" href="{{ route('asset-finance.show', $deadline->contract) }}">{{ $deadline->contract->number }}</a>
                            <span class="text-sm text-base-content/60">{{ $deadline->contract->partner_name }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $deadline->responsible->name ?? '—' }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$deadline->status}") }}</x-status-badge></td>
                    <td class="text-right">
                        @if ($deadline->status === 'open' && $deadline->contract !== null)
                            @can('update', $deadline->contract)
                                <form method="POST" action="{{ route('asset-finance.deadlines.complete', $deadline) }}" class="inline">@csrf
                                    <button type="submit" class="btn btn-xs">{{ __('Erledigt') }}</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('Keine Fristen — Fristen entstehen an der Leasingakte.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$deadlines" standing />
</x-index-page>
@endsection
