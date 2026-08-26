{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $contract->number)
@section('nav-title', __('Leasingakte'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$contract->number . ' — ' . $contract->partner_name">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline>{{ $contract->status->label() }}</x-status-badge>
                <span class="badge badge-outline">{{ $contract->kind->label() }}</span>
                <span class="badge badge-outline">{{ $contract->starts_on->fdate() }} – {{ optional($contract->ends_on)->fdate() ?? __('unbefristet') }}</span>
                @if ($investmentLink !== null)
                    <a class="badge badge-info badge-outline" href="{{ route('investments.show', $investmentLink->investmentCase) }}">{{ __('Investition: :title', ['title' => $investmentLink->investmentCase->title ?? '—']) }}</a>
                @endif
            </div>
            <x-slot:actions>
                @if ($canFinance && $contract->status === \App\Enums\AssetFinance\AssetFinanceStatus::Draft)
                    <form method="POST" action="{{ route('asset-finance.activate', $contract) }}">@csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Aktivieren (Konditionen einfrieren)') }}</button>
                    </form>
                @endif
                @can('update', $contract)
                    @if (in_array($contract->status, [\App\Enums\AssetFinance\AssetFinanceStatus::Returned, \App\Enums\AssetFinance\AssetFinanceStatus::Purchased, \App\Enums\AssetFinance\AssetFinanceStatus::Terminated], true))
                        <form method="POST" action="{{ route('asset-finance.close', $contract) }}">@csrf
                            <button type="submit" class="btn btn-sm">{{ __('Abschließen') }}</button>
                        </form>
                    @endif
                    @if ($contract->status->isOpen() && $contract->status !== \App\Enums\AssetFinance\AssetFinanceStatus::Draft)
                        <details class="inline-block text-left">
                            <summary class="btn btn-sm btn-ghost text-error">{{ __('Kündigen') }}</summary>
                            <form method="POST" action="{{ route('asset-finance.terminate', $contract) }}" class="mt-2 flex items-end gap-2 rounded-box border border-base-300 p-3">
                                @csrf
                                <x-input-field name="reason" :label="__('Begründung (Pflicht)')" required />
                                <button type="submit" class="btn btn-sm btn-error">{{ __('Kündigen') }}</button>
                            </form>
                        </details>
                    @endif
                @endcan
                <x-icon-btn icon="arrow_back" size="sm" :href="route('asset-finance.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Akte')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Vertragsnummer (extern)')" class="font-mono">{{ $contract->contract_no ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Lieferant')">{{ $contract->supplier->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kostenstelle')">{{ $contract->costCenter !== null ? $contract->costCenter->code . ' — ' . $contract->costCenter->label : ($contract->cost_center_label ?? '—') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Projekt')">{{ $contract->project->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Bestellung')">{{ $contract->purchaseOrder->number ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $contract->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kündigungsfrist')">{{ $contract->notice_period_days !== null ? $contract->notice_period_days . ' ' . __('Tage') : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Versicherung')">{{ $contract->insurance_note ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @if ($contract->notes !== null)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $contract->notes }}</p>
            @endif
            <p class="mt-3 text-xs text-muted">
                {{ __('B2B-Akte: keine Bilanzierung, keine steuerliche Zurechnung, keine Verbraucherkredit-Prüfung (CCD II) — führend bleibt das Rechnungswesen.') }}
            </p>
        </x-card>

        @if ($canFinance)
            <x-card :title="__('Konditionen (vertraulich)')">
                <x-detail-grid class="grid-cols-2">
                    <x-detail-grid.row :label="__('Rate')" class="font-mono">{{ $contract->rate_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->rate_amount, 2, withThousandsSeparator: true) . ' € / ' . __("values.{$contract->payment_rhythm}") : '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Sonderzahlung')" class="font-mono">{{ $contract->special_payment !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->special_payment, 2, withThousandsSeparator: true) . ' €' : '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Restwertannahme')" class="font-mono">{{ $contract->residual_value !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->residual_value, 2, withThousandsSeparator: true) . ' €' : '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Kaufoption')" class="font-mono">{{ $contract->purchase_option_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->purchase_option_amount, 2, withThousandsSeparator: true) . ' €' : '—' }}</x-detail-grid.row>
                </x-detail-grid>

                @if ($contract->terms->isNotEmpty())
                    <x-table bare class="mt-2">
                        <x-slot:head><tr><th>{{ __('Kondition') }}</th><th class="text-right">{{ __('Betrag') }}</th></tr></x-slot:head>
                        @foreach ($contract->terms as $term)
                            <tr>
                                <td>{{ $term->kind->label() }} — {{ $term->label }}</td>
                                <td class="text-right font-mono">{{ $term->amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $term->amount, 2, withThousandsSeparator: true) . ' €' : '—' }}{{ $term->unit !== null ? ' / ' . $term->unit : '' }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif

                @if ($contract->terms_snapshot !== null)
                    <p class="mt-2 text-xs text-muted">{{ __('Eingefroren am :date (P2) — Änderungen sind auditpflichtig.', ['date' => \Illuminate\Support\Carbon::parse(data_get($contract->terms_snapshot, 'frozen_at'))->format('d.m.Y H:i')]) }}</p>
                @endif

                @if ($contract->status->isOpen())
                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm font-medium">{{ __('Kondition ergänzen') }}</summary>
                        <form method="POST" action="{{ route('asset-finance.terms.store', $contract) }}" class="mt-2 flex flex-wrap items-end gap-2">
                            @csrf
                            <x-select-field name="kind" :label="__('Art')" required>
                                @foreach ($termKinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field name="label" :label="__('Bezeichnung')" required />
                            <x-input-field name="amount" type="number" step="0.01" :label="__('Betrag')" />
                            <x-input-field name="unit" :label="__('Einheit')" />
                            <button type="submit" class="btn btn-sm">{{ __('Ergänzen') }}</button>
                        </form>
                    </details>
                @endif
            </x-card>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Assets')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Asset') }}</th><th>{{ __('Prüfstatus') }}</th><th>{{ __('Notiz') }}</th></tr></x-slot:head>
                @forelse ($contract->contractAssets as $contractAsset)
                    <tr>
                        <td>
                            @if ($contractAsset->asset !== null)
                                <a href="{{ route('assets.show', $contractAsset->asset) }}" class="link link-hover">{{ $contractAsset->asset->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{-- Prüfstatus aus der führenden Prüfpflichtenverwaltung (Vollaudit M32). --}}
                            @php $complianceStatus = $contractAsset->asset !== null ? ($complianceByAsset[$contractAsset->asset->id] ?? null) : null; @endphp
                            @if ($complianceStatus !== null)
                                @php
                                    $complianceTone = match ($complianceStatus) {
                                        \App\Enums\AssetCompliance\AssetComplianceStatus::Valid => 'success',
                                        \App\Enums\AssetCompliance\AssetComplianceStatus::DueSoon => 'warning',
                                        \App\Enums\AssetCompliance\AssetComplianceStatus::Restricted => 'warning',
                                        \App\Enums\AssetCompliance\AssetComplianceStatus::Overdue, \App\Enums\AssetCompliance\AssetComplianceStatus::Blocked => 'error',
                                        \App\Enums\AssetCompliance\AssetComplianceStatus::NotApplicable => 'neutral',
                                    };
                                @endphp
                                <x-status-badge :tone="$complianceTone" size="xs">{{ $complianceStatus->label() }}</x-status-badge>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">{{ $contractAsset->note ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('Keine Assets zugeordnet.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Fristen')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Art') }}</th><th>{{ __('Fällig') }}</th><th>{{ __('Status') }}</th><th></th></tr></x-slot:head>
                @forelse ($contract->deadlines as $deadline)
                    <tr>
                        <td>{{ $deadline->kind->label() }}</td>
                        <td>
                            {{ $deadline->due_on->fdate() }}
                            @if ($deadline->status === 'open' && $deadline->isDueForWarning())
                                <span class="badge badge-warning badge-outline badge-sm">{{ __('Vorwarnzeit läuft') }}</span>
                            @endif
                        </td>
                        <td><x-status-badge size="md" outline>{{ __("values.{$deadline->status}") }}</x-status-badge></td>
                        <td class="text-right">
                            @can('update', $contract)
                                @if ($deadline->status === 'open')
                                    <form method="POST" action="{{ route('asset-finance.deadlines.complete', $deadline) }}" class="inline">@csrf
                                        <button type="submit" class="btn btn-xs">{{ __('Erledigt') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('Keine Fristen hinterlegt.')" compact />
                @endforelse
            </x-table>
            @can('update', $contract)
                <form method="POST" action="{{ route('asset-finance.deadlines.store', $contract) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 p-3">
                    @csrf
                    <x-select-field name="kind" :label="__('Art')" required>
                        @foreach (\App\Enums\AssetFinance\AssetFinanceDeadlineKind::cases() as $kind)
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="due_on" type="date" :label="__('Fällig am')" required />
                    <x-input-field name="warn_days_before" type="number" min="0" :label="__('Vorwarnzeit (Tage)')" value="30" />
                    <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
                        <option value="">{{ __('—') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                        @endforeach
                    </x-select-field>
                    <button type="submit" class="btn btn-sm">{{ __('Frist eintragen') }}</button>
                </form>
            @endcan
        </x-card>
    </div>

    @if ($canFinance)
        <x-card :title="__('Ratenplan (Soll) & Referenz-Ist')" padding="p-0">
            @if ($projection !== null)
                <div class="grid gap-4 border-b border-base-300 p-3 sm:grid-cols-3">
                    <x-kpi-tile :label="__('Soll (Raten)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['planned'], 2, withThousandsSeparator: true) . ' €'" />
                    <x-kpi-tile :label="__('Referenziert (Eingangsrechnungen)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['referenced'], 2, withThousandsSeparator: true) . ' €'" />
                    <x-kpi-tile :label="__('Offen')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($projection['open'], 2, withThousandsSeparator: true) . ' €'" />
                </div>
            @endif
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Fällig') }}</th><th class="text-right">{{ __('Betrag') }}</th><th>{{ __('Status') }}</th><th>{{ __('Eingangsrechnung') }}</th></tr></x-slot:head>
                @forelse ($contract->rateSchedules as $schedule)
                    <tr>
                        <td>{{ $schedule->due_on->fdate() }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $schedule->amount, 2, withThousandsSeparator: true) }} €</td>
                        <td><x-status-badge size="md" outline>{{ __("values.{$schedule->status}") }}</x-status-badge></td>
                        <td>
                            @if ($schedule->incomingEInvoice !== null)
                                <span class="font-mono text-sm">#{{ $schedule->incomingEInvoice->id }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('Ratenplan entsteht bei der Aktivierung (Rate + Laufzeit).')" compact />
                @endforelse
            </x-table>
            <div class="border-t border-base-300 p-3">
                <form method="POST" action="{{ route('asset-finance.costs.snapshot', $contract) }}">@csrf
                    <button type="submit" class="btn btn-sm">{{ __('Soll-/Ist-Snapshot einfrieren') }}</button>
                </form>
            </div>
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Nutzungslimits')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Art') }}</th><th class="text-right">{{ __('Limit') }}</th><th class="text-right">{{ __('Ist') }}</th><th class="text-right">{{ __('Überschreitung') }}</th><th></th></tr></x-slot:head>
                @forelse ($contract->usageLimits as $limit)
                    <tr>
                        <td>{{ $limit->kind->label() }} ({{ __("values.{$limit->period}") }})</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $limit->limit_value, 2, withThousandsSeparator: true) }}</td>
                        <td class="text-right font-mono">{{ $limit->actual_value !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $limit->actual_value, 2, withThousandsSeparator: true) : '—' }}</td>
                        <td class="text-right font-mono {{ $limit->overrun() > 0 ? 'text-error' : '' }}">{{ $limit->overrun() > 0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($limit->overrun(), 2, withThousandsSeparator: true) : '—' }}</td>
                        <td class="text-right">
                            @can('update', $contract)
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs">{{ __('Ist-Wert') }}</summary>
                                    <form method="POST" action="{{ route('asset-finance.limits.record', $limit) }}" class="mt-2 flex items-end gap-2 rounded-box border border-base-300 p-3">
                                        @csrf
                                        <x-input-field name="actual_value" type="number" step="0.01" min="0" :label="__('Wert (leer = letzter Zählerstand)')" />
                                        <button type="submit" class="btn btn-sm">{{ __('Erfassen') }}</button>
                                    </form>
                                </details>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Keine Nutzungslimits hinterlegt.')" compact />
                @endforelse
            </x-table>
            @can('update', $contract)
                <form method="POST" action="{{ route('asset-finance.limits.store', $contract) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 p-3">
                    @csrf
                    <x-select-field name="kind" :label="__('Art')" required>
                        @foreach (\App\Enums\AssetFinance\AssetFinanceUsageLimitKind::cases() as $kind)
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="limit_value" type="number" step="0.01" min="0" :label="__('Limit')" required />
                    <x-select-field name="period" :label="__('Zeitraum')" required>
                        <option value="total">{{ __('Gesamtlaufzeit') }}</option>
                        <option value="yearly">{{ __('pro Jahr') }}</option>
                    </x-select-field>
                    <x-input-field name="overrun_fee_per_unit" type="number" step="0.0001" min="0" :label="__('Mehrkosten je Einheit')" />
                    <button type="submit" class="btn btn-sm">{{ __('Limit hinterlegen') }}</button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Optionen & Ende-Prozess')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Option') }}</th><th>{{ __('Ausübbar') }}</th><th class="text-right">{{ __('Betrag') }}</th><th></th></tr></x-slot:head>
                @forelse ($contract->options as $option)
                    <tr>
                        <td>{{ __("values.{$option->kind}") }}</td>
                        <td>
                            @if ($option->exercised_at !== null)
                                <span class="badge badge-success badge-outline">{{ __('ausgeübt am :date', ['date' => $option->exercised_at->fdate()]) }}</span>
                            @else
                                {{ optional($option->exercisable_from)->fdate() ?? '—' }} – {{ optional($option->exercisable_until)->fdate() ?? '—' }}
                            @endif
                        </td>
                        <td class="text-right font-mono">{{ $option->amount !== null && $canFinance ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $option->amount, 2, withThousandsSeparator: true) . ' €' : '—' }}</td>
                        <td class="text-right">
                            @if ($canFinance && $option->isExercisable())
                                <form method="POST" action="{{ route('asset-finance.options.exercise', $option) }}" class="inline">@csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Ausüben') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('Keine Optionen hinterlegt.')" compact />
                @endforelse
            </x-table>
            @if ($canFinance && $contract->status->isOpen())
                <form method="POST" action="{{ route('asset-finance.options.store', $contract) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 p-3">
                    @csrf
                    <x-select-field name="kind" :label="__('Option')" required>
                        <option value="purchase">{{ __('Kaufoption') }}</option>
                        <option value="extension">{{ __('Verlängerungsoption') }}</option>
                        <option value="early_termination">{{ __('Vorzeitige Kündigung') }}</option>
                    </x-select-field>
                    <x-date-range layout="split" form-control grid-class="flex flex-wrap items-end gap-2"
                                  from-name="exercisable_from" to-name="exercisable_until" type="date"
                                  :from-label="__('Ausübbar ab')" :to-label="__('Ausübbar bis')" />
                    <x-input-field name="amount" type="number" step="0.01" min="0" :label="__('Betrag')" />
                    <button type="submit" class="btn btn-sm">{{ __('Option hinterlegen') }}</button>
                </form>
            @endif

            <div class="border-t border-base-300">
                <x-table bare>
                    <x-slot:head><tr><th>{{ __('Ende-Prozess') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Nachberechnung') }}</th><th></th></tr></x-slot:head>
                    @forelse ($contract->endProcesses as $endProcess)
                        <tr>
                            <td>{{ $endProcess->kind->label() }}</td>
                            <td><x-status-badge size="md" outline>{{ __("values.{$endProcess->status}") }}</x-status-badge></td>
                            <td class="text-right font-mono">{{ $endProcess->follow_up_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $endProcess->follow_up_amount, 2, withThousandsSeparator: true) . ' €' : '—' }}</td>
                            <td class="text-right">
                                @can('update', $contract)
                                    @if ($endProcess->status !== 'completed')
                                        <form method="POST" action="{{ route('asset-finance.ends.complete', $endProcess) }}" class="inline">@csrf
                                            <button type="submit" class="btn btn-xs btn-primary">{{ __('Abschließen') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('Kein Ende-Prozess gestartet.')" compact />
                    @endforelse
                </x-table>
                @can('update', $contract)
                    @if (in_array($contract->status, [\App\Enums\AssetFinance\AssetFinanceStatus::Active, \App\Enums\AssetFinance\AssetFinanceStatus::Ending, \App\Enums\AssetFinance\AssetFinanceStatus::Extended], true))
                        <details class="border-t border-base-300 p-3">
                            <summary class="cursor-pointer text-sm font-medium">{{ __('Ende-Prozess starten (Rückgabe/Kauf/Verlängerung/Ersatz)') }}</summary>
                            <form method="POST" action="{{ route('asset-finance.ends.store', $contract) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                                @csrf
                                <x-select-field name="kind" :label="__('Entscheidung')" required>
                                    @foreach (\App\Enums\AssetFinance\AssetFinanceEndKind::cases() as $kind)
                                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                    @endforeach
                                </x-select-field>
                                <x-input-field name="new_ends_on" type="date" :label="__('Neues Ende (bei Verlängerung)')" />
                                <x-input-field name="meter_value" type="number" step="0.0001" min="0" :label="__('Kilometer/Zähler')" />
                                <x-input-field name="operating_hours" type="number" step="0.01" min="0" :label="__('Betriebsstunden')" />
                                <x-textarea-field name="condition_note" :label="__('Zustand')" rows="2"></x-textarea-field>
                                <x-textarea-field name="damages" :label="__('Schäden')" rows="2"></x-textarea-field>
                                <x-input-field name="follow_up_amount" type="number" step="0.01" :label="__('Nachberechnung/Erstattung (Referenz)')" />
                                <div class="flex items-end">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Ende-Prozess starten') }}</button>
                                </div>
                            </form>
                        </details>
                    @endif
                @endcan
            </div>
        </x-card>
    </div>
</x-page-shell>
@endsection
