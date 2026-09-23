{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragsläufe (Feature 159, MVP-850): Entwurf mit Vorschau, Freigabe erzeugt Forderungen; Übergabe bei externer Rechnungshoheit. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.runs'))
@section('nav-title', __('club.fees.title.runs'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.fees.subtitle.runs')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.fees.runs.create')" show-label>{{ __('club.fees.action.create_run') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="receipt_long" tone="outline" size="sm" :href="route('club.fees.claims.index')" show-label>{{ __('club.fees.title.claims') }}</x-icon-btn>
        <x-icon-btn icon="account_balance_wallet" tone="ghost" size="sm" :href="route('club.fees.accounts.index')" show-label>{{ __('club.fees.title.accounts') }}</x-icon-btn>
        <x-help-button topic="club.fees" />
    </x-slot:actions>

    @if ($external)
        <div class="alert alert-warning mb-3 text-sm" role="status">
            <x-icon name="lock" />
            <span>{{ __('club.fees.hint.external_billing', ['mode' => $external->label()]) }}</span>
        </div>
    @endif

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.fees.field.month') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th class="text-right">{{ __('club.fees.field.positions') }}</th>
                <th class="text-right">{{ __('club.fees.field.claims') }}</th>
                <th class="text-right">{{ __('club.fees.label.total') }}</th>
                <th>{{ __('club.fees.field.released') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <tr class="hover">
                <td class="font-medium"><a href="{{ route('club.fees.runs.show', $run) }}" class="link link-hover">{{ $run->monthLabel() }}</a></td>
                <td>
                    <x-status-badge :tone="$run->status->tone()" size="sm">{{ $run->status->label() }}</x-status-badge>
                    @if ($run->hasIssues())<x-status-badge tone="error" size="xs" :label="__('club.fees.label.issues', ['count' => count($run->issues ?? [])])" />@endif
                </td>
                <td class="text-right tabular-nums">{{ count($run->positions ?? []) }}</td>
                <td class="text-right tabular-nums">{{ $run->claims_count }}</td>
                <td class="text-right tabular-nums">{{ $run->total->format() }}</td>
                <td class="text-sm text-muted">{{ $run->released_at?->orgTz()->format('d.m.Y H:i') ?? '–' }}@if ($run->releasedBy) · {{ $run->releasedBy->name }}@endif</td>
                <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('club.fees.runs.show', $run)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="play_circle" :colspan="7" :title="__('club.fees.empty.runs')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$runs" standing />
</x-index-page>
@endsection
