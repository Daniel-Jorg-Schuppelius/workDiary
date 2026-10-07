{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundeneingänge (MVP-1074): offene immer, abgeschlossene im globalen Zeitraum — kein eigener Datumsfilter. --}}
@extends('layouts.app')

@section('title', __('customer_intake.title'))
@section('nav-title', __('customer_intake.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('customer_intake.subtitle')">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('customer_intake.kpi.open')" :value="$openCount" />
        <x-kpi-tile :label="__('customer_intake.kpi.awaiting')" :value="$awaitingCount" />
    </div>

    <x-filter-bar :action="route('customer-intakes.index')" :reset="route('customer-intakes.index')">
        <select name="status" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Customer\IntakeStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="kind" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('customer_intake.field.kind') }}">
            <option value="">{{ __('customer_intake.filter.all_kinds') }}</option>
            @foreach (\App\Enums\Customer\IntakeKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(request('kind') === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </select>
        <x-filter-toggle name="mine" :label="__('customer_intake.filter.mine')" :checked="request()->boolean('mine')" />
    </x-filter-bar>

    <x-table :zebra="true" scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Nummer') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th>{{ __('customer_intake.field.kind') }}</th>
                <th>{{ __('customer_intake.field.subject') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('customer_intake.field.desired_date') }}</th>
                <th>{{ __('customer_intake.field.assignee') }}</th>
                <th>{{ __('customer_intake.field.received_at') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($intakes as $intake)
            <tr>
                <td><a href="{{ route('customer-intakes.show', $intake) }}" class="link font-mono">{{ $intake->number }}</a></td>
                <td>{{ $intake->customer?->name ?? '—' }}</td>
                <td>{{ $intake->kind->label() }}</td>
                <td class="max-w-80 truncate" title="{{ $intake->subject }}">{{ $intake->subject }}</td>
                <td><x-status-badge size="md" outline :tone="$intake->status->tone()">{{ $intake->status->label() }}</x-status-badge></td>
                <td class="whitespace-nowrap">{{ $intake->desired_date?->fdate() ?? '—' }}</td>
                <td>{{ $intake->assignee?->name ?? '—' }}</td>
                <td class="whitespace-nowrap">{{ $intake->created_at?->fdatetime() }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" :href="route('customer-intakes.show', $intake)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon="move_to_inbox" :colspan="9" :title="__('customer_intake.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$intakes" standing />
</x-index-page>
@endsection
