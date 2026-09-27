{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- BIA-Register (MVP-943). Erwartet: $processes, $candidates, $canManage --}}
@extends('layouts.app')

@section('title', __('crisis.bia.title'))
@section('nav-title', __('crisis.bia.title'))

@section('content')
<x-index-page :subtitle="__('crisis.bia.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('crisis.bia.create')" show-label>{{ __('crisis.bia.create') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="arrow_back" size="sm" :href="route('crisis.index')" show-label>{{ __('crisis.bcm_report.back') }}</x-icon-btn>
    </x-slot:actions>
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('crisis.bia.field.name') }}</th>
                    <th>{{ __('crisis.bia.field.criticality') }}</th>
                    <th class="text-right">RTO h</th>
                    <th class="text-right">RPO h</th>
                    <th class="text-right">MTPD h</th>
                    <th>{{ __('crisis.bia.field.owner') }}</th>
                    <th>{{ __('crisis.bia.field.review_due_on') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($processes as $process)
                <tr>
                    <td>{{ $process->name }}@unless ($process->is_active) <span class="wd-badge badge-ghost">{{ __('crisis.bia.inactive') }}</span>@endunless</td>
                    <td><span class="wd-badge {{ $process->criticality === \App\Enums\Crisis\CrisisProcessCriticality::Critical ? 'badge-error' : 'badge-ghost' }}">{{ $process->criticality->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $process->rto_hours ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $process->rpo_hours ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $process->mtpd_hours ?? '—' }}</td>
                    <td>{{ $process->owner?->name ?? '—' }}</td>
                    <td class="{{ $process->review_due_on?->isPast() ? 'text-error' : '' }}">{{ $process->review_due_on?->format('d.m.Y') ?? '—' }}</td>
                    <td><div class="flex justify-end">@if ($canManage)<x-icon-btn icon="edit" size="xs" data-entry-modal-trigger :href="route('crisis.bia.edit', $process)" :label="__('crisis.bia.edit')" />@endif</div></td>
                </tr>
            @empty
                <x-table.empty icon="account_tree" :colspan="8" :title="__('crisis.bia.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($canManage && $candidates !== [])
        <x-card class="mt-4" :title="__('crisis.bia.import')">
            <p class="mb-2 text-sm text-muted">{{ __('crisis.bia.import_hint') }}</p>
            <form method="POST" action="{{ route('crisis.bia.import') }}" class="flex flex-col gap-1">
                @csrf
                @foreach ($candidates as $candidate)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" class="checkbox checkbox-sm" name="keys[]" value="{{ $candidate['key'] }}">
                        <span>{{ $candidate['label'] }} <span class="text-muted">({{ __('crisis.bia.kind.' . $candidate['kind']) }})</span></span>
                    </label>
                @endforeach
                <div class="mt-2 flex justify-end"><x-button type="submit" size="sm">{{ __('crisis.bia.import_submit') }}</x-button></div>
            </form>
        </x-card>
    @endif
</x-index-page>
@endsection
