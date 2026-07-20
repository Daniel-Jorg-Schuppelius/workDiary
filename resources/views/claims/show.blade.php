@extends('layouts.app')

@section('title', $claim->number . ' — ' . $claim->title)
@section('nav-title', __('Reklamation'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-page-toolbar :title="$claim->number . ' — ' . $claim->title">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge size="md" outline>{{ $claim->status->label() }}</x-status-badge>
            <span class="badge badge-outline">{{ $claim->source->label() }}</span>
            <span class="badge badge-outline">{{ __("values.{$claim->priority}") }}</span>
            @if ($claim->is_b2b)
                <span class="badge badge-warning badge-outline">{{ __('B2B (§ 377 HGB)') }}</span>
            @endif
            @if ($claim->due_at !== null && $claim->status->isOpen() && $claim->due_at->isPast())
                <span class="badge badge-error">{{ __('überfällig seit :date', ['date' => $claim->due_at->fdatetime()]) }}</span>
            @endif
        </div>
        <x-slot:actions>
            @can('update', $claim)
                <form method="POST" action="{{ route('claims.transition', $claim) }}" class="flex items-center gap-1">
                    @csrf
                    <select name="status" class="select select-sm select-bordered" aria-label="{{ __('Status') }}">
                        @foreach ($claim->status->allowedTransitions() as $target)
                            <option value="{{ $target->value }}">{{ $target->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm">{{ __('Status setzen') }}</button>
                </form>
            @endcan
            <x-icon-btn icon="arrow_back" size="sm" :href="route('claims.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($duplicates->isNotEmpty())
        <div class="alert alert-warning text-sm">
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
                        <a class="link" href="{{ route('diary.show', $claim->diaryEntry) }}">{{ $claim->diaryEntry->title ?? ('#' . $claim->diaryEntry->id) }}</a>
                    @else
                        —
                    @endif
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('Projekt')">{{ $claim->project->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Service-Ticket')">{{ $claim->serviceTicket->ticket_no ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Asset')">{{ $claim->asset->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Artikel')">{{ $claim->article->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Rechnung')">{{ $claim->invoice->number ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Lieferant')">{{ $claim->supplier->name ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @can('update', $claim)
                <form method="POST" action="{{ route('claims.update', $claim) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="title" value="{{ $claim->title }}">
                    <input type="hidden" name="source" value="{{ $claim->source->value }}">
                    <input type="hidden" name="priority" value="{{ $claim->priority }}">
                    <input type="hidden" name="severity" value="{{ $claim->severity }}">
                    <label class="col-span-2 text-xs text-base-content/60">{{ __('Ursachencodes (Klassifikationskatalog, D3)') }}</label>
                    <select name="defect_type_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Mangelart') }}">
                        <option value="">{{ __('Mangelart …') }}</option>
                        @foreach ($defectTypes as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->defect_type_classification_id === $c->id)>{{ $c->label }}</option>
                        @endforeach
                    </select>
                    <select name="root_cause_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Ursache') }}">
                        <option value="">{{ __('Ursache …') }}</option>
                        @foreach ($rootCauses as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->root_cause_classification_id === $c->id)>{{ $c->label }}</option>
                        @endforeach
                    </select>
                    <select name="goodwill_reason_classification_id" class="select select-sm select-bordered" aria-label="{{ __('Kulanzgrund') }}">
                        <option value="">{{ __('Kulanzgrund …') }}</option>
                        @foreach ($goodwillReasons as $c)
                            <option value="{{ $c->sqid }}" @selected($claim->goodwill_reason_classification_id === $c->id)>{{ $c->label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm">{{ __('Speichern') }}</button>
                </form>
            @endcan
        </x-card>
    </div>

    <x-card :title="__('Nachweise')">
        @if ($claim->evidence->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Noch keine Nachweise — Fotos, Protokolle, Messwerte oder Nachrichten hier dokumentieren.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($claim->evidence as $item)
                    <li>
                        <span class="badge badge-outline badge-sm">{{ __("values.{$item->kind}") }}</span>
                        <span class="font-medium">{{ $item->title }}</span>
                        @if ($item->note !== null)
                            — {{ $item->note }}
                        @endif
                        <span class="text-base-content/50">({{ $item->recorded_at->fdatetime() }}@if ($item->recorder !== null), {{ $item->recorder->name }}@endif)</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($claim->attachments->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                @foreach ($claim->attachments as $file)
                    <span class="badge badge-ghost">{{ $file->original_name }}</span>
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
                <input name="title" class="input input-sm input-bordered w-56" placeholder="{{ __('Titel') }}" required>
                <input name="note" class="input input-sm input-bordered w-64" placeholder="{{ __('Notiz (optional)') }}">
                <input type="file" name="file" class="file-input file-input-sm file-input-bordered">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Nachweis erfassen') }}</button>
            </form>
        @endcan
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Bewertung (Anspruchsart)')">
            @foreach ($claim->assessments->sortByDesc('assessed_at') as $assessment)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm {{ $assessment->status === 'active' ? '' : 'opacity-60' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-outline">{{ $assessment->claim_kind->label() }}</span>
                        <span class="badge {{ $assessment->verdict->value === 'justified' ? 'badge-success' : ($assessment->verdict->value === 'rejected' ? 'badge-error' : 'badge-warning') }} badge-outline">{{ $assessment->verdict->label() }}</span>
                        @if ($assessment->status !== 'active')
                            <span class="badge badge-ghost badge-sm">{{ __('abgelöst') }}</span>
                        @endif
                        <span class="text-base-content/50">{{ $assessment->assessed_at->fdatetime() }}, {{ $assessment->assessor->name ?? '—' }}</span>
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
                    <textarea name="justification" rows="2" class="textarea textarea-bordered textarea-sm w-full" placeholder="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Bewertung festhalten') }}</button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Entscheidung')">
            @foreach ($claim->decisions->sortByDesc('decided_at') as $decision)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm">
                    <span class="badge badge-outline">{{ __("values.{$decision->decision}") }}</span>
                    <span class="text-base-content/50">{{ $decision->decided_at->fdatetime() }}, {{ $decision->decider->name ?? '—' }}</span>
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
                    <textarea name="justification" rows="2" class="textarea textarea-bordered textarea-sm w-full" placeholder="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Entscheiden') }}</button>
                </form>
            @endcan
        </x-card>
    </div>

    <x-card :title="__('Rückläufer (RMA)')">
        @foreach ($claim->rmaReturns as $rma)
            <div class="mb-3 rounded border border-base-300 p-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono font-medium">{{ $rma->rma_number }}</span>
                    <x-status-badge size="md" outline>{{ $rma->status->label() }}</x-status-badge>
                    @if ($rma->stock_state !== null)
                        <span class="badge badge-warning badge-outline">{{ __('Quarantäne: :state', ['state' => $rma->stock_state]) }}</span>
                    @endif
                    @if ($rma->disposition !== null)
                        <span class="badge badge-outline">{{ $rma->disposition->label() }}</span>
                    @endif
                    @if ($rma->serial_no !== null)
                        <span class="font-mono text-base-content/60">SN {{ $rma->serial_no }}</span>
                    @endif
                </div>
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
                @can('warehouse', $claim)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($rma->status === \App\Enums\Claims\ClaimRmaStatus::Announced)
                            <form method="POST" action="{{ route('claims.rma.receive', $rma) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <select name="stock_state" class="select select-xs select-bordered" aria-label="{{ __('Quarantäne-Zustand') }}">
                                    @foreach (\App\Services\Claims\ClaimRmaService::QUARANTINE_STATES as $state)
                                        <option value="{{ $state }}">{{ $state }}</option>
                                    @endforeach
                                </select>
                                <input name="condition_note" class="input input-xs input-bordered w-48" placeholder="{{ __('Zustand (optional)') }}">
                                <button type="submit" class="btn btn-xs btn-primary">{{ __('Wareneingang buchen') }}</button>
                            </form>
                        @endif
                        @if (in_array($rma->status, [\App\Enums\Claims\ClaimRmaStatus::Received, \App\Enums\Claims\ClaimRmaStatus::Inspecting], true))
                            <form method="POST" action="{{ route('claims.rma.inspect', $rma) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <select name="result" class="select select-xs select-bordered" aria-label="{{ __('Prüfergebnis') }}">
                                    @foreach (\App\Models\Claims\ClaimInspection::RESULTS as $result)
                                        <option value="{{ $result }}">{{ __("values.$result") }}</option>
                                    @endforeach
                                </select>
                                <input name="findings" class="input input-xs input-bordered w-48" placeholder="{{ __('Befund') }}">
                                <button type="submit" class="btn btn-xs">{{ __('Prüfen') }}</button>
                            </form>
                            <form method="POST" action="{{ route('claims.rma.disposition', $rma) }}" class="flex flex-wrap items-center gap-1">
                                @csrf
                                <select name="disposition" class="select select-xs select-bordered" aria-label="{{ __('Verwendung') }}">
                                    @foreach (\App\Enums\Claims\ClaimRmaDisposition::cases() as $disp)
                                        <option value="{{ $disp->value }}">{{ $disp->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-xs">{{ __('Verwendung entscheiden') }}</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>
        @endforeach
        @can('warehouse', $claim)
            <form method="POST" action="{{ route('claims.rma.store', $claim) }}" class="mt-2 flex flex-wrap items-end gap-2 text-sm">
                @csrf
                <input name="serial_no" class="input input-sm input-bordered w-48" placeholder="{{ __('Seriennummer (optional)') }}" value="{{ $claim->serial_no }}">
                <input name="expected_at" type="date" class="input input-sm input-bordered" aria-label="{{ __('Erwartet am') }}">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Rücksendung ankündigen') }}</button>
            </form>
        @endcan
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Maßnahmen')">
            @foreach ($claim->actions as $action)
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge badge-outline">{{ $action->kind->label() }}</span>
                    <span class="font-medium">{{ $action->title }}</span>
                    <x-status-badge size="md" outline>{{ $action->status->label() }}</x-status-badge>
                    @if ($action->assignee !== null)
                        <span class="text-base-content/60">{{ $action->assignee->name }}</span>
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
                            <button type="submit" class="btn btn-xs">{{ __('OK') }}</button>
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
                    <input name="title" class="input input-sm input-bordered w-56" placeholder="{{ __('Titel') }}" required>
                    <input name="due_at" type="date" class="input input-sm input-bordered" aria-label="{{ __('Frist') }}">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Maßnahme anlegen') }}</button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Kaufmännische Folgen')">
            @foreach ($claim->financialOutcomes as $outcome)
                <div class="mb-2 rounded border border-base-300 p-2 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-outline">{{ $outcome->kind->label() }}</span>
                        <x-status-badge size="md" outline>{{ $outcome->status->label() }}</x-status-badge>
                        @if ($outcome->amount !== null)
                            <span class="font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $outcome->amount, 2, withThousandsSeparator: true) }} {{ $outcome->currency->value }}</span>
                        @endif
                        @if ($outcome->resultInvoice !== null)
                            <span class="badge badge-ghost">{{ __('Beleg :number', ['number' => $outcome->resultInvoice->number]) }}</span>
                        @endif
                        @if ($outcome->external_reference !== null)
                            <span class="badge badge-info badge-outline">{{ __('Extern: :ref', ['ref' => $outcome->external_reference]) }}</span>
                        @endif
                    </div>
                    <p class="mt-1">{{ $outcome->justification }}</p>
                    @can('finance', $claim)
                        <div class="mt-1 flex gap-2">
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Proposed)
                                <form method="POST" action="{{ route('claims.financial.approve', $outcome) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Freigeben (Vier-Augen)') }}</button>
                                </form>
                            @endif
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Approved)
                                <form method="POST" action="{{ route('claims.financial.execute', $outcome) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Ausführen / Beleg erzeugen') }}</button>
                                </form>
                            @endif
                            @if ($outcome->status === \App\Enums\Claims\ClaimFinancialStatus::Executed && $outcome->result_invoice_id === null && $outcome->external_reference === null && $outcome->kind->producesInvoice())
                                <form method="POST" action="{{ route('claims.financial.reference', $outcome) }}" class="flex items-center gap-1">
                                    @csrf
                                    <input name="external_reference" class="input input-xs input-bordered w-44" placeholder="{{ __('Belegnummer (extern)') }}" required maxlength="100">
                                    <button type="submit" class="btn btn-xs">{{ __('Nachtragen') }}</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            @endforeach
            @can('update', $claim)
                <form method="POST" action="{{ route('claims.financial.store', $claim) }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <div class="flex flex-wrap gap-2">
                        <select name="kind" class="select select-sm select-bordered" aria-label="{{ __('Art') }}">
                            @foreach (\App\Enums\Claims\ClaimFinancialKind::cases() as $kind)
                                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                        <input name="amount" type="number" step="0.01" min="0" class="input input-sm input-bordered w-32" placeholder="{{ __('Betrag') }}">
                    </div>
                    <textarea name="justification" rows="2" class="textarea textarea-bordered textarea-sm w-full" placeholder="{{ __('Pflichtbegründung (min. 10 Zeichen)') }}" required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Folge vorschlagen') }}</button>
                </form>
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
                        <span class="text-base-content/60">{{ __('Antwortfrist: :date', ['date' => $recourse->response_due_at->fdatetime()]) }}</span>
                    @endif
                    @if ($recourse->amount_claimed !== null)
                        <span class="font-mono">{{ __('gefordert') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $recourse->amount_claimed, 2, withThousandsSeparator: true) }}</span>
                    @endif
                    @if ($recourse->amount_recovered !== null)
                        <span class="font-mono text-success">{{ __('erstattet') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $recourse->amount_recovered, 2, withThousandsSeparator: true) }}</span>
                    @endif
                </div>
                @can('recourse', $claim)
                    <form method="POST" action="{{ route('claims.recourses.update', $recourse) }}" class="mt-1 flex flex-wrap items-center gap-1">
                        @csrf
                        @method('PUT')
                        <select name="status" class="select select-xs select-bordered" aria-label="{{ __('Status') }}">
                            @foreach (\App\Enums\Claims\ClaimRecourseStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($recourse->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input name="amount_recovered" type="number" step="0.01" min="0" class="input input-xs input-bordered w-28" placeholder="{{ __('Erstattet') }}">
                        <input name="outcome_note" class="input input-xs input-bordered w-48" placeholder="{{ __('Ergebnis (optional)') }}">
                        <button type="submit" class="btn btn-xs">{{ __('Aktualisieren') }}</button>
                    </form>
                @endcan
            </div>
        @endforeach
        @can('recourse', $claim)
            <form method="POST" action="{{ route('claims.recourses.store', $claim) }}" class="mt-2 flex flex-wrap items-end gap-2 text-sm">
                @csrf
                <input name="supplier_id" class="input input-sm input-bordered w-40" placeholder="{{ __('Lieferant (Sqid)') }}" value="{{ $claim->supplier?->sqid }}" required>
                <input name="external_reference" class="input input-sm input-bordered w-40" placeholder="{{ __('Externe RMA-Nr.') }}">
                <input name="amount_claimed" type="number" step="0.01" min="0" class="input input-sm input-bordered w-32" placeholder="{{ __('Forderung') }}">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Regress anlegen') }}</button>
            </form>
        @endcan
    </x-card>
</x-page-shell>
@endsection
