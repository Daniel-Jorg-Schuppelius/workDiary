{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verleih'))
@section('nav-title', __('Verleih'))

@section('content')
<x-index-page :subtitle="__('Geräte- und Maschinenverleih mit Verfügbarkeit, Übergabe, Rücknahme, Kaution und Abrechnung.')">
    <x-slot:actions>
        @can('create', \App\Models\Rental\RentalCase::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('rental.create')"
                        show-label>{{ __('Neue Verleihakte') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="calendar_month" size="sm" :href="route('rental.calendar')" show-label>{{ __('Kalender') }}</x-icon-btn>
        <x-icon-btn icon="query_stats" size="sm" :href="route('rental.reports.index')" show-label>{{ __('Verleihbericht') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('Offene Verleihvorgänge')" :value="$openCount" />
        <x-kpi-tile :label="__('Überfällige Rückgaben')" :value="$overdueCount" />
    </div>

    <x-filter-bar :action="route('rental.index')" :reset="route('rental.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Rental\RentalCaseStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="customer_id" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Kunde') }}">
            <option value="">{{ __('Alle Kunden') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected(request('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
        <label class="label cursor-pointer gap-2 shrink-0">
            <input type="checkbox" name="overdue" value="1" class="checkbox checkbox-sm" @checked(request()->filled('overdue'))>
            <span class="label-text text-sm">{{ __('nur überfällige') }}</span>
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
                    <th>{{ __('Leihobjekte') }}</th>
                    <th>{{ __('Zeitraum') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($cases as $case)
                <tr>
                    <td><a href="{{ route('rental.show', $case) }}" class="link font-mono">{{ $case->number }}</a></td>
                    <td>{{ $case->customer->name ?? '—' }}</td>
                    <td>{{ $case->caseAssets->map(fn($ca) => $ca->asset?->name)->filter()->implode(', ') ?: '—' }}</td>
                    <td>
                        @if ($case->status === \App\Enums\Rental\RentalCaseStatus::Overdue)
                            <span class="text-error font-medium">{{ $case->starts_at->fdate() }} – {{ $case->ends_at->fdatetime() }}</span>
                        @else
                            {{ $case->starts_at->fdate() }} – {{ $case->ends_at->fdate() }}
                        @endif
                    </td>
                    <td><x-status-badge size="md" outline>{{ $case->status->label() }}</x-status-badge></td>
                    <td>{{ $case->responsible->name ?? '—' }}</td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('rental.show', $case)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">forklift</span>' :colspan="7" :title="__('Keine Verleihakten — leihfähige Geräte im Gerätepool pflegen und die erste Akte anlegen.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
