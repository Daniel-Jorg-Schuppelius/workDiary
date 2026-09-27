{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abrufe eines Rahmen-LV (MVP-931). Erwartet: $bill, $callOffs, $remaining, $canManage, $canInvoice --}}
@extends('layouts.app')

@section('title', __('gaeb.call_off.title') . ' — ' . $bill->name)
@section('nav-title', __('gaeb.call_off.title'))

@php
    $qty = static fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 3, withThousandsSeparator: true);
@endphp

@section('content')
<x-index-page :subtitle="$bill->name">
    <x-slot:actions>
        @if ($canManage && $bill->is_framework)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('bill-of-quantities.call-offs.create', $bill)" show-label>{{ __('gaeb.call_off.create') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.show', $bill)" show-label>{{ __('gaeb.show.back_to_boq') }}</x-icon-btn>
    </x-slot:actions>

    @if ($canManage)
        <x-card>
            <form method="POST" action="{{ route('bill-of-quantities.framework', $bill) }}" class="flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')
                <x-checkbox-field name="is_framework" :label="__('gaeb.call_off.framework')" :checked="$bill->is_framework" :hint="__('gaeb.call_off.framework_hint')" />
                <x-button type="submit" size="sm">{{ __('gaeb.call_off.save') }}</x-button>
            </form>
        </x-card>
    @endif

    <x-card padding="p-0" class="mt-4" :title="__('gaeb.call_off.list')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('gaeb.call_off.field.number') }}</th>
                    <th>{{ __('gaeb.call_off.field.title') }}</th>
                    <th>{{ __('gaeb.call_off.field.ordered_on') }}</th>
                    <th>{{ __('gaeb.call_off.field.due_on') }}</th>
                    <th class="text-right">{{ __('gaeb.call_off.field.items') }}</th>
                    <th>{{ __('gaeb.call_off.field.status') }}</th>
                    <th>{{ __('gaeb.call_off.field.invoice') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($callOffs as $callOff)
                @php($invoice = $callOff->activeInvoice())
                <tr>
                    <td class="tabular-nums">{{ $callOff->number }}</td>
                    <td>
                        {{ $callOff->title }}
                        <div class="text-xs opacity-70">
                            @foreach ($callOff->items as $line)
                                {{ $line->item->reference_no }}: {{ $qty((float) $line->quantity) }} {{ $line->item->unit }}@if (! $loop->last) · @endif
                            @endforeach
                        </div>
                    </td>
                    <td>{{ $callOff->ordered_on?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $callOff->due_on?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $callOff->items->count() }}</td>
                    <td><span class="wd-badge badge-ghost">{{ $callOff->status->label() }}</span></td>
                    <td>
                        @if ($invoice)
                            <a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <div class="flex justify-end gap-1">
                            @if ($canManage)
                                @foreach ($callOff->status->allowedTransitions() as $target)
                                    <form method="POST" action="{{ route('bill-of-quantities.call-offs.transition', $callOff) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $target->value }}">
                                        <button type="submit" class="btn btn-xs {{ $target === \App\Enums\Gaeb\BoqCallOffStatus::Cancelled ? 'btn-ghost text-error' : 'btn-ghost' }}">{{ __('gaeb.call_off.transition.' . $target->value) }}</button>
                                    </form>
                                @endforeach
                            @endif
                            @if ($canInvoice && $invoice === null && $callOff->status->isBillable())
                                <form method="POST" action="{{ route('bill-of-quantities.call-offs.invoice', $callOff) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('gaeb.call_off.invoice') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="assignment" :colspan="8" :title="$bill->is_framework ? __('gaeb.call_off.empty') : __('gaeb.call_off.not_framework')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($bill->is_framework)
        <x-card padding="p-0" class="mt-4" :title="__('gaeb.call_off.remaining')">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('gaeb.columns.reference_no') }}</th>
                        <th>{{ __('gaeb.columns.short_text') }}</th>
                        <th class="text-right">{{ __('gaeb.call_off.field.framework') }}</th>
                        <th class="text-right">{{ __('gaeb.call_off.field.called') }}</th>
                        <th class="text-right">{{ __('gaeb.call_off.field.remaining') }}</th>
                        <th>{{ __('gaeb.columns.unit') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($remaining as $row)
                    <tr>
                        <td class="font-mono text-sm whitespace-nowrap">{{ $row['item']->reference_no }}</td>
                        <td>{{ $row['item']->short_text ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $qty($row['framework']) }}</td>
                        <td class="text-right tabular-nums">{{ $qty($row['called']) }}</td>
                        <td class="text-right tabular-nums {{ $row['remaining'] <= 0.0 ? 'text-error' : '' }}">{{ $qty($row['remaining']) }}</td>
                        <td>{{ $row['item']->unit ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-index-page>
@endsection
