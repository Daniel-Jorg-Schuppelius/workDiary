{{--
  Created on   : Thu Sep 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Abodetail (Feature 152): Stammdaten und Periodenliste mit neutralem
  Status (offen/berechnet/nicht berechnet) — keine Beträge, keine Belege.
--}}
@extends('customer.layout')

@section('title', $subscription->label)

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold">{{ $subscription->label }}</h1>
            <p class="text-sm text-muted">
                {{ $subscription->kind->label() }}
                @if ($subscription->productLabel() !== null && $subscription->productLabel() !== $subscription->label)
                    · {{ $subscription->productLabel() }}
                @endif
            </p>
        </div>
        <a href="{{ route('customer.subscriptions.index') }}" class="btn btn-sm btn-ghost">{{ __('resale_portal.back') }}</a>
    </div>

    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3 rounded-box border border-base-300 bg-base-100 p-4">
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.holder') }}</dt>
            <dd>
                {{ $subscription->holderLabel() }}
                @if ($subscription->foreignCustomer !== null)
                    <span class="badge badge-ghost badge-xs">{{ __('resale_portal.holder.end_customer') }}</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.quantity') }}</dt>
            <dd class="tabular-nums">{{ $subscription->quantity }}</dd>
        </div>
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.status') }}</dt>
            <dd><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></dd>
        </div>
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.term') }}</dt>
            <dd class="tabular-nums">
                @if ($subscription->ends_on !== null)
                    {{ __('resale_portal.term.range', ['from' => $subscription->starts_on->fdate(), 'to' => $subscription->ends_on->fdate()]) }}
                @else
                    {{ __('resale_portal.term.since', ['date' => $subscription->starts_on->fdate()]) }} · {{ __('resale_portal.term.running') }}
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.interval') }}</dt>
            <dd>{{ __('resale_portal.interval.' . $subscription->interval->value) }} · {{ __('resale_portal.field.renewal') }}: {{ $subscription->renewal->label() }}</dd>
        </div>
        <div>
            <dt class="text-xs text-muted">{{ __('resale_portal.field.next_period') }}</dt>
            <dd class="tabular-nums">{{ $nextPeriod?->fdate() ?? __('resale_portal.next_period.none') }}</dd>
        </div>
    </dl>

    <div>
        <h2 class="text-lg font-medium">{{ __('resale_portal.periods.title') }}</h2>
        <p class="text-xs text-muted">{{ __('resale_portal.periods.hint') }}</p>
    </div>
    <x-table :caption="__('resale_portal.periods.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('resale_portal.field.period') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale_portal.field.quantity') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($subscription->periods as $period)
            <tr class="hover">
                <td class="tabular-nums whitespace-nowrap">{{ __('resale_portal.term.range', ['from' => $period->starts_on->fdate(), 'to' => $period->ends_on->fdate()]) }}</td>
                <td class="text-right tabular-nums">{{ $period->quantity }}</td>
                <td><x-status-badge size="xs" outline :label="$periodStatusLabels[$period->status->value] ?? $period->status->value" /></td>
            </tr>
        @empty
            <x-table.empty :colspan="3" :title="__('resale_portal.periods.empty')" compact />
        @endforelse
    </x-table>
</div>
@endsection
