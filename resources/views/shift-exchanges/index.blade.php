{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  Schichttausch mit Freigabe (Feature 007).
--}}

@extends('layouts.app')
@section('title', __('schedule.exchange.title'))
@section('nav-title', __('schedule.exchange.title'))

@section('content')
    <x-index-page :subtitle="__('schedule.exchange.subtitle')">

        @include('schedule._shift_tabs')

        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif

        {{-- ── Freigabe (nur Teamleitung) ──────────────────────────────── --}}
        @if ($canApprove)
            <x-form-group :legend="__('schedule.exchange.pending_legend')" icon="approval" tone="warning">
                <x-table :zebra="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Schicht') }}</th>
                            <th>{{ __('Von') }}</th>
                            <th>{{ __('An') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Begründung') }}</th>
                            <th class="text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($pendingApproval as $exchange)
                        <tr class="hover">
                            <td class="whitespace-nowrap">
                                {{ $exchange->scheduledShift?->date?->format('d.m.Y') }}
                                <span class="opacity-60">{{ $exchange->scheduledShift?->shiftType?->abbreviation }}</span>
                                @if ($exchange->isSwap())
                                    <span class="badge badge-xs badge-info">{{ __('schedule.exchange.swap') }}</span>
                                @endif
                            </td>
                            <td>{{ $exchange->requester?->name }}</td>
                            <td>{{ $exchange->targetUser?->name ?? __('schedule.exchange.open_target') }}</td>
                            <td><x-status-badge :tone="$exchange->statusTone()" size="sm">{{ $exchange->statusLabel() }}</x-status-badge></td>
                            <td class="text-xs">{{ $exchange->reason }}</td>
                            <td class="text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('schedule.exchanges.approve', $exchange) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-xs">{{ __('Freigeben') }}</button>
                                </form>
                                <form method="POST" action="{{ route('schedule.exchanges.reject', $exchange) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Ablehnen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('schedule.exchange.no_pending')" />
                    @endforelse
                </x-table>
            </x-form-group>
        @endif

        {{-- ── Meine Anträge & an mich gerichtete ─────────────────────── --}}
        <x-form-group :legend="__('schedule.exchange.mine_legend')" icon="swap_horiz" tone="primary">
            <x-table :zebra="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Schicht') }}</th>
                        <th>{{ __('Von') }}</th>
                        <th>{{ __('An') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($mine as $exchange)
                    <tr class="hover">
                        <td class="whitespace-nowrap">
                            {{ $exchange->scheduledShift?->date?->format('d.m.Y') }}
                            <span class="opacity-60">{{ $exchange->scheduledShift?->shiftType?->abbreviation }}</span>
                        </td>
                        <td>{{ $exchange->requester?->name }}</td>
                        <td>{{ $exchange->targetUser?->name ?? __('schedule.exchange.open_target') }}</td>
                        <td><x-status-badge :tone="$exchange->statusTone()" size="sm">{{ $exchange->statusLabel() }}</x-status-badge></td>
                        <td class="text-right whitespace-nowrap">
                            @can('accept', $exchange)
                                @if ($exchange->status->isOpen() && $exchange->status->value === 'requested')
                                    <form method="POST" action="{{ route('schedule.exchanges.accept', $exchange) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-info btn-xs">{{ __('Annehmen') }}</button>
                                    </form>
                                @endif
                            @endcan
                            @can('cancel', $exchange)
                                @if ($exchange->status->isOpen())
                                    <form method="POST" action="{{ route('schedule.exchanges.cancel', $exchange) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('Zurückziehen') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('schedule.exchange.no_mine')" />
                @endforelse
            </x-table>
        </x-form-group>

    </x-index-page>
@endsection
