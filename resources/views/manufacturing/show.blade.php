{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', ($order->number ?? __('manufacturing.order.title')) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.order.title'))

@php
    /** @var \App\Models\ManufacturingOrder $order */
    $status = $order->status->value;
    $isOpen = ! $order->status->isTerminal();
    $canConsume = $canManage && in_array($status, ['released', 'in_progress'], true);
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$order->article?->name . ($order->variant ? ' — ' . ($order->variant->name ?? $order->variant->option_signature) : '')">
            <x-slot:actions>
                @if ($order->procedureRun)
                    <x-icon-btn icon="checklist" size="sm" :href="route('procedure-runs.show', $order->procedureRun)" show-label>{{ __('manufacturing.order.action.procedure_run') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('manufacturing-orders.record.pdf', $order)" target="_blank" show-label>{{ __('manufacturing.record.title') }}</x-icon-btn>
                @if ($canManage)
                    @if ($status === 'draft')
                        <form method="POST" action="{{ route('manufacturing-orders.release', $order) }}">@csrf
                            <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.release') }}</x-icon-btn>
                        </form>
                    @endif
                    @if (in_array($status, ['released', 'waiting', 'blocked'], true))
                        <form method="POST" action="{{ route('manufacturing-orders.start', $order) }}">@csrf
                            <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.start') }}</x-icon-btn>
                        </form>
                    @endif
                    @if (in_array($status, ['released', 'in_progress'], true))
                        <form method="POST" action="{{ route('manufacturing-orders.reserve', $order) }}">@csrf
                            <x-icon-btn icon="inventory" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.reserve') }}</x-icon-btn>
                        </form>
                        {{-- Kommissionierliste aus den aktiven Reservierungen (Feature 048, MVP-706). --}}
                        <x-icon-btn icon="checklist" size="sm" :href="route('inventory.pick-lists.show', ['source' => 'manufacturing-order', 'sqid' => $order->sqid])" show-label>{{ __('inventory.action.pick_list') }}</x-icon-btn>
                    @endif
                    @if ($order->customer_id && $status === 'draft')
                        <form method="POST" action="{{ route('manufacturing-orders.quotation.lexoffice', $order) }}">@csrf
                            <x-icon-btn icon="request_quote" size="sm" type="submit" show-label>{{ __('Angebot an Lexoffice') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($order->customer_id && ! in_array($status, ['draft', 'cancelled'], true))
                        <form method="POST" action="{{ route('manufacturing-orders.order-confirmation.lexoffice', $order) }}">@csrf
                            <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('Auftragsbestätigung an Lexoffice') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($isOpen)
                        <x-action-form :action="route('manufacturing-orders.cancel', $order)" :confirm="__('manufacturing.order.action.cancel').'?'">
                            <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" :title="__('manufacturing.order.action.cancel')" />
                        </x-action-form>
                    @endif
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Kopfdaten --}}
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="font-mono text-sm opacity-70">{{ $order->number ?? '—' }}</div>
                <div class="text-sm opacity-70 mt-1">{{ __('manufacturing.order.field.target_qty') }}: <strong>{{ $order->target_qty }} {{ $order->unit }}</strong>
                    · {{ __('manufacturing.order.field.good') }}: <strong>{{ $order->goodTotal() }}</strong></div>
            </div>
            <span class="badge badge-sm">{{ $order->status->label() }}</span>
        </div>
    </x-card>

    {{-- Material --}}
    <x-card padding="p-0">
        <h2 class="font-semibold p-4 pb-0">{{ __('manufacturing.order.field.materials') }}</h2>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Artikel') }}</th>
                    <th class="text-right">{{ __('manufacturing.order.field.target_qty') }}</th>
                    <th class="text-right">{{ __('inventory.field.reserved') }}</th>
                    <th class="text-right">{{ __('manufacturing.order.field.consumed') }}</th>
                    <th class="text-right">{{ __('manufacturing.order.field.actual_cost') }}</th>
                    @if ($canConsume)
                        <th class="text-right">{{ __('manufacturing.order.action.consume') }}</th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($order->materials as $material)
                <tr>
                    <td>{{ $material->name_snapshot }}</td>
                    <td class="text-right tabular-nums">{{ $material->target_qty }} {{ $material->unit_snapshot }}</td>
                    <td class="text-right tabular-nums">{{ $material->reserved_qty }}</td>
                    <td class="text-right tabular-nums">{{ $material->consumed_qty }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($material->actual_cost?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                    @if ($canConsume)
                        <td class="text-right">
                            @unless ($material->is_tool)
                                <form method="POST" action="{{ route('manufacturing-orders.materials.consume', [$order, $material]) }}" class="inline-flex items-center justify-end gap-1">
                                    @csrf
                                    <input name="quantity" type="number" step="0.0001" min="0.0001" required
                                           class="input input-xs input-bordered w-20" aria-label="{{ __('manufacturing.order.field.quantity') }}">
                                    <button type="submit" class="btn btn-xs">{{ __('manufacturing.order.action.consume') }}</button>
                                </form>
                            @endunless
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$canConsume ? 6 : 5" icon="build" :title="__('article.no_options')" />
            @endforelse
        </x-table>
    </x-card>

    {{-- Vollaudit 2026-07 (M20): Fehlmaterial-/Ersatzmaterialprozess (MVP-068). --}}
    @if ($canManage && in_array($status, ['released', 'in_progress'], true))
        <x-card :title="__('Ersatzmaterial')" icon="swap_horiz" :count="$substitutes->count()">
            <form method="POST" action="{{ route('manufacturing-orders.substitutes.request', $order) }}" class="mb-3 flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset"><label for="material" class="fieldset-label">{{ __('Materialposition') }}</label>
                    <select id="material" name="material" class="select select-sm select-bordered" required>
                        @foreach ($order->materials->where('is_tool', false) as $material)
                            <option value="{{ $material->sqid }}">{{ $material->name_snapshot }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset grow"><label for="substitute_article" class="fieldset-label">{{ __('Ersatzartikel') }}</label>
                    <select id="substitute_article" name="substitute_article" class="select select-sm select-bordered w-full" required>
                        @foreach ($substituteArticles as $subArticle)
                            <option value="{{ $subArticle->sqid }}">{{ $subArticle->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset"><label for="quantity" class="fieldset-label">{{ __('manufacturing.order.field.quantity') }}</label>
                    <input id="quantity" name="quantity" type="number" step="0.0001" min="0.0001" required class="input input-sm input-bordered w-24"></div>
                <div class="fieldset grow"><label for="reason" class="fieldset-label">{{ __('Begründung') }}</label>
                    <input id="reason" name="reason" required minlength="5" maxlength="500" class="input input-sm input-bordered w-full"></div>
                <button type="submit" class="btn btn-sm">{{ __('Ersatz beantragen') }}</button>
            </form>

            @if ($substitutes->isNotEmpty())
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($substitutes as $substitute)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>
                                {{ $substitute->plannedArticle?->name ?? '—' }} → <strong>{{ $substitute->substituteArticle?->name ?? '—' }}</strong>
                                · {{ $substitute->quantity }} · {{ $substitute->reason }}
                            </span>
                            @if ($substitute->status->value === 'requested')
                                <span class="flex items-center gap-1">
                                    <x-action-form :action="route('manufacturing-orders.substitutes.decide', [$order, $substitute])">
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="btn btn-xs btn-success">{{ __('Genehmigen') }}</button>
                                    </x-action-form>
                                    <x-action-form :action="route('manufacturing-orders.substitutes.decide', [$order, $substitute])">
                                        <input type="hidden" name="decision" value="reject">
                                        <button type="submit" class="btn btn-xs btn-error">{{ __('Ablehnen') }}</button>
                                    </x-action-form>
                                </span>
                            @else
                                <x-status-badge size="sm" :tone="$substitute->status->value === 'approved' ? 'success' : 'error'">{{ $substitute->status->value }}</x-status-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    {{-- Fremdfertigung (E7) --}}
    @if ($canManage && $status === 'draft' && $suppliers->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.subcontract') }}</h2>
            <form method="POST" action="{{ route('manufacturing-orders.subcontract', $order) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow"><label for="supplier" class="fieldset-label">{{ __('procurement.field.supplier') }}</label>
                    <select id="supplier" name="supplier" class="select select-sm select-bordered w-full" required>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->sqid }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select></div>
                <button type="submit" class="btn btn-sm">{{ __('manufacturing.order.action.subcontract') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Arbeitsplatz / Kapazität (E7) --}}
    @if ($canManage && $isOpen && $workCenters->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('manufacturing.capacity.assign') }}</h2>
            <form method="POST" action="{{ route('manufacturing-orders.work-center', $order) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow"><label for="work_center" class="fieldset-label">{{ __('manufacturing.capacity.work_center') }}</label>
                    <select id="work_center" name="work_center" class="select select-sm select-bordered w-full" required>
                        @foreach ($workCenters as $wc)
                            <option value="{{ $wc->sqid }}" @selected($order->work_center_id === $wc->id)>{{ $wc->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset"><label for="minutes" class="fieldset-label">{{ __('manufacturing.capacity.minutes') }}</label>
                    <input id="minutes" name="minutes" type="number" min="0" value="{{ $order->planned_minutes ?? 0 }}" class="input input-sm input-bordered w-24"></div>
                <div class="fieldset"><label for="day" class="fieldset-label">{{ __('manufacturing.capacity.day') }}</label>
                    <input id="day" name="day" type="date" value="{{ $order->planned_start?->toDateString() }}" class="input input-sm input-bordered"></div>
                <button type="submit" class="btn btn-sm">{{ __('manufacturing.capacity.assign') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Rückmeldung + Auslieferung --}}
    @if ($canManage && in_array($status, ['in_progress', 'released'], true))
        <div class="grid md:grid-cols-2 gap-4">
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.report') }}</h2>
                <form method="POST" action="{{ route('manufacturing-orders.report', $order) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label for="produced_qty" class="fieldset-label">{{ __('manufacturing.order.field.produced') }}</label>
                        <input id="produced_qty" name="produced_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label for="good_qty" class="fieldset-label">{{ __('manufacturing.order.field.good') }}</label>
                        <input id="good_qty" name="good_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label for="scrap_qty" class="fieldset-label">{{ __('manufacturing.order.field.scrap') }}</label>
                        <input id="scrap_qty" name="scrap_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label for="rework_qty" class="fieldset-label">{{ __('manufacturing.order.field.rework') }}</label>
                        <input id="rework_qty" name="rework_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <x-button type="submit" tone="primary" size="sm">{{ __('manufacturing.order.action.report') }}</x-button>
                </form>
            </x-card>
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.deliver') }}</h2>
                <form method="POST" action="{{ route('manufacturing-orders.deliver', $order) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label for="quantity-2" class="fieldset-label">{{ __('manufacturing.order.field.quantity') }}</label>
                        <input id="quantity-2" name="quantity" type="number" step="0.0001" min="0.0001" class="input input-sm input-bordered w-28"></div>
                    <button type="submit" class="btn btn-sm">{{ __('manufacturing.order.action.deliver') }}</button>
                </form>
            </x-card>
        </div>
    @endif

    {{-- Rückmeldungen --}}
    @if ($order->reports->isNotEmpty())
        <x-card padding="p-0">
            <h2 class="font-semibold p-4 pb-0">{{ __('manufacturing.order.field.reports') }}</h2>
            <x-table bare class="table-sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('manufacturing.order.field.produced') }}</th>
                        <th>{{ __('manufacturing.order.field.good') }}</th>
                        <th>{{ __('manufacturing.order.field.scrap') }}</th>
                        <th>{{ __('manufacturing.order.field.rework') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($order->reports as $report)
                    <tr>
                        <td class="tabular-nums">{{ $report->produced_qty }}</td>
                        <td class="tabular-nums">{{ $report->good_qty }}</td>
                        <td class="tabular-nums">{{ $report->scrap_qty }}</td>
                        <td class="tabular-nums">{{ $report->rework_qty }}</td>
                        <td>{{ $report->reported_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </x-table>
            @if ($quality !== null)
                <div class="flex flex-wrap gap-x-6 gap-y-1 p-4 pt-3 text-sm border-t border-base-200">
                    <div>{{ __('manufacturing.planning.yield') }}: <strong>{{ number_format((float) $quality['yield'] * 100, 1) }} %</strong></div>
                    <div>{{ __('manufacturing.planning.scrap_rate') }}: <strong>{{ number_format((float) $quality['scrap_rate'] * 100, 1) }} %</strong></div>
                    <div>{{ __('manufacturing.planning.rework_rate') }}: <strong>{{ number_format((float) $quality['rework_rate'] * 100, 1) }} %</strong></div>
                </div>
            @endif
        </x-card>
    @endif

    {{-- Auslieferungen (E4/045: Lieferschein an Lexoffice) --}}
    @if ($order->deliveries->isNotEmpty())
        <x-card padding="p-0">
            <h2 class="font-semibold p-4 pb-0">{{ __('manufacturing.order.field.deliveries') }}</h2>
            <x-table bare class="table-sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('manufacturing.order.field.article') }}</th>
                        <th class="text-right">{{ __('manufacturing.order.field.quantity') }}</th>
                        <th>{{ __('manufacturing.order.field.facturation_status') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($order->deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->name_snapshot }}</td>
                        <td class="text-right tabular-nums">{{ $delivery->quantity }} {{ $delivery->unit }}</td>
                        <td><span class="badge badge-sm">{{ $delivery->facturation_status->label() }}</span></td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('manufacturing-orders.deliveries.pdf', [$order, $delivery]) }}" target="_blank"
                                   class="btn btn-xs btn-ghost">{{ __('manufacturing.delivery_note.title') }}</a>
                                {{-- Feature 128 (MVP-692): Lieferschein per E-Mail an den Kunden. --}}
                                <a href="{{ route('manufacturing-orders.deliveries.mail.form', [$order, $delivery]) }}"
                                   data-entry-modal-trigger
                                   class="btn btn-xs btn-ghost">{{ __('Per E-Mail senden') }}</a>
                                @if ($canManage && $delivery->facturation_target === 'lexoffice' && in_array($delivery->facturation_status->value, ['pending', 'failed'], true))
                                    <form method="POST" action="{{ route('manufacturing-orders.deliveries.lexoffice', [$order, $delivery]) }}">@csrf
                                        <button type="submit" class="btn btn-xs">{{ __('manufacturing.order.action.push_lexoffice') }}</button>
                                    </form>
                                @elseif ($delivery->facturation_status->value === 'handed_over' && $delivery->external_id)
                                    <span class="text-xs text-muted">Lexoffice: {{ $delivery->external_id }}</span>
                                @endif

                                {{-- Versandauftrag (Feature 059, Rang 20) --}}
                                @if ($delivery->shipment)
                                    <span class="badge badge-sm">{{ __('shipping.label_short') }}: {{ $delivery->shipment->status->label() }}</span>
                                    @if ($delivery->shipment->tracking_number)
                                        <span class="text-xs text-muted">{{ strtoupper($delivery->shipment->carrier) }}: {{ $delivery->shipment->tracking_number }}</span>
                                    @endif
                                @elseif ($canManage && $delivery->customer_id && $carriers->isNotEmpty())
                                    <form method="POST" action="{{ route('manufacturing-orders.deliveries.shipment', [$order, $delivery]) }}" class="join">@csrf
                                        <select name="carrier" required class="join-item select select-xs select-bordered">
                                            @foreach ($carriers as $carrier)
                                                <option value="{{ $carrier->carrier }}">{{ $carrier->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="weight_grams" value="1000" min="1" step="1"
                                               class="join-item input input-xs input-bordered w-20"
                                               title="{{ __('shipping.field.weight_grams') }}" required>
                                        {{-- Optionale Packstück-Maße (cm) — für UPS/FedEx-Dimensions --}}
                                        <input aria-label="{{ __('Länge in cm') }}" type="number" name="length_cm" min="1" max="400" step="1" placeholder="L"
                                               class="join-item input input-xs input-bordered w-12"
                                               title="{{ __('shipping.field.length_cm') }}">
                                        <input aria-label="{{ __('Breite in cm') }}" type="number" name="width_cm" min="1" max="400" step="1" placeholder="B"
                                               class="join-item input input-xs input-bordered w-12"
                                               title="{{ __('shipping.field.width_cm') }}">
                                        <input aria-label="{{ __('Höhe in cm') }}" type="number" name="height_cm" min="1" max="400" step="1" placeholder="H"
                                               class="join-item input input-xs input-bordered w-12"
                                               title="{{ __('shipping.field.height_cm') }}">
                                        <button type="submit" class="join-item btn btn-xs">{{ __('shipping.action.create') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
