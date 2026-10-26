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
    <x-page-toolbar :title="__('resale_portal.title')" :subtitle="__('resale_portal.subtitle')" />

    <x-card>
    {{-- Schmale Fenster: die Tabelle scrollt in der Karte, nicht die Seite (Portal-Main hat keinen Scrollbereich). --}}
    <div class="overflow-x-auto">
    <x-table bare :caption="__('resale_portal.title')">
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
                        <x-status-badge size="xs">{{ __('resale_portal.holder.end_customer') }}</x-status-badge>
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
            <x-table.empty :colspan="8" icon="workspace_premium" :title="__('resale_portal.empty')" compact />
        @endforelse
    </x-table>
    </div>
    </x-card>
    <x-pagination :paginator="$subscriptions" standing />

    {{-- Verkaufte Stücklizenzen (Feature 185, MVP-1428) — ohne Schlüssel, die gibt der Auftragnehmer persönlich weiter. --}}
    @if ($licenses->isNotEmpty())
        <x-card :title="__('resale_portal.licenses.title')" icon="key" :count="$licenses->count()" padding="p-0">
            <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale_portal.licenses.hint') }}</p>
            <div class="overflow-x-auto">
            <x-table bare :caption="__('resale_portal.licenses.title')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale_portal.licenses.product') }}</x-table.th>
                        <x-table.th>{{ __('resale_portal.licenses.license') }}</x-table.th>
                        <x-table.th>{{ __('resale_portal.field.holder') }}</x-table.th>
                        <x-table.th>{{ __('resale_portal.licenses.sold_on') }}</x-table.th>
                        <x-table.th>{{ __('resale_portal.licenses.invoice') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($licenses as $license)
                    <tr class="hover">
                        <td class="text-sm">{{ $license->unit->batch->product->name }}</td>
                        <td class="whitespace-nowrap text-sm">{{ $license->unit->label() }}</td>
                        <td class="text-sm">
                            {{ $license->foreignCustomer?->name ?? __('resale.holder.customer') }}
                            @if ($license->foreignCustomer !== null)
                                <x-status-badge size="xs">{{ __('resale_portal.holder.end_customer') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="tabular-nums text-sm whitespace-nowrap">{{ $license->sold_on->fdate() }}</td>
                        <td class="text-sm">{{ $license->mirrorLine()?->voucherNumber ?? $license->invoice_reference ?? __('resale_portal.licenses.no_invoice') }}</td>
                    </tr>
                @endforeach
            </x-table>
            </div>
        </x-card>
    @endif
</div>
@endsection
