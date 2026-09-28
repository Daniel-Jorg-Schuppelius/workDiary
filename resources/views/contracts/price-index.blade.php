{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : price-index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Verbraucherpreisindex mit Freigabe (MVP-952). Erwartet: $values, $pending, $canApprove --}}
@extends('layouts.app')

@section('title', __('contract.price_index.title'))
@section('nav-title', __('contract.price_index.title'))

@section('content')
    <x-index-page :subtitle="__('contract.price_index.subtitle')">

    @include('contracts._tabs')
        @if ($pending > 0)
            <div class="alert alert-warning mb-3 text-sm">{{ __('contract.price_index.pending', ['count' => $pending]) }}</div>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('contracts.price-index.store') }}" class="mb-3 flex flex-wrap items-end gap-2" data-entry-form>
                @csrf
                <x-input-field name="period" type="month" :label="__('contract.price_index.field.period')" required />
                <x-input-field name="value" type="number" step="0.1" min="1" :label="__('contract.price_index.field.value')" required />
                <button type="submit" class="btn btn-sm">{{ __('contract.price_index.add') }}</button>
            </form>
        @endif
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('contract.price_index.field.period') }}</th>
                    <th class="text-right">{{ __('contract.price_index.field.value') }}</th>
                    <th>{{ __('contract.price_index.field.source') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($values as $value)
                <tr>
                    <td>{{ $value->period_on->format('m/Y') }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value->value, 1) }}</td>
                    <td>{{ __('contract.price_index.source.' . $value->source) }}</td>
                    <td>
                        <span class="wd-badge badge-{{ $value->status->tone() }}">{{ $value->status->label() }}</span>
                        @if ($value->approver !== null)<span class="text-xs text-muted">{{ $value->approver->name }}</span>@endif
                    </td>
                    <td class="text-right">
                        @if ($canApprove && $value->status === \App\Enums\Contract\PriceIndexStatus::Pending)
                            <div class="flex justify-end gap-1">
                                <form method="POST" action="{{ route('contracts.price-index.approve', $value) }}">
                                    @csrf
                                    <x-icon-btn icon="check" size="xs" type="submit" :title="__('contract.price_index.approve')" />
                                </form>
                                <form method="POST" action="{{ route('contracts.price-index.reject', $value) }}">
                                    @csrf
                                    <x-icon-btn icon="close" size="xs" type="submit" :title="__('contract.price_index.reject')" />
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="trending_up" :colspan="5" :title="__('contract.price_index.empty')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$values" standing />
    </x-index-page>
@endsection
