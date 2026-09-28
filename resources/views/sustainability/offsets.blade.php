{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : offsets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Klimanachweise und Prüfung von Umweltaussagen (MVP-961). Erwartet: $offsets, $totals, $findings, $claimText, $canManage --}}
@extends('layouts.app')

@section('title', __('sustainability.offset.title'))
@section('nav-title', __('sustainability.offset.title'))

@php
    $t = static fn ($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 3, withThousandsSeparator: true, trimTrailingZeros: true) . ' t';
@endphp

@section('content')
<x-index-page :subtitle="__('sustainability.offset.subtitle')">

    @include('sustainability._tabs')

    <div class="alert alert-info text-sm" role="note">{{ __('sustainability.offset.separate') }}</div>

    <x-card :title="__('sustainability.offset.list')" padding="p-0" class="mt-4">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('sustainability.offset.field.claim_year') }}</th>
                    <th>{{ __('sustainability.offset.field.kind') }}</th>
                    <th>{{ __('sustainability.offset.field.provider') }}</th>
                    <th class="text-right">{{ __('sustainability.offset.field.quantity_t') }}</th>
                    <th>{{ __('sustainability.offset.field.evidence') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($offsets as $offset)
                <tr>
                    <td>{{ $offset->claim_year }}</td>
                    <td>{{ $offset->kind->label() }}</td>
                    <td>{{ $offset->provider }}@if ($offset->standard) <span class="text-xs text-muted">· {{ $offset->standard }}</span>@endif @if ($offset->project_name)<div class="text-xs text-muted">{{ $offset->project_name }}</div>@endif</td>
                    <td class="text-right tabular-nums">{{ $t($offset->quantity_t) }}</td>
                    <td>
                        @if ($offset->isEvidenced())
                            <x-status-badge tone="success">{{ __('sustainability.offset.evidenced') }}</x-status-badge>
                        @else
                            <x-status-badge tone="warning">{{ __('sustainability.offset.not_evidenced') }}</x-status-badge>
                        @endif
                        @if ($offset->registry_reference)<div class="text-xs text-muted">{{ $offset->registry_reference }}</div>@endif
                    </td>
                    <td class="text-right">
                        @if ($canManage)
                            <form method="POST" action="{{ route('sustainability.offsets.destroy', $offset) }}" data-confirm-dialog data-confirm-message="{{ __('sustainability.offset.confirm_delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" type="submit" :title="__('Löschen')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="forest" :colspan="6" :title="__('sustainability.offset.empty')" compact />
            @endforelse
        </x-table>
        @if ($totals->isNotEmpty())
            <p class="px-4 py-2 text-xs text-muted">{{ __('sustainability.offset.totals') }}: @foreach ($totals as $year => $sum){{ $year }}: {{ $t($sum) }}@if (! $loop->last) · @endif @endforeach</p>
        @endif
        @if ($canManage)
            <form method="POST" action="{{ route('sustainability.offsets.store') }}" class="grid gap-2 border-t border-base-300 px-4 py-3 sm:grid-cols-4" data-entry-form>
                @csrf
                <x-select-field name="kind" :label="__('sustainability.offset.field.kind')" required>
                    @foreach (\App\Enums\Sustainability\SustainabilityOffsetKind::cases() as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="provider" :label="__('sustainability.offset.field.provider')" required />
                <x-input-field name="standard" :label="__('sustainability.offset.field.standard')" :hint="__('sustainability.offset.hint.standard')" />
                <x-input-field name="project_name" :label="__('sustainability.offset.field.project_name')" />
                <x-input-field name="quantity_t" type="number" step="0.001" min="0.001" :label="__('sustainability.offset.field.quantity_t')" required />
                <x-input-field name="claim_year" type="number" min="2000" max="2100" :label="__('sustainability.offset.field.claim_year')" :value="now()->year" required />
                <x-input-field name="vintage_year" type="number" min="2000" max="2100" :label="__('sustainability.offset.field.vintage_year')" />
                <x-input-field name="retired_on" type="date" :label="__('sustainability.offset.field.retired_on')" />
                <x-input-field name="registry_reference" :label="__('sustainability.offset.field.registry_reference')" />
                <x-input-field name="note" :label="__('sustainability.offset.field.note')" />
                <div class="sm:col-span-4 flex justify-end"><button type="submit" class="btn btn-sm">{{ __('sustainability.offset.add') }}</button></div>
            </form>
        @endif
    </x-card>

    <x-card :title="__('sustainability.claim.title')" class="mt-4">
        <p class="mb-2 text-sm text-muted">{{ __('sustainability.claim.intro') }}</p>
        <form method="POST" action="{{ route('sustainability.claims.check') }}" class="grid gap-2">
            @csrf
            <x-textarea-field name="claim_text" rows="3" :label="__('sustainability.claim.text')" required>{{ old('claim_text', $claimText) }}</x-textarea-field>
            <div class="flex justify-end"><button type="submit" class="btn btn-sm">{{ __('sustainability.claim.check') }}</button></div>
        </form>
        @if (session('claim_checked'))
            @if ($findings === [])
                <div class="alert alert-success mt-3 text-sm">{{ __('sustainability.claim.none') }}</div>
            @else
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($findings as $finding)
                        <li><x-status-badge tone="warning">{{ $finding['term'] }}</x-status-badge> {{ __('sustainability.claim.reason.' . $finding['reason']) }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
        <p class="mt-2 text-xs text-muted">{{ __('sustainability.claim.disclaimer') }}</p>
    </x-card>
</x-index-page>
@endsection
