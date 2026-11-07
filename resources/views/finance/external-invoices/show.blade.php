{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Extern ausgestellte Rechnung (Feature 196): Stand beim Anbieter, gesichertes
  Original, zugeordnete Übergabepositionen mit Abgleich, Nachweise und
  Versandakte. Nummer und Inhalt führt das Fakturasystem.
--}}
@extends('layouts.app')

@section('title', __('finance.external_invoice.title', ['number' => $invoice->displayNumber()]))
@section('nav-title', __('finance.external_invoice.title', ['number' => $invoice->displayNumber()]))

@section('content')
@php
    $nf = static fn (?string $v): string => $v === null ? '—' : \CommonToolkit\ValueObjects\Decimal::of($v, 2)->format();
    $blockers = $preflight['errors'];
@endphp
<x-page-shell>
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ $invoice->customer?->name ?? '—' }}
                    <span class="text-muted">·</span>
                    {{ $invoice->displayNumber() }}
                </h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-status-badge>
                    <x-status-badge tone="info" outline>{{ $provider?->label() ?? $invoice->plugin_id }}</x-status-badge>
                    @if ($invoice->original_format !== null)
                        <x-status-badge tone="neutral" outline>{{ $invoice->original_format->label() }}</x-status-badge>
                    @endif
                    <x-status-badge :tone="$invoice->provider_send_state->tone()" outline :title="__('finance.external_invoice.field.provider_send_state')">{{ $invoice->provider_send_state->label() }}</x-status-badge>
                    <x-status-badge tone="neutral" outline :title="__('finance.external_invoice.route.label')">{{ $route->label() }}</x-status-badge>
                </div>
                <x-detail-grid class="mt-3 sm:grid-cols-[max-content_1fr_max-content_1fr]">
                    <x-detail-grid.row :label="__('finance.external_invoice.field.issued_on') . ':'">{{ $invoice->issued_on?->fdate() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('finance.external_invoice.field.synced_at') . ':'">{{ $invoice->synced_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('finance.external_invoice.field.net') . ':'" class="tabular-nums">{{ $nf($invoice->net_amount) }} {{ $invoice->currency }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('finance.external_invoice.field.gross') . ':'" class="tabular-nums">{{ $nf($invoice->gross_amount) }} {{ $invoice->currency }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('finance.external_invoice.field.original') . ':'" class="sm:col-span-3 break-all font-mono text-xs">
                        {{ $invoice->original_sha256 ?? __('finance.external_invoice.field.original_missing') }}
                    </x-detail-grid.row>
                    @if ($invoice->sync_error)
                        <x-detail-grid.row :label="__('finance.external_invoice.field.sync_error') . ':'" class="sm:col-span-3 text-error">{{ $invoice->sync_error }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                @if ($canUpdate)
                    <x-action-form :action="route('finance.external-invoices.refresh', $invoice)">
                        <x-icon-btn icon="sync" size="sm" type="submit" show-label>{{ __('finance.external_invoice.action.refresh') }}</x-icon-btn>
                    </x-action-form>
                    @if ($packages->isEmpty() && $invoice->status->isFinalized())
                        <x-action-form :action="route('finance.external-invoices.original.refetch', $invoice)"
                                       :confirm="__('finance.external_invoice.confirm_refetch')" confirm-icon="cloud_download" confirm-tone="warning">
                            <x-icon-btn icon="cloud_download" size="sm" type="submit" show-label>{{ __('finance.external_invoice.action.refetch') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endif
                @if ($invoice->original_sha256 !== null)
                    <x-icon-btn icon="download" tone="outline" size="sm" :href="route('finance.external-invoices.original', $invoice)" show-label>{{ __('finance.external_invoice.action.original') }}</x-icon-btn>
                @endif
                @if ($canSend)
                    <x-icon-btn icon="send" tone="primary" size="sm" data-entry-modal-trigger :href="route('finance.external-invoices.send.form', $invoice)" show-label>{{ __('finance.external_invoice.action.send') }}</x-icon-btn>
                    @if ($packages->contains(fn ($p) => $p->kind === \App\Enums\Finance\ExternalPackageKind::Initial && $p->isDelivered()))
                        <x-icon-btn icon="forward_to_inbox" size="sm" data-entry-modal-trigger :href="route('finance.external-invoices.send.form', [$invoice, 'kind' => 'resend'])" show-label>{{ __('finance.external_invoice.action.resend') }}</x-icon-btn>
                    @endif
                @endif
            </div>
        </div>
        @if ($blockers !== [])
            <div class="mt-3 rounded-box border border-warning/40 bg-warning/10 p-3 text-sm">
                <p class="font-medium">{{ __('finance.external_invoice.blockers') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif
        <x-validation-errors class="mt-3" />
    </x-card>

    {{-- Zuordnung und Abgleich: nur eingefrorene Übergabepositionen tragen den Nachweis. --}}
    <x-card :title="__('finance.external_invoice.positions.heading')" icon="link" padding="p-0">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.external_invoice.positions.transfer') }}</th>
                    <th>{{ __('finance.external_invoice.positions.name') }}</th>
                    <th class="text-right">{{ __('finance.external_invoice.positions.quantity') }}</th>
                    <th class="text-right">{{ __('finance.external_invoice.positions.net') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($invoice->positionLinks as $link)
                <tr>
                    <td class="whitespace-nowrap"><a class="link" href="{{ route('finance.transfers.show', $link->transfer) }}">{{ __('finance.external_invoice.positions.transfer_label', ['id' => $link->transfer->id]) }}</a></td>
                    <td>{{ $link->position->name }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\ValueObjects\Decimal::of((string) $link->position->quantity, 2)->format() }}</td>
                    <td class="text-right tabular-nums">{{ $nf((string) $link->position->amount) }}</td>
                    <td class="text-right">
                        @if ($canUpdate && $packages->isEmpty())
                            <x-action-form :action="route('finance.external-invoices.unassign', [$invoice, $link->position])" method="DELETE"
                                           :confirm="__('finance.external_invoice.confirm_unassign')" confirm-icon="link_off" confirm-tone="error">
                                <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :title="__('finance.external_invoice.action.unassign')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="link_off" :colspan="5" :title="__('finance.external_invoice.positions.empty')" compact />
            @endforelse
        </x-table>
        <div class="space-y-2 px-4 py-3 text-sm">
            <p class="tabular-nums">{{ __('finance.external_invoice.reconcile.totals', ['external' => $nf($reconciliation['external_net']), 'mapped' => $nf($reconciliation['mapped_net'])]) }}</p>
            @if ($reconciliation['deviations'] === [])
                <p class="text-success">{{ __('finance.external_invoice.reconcile.ok') }}</p>
            @else
                <p @class(['text-warning' => ! $reconciliation['confirmed'], 'text-base-content/70' => $reconciliation['confirmed']])>
                    {{ $reconciliation['confirmed'] ? __('finance.external_invoice.reconcile.confirmed', ['user' => $invoice->reconciler?->name ?? '—', 'at' => $invoice->reconciled_at?->fdatetime() ?? '—']) : __('finance.external_invoice.reconcile.open') }}
                </p>
                <ul class="list-disc pl-5">
                    @foreach ($reconciliation['deviations'] as $deviation)
                        <li>{{ __('finance.external_invoice.reconcile.' . $deviation['type'], ['name' => $deviation['name'], 'quantity' => $deviation['quantity'], 'net' => $nf($deviation['net'])]) }}</li>
                    @endforeach
                </ul>
                @if ($invoice->note)<p class="text-base-content/70">{{ $invoice->note }}</p>@endif
                @if ($canUpdate && ! $reconciliation['confirmed'] && $packages->isEmpty())
                    <form method="POST" action="{{ route('finance.external-invoices.reconcile', $invoice) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-input-field name="note" :label="__('finance.external_invoice.reconcile.note')" maxlength="500" required class="min-w-64 flex-1" />
                        <x-button type="submit" tone="warning" size="sm" icon="task_alt">{{ __('finance.external_invoice.action.reconcile') }}</x-button>
                    </form>
                @endif
            @endif
        </div>
    </x-card>

    @include('invoices._supporting_documents', ['carrier' => $invoice, 'routePrefix' => 'finance.external-invoices.documents'])

    {{-- Versandakte: je Fassung Hashes, Nachweisversionen, Prüfungen und die Zustellversuche. --}}
    <x-card :title="__('finance.external_invoice.packages.heading')" icon="outgoing_mail" :count="$packages->count()" padding="p-0">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.external_invoice.packages.created') }}</th>
                    <th>{{ __('finance.external_invoice.packages.kind') }}</th>
                    <th>{{ __('finance.external_invoice.packages.channel') }}</th>
                    <th>{{ __('finance.external_invoice.packages.files') }}</th>
                    <th>{{ __('finance.external_invoice.packages.attempts') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($packages->sortByDesc('id') as $package)
                @php $unclear = $dispatches->isUnclear($package); $pending = $dispatches->isPending($package); $delivered = $package->isDelivered(); @endphp
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ $package->created_at?->fdatetime() }}<div class="text-xs text-base-content/70">{{ $package->creator?->name }}</div></td>
                    <td class="whitespace-nowrap">{{ $package->kind->label() }}<div class="text-xs text-base-content/70">{{ $package->format->label() }}</div></td>
                    <td class="max-w-xs break-all">{{ __('values.' . $package->channel) }}<div class="text-xs text-base-content/70">{{ $package->recipient }}</div></td>
                    <td class="max-w-sm">
                        @foreach ($package->files as $index => $file)
                            <div class="truncate"><a class="link" href="{{ route('finance.external-invoices.packages.file', [$package, $index]) }}">{{ $file['name'] }}</a></div>
                        @endforeach
                        <div class="break-all font-mono text-[11px] text-base-content/70" title="{{ __('finance.external_invoice.packages.sha') }}">{{ $package->sha256 }}</div>
                        @if ($package->original_sha256)<div class="break-all font-mono text-[11px] text-base-content/70" title="{{ __('finance.external_invoice.packages.original_sha') }}">{{ $package->original_sha256 }}</div>@endif
                        <div class="text-xs text-base-content/70">{{ __('finance.external_invoice.packages.evidence', ['count' => count($package->evidence ?? [])]) }}</div>
                    </td>
                    <td class="whitespace-nowrap">
                        @forelse ($package->dispatches as $attempt)
                            <div><x-status-badge :tone="$attempt->status->tone()" size="sm">{{ $attempt->status->label() }}</x-status-badge> <span class="text-xs tabular-nums">{{ $attempt->created_at?->fdatetime() }}</span></div>
                        @empty
                            —
                        @endforelse
                        @if ($unclear)<div class="text-xs text-warning">{{ __('finance.external_invoice.packages.unclear') }}</div>@endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @if ($canSend)
                            <div class="flex justify-end gap-1">
                                @if ($unclear)
                                    <x-action-form :action="route('finance.external-invoices.packages.resolve', $package)">
                                        <input type="hidden" name="delivered" value="1">
                                        <x-icon-btn icon="mark_email_read" size="xs" type="submit" :title="__('finance.external_invoice.action.resolve_delivered')" />
                                    </x-action-form>
                                    <x-action-form :action="route('finance.external-invoices.packages.resolve', $package)">
                                        <input type="hidden" name="delivered" value="0">
                                        <x-icon-btn icon="unsubscribe" tone="error" size="xs" type="submit" :title="__('finance.external_invoice.action.resolve_failed')" />
                                    </x-action-form>
                                @elseif (! $pending && ! $delivered)
                                    <x-action-form :action="route('finance.external-invoices.packages.retry', $package)">
                                        <x-icon-btn icon="replay" size="xs" type="submit" :title="__('finance.external_invoice.action.retry')" />
                                    </x-action-form>
                                @elseif ($delivered)
                                    <x-action-form :action="route('finance.external-invoices.packages.redeliver', $package)"
                                                   :confirm="__('finance.external_invoice.confirm_redeliver')" confirm-icon="forward_to_inbox" confirm-tone="warning">
                                        <input type="hidden" name="confirm" value="1">
                                        <x-icon-btn icon="forward_to_inbox" size="xs" type="submit" :title="__('finance.external_invoice.action.redeliver')" />
                                    </x-action-form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="outgoing_mail" :colspan="6" :title="__('finance.external_invoice.packages.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
