{{--
  Created on   : Thu Sep 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal „meine Abos" (Feature 152): Bestand der Abos des Kunden und seiner
  Endkunden — bewusst ohne Preise, Einkauf und Rechnungsbezüge. Halter,
  Laufzeit und nächste Periode kommen vom Controller (PeriodPlanner).
--}}
@extends('customer.layout')

@section('title', __('resale_portal.title'))

@section('content')
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">{{ __('resale_portal.title') }}</h1>
        <p class="text-sm text-muted">{{ __('resale_portal.subtitle') }}</p>
    </div>

    <x-table :caption="__('resale_portal.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('resale_portal.field.product') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.holder') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale_portal.field.quantity') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.term') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.interval') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.renewal') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.next_period') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($subscriptions as $subscription)
            @php
                $next = $nextPeriods[$subscription->id] ?? null;
                $product = $subscription->productLabel();
            @endphp
            <tr class="hover">
                <td>
                    <a class="link font-medium" href="{{ route('customer.subscriptions.show', $subscription) }}">{{ $subscription->label }}</a>
                    <div class="text-xs text-muted">
                        {{ $subscription->kind->label() }}@if ($product !== null && $product !== $subscription->label) · {{ $product }}@endif
                    </div>
                </td>
                <td>
                    {{ $subscription->holderLabel() }}
                    @if ($subscription->foreignCustomer !== null)
                        <span class="badge badge-ghost badge-xs">{{ __('resale_portal.holder.end_customer') }}</span>
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                <td class="tabular-nums whitespace-nowrap">
                    @if ($subscription->ends_on !== null)
                        {{ __('resale_portal.term.range', ['from' => $subscription->starts_on->fdate(), 'to' => $subscription->ends_on->fdate()]) }}
                    @else
                        {{ __('resale_portal.term.since', ['date' => $subscription->starts_on->fdate()]) }} · {{ __('resale_portal.term.running') }}
                    @endif
                </td>
                <td>{{ __('resale_portal.interval.' . $subscription->interval->value) }}</td>
                <td>{{ $subscription->renewal->label() }}</td>
                <td class="tabular-nums">{{ $next?->fdate() ?? __('resale_portal.next_period.none') }}</td>
                <td><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></td>
            </tr>
        @empty
            <x-table.empty :colspan="8" :title="__('resale_portal.empty')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$subscriptions" standing />
</div>
@endsection
