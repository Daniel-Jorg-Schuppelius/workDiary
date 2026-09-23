{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragsvorschau eines Abrechnungsmonats (MVP-849): berechnete Positionen ohne Forderung; Fehler sichtbar. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.preview'))
@section('nav-title', __('club.fees.title.preview'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.fees.subtitle.preview', ['month' => $month->translatedFormat('F Y')])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.fees.accounts.index')" show-label>{{ __('club.fees.title.accounts') }}</x-icon-btn>
        <x-help-button topic="club.fees" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.fees.preview')" :reset="route('club.fees.preview')">
        <label for="fee-preview-month" class="sr-only">{{ __('club.fees.field.month') }}</label>
        <input id="fee-preview-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="input input-sm input-bordered w-44 shrink-0">
    </x-filter-bar>

    @if ($issues !== [])
        <div class="alert alert-error mb-3 text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($issues as $issue)
                    <li>{{ $issue['message'] }}@if ($issue['member_id']) — {{ $members->get($issue['member_id'])?->fullName() ?? $issue['member_id'] }}@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.fees.field.account') }}</th>
                <th>{{ __('club.field.member') }}</th>
                <th>{{ __('club.fees.field.position') }}</th>
                <th>{{ __('club.field.period') }}</th>
                <th>{{ __('club.fees.field.due_on') }}</th>
                <th>{{ __('club.fees.field.basis') }}</th>
                <th class="text-right">{{ __('club.fees.field.amount') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($positions as $position)
            <tr class="hover">
                <td class="text-sm">{{ $accounts->get($position->accountId)?->name ?? '–' }}</td>
                <td class="text-sm">{{ $position->memberId ? ($members->get($position->memberId)?->fullName() ?? '–') : __('club.fees.label.whole_account') }}</td>
                <td class="text-sm"><x-status-badge tone="ghost" size="xs">{{ $position->kind->label() }}</x-status-badge> {{ $position->label }}</td>
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $position->periodStart->format('d.m.Y') }} – {{ $position->periodEnd->format('d.m.Y') }}</td>
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $position->dueOn->format('d.m.Y') }}</td>
                <td class="text-xs text-muted">
                    @if (isset($position->basis['active_days']))
                        {{ __('club.fees.label.basis_days', ['active' => $position->basis['active_days'], 'total' => $position->basis['period_days']]) }}
                        @if (($position->basis['discount_percent'] ?? '0') !== '0') · {{ __('club.fees.field.discount') }} {{ $position->basis['discount_percent'] }} %@endif
                    @elseif (isset($position->basis['joined_on']))
                        {{ __('club.fees.label.basis_joined', ['date' => \Carbon\CarbonImmutable::parse($position->basis['joined_on'])->format('d.m.Y')]) }}
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $position->amount->format() }}</td>
            </tr>
        @empty
            <x-table.empty icon="calculate" :colspan="7" :title="__('club.fees.empty.positions')" compact />
        @endforelse
        @if ($total)
            <x-slot:foot>
                <tr>
                    <th colspan="6" class="text-right">{{ __('club.fees.label.total') }}</th>
                    <th class="text-right tabular-nums">{{ $total->format() }}</th>
                </tr>
            </x-slot:foot>
        @endif
    </x-table>
</x-index-page>
@endsection
