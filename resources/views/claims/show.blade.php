{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $claim->number . ' — ' . $claim->title)
@section('nav-title', __('Reklamation'))

@section('content')
<x-page-shell>
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$claim->number . ' — ' . $claim->title"
                        back-route="claims.index" :back-label="__('Zur Liste')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline>{{ $claim->status->label() }}</x-status-badge>
                <x-status-badge tone="plain" size="md" outline>{{ $claim->source->label() }}</x-status-badge>
                <x-status-badge tone="plain" size="md" outline>{{ __("values.{$claim->priority}") }}</x-status-badge>
                @if ($claim->is_b2b)
                    <x-status-badge tone="warning" size="md" outline>{{ __('B2B (§ 377 HGB)') }}</x-status-badge>
                @endif
                @if ($claim->due_at !== null && $claim->status->isOpen() && $claim->due_at->isPast())
                    <x-status-badge tone="error" size="md">{{ __('überfällig seit :date', ['date' => $claim->due_at->fdatetime()]) }}</x-status-badge>
                @endif
            </div>
            <x-slot:actions>
                @can('update', $claim)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('claims.edit', $claim)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    <form method="POST" action="{{ route('claims.transition', $claim) }}" class="flex items-center gap-1">
                        @csrf
                        <select name="status" class="select select-sm select-bordered" aria-label="{{ __('Status') }}">
                            @foreach ($claim->status->allowedTransitions() as $target)
                                <option value="{{ $target->value }}">{{ $target->label() }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" tone="ghost">{{ __('Status setzen') }}</x-button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($duplicates->isNotEmpty())
        <div role="alert" class="alert alert-warning text-sm">
            {{ __('Mögliche Dubletten (gleicher Kunde/Objektbezug):') }}
            @foreach ($duplicates as $dup)
                <a class="link" href="{{ route('claims.show', $dup) }}">{{ $dup->number }}</a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Fallakte')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Kunde')">{{ $claim->customer->name ?? __('interner Mangel') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Gemeldet am')">{{ $claim->reported_at->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Rügedatum (B2B)')">{{ optional($claim->complaint_notice_at)->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Frist')">{{ optional($claim->due_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $claim->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Melder')">{{ $claim->reporter_name ?? '—' }} {{ $claim->reporter_email !== null ? '(' . $claim->reporter_email . ')' : '' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Seriennummer')" class="font-mono">{{ $claim->serial_no ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @if ($claim->description !== null)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $claim->description }}</p>
            @endif
        </x-card>

        <x-card :title="__('Betroffene Objekte & Ursache')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Auftrag')">
                    @if ($claim->diaryEntry !== null)
                        <x-order-link :entry="$claim->diaryEntry" link-class="link">{{ $claim->diaryEntry->title ?? ('#' . $claim->diaryEntry->id) }}</x-order-link>
                    @else
                        —
                    @endif
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('Projekt')">{{ $claim->project->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Service-Ticket')">{{ $claim->serviceTicket->ticket_no ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Asset')">{{ $claim->asset->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Artikel')">{{ $claim->article->name ?? '—' }}</x-detail-grid.row>
                @if ($claim->stockLot !== null)
                    <x-detail-grid.row :label="__('validation.attributes.stock_lot_id')" class="font-mono">{{ $claim->stockLot->lot_no }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('Rechnung')">{{ $claim->invoice->number ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Lieferant')">{{ $claim->supplier->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('validation.attributes.protocol_id')">{{ $claim->protocol->title ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('validation.attributes.purchase_order_id')">{{ $claim->purchaseOrder->number ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @if ($claim->serial_no !== null && $claim->stock_serial_id !== null)
                <p class="mt-1 text-xs text-muted">{{ __('claims.form.serial_linked') }}</p>
            @endif
            @can('update', $claim)
                <form method="POST" action="{{ route('claims.update', $claim) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="title" value="{{ $claim->title }}">
                    <input type="hidden" name="source" value="{{ $claim->source->value }}">
                    <input type="hidden" name="priority" value="{{ $claim->priority }}">
                    <input type="hidden" name="severity" value="{{ $claim->severity }}">
                    <label class="col-span-2 text-xs text-muted">{{ __('Ursachencodes (Klassifikationskatalog, D3)') }}</label>
                    <select name="defect_type_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Mangelart') }}">
                        <option value="">{{ __('Mangelart …') }}</option>
                        @foreach ($defectTypes as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->defect_type_classification_id === $c->id)>{{ $c->display_label }}</option>
                        @endforeach
                    </select>
                    <select name="root_cause_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Ursache') }}">
                        <option value="">{{ __('Ursache …') }}</option>
                        @foreach ($rootCauses as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->root_cause_classification_id === $c->id)>{{ $c->display_label }}</option>
                        @endforeach
                    </select>
                    <select name="goodwill_reason_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Kulanzgrund') }}">
                        <option value="">{{ __('Kulanzgrund …') }}</option>
                        @foreach ($goodwillReasons as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->goodwill_reason_classification_id === $c->id)>{{ $c->display_label }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit" tone="plain">{{ __('Speichern') }}</x-button>
                </form>
            @endcan
        </x-card>
    </div>

    <x-card :title="__('Nachweise')">
        @if ($claim->evidence->isEmpty())
            <x-empty-state icon="attach_file" :message="__('Noch keine Nachweise — Fotos, Protokolle, Messwerte oder Nachrichten hier dokumentieren.')" compact />
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($claim->evidence as $item)
                    <li>
                        <x-status-badge tone="plain" outline>{{ __("values.{$item->kind}") }}</x-status-badge>
                        <span class="font-medium">{{ $item->title }}</span>
                        @if ($item->note !== null)
                            — {{ $item->note }}
                        @endif
                        <span class="text-muted">({{ $item->recorded_at->fdatetime() }}@if ($item->recorder !== null), {{ $item->recorder->name }}@endif)</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($claim->attachments->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                @foreach ($claim->attachments as $file)
                    <x-status-badge size="md">{{ $file->original_name }}</x-status-badge>
                @endforeach
            </div>
        @endif
        @can('update', $claim)
            <form method="POST" action="{{ route('claims.evidence.store', $claim) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                @csrf
                <select name="kind" class="select select-sm select-bordered" aria-label="{{ __('Art') }}">
                    @foreach (\App\Models\Claims\ClaimEvidence::KINDS as $kind)
                        <option value="{{ $kind }}">{{ __("values.$kind") }}</option>
                    @endforeach
                </select>
                <input aria-label="{{ __('Titel') }}" name="title" class="input input-sm input-bordered w-56" placeholder="{{ __('Titel') }}" required>
                <input aria-label="{{ __('Notiz (optional)') }}" name="note" class="input input-sm input-bordered w-64" placeholder="{{ __('Notiz (optional)') }}">
                <input type="file" name="file" class="file-input file-input-sm file-input-bordered">
                <x-button type="submit">{{ __('Nachweis erfassen') }}</x-button>
            </form>
        @endcan
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Bewertung (Anspruchsart)')">
            @foreach ($claim->assessments->sortByDesc('assessed_at') as $assessment)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm {{ $assessment->status === \App\Enums\Claims\ClaimAssessmentStatus::Active ? '' : 'opacity-60' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge tone="plain" size="md" outline>{{ $assessment->claim_kind->label() }}</x-status-badge>
                        <x-status-badge :tone="$assessment->verdict->value === 'justified' ? 'success' : ($assessment->verdict->value === 'rejected' ? 'error' : 'warning')" size="md" outline>{{ $assessment->verdict->label() }}</x-status-badge>
                        @if ($assessment->status !== \App\Enums\Claims\ClaimAssessmentStatus::Active)
                            <x-status-badge>{{ __('abgelöst') }}</x-status-badge>
                        @endif
                        <span class="text-muted">{{ $assessment->assessed_at->fdatetime() }}, {{ $assessment->assessor->name ?? '—' }}</span>
                    </div>
                    <p class="mt-1">{{ $assessment->justification }}</p>
                    @if (($assessment->snapshot['serial_shipped_to_customer'] ?? null) === false)
                        <p class="mt-1 text-warning">⚠ {{ __('Seriennummer wurde laut Lager nie an diesen Kunden geliefert.') }}</p>
                    @endif
                </div>
            @endforeach
            @can('decide', $claim)
                <form method="POST" action="{{ route('claims.assess', $claim) }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        <select name="claim_kind" class="select select-sm select-bordered" aria-label="{{ __('Anspruchsart') }}">
                            @foreach (\App\Enums\Claims\ClaimKind::cases() as $kind)
                                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                        <select name="verdict" class="select select-sm select-bordered" aria-label="{{ __('Ergebnis') }}">
                            @foreach (\App\Enums\Claims\ClaimVerdict::cases() as $verdict)
                                <option value="{{ $verdict->value }}">{{ $verdict->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <textarea aria-label="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" name="justification" rows="2" class="textarea textarea-bordered textarea-sm w-full" placeholder="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" required></textarea>
                    <x-button type="submit">{{ __('Bewertung festhalten') }}</x-button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Entscheidung')">
            @foreach ($claim->decisions->sortByDesc('decided_at') as $decision)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm">
                    <x-status-badge tone="plain" size="md" outline>{{ __("values.{$decision->decision}") }}</x-status-badge>
                    <span class="text-muted">{{ $decision->decided_at->fdatetime() }}, {{ $decision->decider->name ?? '—' }}</span>
                    <p class="mt-1">{{ $decision->justification }}</p>
                </div>
            @endforeach
            @can('decide', $claim)
                <form method="POST" action="{{ route('claims.decide', $claim) }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <select name="decision" class="select select-sm select-bordered" aria-label="{{ __('Entscheidung') }}">
                        @foreach (\App\Models\Claims\ClaimDecision::DECISIONS as $d)
                            <option value="{{ $d }}">{{ __("values.$d") }}</option>
                        @endforeach
                    </select>
                    <textarea aria-label="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" name="justification" rows="2" class="textarea textarea-bordered textarea-sm w-full" placeholder="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" required></textarea>
                    <x-button type="submit">{{ __('Entscheiden') }}</x-button>
                </form>
            @endcan
        </x-card>
    </div>

    {{-- Schadensfälle (MVP-920) --}}
    <x-damage-cases-card :subject="$claim" />

    <x-card :title="__('Rückläufer (RMA)')">
        @foreach ($claim->rmaReturns as $rma)
            <div class="mb-3 rounded border border-base-300 p-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-medium">{{ $rma->rma_number }}</span>
                    <x-status-badge size="md" outline>{{ $rma->status->label() }}</x-status-badge>
                    @if ($rma->stock_state !== null)
                        <x-status-badge tone="warning" size="md" outline>{{ __('Quarantäne: :state', ['state' => $rma->stock_state->label()]) }}</x-status-badge>
                    @endif
                    @if ($rma->disposition !== null)
                        <x-status-badge tone="plain" size="md" outline>{{ $rma->disposition->label() }}</x-status-badge>
                    @endif
                    @if ($rma->serial_no !== null)
                        <span class="font-mono text-muted">SN {{ $rma->serial_no }}</span>
                    @endif
                </div>
                <p class="mt-1 text-muted">
                    @if ($rma->articleVariant !== null)
                        {{ $rma->qty !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $rma->qty, 4, trimTrailingZeros: true) . ' × ' : '' }}{{ trim(($rma->articleVariant->sku ? $rma->articleVariant->sku . ' · ' : '') . ($rma->articleVariant->name ?? $rma->article?->name ?? '')) }}@if ($rma->warehouse !== null), {{ $rma->warehouse->name }}@endif
                        @if ($rma->stockLot !== null)
                            · {{ __('validation.attributes.stock_lot_id') }} {{ $rma->stockLot->lot_no }}
                        @endif
                    @else
                        {{ __('claims.rma.without_stock') }}
                    @endif
                </p>
                @if ($rma->disposition_note !== null)
                    <p class="mt-1">{{ $rma->disposition_note }}</p>
                @endif
                @foreach ($rma->inspections as $inspection)
                    <p class="mt-1">
                        {{ __('Prüfung: :result', ['result' => __("values.{$inspection->result}")]) }}
                        @if ($inspection->findings !== null)
                            — {{ $inspection->findings }}
                        @endif
                        @if ($inspection->serial_checked)
                            <span class="{{ $inspection->serial_check_result === 'shipped_to_customer' ? 'text-success' : 'text-warning' }}">
                                ({{ $inspection->serial_check_result === 'shipped_to_customer' ? __('Seriennummer an Kunden geliefert') : __('Seriennummer NICHT an Kunden geliefert') }})
                            </span>
                        @endif
                    </p>
                @endforeach
                {{-- Retourenlabels (MVP-917) --}}
                @foreach ($rma->returnShipments as $shipment)
                    <p class="mt-1 flex flex-wrap items-center gap-2">
                        <x-icon name="assignment_return" />
                        <span>{{ __('claims.return_label.title') }}: {{ strtoupper($shipment->carrier) }} {{ $shipment->tracking_number }}</span>
                        <x-status-badge tone="plain">{{ $shipment->status->label() }}</x-status-badge>
                        @can('warehouse', $claim)
                            <a class="link text-xs" href="{{ route('claims.rma.return-label.download', [$rma, $shipment]) }}">{{ __('claims.return_label.download') }}</a>
                        @endcan
                    </p>
                @endforeach
                @can('warehouse', $claim)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($rma->status === \App\Enums\Claims\ClaimRmaStatus::Announced && $returnCarriers !== [])
                            <form method="POST" action="{{ route('claims.rma.return-label', $rma) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <select name="carrier" class="select select-xs select-bordered" aria-label="{{ __('shipping.field.carrier') }}" required>
                                    @foreach ($returnCarriers as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="weight_grams" value="1000" min="1" step="1" required class="input input-xs input-bordered w-24"
                                       aria-label="{{ __('shipping.field.weight_grams') }}" title="{{ __('shipping.field.weight_grams') }}">
                                <x-button type="submit" tone="plain" size="xs">{{ __('claims.return_label.create') }}</x-button>
                            </form>
                        @endif
                        @if ($rma->status === \App\Enums\Claims\ClaimRmaStatus::Announced)
                            <x-button type="button" size="xs" data-open-dialog="claim-rma-receive-{{ $rma->id }}">{{ __('Wareneingang buchen') }}</x-button>
                        @endif
                        @if (in_array($rma->status, [\App\Enums\Claims\ClaimRmaStatus::Received, \App\Enums\Claims\ClaimRmaStatus::Inspecting], true))
                            <form method="POST" action="{{ route('claims.rma.inspect', $rma) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <select name="result" class="select select-xs select-bordered" aria-label="{{ __('Prüfergebnis') }}">
                                    @foreach (\App\Models\Claims\ClaimInspection::RESULTS as $result)
                                        <option value="{{ $result }}">{{ __("values.$result") }}</option>
                                    @endforeach
                                </select>
                                <input aria-label="{{ __('Befund') }}" name="findings" class="input input-xs input-bordered w-48" placeholder="{{ __('Befund') }}">
                                <x-button type="submit" tone="plain" size="xs">{{ __('Prüfen') }}</x-button>
                            </form>
                            <x-button type="button" tone="plain" size="xs" data-open-dialog="claim-rma-disposition-{{ $rma->id }}">{{ __('Verwendung entscheiden') }}</x-button>
                        @endif
                    </div>
                @endcan
            </div>
        @endforeach
        @can('warehouse', $claim)
            <x-button type="button" class="mt-2" data-open-dialog="claim-rma-create">{{ __('Rücksendung ankündigen') }}</x-button>
        @endcan
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Maßnahmen')">
            @foreach ($claim->actions as $action)
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge tone="plain" size="md" outline>{{ $action->kind->label() }}</x-status-badge>
                    <span class="font-medium">{{ $action->title }}</span>
                    <x-status-badge size="md" outline>{{ $action->status->label() }}</x-status-badge>
                    @if ($action->assignee !== null)
                        <span class="text-muted">{{ $action->assignee->name }}</span>
                    @endif
                    @if ($action->followUp instanceof \App\Models\Diary\DiaryEntry)
                        <span class="text-muted">{{ __('claims.action.follow_up_order') }}:</span>
                        <x-order-link :entry="$action->followUp" link-class="link">{{ $action->followUp->title ?? ('#' . $action->followUp->id) }}</x-order-link>
                    @endif
                    @can('update', $claim)
                        <form method="POST" action="{{ route('claims.actions.update', $action) }}" class="flex items-center gap-1">
                            @csrf
                            @method('PUT')
                            <select name="status" class="select select-xs select-bordered" aria-label="{{ __('Status') }}">
                                @foreach (\App\Enums\Claims\ClaimActionStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($action->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <select name="follow_up_order_id" class="select select-xs select-bordered max-w-48" aria-label="{{ __('claims.action.follow_up_order') }}">
                                <option value="">{{ __('claims.action.follow_up_none') }}</option>
                                @foreach ($followUpOrders as $order)
                                    <option value="{{ $order->sqid }}" @selected((int) $action->follow_up_id === (int) $order->id && $action->followUp instanceof \App\Models\Diary\DiaryEntry)>{{ $order->title ?? ('#' . $order->id) }}</option>
                                @endforeach
                            </select>
                            <x-button type="submit" tone="plain" size="xs">{{ __('OK') }}</x-button>
                        </form>
                    @endcan
                </div>
            @endforeach
            @can('update', $claim)
                <form method="POST" action="{{ route('claims.actions.store', $claim) }}" class="mt-2 flex flex-wrap items-end gap-2 text-sm">
                    @csrf
                    <select name="kind" class="select select-sm select-bordered" aria-label="{{ __('Art') }}">
                        @foreach (\App\Enums\Claims\ClaimActionKind::cases() as $kind)
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endforeach
                    </select>
                    <input aria-label="{{ __('Titel') }}" name="title" class="input input-sm input-bordered w-56" placeholder="{{ __('Titel') }}" required>
                    <input name="due_at" type="date" class="input input-sm input-bordered" aria-label="{{ __('Frist') }}">
                    <select name="assigned_user_id" class="select select-sm select-bordered" aria-label="{{ __('Verantwortlich') }}">
                        <option value="">{{ __('Verantwortlich') }}</option>
                        @foreach ($users as $assignee)
                            <option value="{{ $assignee->sqid }}">{{ $assignee->name }}</option>
                        @endforeach
                    </select>
                    <select name="follow_up_order_id" class="select select-sm select-bordered max-w-56" aria-label="{{ __('claims.action.follow_up_order') }}">
                        <option value="">{{ __('claims.action.follow_up_none') }}</option>
                        @foreach ($followUpOrders as $order)
                            <option value="{{ $order->sqid }}">{{ $order->title ?? ('#' . $order->id) }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit">{{ __('Maßnahme anlegen') }}</x-button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Kaufmännische Folgen')">
            @foreach ($claim->financialOutcomes as $outcome)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge tone="plain" size="md" outline>{{ $outcome->kind->label() }}</x-status-badge>
                        <x-status-badge size="md" outline>{{ $outcome->status->label() }}</x-status-badge>
                        @if ($outcome->amount !== null)
                            <span class="font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $outcome->amount, 2, withThousandsSeparator: true) }} {{ $outcome->currency->value }}</span>
                        @endif
                        @if ($outcome->invoice !== null)
                            <span class="text-muted">{{ __('claims.financial.source_invoice', ['number' => $outcome->invoice->number]) }}</span>
                        @endif
                        @if ($outcome->resultInvoice !== null)
                            <x-status-badge size="md">{{ __('Beleg :number', ['number' => $outcome->resultInvoice->number]) }}</x-status-badge>
                        @endif
                        @if ($outcome->external_reference !== null)
                            <x-status-badge tone="info" size="md" outline>{{ __('Extern: :ref', ['ref' => $outcome->external_reference]) }}</x-status-badge>
                        @endif
                    </div>
                    <p class="mt-1">{{ $outcome->justification }}</p>
                    @can('finance', $claim)
                        <div class="mt-1 flex gap-2">
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Proposed)
                                <form method="POST" action="{{ route('claims.financial.approve', $outcome) }}">
                                    @csrf
                                    <x-button type="submit" size="xs">{{ __('Freigeben (Vier-Augen)') }}</x-button>
                                </form>
                                <x-action-form :action="route('claims.financial.reject', $outcome)" :confirm="__('claims.financial.reject_confirm')" :confirm-label="__('claims.financial.reject')">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('claims.financial.reject') }}</x-button>
                                </x-action-form>
                            @endif
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Approved)
                                <form method="POST" action="{{ route('claims.financial.execute', $outcome) }}">
                                    @csrf
                                    <x-button type="submit" size="xs">{{ __('Ausführen / Beleg erzeugen') }}</x-button>
                                </form>
                            @endif
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Executed && $outcome->result_invoice_id === null && $outcome->external_reference === null && $outcome->kind->producesInvoice())
                                <form method="POST" action="{{ route('claims.financial.reference', $outcome) }}" class="flex items-center gap-1">
                                    @csrf
                                    <input aria-label="{{ __('Belegnummer (extern)') }}" name="external_reference" class="input input-xs input-bordered w-44" placeholder="{{ __('Belegnummer (extern)') }}" required maxlength="100">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('Nachtragen') }}</x-button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            @endforeach
            @can('update', $claim)
                <x-button type="button" class="mt-2" data-open-dialog="claim-financial-create">{{ __('Folge vorschlagen') }}</x-button>
            @endcan
        </x-card>
    </div>

    <x-card :title="__('Lieferanten-/Herstellerregress')">
        @foreach ($claim->supplierRecourses as $recourse)
            <div class="mb-2 rounded border border-base-300 p-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ $recourse->supplier->name ?? '—' }}</span>
                    <x-status-badge size="md" outline>{{ $recourse->status->label() }}</x-status-badge>
                    @if ($recourse->response_due_at !== null)
                        <span class="text-muted">{{ __('Antwortfrist: :date', ['date' => $recourse->response_due_at->fdatetime()]) }}</span>
                    @elseif ($recourse->response_due_days !== null)
                        <span class="text-muted">{{ __('claims.recourse.due_days', ['days' => $recourse->response_due_days]) }}</span>
                    @endif
                    @if ($recourse->amount_claimed !== null)
                        <span class="font-mono">{{ __('gefordert') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $recourse->amount_claimed, 2, withThousandsSeparator: true) }}</span>
                    @endif
                    @if ($recourse->amount_recovered !== null)
                        <span class="font-mono text-success">{{ __('erstattet') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $recourse->amount_recovered, 2, withThousandsSeparator: true) }}</span>
                    @endif
                </div>
                <p class="mt-1 flex flex-wrap gap-x-3 text-muted">
                    @if ($recourse->purchaseOrder !== null)
                        <span>{{ __('validation.attributes.purchase_order_id') }} {{ $recourse->purchaseOrder->number }}</span>
                    @endif
                    @if ($recourse->incomingEInvoice !== null)
                        <span>{{ __('claims.recourse.field_incoming_invoice') }} {{ $recourse->incomingEInvoice->invoice_number ?? '#' . $recourse->incomingEInvoice->id }}</span>
                    @endif
                    @if ($recourse->article !== null)
                        <span>{{ __('Artikel') }} {{ $recourse->article->name }}</span>
                    @endif
                    @if ($recourse->serial_no !== null)
                        <span class="font-mono">SN {{ $recourse->serial_no }}</span>
                    @endif
                    @if ($recourse->external_reference !== null)
                        <span>{{ __('Externe RMA-Nr.') }} {{ $recourse->external_reference }}</span>
                    @endif
                </p>
                @if ($recourse->warranty_terms !== null)
                    <p class="mt-1 whitespace-pre-line"><span class="text-muted">{{ __('validation.attributes.warranty_terms') }}:</span> {{ $recourse->warranty_terms }}</p>
                @endif
                @if ($recourse->outcome_note !== null)
                    <p class="mt-1">{{ $recourse->outcome_note }}</p>
                @endif
                @can('recourse', $claim)
                    <form method="POST" action="{{ route('claims.recourses.update', $recourse) }}" class="mt-1 flex flex-wrap items-center gap-1">
                        @csrf
                        @method('PUT')
                        <select name="status" class="select select-xs select-bordered" aria-label="{{ __('Status') }}">
                            @foreach (\App\Enums\Claims\ClaimRecourseStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($recourse->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @if ($recourse->submitted_at === null)
                            <input aria-label="{{ __('validation.attributes.response_due_days') }}" title="{{ __('validation.attributes.response_due_days') }}" name="response_due_days" type="number" min="1" max="365" step="1"
                                   class="input input-xs input-bordered w-24" value="{{ $recourse->response_due_days }}" placeholder="{{ (int) config('claims.recourse_response_days', 14) }}">
                        @endif
                        <input aria-label="{{ __('Erstattet') }}" name="amount_recovered" type="number" step="0.01" min="0" class="input input-xs input-bordered w-28" placeholder="{{ __('Erstattet') }}">
                        <input aria-label="{{ __('Ergebnis (optional)') }}" name="outcome_note" class="input input-xs input-bordered w-48" placeholder="{{ __('Ergebnis (optional)') }}">
                        <x-button type="submit" tone="plain" size="xs">{{ __('Aktualisieren') }}</x-button>
                    </form>
                @endcan
            </div>
        @endforeach
        @can('recourse', $claim)
            <x-button type="button" class="mt-2" data-open-dialog="claim-recourse-create">{{ __('Regress anlegen') }}</x-button>
        @endcan
    </x-card>
</x-page-shell>

{{-- Lokale Dialoge (geöffnet via data-open-dialog) --}}
@can('update', $claim)
    @include('claims._financial_dialog', ['claim' => $claim, 'invoices' => $financialInvoices, 'localBilling' => $localBilling])
@endcan
@if ($recourseOptions !== null)
    @include('claims._recourse_dialog', ['claim' => $claim, 'recourseOptions' => $recourseOptions])
@endif
@can('warehouse', $claim)
    @include('claims._rma_dialog', ['claim' => $claim, 'stock' => $stock])
    @foreach ($claim->rmaReturns as $rma)
        @if ($rma->status === \App\Enums\Claims\ClaimRmaStatus::Announced)
            @include('claims._rma_receive_dialog', ['claim' => $claim, 'rma' => $rma, 'stock' => $stock])
        @elseif (in_array($rma->status, [\App\Enums\Claims\ClaimRmaStatus::Received, \App\Enums\Claims\ClaimRmaStatus::Inspecting], true))
            @include('claims._rma_disposition_dialog', ['rma' => $rma, 'stock' => $stock])
        @endif
    @endforeach
@endcan
@endsection
