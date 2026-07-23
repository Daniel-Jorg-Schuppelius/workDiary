@extends('layouts.app')

@section('title', $case->number)
@section('nav-title', __('Verleihakte'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$case->number . ' — ' . ($case->customer->name ?? '')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline>{{ $case->status->label() }}</x-status-badge>
                <span class="badge badge-outline">{{ $case->starts_at->fdatetime() }} – {{ $case->ends_at->fdatetime() }}</span>
                @if ($case->isOverdue())
                    <span class="badge badge-error">{{ __('Rückgabe überfällig') }}</span>
                @endif
                @if ($case->rateCard !== null)
                    <span class="badge badge-outline">{{ __('Preisliste :name (v:version)', ['name' => $case->rateCard->name, 'version' => $case->rateCard->version]) }}</span>
                @endif
            </div>
            <x-slot:actions>
                @can('update', $case)
                    @if ($case->status === \App\Enums\Rental\RentalCaseStatus::Draft)
                        <form method="POST" action="{{ route('rental.reserve', $case) }}">@csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Reservieren') }}</button>
                        </form>
                    @endif
                    @if ($case->status === \App\Enums\Rental\RentalCaseStatus::Returned)
                        <form method="POST" action="{{ route('rental.close', $case) }}">@csrf
                            <button type="submit" class="btn btn-sm">{{ __('Abschließen') }}</button>
                        </form>
                    @endif
                    @if ($case->status->isOpen())
                        <form method="POST" action="{{ route('rental.cancel', $case) }}" data-confirm="{{ __('Verleihakte wirklich stornieren?') }}">@csrf
                            <button type="submit" class="btn btn-sm btn-ghost text-error">{{ __('Stornieren') }}</button>
                        </form>
                    @endif
                @endcan
                <x-icon-btn icon="arrow_back" size="sm" :href="route('rental.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Akte')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Kunde')">{{ $case->customer->name ?? '—' }} {{ $case->contact_name !== null ? '(' . $case->contact_name . ')' : '' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Projekt')">{{ $case->project->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Übergabeort')">{{ $case->handover_location ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Rückgabeort')">{{ $case->return_location ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $case->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kaution (vereinbart)')">{{ $case->deposit_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $case->deposit_amount, 2, withThousandsSeparator: true) . ' €' : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Versicherung')">{{ $case->insurance_note ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Tatsächliche Rückgabe')">{{ optional($case->actual_return_at)->fdatetime() ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            @if ($case->notes !== null)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $case->notes }}</p>
            @endif

            @can('update', $case)
                @if ($case->status->isOpen() && $case->status !== \App\Enums\Rental\RentalCaseStatus::Draft)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm font-medium">{{ __('Laufzeit verlängern') }}</summary>
                        <form method="POST" action="{{ route('rental.extend', $case) }}" class="mt-2 flex flex-wrap items-end gap-2">
                            @csrf
                            <x-input-field name="ends_at" type="datetime-local" :label="__('Neues Ende')" required />
                            <x-input-field name="reason" :label="__('Grund')" />
                            <button type="submit" class="btn btn-sm">{{ __('Verlängern') }}</button>
                        </form>
                    </details>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('Konditionen (Snapshot, D10)')">
            @if ($case->terms_snapshot !== null)
                <x-table bare>
                    <x-slot:head>
                        <tr><th>{{ __('Kondition') }}</th><th>{{ __('Art') }}</th><th class="text-right">{{ __('Betrag') }}</th></tr>
                    </x-slot:head>
                    @foreach ((array) data_get($case->terms_snapshot, 'items', []) as $item)
                        <tr>
                            <td>{{ $item['label'] ?? '—' }}</td>
                            <td>{{ \App\Enums\Rental\RentalChargeKind::tryFrom($item['kind'] ?? '')?->label() ?? ($item['kind'] ?? '—') }}</td>
                            <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($item['amount'] ?? 0), 2, withThousandsSeparator: true) }} € / {{ $item['unit'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <p class="mt-2 text-xs text-base-content/60">
                    {{ __('Eingefroren aus :name (Version :version) — spätere Preisänderungen bewerten diese Akte nicht um.', ['name' => data_get($case->terms_snapshot, 'name'), 'version' => data_get($case->terms_snapshot, 'version')]) }}
                </p>
            @else
                <p class="text-sm text-base-content/60">{{ __('Keine Preisliste hinterlegt — Positionen werden manuell erfasst.') }}</p>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Leihobjekte')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Asset') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Zubehör') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($case->caseAssets as $caseAsset)
                <tr>
                    <td>{{ $caseAsset->asset->name ?? '—' }}</td>
                    <td>
                        <x-status-badge size="md" outline>{{ __("values.{$caseAsset->status}") }}</x-status-badge>
                        @if ($caseAsset->replacedBy !== null)
                            <span class="text-xs text-base-content/60">{{ __('ersetzt durch :name', ['name' => $caseAsset->replacedBy->asset->name ?? '—']) }}</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ collect($caseAsset->accessories ?? [])->implode(', ') ?: '—' }}</td>
                    <td class="text-right">
                        @can('handover', $case)
                            @if ($caseAsset->status === 'planned' && in_array($case->status, [\App\Enums\Rental\RentalCaseStatus::Reserved, \App\Enums\Rental\RentalCaseStatus::HandedOver], true))
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs btn-primary">{{ __('Übergabe') }}</summary>
                                    @include('rental._report_form', ['case' => $case, 'caseAsset' => $caseAsset, 'mode' => 'handover'])
                                </details>
                            @endif
                            @if ($caseAsset->status === 'handed_over' && in_array($case->status, [\App\Enums\Rental\RentalCaseStatus::HandedOver, \App\Enums\Rental\RentalCaseStatus::Overdue], true))
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs">{{ __('Rücknahme') }}</summary>
                                    @include('rental._report_form', ['case' => $case, 'caseAsset' => $caseAsset, 'mode' => 'return'])
                                </details>
                            @endif
                        @endcan
                        @can('update', $case)
                            @if (in_array($caseAsset->status, ['planned', 'handed_over'], true) && $case->status->isOpen() && $case->status !== \App\Enums\Rental\RentalCaseStatus::Draft)
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs btn-ghost">{{ __('Tausch') }}</summary>
                                    <form method="POST" action="{{ route('rental.swap', $case) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                                        @csrf
                                        <input type="hidden" name="case_asset_id" value="{{ $caseAsset->sqid }}">
                                        <x-select-field name="asset_id" :label="__('Ersatzgerät')" required>
                                            @foreach ($rentableAssets as $a)
                                                @if ($a->id !== $caseAsset->asset_id)
                                                    <option value="{{ $a->sqid }}">{{ $a->name }}</option>
                                                @endif
                                            @endforeach
                                        </x-select-field>
                                        <x-input-field name="note" :label="__('Grund')" />
                                        <button type="submit" class="btn btn-sm">{{ __('Tauschen') }}</button>
                                    </form>
                                </details>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Keine Leihobjekte zugeordnet.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Übergabeprotokolle')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('Asset') }}</th><th>{{ __('Zeitpunkt') }}</th><th>{{ __('Zustand') }}</th><th>{{ __('Unterschrift') }}</th><th>{{ __('Portal') }}</th></tr>
                </x-slot:head>
                @forelse ($case->handoverReports as $report)
                    <tr>
                        <td>{{ $report->asset->name ?? '—' }}</td>
                        <td>{{ $report->reported_at->fdatetime() }}</td>
                        <td>{{ $report->condition->label() }}
                            @if ($report->meter_value !== null)<span class="text-xs text-base-content/60"> · {{ __('Zähler') }} {{ $report->meter_value }}</span>@endif
                            @if ($report->operating_hours !== null)<span class="text-xs text-base-content/60"> · {{ $report->operating_hours }} h</span>@endif
                        </td>
                        <td>{{ $report->signature_name ?? '—' }}</td>
                        <td>{{ $report->portal_confirmed_at !== null ? $report->portal_confirmed_at->fdatetime() : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Noch keine Übergabe protokolliert.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Rücknahmeprotokolle')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('Asset') }}</th><th>{{ __('Zeitpunkt') }}</th><th>{{ __('Zustand') }}</th><th>{{ __('Folge') }}</th></tr>
                </x-slot:head>
                @forelse ($case->returnReports as $report)
                    <tr>
                        <td>{{ $report->asset->name ?? '—' }}</td>
                        <td>{{ $report->reported_at->fdatetime() }}</td>
                        <td>{{ $report->condition->label() }}
                            @if (filled($report->damages))<span class="text-error text-xs"> · {{ __('Schaden') }}</span>@endif
                            @if (filled($report->missing_parts))<span class="text-warning text-xs"> · {{ __('Fehlteile') }}</span>@endif
                        </td>
                        <td><x-status-badge size="md" outline>{{ $report->follow_up->label() }}</x-status-badge></td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('Noch keine Rücknahme protokolliert.')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    @can('finance', $case)
        <x-card :title="__('Mietpositionen & Abrechnung')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Position') }}</th>
                        <th>{{ __('Art') }}</th>
                        <th class="text-right">{{ __('Menge × Preis') }}</th>
                        <th class="text-right">{{ __('Betrag') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Beleg') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($case->charges as $charge)
                    <tr>
                        <td>{{ $charge->label }}</td>
                        <td>{{ $charge->kind->label() }}</td>
                        <td class="text-right font-mono">{{ $charge->quantity }} × {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $charge->unit_price, 2, withThousandsSeparator: true) }} €</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $charge->amount, 2, withThousandsSeparator: true) }} €</td>
                        <td><x-status-badge size="md" outline>{{ $charge->status->label() }}</x-status-badge></td>
                        <td>
                            @if ($charge->invoice !== null)
                                <span class="font-mono text-sm">{{ $charge->invoice->number }}</span>
                            @elseif ($charge->external_reference !== null)
                                <span class="font-mono text-sm">{{ $charge->external_reference }}</span>
                            @elseif ($charge->status === \App\Enums\Rental\RentalChargeStatus::Transferred)
                                <form method="POST" action="{{ route('rental.charges.reference', $charge) }}" class="flex items-center gap-1">
                                    @csrf
                                    <input type="text" name="external_reference" class="input input-xs input-bordered w-32" placeholder="{{ __('Belegnr.') }}" required>
                                    <button type="submit" class="btn btn-xs">{{ __('OK') }}</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($charge->status === \App\Enums\Rental\RentalChargeStatus::Draft)
                                <form method="POST" action="{{ route('rental.charges.release', $charge) }}" class="inline">@csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Freigeben') }}</button>
                                </form>
                            @endif
                            @if (! $charge->status->isSettled() && $charge->status !== \App\Enums\Rental\RentalChargeStatus::Cancelled)
                                <form method="POST" action="{{ route('rental.charges.cancel', $charge) }}" class="inline">@csrf
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Stornieren') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="7" :title="__('Noch keine Positionen — aus den Konditionen übernehmen oder manuell erfassen.')" compact />
                @endforelse
            </x-table>
            <div class="flex flex-wrap items-center gap-2 border-t border-base-300 p-3">
                @if ($chargeSuggestions !== [])
                    <form method="POST" action="{{ route('rental.charges.suggest', $case) }}">@csrf
                        <button type="submit" class="btn btn-sm">{{ __(':count Positionen aus Konditionen übernehmen', ['count' => count($chargeSuggestions)]) }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('rental.invoice', $case) }}">@csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Freigegebene Positionen abrechnen') }}</button>
                </form>
                <details class="inline-block">
                    <summary class="btn btn-sm btn-ghost">{{ __('Position erfassen') }}</summary>
                    <form method="POST" action="{{ route('rental.charges.store', $case) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                        @csrf
                        <x-select-field name="kind" :label="__('Art')" required>
                            @foreach (\App\Enums\Rental\RentalChargeKind::cases() as $kind)
                                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                            @endforeach
                        </x-select-field>
                        <x-input-field name="label" :label="__('Bezeichnung')" required />
                        <x-input-field name="quantity" type="number" step="0.01" min="0.01" :label="__('Menge')" value="1" required />
                        <x-input-field name="unit" :label="__('Einheit')" value="Tag" required />
                        <x-input-field name="unit_price" type="number" step="0.01" :label="__('Einzelpreis')" required />
                        <x-input-field name="reason_text" :label="__('Begründung (Pflicht bei Schaden/Verlust/Minderung)')" />
                        <button type="submit" class="btn btn-sm">{{ __('Erfassen') }}</button>
                    </form>
                </details>
            </div>
        </x-card>

        <x-card :title="__('Kaution (eigener Finanzvorgang)')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th class="text-right">{{ __('Betrag') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Erhalten') }}</th>
                        <th>{{ __('Abgerechnet') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($case->deposits as $deposit)
                    <tr>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $deposit->amount, 2, withThousandsSeparator: true) }} €
                            @if ($deposit->retained_amount !== null)
                                <span class="text-xs text-error">({{ __('einbehalten') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $deposit->retained_amount, 2, withThousandsSeparator: true) }} €)</span>
                            @endif
                        </td>
                        <td><x-status-badge size="md" outline>{{ $deposit->status->label() }}</x-status-badge></td>
                        <td>{{ optional($deposit->received_at)->fdatetime() ?? '—' }}</td>
                        <td>{{ optional($deposit->refunded_at)->fdatetime() ?? '—' }}</td>
                        <td class="text-right">
                            @if ($deposit->status === \App\Enums\Rental\RentalDepositStatus::Requested)
                                <form method="POST" action="{{ route('rental.deposits.receive', $deposit) }}" class="inline">@csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Erhalten') }}</button>
                                </form>
                            @endif
                            @if ($deposit->status === \App\Enums\Rental\RentalDepositStatus::Received)
                                <details class="inline-block text-left">
                                    <summary class="btn btn-xs">{{ __('Abrechnen') }}</summary>
                                    <form method="POST" action="{{ route('rental.deposits.settle', $deposit) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                                        @csrf
                                        <x-input-field name="retained_amount" type="number" step="0.01" min="0" :label="__('Einbehalt (0 = volle Erstattung)')" value="0" />
                                        <x-input-field name="reason" :label="__('Begründung (Pflicht bei Einbehalt)')" />
                                        <button type="submit" class="btn btn-sm">{{ __('Abrechnen') }}</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Keine Kaution erfasst.')" compact />
                @endforelse
            </x-table>
            <div class="border-t border-base-300 p-3">
                <details class="inline-block">
                    <summary class="btn btn-sm btn-ghost">{{ __('Kaution anfordern') }}</summary>
                    <form method="POST" action="{{ route('rental.deposits.store', $case) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                        @csrf
                        <x-input-field name="amount" type="number" step="0.01" min="0.01" :label="__('Betrag')" :value="$case->deposit_amount" required />
                        <x-input-field name="note" :label="__('Notiz')" />
                        <button type="submit" class="btn btn-sm">{{ __('Anfordern') }}</button>
                    </form>
                </details>
            </div>
        </x-card>
    @endcan

    <x-card :title="__('Belegungsfenster')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr><th>{{ __('Asset') }}</th><th>{{ __('Art') }}</th><th>{{ __('Zeitraum (inkl. Puffer)') }}</th><th>{{ __('Status') }}</th></tr>
            </x-slot:head>
            @forelse ($case->reservations as $reservation)
                <tr>
                    <td>{{ $reservation->asset->name ?? '—' }}</td>
                    <td>{{ $reservation->kind->label() }}</td>
                    <td>{{ $reservation->blockedFrom()->fdatetime() }} – {{ $reservation->blockedUntil()->fdatetime() }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$reservation->status}") }}</x-status-badge></td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Keine Belegungsfenster — entstehen mit der Reservierung.')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
