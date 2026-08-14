{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $order->number . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.title'))

@php
    /** @var \App\Models\PurchaseOrder $order */
    $status = $order->status->value;
    $isOpen = ! $order->status->isTerminal();
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$order->supplier?->name" :subtitle="$order->number"
                        :badge="$order->status->label()" badge-tone="ghost">
            <span class="text-sm text-base-content/70">{{ __('procurement.field.warehouse') }}: <strong>{{ $order->warehouse?->name }}</strong></span>
            @if ($canManage)
                <x-slot:actions>
                    @if ($status === 'draft')
                        <form method="POST" action="{{ route('purchase-orders.submit', $order) }}">@csrf
                            <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('procurement.action.submit') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($isOpen)
                        <x-action-form :action="route('purchase-orders.cancel', $order)" :confirm="__('procurement.action.cancel').'?'">
                            <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" :title="__('procurement.action.cancel')" />
                        </x-action-form>
                    @endif
                    <a href="{{ route('purchase-orders.pdf', $order) }}" target="_blank" class="btn btn-sm btn-ghost gap-1">
                        <span class="material-symbols-rounded text-base">picture_as_pdf</span>{{ __('procurement.action.export_pdf') }}
                    </a>
                    @if ($status !== 'draft')
                        <a href="{{ route('purchase-orders.order-xml', $order) }}" class="btn btn-sm btn-ghost gap-1">
                            <span class="material-symbols-rounded text-base">download</span>{{ __('procurement.action.export_xbestellung') }}
                        </a>
                        <a href="{{ route('purchase-orders.order-xml', ['purchaseOrder' => $order, 'format' => 'orderx']) }}" class="btn btn-sm btn-ghost gap-1">
                            <span class="material-symbols-rounded text-base">download</span>{{ __('procurement.action.export_orderx') }}
                        </a>
                        <a href="{{ route('purchase-orders.order-xml', ['purchaseOrder' => $order, 'format' => 'opentrans']) }}" class="btn btn-sm btn-ghost gap-1">
                            <span class="material-symbols-rounded text-base">download</span>{{ __('procurement.action.export_opentrans') }}
                        </a>
                        <a href="{{ route('purchase-orders.order-xml', ['purchaseOrder' => $order, 'format' => 'ugl']) }}" class="btn btn-sm btn-ghost gap-1">
                            <span class="material-symbols-rounded text-base">download</span>{{ __('procurement.action.export_ugl') }}
                        </a>
                    @endif
                </x-slot:actions>
            @endif
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Position hinzufügen --}}
    @if ($canManage && $status === 'draft')
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('procurement.action.add_line') }}</h2>
            <form method="POST" action="{{ route('purchase-orders.lines.add', $order) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow"><label class="fieldset-label">{{ __('procurement.field.article') }}</label>
                    <select name="article" class="select select-sm select-bordered w-full" required>
                        @foreach ($articles as $article)
                            <option value="{{ $article->sqid }}">{{ $article->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('procurement.field.qty') }}</label>
                    <input name="qty" type="number" step="0.0001" min="0.0001" value="1" class="input input-sm input-bordered w-24"></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('procurement.field.unit_price') }}</label>
                    <input name="unit_price" type="number" step="0.0001" min="0" class="input input-sm input-bordered w-28"></div>
                <div class="fieldset grow"><label class="fieldset-label">{{ __('procurement.field.line_note') }}</label>
                    <input name="note" maxlength="500" class="input input-sm input-bordered w-full"></div>
                <x-button type="submit" tone="primary" size="sm">{{ __('procurement.action.add_line') }}</x-button>
            </form>
            <form method="POST" action="{{ route('purchase-orders.conditions', $order) }}" class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t border-base-300">
                @csrf
                <div class="fieldset"><label class="fieldset-label">{{ __('procurement.field.freight_cost') }}</label>
                    <input name="freight_cost" type="number" step="0.01" min="0" value="{{ $order->freight_cost !== null ? rtrim(rtrim(number_format(($order->freight_cost?->toFloat() ?? 0.0), 2, '.', ''), '0'), '.') : '' }}" class="input input-sm input-bordered w-32"></div>
                <x-button type="submit" tone="ghost" size="sm">{{ __('procurement.action.save_conditions') }}</x-button>
            </form>
        </x-card>
    @endif

    {{-- Positionen --}}
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.field.article') }}</th>
                    <th class="text-right">{{ __('procurement.field.ordered_qty') }}</th>
                    <th class="text-right">{{ __('procurement.field.received_qty') }}</th>
                    @if ($canManage && in_array($status, ['ordered', 'partially_received'], true))
                        <th class="text-right">{{ __('procurement.action.receive') }}</th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($order->lines as $line)
                <tr>
                    <td>
                        {{ $line->description }}
                        @if (trim((string) $line->note) !== '')<div class="text-xs opacity-60">{{ $line->note }}</div>@endif
                    </td>
                    <td class="text-right tabular-nums">{{ $line->ordered_qty }} {{ $line->unit }}</td>
                    <td class="text-right tabular-nums">{{ $line->received_qty }}</td>
                    @if ($canManage && in_array($status, ['ordered', 'partially_received'], true))
                        <td class="text-right">
                            <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="flex flex-wrap items-center justify-end gap-1">
                                @csrf
                                <input type="hidden" name="line" value="{{ $line->sqid }}">
                                @php($lineArticle = $line->variant?->article)
                                {{-- Vollaudit 2026-07 (M19): Pflichterfassung Charge/Serie/MHD (E2). --}}
                                @if ($lineArticle?->batch_required)
                                    <input name="lot_no" required placeholder="{{ __('inventory.lot.lot_no') }}" class="input input-xs input-bordered w-24">
                                    <input name="best_before" type="date" title="{{ __('inventory.lot.best_before') }}" class="input input-xs input-bordered w-32">
                                @endif
                                @if ($lineArticle?->serial_required)
                                    <input name="serial_no" required placeholder="{{ __('inventory.serial.field.serial_no') }}" class="input input-xs input-bordered w-24">
                                @endif
                                <input name="qty" type="number" step="0.0001" min="0.0001" value="{{ $lineArticle?->serial_required ? 1 : $line->openQty() }}" class="input input-xs input-bordered w-20">
                                <button type="submit" class="btn btn-xs">{{ __('procurement.action.receive') }}</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$canManage && in_array($status, ['ordered', 'partially_received'], true) ? 4 : 3"
                               icon="local_shipping" :title="__('article.no_options')" />
            @endforelse
        </x-table>
    </x-card>

    {{-- Lieferavis / ASN (E4) --}}
    @if (in_array($status, ['ordered', 'partially_received'], true))
        @if ($status !== 'draft')
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('procurement.reconcile.title') }}</h2>
                <p class="text-sm opacity-60 mb-2">{{ __('procurement.reconcile.hint') }}</p>
                <form method="POST" action="{{ route('purchase-orders.reconcile-invoice', $order) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset grow">
                        <label class="fieldset-label">{{ __('procurement.reconcile.upload') }}</label>
                        <input name="invoice_ugl" type="file" accept=".ugl,text/plain" required class="file-input file-input-sm file-input-bordered">
                    </div>
                    <x-button type="submit" tone="secondary" size="sm">{{ __('procurement.reconcile.submit') }}</x-button>
                </form>
            </x-card>
        @endif

        {{-- Inline- statt Block-Form: paart sonst mit der Inline-Form oben (lazy Raw-Block-Regex). --}}
        @php($openLines = $order->lines->filter(fn ($l) => bccomp($l->openQty(), '0', 4) > 0))
        @if ($canManage && $openLines->isNotEmpty())
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('procurement.advice.announce') }}</h2>
                @if ($canImportAdvice)
                    <form method="POST" action="{{ route('purchase-orders.advices.import', $order) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2 mb-3">
                        @csrf
                        <div class="fieldset grow">
                            <label class="fieldset-label">{{ __('procurement.advice.import') }}</label>
                            <input name="advice_xml" type="file" accept=".xml,application/xml,text/xml" required class="file-input file-input-sm file-input-bordered">
                        </div>
                        <x-button type="submit" tone="secondary" size="sm">{{ __('procurement.advice.import_submit') }}</x-button>
                    </form>
                @endif
                <form method="POST" action="{{ route('purchase-orders.advices.announce', $order) }}" class="flex flex-col gap-2">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        <div class="fieldset"><label class="fieldset-label">{{ __('procurement.advice.reference') }}</label>
                            <input name="reference" maxlength="128" class="input input-sm input-bordered"></div>
                        <div class="fieldset"><label class="fieldset-label">{{ __('procurement.field.expected_at') }}</label>
                            <input name="expected_at" type="date" class="input input-sm input-bordered"></div>
                    </div>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('procurement.field.article') }}</th>
                                <th class="text-right">{{ __('procurement.ui.open') }}</th>
                                <th class="text-right">{{ __('procurement.advice.announced_qty') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($openLines as $line)
                            <tr>
                                <td>{{ $line->description }}</td>
                                <td class="text-right tabular-nums">{{ $line->openQty() }}</td>
                                <td class="text-right"><input name="qty[{{ $line->sqid }}]" type="number" step="0.0001" min="0" value="{{ $line->openQty() }}" class="input input-xs input-bordered w-24 text-right"></td>
                            </tr>
                        @endforeach
                    </x-table>
                    <x-button type="submit" tone="primary" size="sm" class="self-start">{{ __('procurement.advice.announce') }}</x-button>
                </form>
            </x-card>
        @endif

        @if ($order->advices->isNotEmpty())
            <x-card padding="p-0">
                <h2 class="font-semibold p-4 pb-0">{{ __('procurement.advice.title') }}</h2>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('procurement.advice.reference') }}</th>
                            <th>{{ __('procurement.field.expected_at') }}</th>
                            <th>{{ __('Status') }}</th>
                            @if ($canManage)<th></th>@endif
                        </tr>
                    </x-slot:head>
                    @foreach ($order->advices as $advice)
                        <tr>
                            <td>{{ $advice->reference ?? '—' }}</td>
                            <td>{{ $advice->expected_at?->format('d.m.Y') ?? '—' }}</td>
                            <td><span class="badge badge-sm badge-ghost">{{ $advice->status->label() }}</span></td>
                            @if ($canManage)
                                <td class="text-right">
                                    @if ($advice->status->isOpen())
                                        <form method="POST" action="{{ route('purchase-orders.advices.receive', $advice) }}" class="inline">@csrf
                                            <x-button type="submit" tone="primary" size="xs">{{ __('procurement.advice.receive') }}</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('purchase-orders.advices.cancel', $advice) }}" class="inline">@csrf
                                            <button type="submit" class="btn btn-xs">{{ __('procurement.action.cancel') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
