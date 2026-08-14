{{--
  Created on   : Fri Jul 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : approvals.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.approval.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.approval.title'))

@section('content')
<x-index-page :subtitle="__('procurement.approval.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('pricing-margin-rules.index')" show-label>{{ __('procurement.margin.title') }}</x-icon-btn>
    </x-slot:actions>

    @if ($requests->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">fact_check</span>'
                       :title="__('procurement.approval.empty')" />
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('procurement.catalog.col.internal_article') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.old_ek') }}</th>
                    <th class="text-right">{{ __('procurement.approval.col.suggested') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.margin') }}</th>
                    <th>{{ __('procurement.approval.col.requested_by') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>
                </x-slot:head>
                @foreach ($requests as $request)
                    @php($open = $request->status === \App\Models\PriceChangeRequest::STATUS_REQUESTED)
                    <tr @class(['opacity-60' => ! $open])>
                        <td>
                            {{ $request->article?->name ?: '—' }}
                            <div class="text-xs opacity-60">{{ $request->item?->external_no }} {{ $request->item?->name }}</div>
                        </td>
                        <td class="text-right tabular-nums text-sm opacity-70">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($request->purchase_price_snapshot?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums font-medium">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($request->suggested_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right tabular-nums">{{ rtrim(rtrim($request->margin_snapshot, '0'), '.') }} %</td>
                        <td class="text-sm">
                            {{ $request->requestedBy?->name ?: '—' }}
                            <div class="text-xs opacity-60">{{ $request->created_at?->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>
                            <span class="badge badge-sm">{{ __('procurement.approval.status.' . $request->status) }}</span>
                            @if (! $open && $request->decidedBy)
                                <div class="text-xs opacity-60 mt-0.5">{{ $request->decidedBy->name }} · {{ $request->decided_at?->format('d.m.Y H:i') }}</div>
                            @endif
                            @if ($request->decision_note)
                                <div class="text-xs opacity-60">{{ $request->decision_note }}</div>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($open && $canDecide)
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('pricing-margin-rules.approvals.approve', $request) }}">@csrf
                                        <x-icon-btn icon="done" size="xs" tone="success" type="submit" :title="__('procurement.approval.action.approve')" />
                                    </form>
                                    <form method="POST" action="{{ route('pricing-margin-rules.approvals.reject', $request) }}" class="flex items-center gap-1">
                                        @csrf
                                        <input name="note" type="text" maxlength="500" class="input input-xs input-bordered w-32"
                                               placeholder="{{ __('procurement.approval.note_placeholder') }}">
                                        <x-icon-btn icon="close" size="xs" tone="error" type="submit" :title="__('procurement.approval.action.reject')" />
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$requests" standing />
    @endif
</x-index-page>
@endsection
