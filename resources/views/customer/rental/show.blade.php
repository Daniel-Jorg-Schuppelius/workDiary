{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', $case->number)

@section('content')
<div class="space-y-4">
    <x-page-toolbar :title="__('Verleihvorgang :number', ['number' => $case->number])" :subtitle="$case->starts_at->fdatetime() . ' – ' . $case->ends_at->fdatetime()"
                    :back="route('customer.rentals.index')" :back-label="__('Meine Leihgeräte')">
        <x-slot:badges>
            <x-status-badge tone="plain" size="sm" outline>{{ $case->status->label() }}</x-status-badge>
        </x-slot:badges>
    </x-page-toolbar>

    <x-card :title="__('Geräte')" icon="handyman" padding="p-0">
    <x-table bare>
        <x-slot:head>
            <tr><x-table.th>{{ __('Gerät') }}</x-table.th><x-table.th>{{ __('Status') }}</x-table.th></tr>
        </x-slot:head>
        @foreach ($case->caseAssets as $caseAsset)
            <tr class="hover">
                <td>{{ $caseAsset->asset->name ?? '—' }}</td>
                <td><x-status-badge tone="plain" size="sm" outline>{{ $caseAsset->status->label() }}</x-status-badge></td>
            </tr>
        @endforeach
    </x-table>
    </x-card>

    <x-card :title="__('Übergaben')" icon="swap_horiz" padding="p-0">
    <x-table bare>
        <x-slot:head>
            <tr><x-table.th>{{ __('Zeitpunkt') }}</x-table.th><x-table.th>{{ __('Zubehör') }}</x-table.th><x-table.th>{{ __('Bestätigung') }}</x-table.th></tr>
        </x-slot:head>
                @forelse ($case->handoverReports as $report)
                    <tr>
                        <td>{{ $report->reported_at->fdatetime() }}</td>
                        <td>{{ $report->accessoryItems->map(fn($i) => $i->label)->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($report->portal_confirmed_at !== null)
                                {{ __('bestätigt am :date', ['date' => $report->portal_confirmed_at->fdatetime()]) }}
                            @else
                                <form method="POST" action="{{ route('customer.rentals.confirm', [$case, $report]) }}">
                                    @csrf
                                    <x-button type="submit" size="xs">{{ __('Übergabe bestätigen') }}</x-button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" icon="swap_horiz" :title="__('Noch keine Übergabe.')" compact />
                @endforelse
    </x-table>
    </x-card>
</div>
@endsection
