{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schadensfall (MVP-919). Erwartet: $case --}}
@extends('layouts.app')

@section('title', (string) $case->number)
@section('nav-title', __('damage.case'))

@php
    $money = static fn (?string $amount): string => $amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) . ' ' . $case->currency->value : '—';
    $subject = $case->subject;
@endphp

@section('content')
<x-page-shell>
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$case->number . ' — ' . $case->title"
                        back-route="damage-cases.index" :back-label="__('damage.title')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline :tone="$case->status->tone()">{{ $case->status->label() }}</x-status-badge>
                <span class="badge badge-outline">{{ $case->kind->label() }}</span>
            </div>
            <x-slot:actions>
                @can('update', $case)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('damage-cases.edit', $case)" show-label>{{ __('damage.action.edit') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-card :title="__('damage.section.case')" icon="car_crash">
                <x-detail-grid class="grid-cols-2">
                    <x-detail-grid.row :label="__('damage.field.subject')">
                        @if ($subject !== null && $subject->damageSubjectUrl() !== null)
                            <a class="link" href="{{ $subject->damageSubjectUrl() }}">{{ $subject->damageSubjectLabel() }}</a>
                        @else
                            {{ $subject?->damageSubjectLabel() ?? '—' }}
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.responsible_user_id')">{{ $case->responsible?->name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.occurred_at')">{{ $case->occurred_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.reported_at')">{{ $case->reported_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.insurer_name')">{{ $case->insurer_name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.policy_number')">{{ $case->policy_number ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.claim_number')">{{ $case->claim_number ?? '—' }}</x-detail-grid.row>
                </x-detail-grid>
                @if ($case->description)
                    <p class="mt-3 whitespace-pre-line text-sm">{{ $case->description }}</p>
                @endif
            </x-card>

            <x-card :title="__('damage.section.amounts')" icon="payments">
                <x-detail-grid class="grid-cols-2">
                    <x-detail-grid.row :label="__('damage.field.estimated_amount')">{{ $money($case->estimated_amount) }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.deductible_amount')">{{ $money($case->deductible_amount) }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.settled_amount')">{{ $money($case->settled_amount) }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('damage.field.net_recovery')">{{ $money($case->netRecovery()) }}</x-detail-grid.row>
                </x-detail-grid>
            </x-card>

            <x-attachments-section :attachments="$case->attachments" upload-type="damage" :upload-id="$case->sqid" :can-upload="Gate::allows('update', $case)" />
        </div>

        <div class="flex flex-col gap-4">
            @can('update', $case)
                @if ($case->status->allowedTransitions() !== [])
                    <x-card :title="__('damage.section.status')" icon="swap_horiz">
                        <div class="flex flex-col gap-3">
                            @foreach ($case->status->allowedTransitions() as $target)
                                <form method="POST" action="{{ route('damage-cases.transition', $case) }}" class="flex flex-col gap-2">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $target->value }}">
                                    @if ($target === \App\Enums\Damage\DamageCaseStatus::Settled)
                                        <x-input-field name="settled_amount" type="number" step="0.01" min="0" :label="__('damage.field.settled_amount')" :value="old('settled_amount', $case->settled_amount)" required />
                                    @endif
                                    @if ($target === \App\Enums\Damage\DamageCaseStatus::Submitted && $case->claim_number === null)
                                        <x-input-field name="claim_number" :label="__('damage.field.claim_number')" :value="old('claim_number')" />
                                    @endif
                                    <x-button type="submit" size="sm" :tone="$target->tone() === 'error' ? 'error' : 'primary'">{{ __('damage.transition.' . $target->value) }}</x-button>
                                </form>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            @endcan

            <x-card :title="__('damage.section.journal')" icon="history">
                <x-journal :entries="$case->journal" newest-first />
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
