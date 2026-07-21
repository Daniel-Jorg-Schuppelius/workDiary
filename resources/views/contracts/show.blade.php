@extends('layouts.app')

@section('title', $contract->number)
@section('nav-title', __('Vertragsakte'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-page-toolbar :title="$contract->number . ' — ' . $contract->title">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge size="md" outline>{{ $contract->status->label() }}</x-status-badge>
            <span class="badge badge-outline">{{ $contract->kind->label() }}</span>
            <span class="badge badge-outline">{{ $contract->term_kind->label() }}</span>
            <span class="badge badge-outline">{{ $contract->starts_on->fdate() }} – {{ optional($contract->ends_on)->fdate() ?? __('unbefristet') }}</span>
        </div>
        <x-slot:actions>
            @can('update', $contract)
                @if ($contract->status === \App\Enums\Contract\ContractStatus::Draft)
                    <form method="POST" action="{{ route('contracts.activate', $contract) }}">@csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Aktivieren') }}</button>
                    </form>
                @endif
                @if ($contract->status->isOpen())
                    <form method="POST" action="{{ route('contracts.end', $contract) }}">@csrf
                        <button type="submit" class="btn btn-sm">{{ __('Beenden') }}</button>
                    </form>
                @endif
                @if (in_array($contract->status, [\App\Enums\Contract\ContractStatus::Draft, \App\Enums\Contract\ContractStatus::Active], true))
                    <details class="inline-block text-left">
                        <summary class="btn btn-sm btn-ghost text-error">{{ __('Kündigen') }}</summary>
                        <form method="POST" action="{{ route('contracts.terminate', $contract) }}" class="mt-2 flex items-end gap-2 rounded-box border border-base-300 p-3">
                            @csrf
                            <x-input-field name="reason" :label="__('Begründung (Pflicht)')" required />
                            <button type="submit" class="btn btn-sm btn-error">{{ __('Kündigen') }}</button>
                        </form>
                    </details>
                @endif
            @endcan
            <x-icon-btn icon="arrow_back" size="sm" :href="route('contracts.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Vertragsdaten')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Vertragspartner')">{{ $contract->partnerLabel() ?: '—' }} <span class="text-base-content/50">({{ $contract->partner_type->label() }})</span></x-detail-grid.row>
                <x-detail-grid.row :label="__('Kündigungsfrist')">{{ $contract->notice_period_days !== null ? $contract->notice_period_days . ' ' . __('Tage') : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Mindestlaufzeit')">{{ $contract->min_term_months !== null ? $contract->min_term_months . ' ' . __('Monate') : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Automatische Verlängerung')">{{ $contract->auto_renew ? __('ja, um :n Monate', ['n' => $contract->renew_period_months ?? '—']) : __('nein') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Vertragswert')">{{ $contract->value_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->value_amount, 2, withThousandsSeparator: true) . ' ' . $contract->currency->value : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $contract->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Dokument')">{{ $contract->document->title ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            <div class="mt-3 border-t border-base-300 pt-2 text-sm">
                <div class="font-medium">{{ __('Indexierung') }}: {{ $contract->indexation_method->label() }}</div>
                @if ($contract->indexation_method !== \App\Enums\Contract\IndexationMethod::None)
                    <div class="text-base-content/70">
                        {{ $contract->indexation_value !== null ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $contract->indexation_value, 4, withThousandsSeparator: true), '0'), ',') : '' }}
                        @if ($contract->indexation_review_on) · {{ __('Stichtag') }} {{ $contract->indexation_review_on->fdate() }} @endif
                        @if ($contract->indexation_note) · {{ $contract->indexation_note }} @endif
                    </div>
                @endif
            </div>
            @if ($contract->notes !== null)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $contract->notes }}</p>
            @endif
        </x-card>

        <x-card :title="__('Kündigung & Laufzeit')">
            @if ($nextTermination !== null)
                <div class="rounded-box bg-base-200 p-4">
                    <div class="text-xs uppercase text-base-content/60">{{ __('Nächstmöglich kündbar zum') }}</div>
                    <div class="text-2xl font-semibold">{{ $nextTermination->fdate() }}</div>
                    @if ($noticeDeadline !== null)
                        <div class="mt-1 text-sm text-base-content/70">
                            {{ __('Kündigung muss bis :date eingehen.', ['date' => $noticeDeadline->fdate()]) }}
                            @if ($noticeDeadline->isToday() || $noticeDeadline->isPast())
                                <span class="badge badge-error badge-sm">{{ __('Frist erreicht') }}</span>
                            @elseif ($noticeDeadline->lte(now()->addDays(30)))
                                <span class="badge badge-warning badge-sm">{{ __('Frist bald') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
                <p class="mt-3 text-xs text-base-content/60">
                    {{ __('Berechnet aus Laufzeitmodell, Kündigungsfrist und automatischer Verlängerung. Kein Rechtsrat — maßgeblich bleibt der Vertragstext.') }}
                </p>
            @else
                <p class="text-sm text-base-content/60">{{ __('Kein Kündigungstermin — Vertrag beendet/storniert.') }}</p>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Vertragskalender & Obligationen')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Art') }}</th>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Fällig am') }}</th>
                    <th>{{ __('Vorwarnung') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($contract->obligations as $obligation)
                <tr @class(['opacity-60' => $obligation->status === 'done'])>
                    <td>{{ $obligation->kind->label() }}</td>
                    <td>{{ $obligation->title }} @if ($obligation->recurring) <span class="badge badge-ghost badge-sm">{{ __('wiederkehrend') }}</span> @endif</td>
                    <td>{{ $obligation->due_on->fdate() }}</td>
                    <td>{{ $obligation->warn_days_before }} {{ __('Tage') }}</td>
                    <td>
                        @if ($obligation->status === 'missed')
                            <span class="badge badge-error badge-sm">{{ __('versäumt') }}</span>
                        @elseif ($obligation->status === 'done')
                            <span class="badge badge-success badge-sm">{{ __('erledigt') }}</span>
                        @else
                            <span class="badge badge-info badge-outline badge-sm">{{ __('offen') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($obligation->status !== 'done')
                            @can('update', $contract)
                                <form method="POST" action="{{ route('contracts.obligations.complete', $obligation) }}">@csrf
                                    <button type="submit" class="btn btn-xs">{{ __('Erledigt') }}</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event_upcoming</span>' :colspan="6" :title="__('Keine Obligationen — Termine über das Formular ergänzen.')" compact />
            @endforelse
        </x-table>

        @can('update', $contract)
            <form method="POST" action="{{ route('contracts.obligations.store', $contract) }}" class="mt-4 grid gap-2 rounded-box border border-base-300 p-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <x-select-field name="kind" :label="__('Art')" required>
                    @foreach ($obligationKinds as $ok)
                        <option value="{{ $ok->value }}">{{ $ok->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="title" :label="__('Titel')" required />
                <x-input-field name="due_on" type="date" :label="__('Fällig am')" required />
                <x-input-field name="warn_days_before" type="number" min="0" :label="__('Vorwarnung (Tage)')" value="30" />
                <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
                    <option value="">{{ __('-- optional --') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="recurrence_months" type="number" min="1" :label="__('Wiederholung (Monate)')" />
                <x-checkbox-field name="recurring" :label="__('Wiederkehrend')" :toggle="false" />
                <div class="flex items-end"><button type="submit" class="btn btn-sm btn-primary">{{ __('Obligation ergänzen') }}</button></div>
            </form>
        @endcan
    </x-card>

    <x-card :title="__('Verknüpfte Leasing-/Finanzierungsverträge')">
        <p class="text-sm text-base-content/60">{{ __('Ein Leasing-/Finanzierungsvertrag (Feature 074) kann optional auf diesen allgemeinen Vertrag verweisen. Der Spezialfall bleibt eigenständig.') }}</p>
        @if ($linkedAssetFinance->isNotEmpty())
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach ($linkedAssetFinance as $af)
                    <li><span class="font-mono">{{ $af->number }}</span> — {{ $af->partner_name }}</li>
                @endforeach
            </ul>
        @endif
        @can('update', $contract)
            <form method="POST" action="{{ route('contracts.asset-finance.link', $contract) }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <x-select-field name="asset_finance_id" :label="__('Leasingvertrag')">
                    <option value="">{{ __('— auswählen —') }}</option>
                    @foreach ($assetFinanceOptions as $af)
                        <option value="{{ $af->sqid }}">{{ $af->number }} — {{ $af->partner_name }}</option>
                    @endforeach
                </x-select-field>
                <button type="submit" class="btn btn-sm">{{ __('Verknüpfen') }}</button>
            </form>
        @endcan
    </x-card>
</x-page-shell>
@endsection
