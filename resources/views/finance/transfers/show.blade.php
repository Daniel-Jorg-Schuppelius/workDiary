{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau/Detail eines Übergabenachweises (Feature 045, Teil B): Kopf,
  entstehende Positionen (Zeit-Blöcke mit Taktung bzw. Material), Quellen,
  Aktionen entlang der Statusmaschine, Ereignisprotokoll. Bei
  transferred+lexoffice: Verweis auf den externen Rechnungsentwurf
  (ExternalReference) — KEIN automatischer Status-Sync (offener Punkt).
--}}

@extends('layouts.app')

@section('title', __('finance.title.transfer'))
@section('nav-title', __('finance.title.transfer'))

@section('content')
<x-page-shell>
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ $transfer->customer?->name ?? '—' }}
                    <span class="text-base-content/50">·</span>
                    {{ $transfer->channel->label() }}
                </h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge :tone="$transfer->status->tone()">{{ $transfer->status->label() }}</x-status-badge>
                    @if ($transfer->corrects !== null)
                        <a class="link" href="{{ route('finance.transfers.show', $transfer->corrects) }}">
                            <x-status-badge tone="warning" outline>{{ __('finance.field.corrects', ['id' => $transfer->corrects->id]) }}</x-status-badge>
                        </a>
                    @endif
                    @foreach ($transfer->corrections as $correction)
                        <a class="link" href="{{ route('finance.transfers.show', $correction) }}">
                            <x-status-badge tone="warning" outline>{{ __('finance.field.corrected_by', ['id' => $correction->id]) }}</x-status-badge>
                        </a>
                    @endforeach
                    <x-status-badge :tone="$transfer->channel->tone()" outline>{{ $transfer->channel->label() }}</x-status-badge>
                    <x-status-badge :tone="$transfer->target->tone()" outline>{{ $transfer->target->label() }}</x-status-badge>
                </div>
                <dl class="mt-3 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.period') }}:</dt>
                        <dd>{{ $transfer->period_from?->format('d.m.Y') ?? '—' }} – {{ $transfer->period_to?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                    {{-- Kopfzahlen = das, was fakturiert wird (Positionen).
                         Die ungetaktete Quellsumme steht bei den Einzelquellen. --}}
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.position_count') }}:</dt>
                        <dd class="tabular-nums">{{ $positions->count() }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.total_quantity') }}:</dt>
                        <dd class="tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $positionTotals['quantity'], 2, withThousandsSeparator: true) }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.total_amount') }}:</dt>
                        <dd class="tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $positionTotals['amount'], 2, withThousandsSeparator: true) }}</dd>
                    </div>
                    @if (filled($transfer->correction_reason))
                        <div class="flex gap-2 sm:col-span-2">
                            <dt class="text-base-content/60">{{ __('finance.field.correction_reason') }}:</dt>
                            <dd>{{ $transfer->correction_reason }}</dd>
                        </div>
                    @endif
                    <div class="flex gap-2 sm:col-span-2">
                        <dt class="text-base-content/60">{{ __('finance.field.payload_hash') }}:</dt>
                        <dd class="break-all font-mono text-xs">{{ $transfer->payload_hash }}</dd>
                    </div>
                    @if ($transfer->transferred_at !== null)
                        <div class="flex gap-2">
                            <dt class="text-base-content/60">{{ __('finance.field.transferred_at') }}:</dt>
                            <dd>{{ $transfer->transferred_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Failed && $transfer->failure_reason !== null)
                    <div class="alert alert-error mt-3 text-sm">
                        <x-icon name="error" />
                        <span><span class="font-semibold">{{ __('finance.field.failure_reason') }}:</span> {{ $transfer->failure_reason }}</span>
                    </div>
                @endif

                {{-- Statusrücklauf Lexoffice (minimal): Verweis auf den externen Entwurf --}}
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Transferred
                    && $transfer->target === \App\Enums\Finance\TransferTarget::Lexoffice
                    && $transfer->externalReference !== null)
                    <div class="alert alert-info mt-3 text-sm">
                        <x-icon name="cloud_done" />
                        <span>
                            {{ __('finance.hint.lexoffice_draft_created') }}
                            <span class="font-mono text-xs">{{ $transfer->externalReference->external_id }}</span>
                            @php $extUrl = data_get($transfer->externalReference->payload, 'resourceUri'); @endphp
                            @if (is_string($extUrl) && $extUrl !== '')
                                — <a class="link" href="{{ $extUrl }}" target="_blank" rel="noopener noreferrer">{{ __('finance.action.open_external') }}</a>
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Statusrücklauf sevDesk (minimal, analog Lexoffice): Verweis auf den externen Entwurf --}}
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Transferred
                    && $transfer->target === \App\Enums\Finance\TransferTarget::SevDesk
                    && $transfer->externalReference !== null)
                    <div class="alert alert-info mt-3 text-sm">
                        <x-icon name="cloud_done" />
                        <span>
                            {{ __('finance.hint.sevdesk_draft_created') }}
                            <span class="font-mono text-xs">{{ $transfer->externalReference->external_id }}</span>
                        </span>
                    </div>
                @endif

                {{-- Statusrücklauf easybill (minimal, analog sevDesk): Verweis auf den externen Entwurf --}}
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Transferred
                    && $transfer->target === \App\Enums\Finance\TransferTarget::Easybill
                    && $transfer->externalReference !== null)
                    <div class="alert alert-info mt-3 text-sm">
                        <x-icon name="cloud_done" />
                        <span>
                            {{ __('finance.hint.easybill_draft_created') }}
                            <span class="font-mono text-xs">{{ $transfer->externalReference->external_id }}</span>
                        </span>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Draft)
                    @can('confirm', $transfer)
                        <form method="POST" action="{{ route('finance.transfers.confirm', $transfer) }}">
                            @csrf
                            <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('finance.action.confirm') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Confirmed)
                    @can('markTransferred', $transfer)
                        <x-action-form :action="route('finance.transfers.execute', $transfer)"
                              data-confirm-title="{{ __('finance.action.execute') }}"
                              :confirm="__('finance.confirm_execute')"
                              confirm-icon="cloud_upload"
                              confirm-tone="primary"
                              :confirm-label="__('finance.action.execute')">
                            <x-icon-btn icon="cloud_upload" tone="primary" size="sm" type="submit" show-label>{{ __('finance.action.execute') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Failed)
                    @can('confirm', $transfer)
                        <form method="POST" action="{{ route('finance.transfers.confirm', $transfer) }}">
                            @csrf
                            <x-icon-btn icon="refresh" tone="warning" size="sm" type="submit" show-label>{{ __('finance.action.retry') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if (in_array($transfer->status, [\App\Enums\Finance\TransferStatus::Draft, \App\Enums\Finance\TransferStatus::Confirmed], true))
                    @can('void', $transfer)
                        <x-action-form :action="route('finance.transfers.void', $transfer)"
                              data-confirm-title="{{ __('finance.action.void') }}"
                              :confirm="__('finance.confirm_void')"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm-label="__('finance.action.void')">
                            <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" show-label>{{ __('finance.action.void') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($canCorrect)
                    {{-- Korrektur-Übergabe (MVP-490): neuer Nachweis mit denselben
                         Quellen; der alte bleibt unverändert stehen. --}}
                    <x-action-form :action="route('finance.transfers.correct', $transfer)"
                          data-confirm-title="{{ __('finance.action.correct') }}"
                          :confirm="__('finance.confirm_correct')"
                          confirm-icon="difference"
                          confirm-tone="warning"
                          :confirm-label="__('finance.action.correct')">
                        <x-icon-btn icon="difference" tone="warning" size="sm" type="submit" show-label>{{ __('finance.action.correct') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($transfer->file_path !== null && $transfer->status === \App\Enums\Finance\TransferStatus::Transferred)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="route('finance.transfers.download', $transfer)"
                                show-label>{{ __('finance.action.download') }}</x-icon-btn>
                @endif
            </div>
        </div>

        <x-validation-errors class="mt-3" />
    </x-card>

    {{-- Rechnungstexte des Belegs (MVP-491): gehen als Einleitung und
         Schlussbemerkung ans Ziel. --}}
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.texts') }}</h3>
        @if ($canEditTexts)
            <form method="POST" action="{{ route('finance.transfers.texts.update', $transfer) }}" class="grid gap-3">
                @csrf
                @method('PATCH')
                <x-textarea-field name="intro_text" :label="__('finance.field.intro_text')" rows="3" maxlength="2000"
                                  :value="old('intro_text', $transfer->intro_text)"
                                  :hint="__('finance.hint.intro_text')" />
                <x-textarea-field name="closing_text" :label="__('finance.field.closing_text')" rows="3" maxlength="2000"
                                  :value="old('closing_text', $transfer->closing_text)"
                                  :hint="__('finance.hint.closing_text')" />
                <div class="flex justify-end">
                    <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
                </div>
            </form>
        @else
            <dl class="grid gap-2 text-sm">
                <div>
                    <dt class="text-base-content/60">{{ __('finance.field.intro_text') }}</dt>
                    <dd class="whitespace-pre-line">{{ $transfer->intro_text ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-base-content/60">{{ __('finance.field.closing_text') }}</dt>
                    <dd class="whitespace-pre-line">{{ $transfer->closing_text ?: '—' }}</dd>
                </div>
            </dl>
        @endif
    </x-card>

    @include('invoicing._text_correction_learn')

    {{-- Entstehende Positionen: im Entwurf berechnet, ab „Bestätigt" eingefroren
         und prüfbar (MVP-487/488). --}}
    <x-card>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold">{{ __('finance.title.positions') }}</h3>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canEditPositions && $canEditPositionPrices && $positions->count() > 1)
                    <x-button type="submit" form="merge-positions" tone="ghost" size="xs" icon="merge">{{ __('finance.action.merge_positions') }}</x-button>
                @endif
                @if ($aiUsable && $positions->isNotEmpty())
                    <form method="POST" action="{{ route('finance.transfers.positions.suggest-all', $transfer) }}">
                        @csrf
                        <x-button type="submit" tone="ghost" size="xs" icon="auto_awesome">{{ __('ai.suggestion.suggest_all') }}</x-button>
                    </form>
                @endif
            </div>
        </div>
        @if ($canEditPositions && $canEditPositionPrices && $positions->count() > 1)
            <form method="POST" action="{{ route('finance.transfers.positions.merge', $transfer) }}" id="merge-positions">@csrf</form>
        @endif
        <x-table>
            <x-slot:head>
                <tr>
                    @if ($canEditPositions && $canEditPositionPrices && $positions->count() > 1)
                        <th class="w-8"></th>
                    @endif
                    <th>{{ __('finance.csv.position') }}</th>
                    <th class="text-right">{{ __('finance.csv.quantity') }}</th>
                    <th>{{ __('finance.csv.unit') }}</th>
                    <th class="text-right">{{ __('finance.csv.unit_price_net') }}</th>
                    <th class="text-right">{{ __('finance.csv.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($positions as $position)
                <tr @class(['bg-warning/5' => $position->isUnpriced()])>
                    @if ($canEditPositions && $canEditPositionPrices && $positions->count() > 1)
                        <td class="align-top">
                            @if ($position->exists)
                                <input type="checkbox" form="merge-positions" name="positions[]" value="{{ $position->id }}"
                                       class="checkbox checkbox-sm" aria-label="{{ __('finance.action.merge_positions') }}">
                            @endif
                        </td>
                    @endif
                    <td>
                        <div class="flex items-start gap-2">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $position->name }}</span>
                                @if (filled($position->description))
                                    <p class="mt-0.5 whitespace-pre-line text-xs text-base-content/60">{{ $position->description }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-base-content/50">
                                    {{ __('finance.field.price_source') }}: {{ $position->priceSourceLabel() }}
                                    @if ($position->article_id !== null)
                                        · {{ __('finance.field.article') }}: {{ $position->article_id }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($position->quantityFloat(), 2, withThousandsSeparator: true) }}</td>
                    <td>{{ $position->unit_name }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($position->unitPriceFloat(), 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($position->amountFloat(), 2, withThousandsSeparator: true) }}</td>
                </tr>

                @if ($canEditPositions && $position->exists)
                    {{-- Nachbessern vor dem Senden: Text immer, Menge/Preis nur mit finance.config. --}}
                    <tr>
                        <td colspan="{{ $canEditPositionPrices && $positions->count() > 1 ? 6 : 5 }}" class="py-1">
                            <details class="text-xs">
                                <summary class="cursor-pointer text-base-content/60">{{ __('finance.action.edit_position') }}</summary>
                                <form method="POST" action="{{ route('finance.transfers.positions.update', [$transfer, $position]) }}"
                                      class="mt-2 grid gap-2 md:grid-cols-4">
                                    @csrf
                                    @method('PATCH')
                                    <div class="md:col-span-4">
                                        <input type="text" name="name" maxlength="500" value="{{ $position->name }}"
                                               class="input input-bordered input-sm w-full" required>
                                    </div>
                                    <div class="md:col-span-4">
                                        <textarea name="description" rows="3" maxlength="4000"
                                                  class="textarea textarea-bordered w-full">{{ $position->description }}</textarea>
                                    </div>
                                    @if ($canEditPositionPrices)
                                        <input type="number" step="0.001" min="0" name="quantity" value="{{ $position->quantityFloat() }}"
                                               class="input input-bordered input-sm w-full" aria-label="{{ __('finance.csv.quantity') }}">
                                        <input type="number" step="0.0001" min="0" name="unit_price" value="{{ $position->unitPriceFloat() }}"
                                               class="input input-bordered input-sm w-full" aria-label="{{ __('finance.csv.unit_price_net') }}">
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 md:col-span-2 md:justify-end">
                                        <x-button type="submit" tone="ghost" size="xs" icon="arrow_upward"
                                                  formaction="{{ route('finance.transfers.positions.move', [$transfer, $position]) }}"
                                                  formmethod="POST" name="direction" value="up">{{ __('finance.action.move_up') }}</x-button>
                                        <x-button type="submit" tone="ghost" size="xs" icon="arrow_downward"
                                                  formaction="{{ route('finance.transfers.positions.move', [$transfer, $position]) }}"
                                                  formmethod="POST" name="direction" value="down">{{ __('finance.action.move_down') }}</x-button>
                                        @if ($aiUsable)
                                            <x-button type="submit" tone="ghost" size="xs" icon="auto_awesome"
                                                      formaction="{{ route('finance.transfers.positions.suggest', [$transfer, $position]) }}"
                                                      formmethod="POST">{{ __('ai.suggestion.suggest') }}</x-button>
                                        @endif
                                        <x-button type="submit" tone="primary" size="xs" icon="save">{{ __('Speichern') }}</x-button>
                                    </div>
                                </form>
                                @if ($canEditPositionPrices)
                                    <x-action-form :action="route('finance.transfers.positions.destroy', [$transfer, $position])"
                                          method="DELETE"
                                          class="mt-2"
                                          data-confirm-title="{{ __('finance.action.remove_position') }}"
                                          :confirm="__('finance.confirm_remove_position')"
                                          confirm-icon="delete"
                                          confirm-tone="error"
                                          :confirm-label="__('finance.action.remove_position')">
                                        <x-button type="submit" tone="ghost" size="xs" icon="delete">{{ __('finance.action.remove_position') }}</x-button>
                                    </x-action-form>
                                @endif
                            </details>
                        </td>
                    </tr>
                @endif

                @if ($position->exists && ($aiSuggestions[$position->id] ?? null) !== null)
                    <tr data-ai-suggestion-row>
                        <td colspan="{{ $canEditPositions && $canEditPositionPrices && $positions->count() > 1 ? 6 : 5 }}">
                            <x-ai-suggestion
                                :original="$aiSuggestions[$position->id]->original"
                                :suggestion="$aiSuggestions[$position->id]->suggestion"
                                :provider="$aiSuggestions[$position->id]->provider"
                                :fallback="$aiSuggestions[$position->id]->fallback_used"
                                :cached="$aiSuggestions[$position->id]->from_cache"
                                :accept-action="route('ai.suggestions.accept', $aiSuggestions[$position->id])"
                                :reject-action="route('ai.suggestions.reject', $aiSuggestions[$position->id])"
                                field-name="text"
                            />
                        </td>
                    </tr>
                @endif
            @empty
                <x-table.empty :colspan="$canEditPositions && $canEditPositionPrices && $positions->count() > 1 ? 6 : 5"
                               :title="__('finance.empty_positions_title')"
                               :message="__('finance.empty_positions')" />
            @endforelse
            @if ($positions->isNotEmpty())
                <tr class="font-semibold">
                    <td @if ($canEditPositions && $canEditPositionPrices && $positions->count() > 1) colspan="2" @endif>{{ __('finance.csv.total') }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $positionTotals['quantity'], 2, withThousandsSeparator: true) }}</td>
                    <td colspan="2"></td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $positionTotals['amount'], 2, withThousandsSeparator: true) }}</td>
                </tr>
            @endif
        </x-table>
        @if ($transfer->status === \App\Enums\Finance\TransferStatus::Draft && $positions->isNotEmpty())
            {{-- Im Entwurf sind die Positionen nur berechnet: bearbeiten und
                 KI-Vorschläge gibt es erst ab „Bestätigt" (dann eingefroren). --}}
            <div class="alert alert-info mt-3 text-sm" role="alert">
                <x-icon name="info" />
                <span>{{ __('finance.hint.positions_draft') }}</span>
            </div>
        @endif
        @if ($unpricedPositions > 0)
            <div class="alert alert-warning mt-3 text-sm" role="alert">
                <div>
                    <span class="font-semibold">{{ __('finance.position.unpriced_title') }}</span>
                    <p class="mt-0.5">{{ __('finance.position.unpriced_hint', ['count' => $unpricedPositions]) }}</p>
                </div>
            </div>
        @endif
        @if ($transfer->channel === \App\Enums\Finance\TransferChannel::Time)
            <p class="mt-2 text-xs text-base-content/60">{{ __('finance.hint.positions_increment') }}</p>
        @endif
    </x-card>

    {{-- Einzelquellen (Snapshot des Nachweises). Im Material-Kanal werden die im
         Snapshot festgehaltenen Felder Einheit, Steuersatz und DATEV-Kostenposition
         (Kriterium 045) zusätzlich gezeigt — sie dokumentieren die Materialzeile
         zum Übergabezeitpunkt, unabhängig von späteren Stammdatenänderungen. --}}
    @php $isMaterial = $transfer->channel === \App\Enums\Finance\TransferChannel::Material; @endphp
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.sources') }}</h3>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.csv.date') }}</th>
                    <th>{{ __('finance.field.source') }}</th>
                    <th class="text-right">{{ __('finance.csv.quantity') }}</th>
                    @if ($isMaterial)
                        <th>{{ __('finance.csv.unit') }}</th>
                        <th class="text-right">{{ __('finance.csv.tax_rate') }}</th>
                        <th>{{ __('finance.csv.cost_position') }}</th>
                    @endif
                    <th class="text-right">{{ __('finance.csv.amount') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($transfer->items as $item)
                <tr>
                    @if ($item->source instanceof \App\Models\TimeEntry)
                        <td>{{ $item->source->date?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            {{ $item->source->project?->name ?? '—' }}
                            <span class="text-base-content/50">·</span>
                            {{ $item->source->user?->name ?? '—' }}
                            @if (trim((string) $item->source->description) !== '')
                                <span class="block text-xs text-base-content/60">{{ $item->source->description }}</span>
                            @endif
                        </td>
                    @elseif ($item->source instanceof \App\Models\MaterialUsage)
                        <td>{{ $item->source->timesheet?->work_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            {{ trim((string) $item->source->description) ?: __('Material') }}
                            <span class="block text-xs text-base-content/60">{{ $item->source->timesheet?->project?->name ?? '' }}</span>
                        </td>
                    @else
                        <td>—</td>
                        <td class="text-base-content/50">{{ __('finance.field.source_deleted') }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $item->quantity !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, 2, withThousandsSeparator: true) : '—' }}</td>
                    @if ($isMaterial)
                        <td>{{ $item->unit ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $item->tax_rate !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->tax_rate, 2, withThousandsSeparator: true) . ' %' : '—' }}</td>
                        <td class="font-mono text-xs">{{ $item->cost_position ?? '—' }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $item->amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->amount, 2, withThousandsSeparator: true) : '—' }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold">
                <td colspan="2">{{ __('finance.csv.total') }}</td>
                <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transfer->total_quantity, 2, withThousandsSeparator: true) }}</td>
                @if ($isMaterial)
                    <td colspan="3"></td>
                @endif
                <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transfer->total_amount, 2, withThousandsSeparator: true) }}</td>
            </tr>
        </x-table>
    </x-card>

    {{-- Ereignisprotokoll (Hash-Kette) --}}
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.events') }}</h3>
        <ul class="space-y-1 text-sm">
            @foreach ($transfer->events as $event)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-xs text-base-content/50">{{ $event->created_at?->format('d.m.Y H:i:s') }}</span>
                    <x-status-badge tone="ghost" outline>{{ \App\Support\Trans::or('finance.event.' . $event->event, $event->event) }}</x-status-badge>
                    @if (data_get($event->payload, 'failure_reason'))
                        <span class="text-error">{{ data_get($event->payload, 'failure_reason') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</x-page-shell>
@endsection
