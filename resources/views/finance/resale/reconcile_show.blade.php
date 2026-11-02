{{--
  Created on   : Mon Sep 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : reconcile_show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abgleich eines Rechnungsempfängers (Feature 152), Arbeitsmappe seit
  Phase 165: Abos mit Halter-Aktionen, Abgleichstafel je Produkt ($board,
  Phase 166), Serienvorlagen, Gutschriften und Stücklizenzen. Zuordnung über
  Abo-Grenzen hinweg, Verzicht, Übernahme und Rechnungsentwurf direkt hier.
--}}
@extends('layouts.app')
@section('title', __('resale.reconcile.show_title', ['customer' => $customer->name]))
@section('nav-title', __('resale.title.menu'))

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
    $compact = \App\View\Components\Resale\LicenceMonths::class;
@endphp

@section('content')
    <x-index-page :title="__('resale.reconcile.show_title', ['customer' => $customer->name])" :subtitle="__('resale.reconcile.show_subtitle')"
                  back-route="finance.resale.reconcile.index" :back-label="__('resale.action.back')">
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
            @can(\App\Enums\User\Permission::ResellingInvoice->value)
                <x-icon-btn icon="receipt_long" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.periods.draft.create', ['customer' => $customer->sqid])" show-label>{{ __('resale.draft.action') }}</x-icon-btn>
            @endcan
            <x-icon-btn icon="person" tone="ghost" size="sm" :href="route('customers.show', $customer)" show-label>{{ __('resale.reconcile.action.customer') }}</x-icon-btn>
        </x-slot:actions>

        {{-- Zuordnung und Halterwechsel senden ohne Dialog: Feldfehler landen hier. --}}
        <x-validation-errors class="mb-4" />

        {{-- Arbeitsliste (Phase 165, MVP-1445): Position unter den offenen Empfängern, Vorheriger/Nächster; fertig = nichts mehr zu tun. --}}
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4 text-sm">
            <span class="flex flex-wrap items-center gap-2">
                @if ($workspace['done'])
                    <x-status-badge tone="success" size="sm" :label="__('resale.reconcile.worklist.done')" />
                    <span class="text-muted">{{ __('resale.reconcile.worklist.done_hint') }}</span>
                @else
                    <span class="font-medium">{{ __('resale.reconcile.worklist.position', ['position' => $workspace['position'], 'total' => $workspace['total']]) }}</span>
                    @if ($workspace['open_amount'] !== null)
                        <span class="text-muted">· {{ __('resale.reconcile.worklist.open_amount') }} <span class="font-medium text-error">{{ $workspace['open_amount']->format() }}</span></span>
                    @endif
                @endif
            </span>
            <span class="flex items-center gap-1">
                @if ($workspace['prev'] !== null)
                    <x-icon-btn icon="chevron_left" size="sm" tone="ghost" :href="route('finance.resale.reconcile.show', $workspace['prev'])" show-label>{{ __('resale.reconcile.worklist.prev') }}</x-icon-btn>
                @endif
                @if ($workspace['next'] !== null)
                    <x-icon-btn icon="chevron_right" size="sm" tone="primary" :href="route('finance.resale.reconcile.show', $workspace['next'])" show-label>{{ __('resale.reconcile.worklist.next') }}</x-icon-btn>
                @else
                    <span class="text-muted">{{ __('resale.reconcile.worklist.all_done') }}</span>
                @endif
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            {{-- Kacheln filtern die Tafel auf die Produkte hinter ihrer Zahl; die aktive Kachel hebt den Filter wieder auf (Pilot 2026-11-01). --}}
            @php
                $focusUrl = static fn (string $key): string => route('finance.resale.reconcile.show', $focus === $key ? [$customer] : [$customer, 'focus' => $key]) . '#board';
            @endphp
            <x-kpi-tile :label="__('resale.reconcile.kpi.open')" :value="$open" :tone="$open > 0 ? 'error' : 'success'" :href="$focusUrl('open')" :active="$focus === 'open'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.partial')" :value="$partial" :tone="$partial > 0 ? 'warning' : 'neutral'" :href="$focusUrl('partial')" :active="$focus === 'partial'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.proposed')" :value="$proposed" :tone="$proposed > 0 ? 'info' : 'neutral'" :href="$focusUrl('proposed')" :active="$focus === 'proposed'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.free')" :value="$compact::compact($free)" format="raw" :tone="$free > 0.001 ? 'warning' : 'neutral'" :href="$focusUrl('free')" :active="$focus === 'free'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.missing')" :value="$compact::compact($missing)" format="raw" :tone="$missing > 0.001 ? 'error' : 'neutral'" :href="$focusUrl('missing')" :active="$focus === 'missing'" />
            <x-kpi-tile :label="__('resale.reconcile.kpi.surplus')" :value="$compact::compact($surplus)" format="raw" :tone="$surplus > 0.001 ? 'info' : 'neutral'" :href="$focusUrl('surplus')" :active="$focus === 'surplus'" />
        </div>

        @if (! $has_source)
            <div role="alert" class="alert alert-warning mb-4 text-sm">{{ __('resale.mirror.no_source') }}</div>
        @elseif ($pending > 0)
            <div class="alert alert-info mb-4 text-sm" title="{{ __('resale.link_ui.pending_hint') }}">{{ trans_choice('resale.invoices.pending', $pending, ['count' => $pending]) }}</div>
        @endif

        @if ($inbox !== [])
            {{-- Endkunden im Rechnungstext, deren Abos noch ohne Halter sind. --}}
            <div role="alert" class="alert alert-warning mb-4 text-sm">
                <div>
                    <span class="font-medium">{{ __('resale.reconcile.inbox.title') }}</span>
                    <span class="block text-xs">{{ __('resale.reconcile.inbox.hint', ['customer' => $customer->name]) }}</span>
                    <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        @foreach ($inbox as $hint)
                            <li>
                                <span class="font-medium">{{ $hint['company'] }}</span>
                                @foreach ($hint['subscriptions'] as $unassigned)
                                    · <a href="{{ route('finance.resale.show', $unassigned->sqid) }}" class="link link-hover">{{ $unassigned->label }} × {{ $unassigned->quantity }}</a>
                                @endforeach
                                · {{ trans_choice('resale.reconcile.inbox.mentions', $hint['mentions'], ['count' => $hint['mentions']]) }}
                            </li>
                        @endforeach
                    </ul>
                    <x-icon-btn icon="inbox" size="xs" tone="ghost" :href="route('finance.resale.inbox')" show-label class="mt-1">{{ __('resale.inbox.title') }}</x-icon-btn>
                </div>
            </div>
        @endif

        {{-- Abos des Empfängers mit Halter-Aktionen (Phase 165, MVP-1443): bearbeiten (auch Halter und Ende), Übernahme,
             abtreten, Abtretung zurücknehmen — ohne Seitenwechsel; die Dialoge kehren hierher zurück. --}}
        <x-card :title="__('resale.reconcile.subscriptions.title')" padding="p-0" class="mb-4">
            <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.reconcile.subscriptions.hint') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.field.label') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.reconcile.subscriptions.col.open') }}</x-table.th>
                        <x-table.th class="text-right"></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($subscriptions as $subscription)
                    @php
                        $openCount = $subscription->duePeriods($today)->count();
                    @endphp
                    <tr class="hover">
                        <td class="text-sm">
                            <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
                            @if ($subscription->isAssignment())<span class="block text-xs text-muted">{{ __('resale.transfer.from') }} {{ $subscription->parent?->label }} · {{ $subscription->parent?->holderLabel() }}</span>@endif
                            {{-- Abtretung an den eigenen Halter (Phase 167, MVP-1458): zurücknehmen, dann greifen die Rechnungsbezüge wieder. --}}
                            @if ($subscription->selfAssignmentConflict())<x-status-badge size="xs" tone="error" class="mt-0.5" :label="__('resale.transfer.self_assignment')" :title="__('resale.transfer.self_assignment_text')" />@endif
                        </td>
                        <td class="text-sm">{{ $subscription->holderLabel() }}</td>
                        <td class="text-sm">
                            {{ $subscription->provider->label() }}@if ($subscription->provider_account)<span class="block text-xs text-muted">{{ $subscription->provider_account }}</span>@endif
                            {{-- Subuser-Konto: das Domainportal stellt die Rechnung, das Register entwirft nichts (MVP-1446). --}}
                            @if (($portalAccount = $subscription->providerBillingAccount()) !== null)<span class="block text-xs text-info">{{ __('resale.plan.portal_billed', ['account' => $portalAccount]) }}</span>@endif
                        </td>
                        <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                        <td class="text-sm tabular-nums whitespace-nowrap">{{ $subscription->starts_on->fdate() }} – {{ $subscription->ends_on?->fdate() ?? __('resale.reconcile.subscriptions.open_end') }}</td>
                        <td><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></td>
                        <td class="text-right tabular-nums">@if ($openCount > 0)<x-status-badge size="xs" tone="error">{{ $openCount }}</x-status-badge>@else<span class="text-muted">0</span>@endif</td>
                        <td class="text-right">
                            @if ($canManage)
                                <div class="flex items-center justify-end gap-1">
                                    <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.edit', $subscription->sqid)" :title="__('resale.action.edit')" />
                                    {{-- Andere Firma führt die Lizenzen weiter, etwa nach Insolvenz (Phase 166, MVP-1451). --}}
                                    <x-icon-btn icon="handshake" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.takeover.create', $subscription->sqid)" :title="__('resale.takeover.action')" />
                                    @if (! $subscription->isAssignment())
                                        <x-icon-btn icon="call_split" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.transfer.create', $subscription->sqid)" :title="__('resale.transfer.action')" />
                                    @else
                                        <form method="POST" action="{{ route('finance.resale.destroy', $subscription->sqid) }}" class="inline" data-confirm-dialog data-confirm-message="{{ __('resale.reconcile.subscriptions.confirm_return', ['holder' => $subscription->holderLabel()]) }}" data-confirm-tone="warning">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn icon="undo" size="xs" tone="ghost" type="submit" :title="__('resale.reconcile.subscriptions.action.return')" />
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" :title="__('resale.reconcile.subscriptions.empty')" compact />
                @endforelse
            </x-table>
        </x-card>

        {{-- Abgleichstafel je Produkt (Phase 166, MVP-1447): Perioden (Soll) und Rechnungspositionen (Ist) auf einem Zeitstrahl —
             ersetzt Bilanz je Produkt, offene Perioden, alle Perioden und alle Positionen. --}}
        <div id="board" class="scroll-mt-4">
            @if ($focus !== '')
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <x-status-badge size="sm" tone="info" :label="__('resale.board.focus', ['filter' => __('resale.reconcile.kpi.' . $focus)])" />
                    <a href="{{ route('finance.resale.reconcile.show', $customer) }}#board" class="link link-hover text-xs">{{ __('resale.board.focus_reset') }}</a>
                </div>
            @endif
            @include('finance.resale._reconcile_board')
        </div>

        {{-- Serienvorlagen des Empfängers (Phase 165, MVP-1444): Zuordnung, Ausführungsstand und Befunde hier statt im Lizenzkonto. --}}
        @include('finance.resale._plan_section', ['plan' => $plan, 'candidates' => $planCandidates, 'storeRoute' => route('finance.resale.reconcile.plan.store', $customer), 'canManage' => $canManage])

        {{-- Gutschriften des Empfängers (Review 2026-09-10, A3): mindern die Deckung; Zuordnung nur von Hand,
             die Menge wird positiv eingegeben (gutgeschriebene Lizenzen) und vom PeriodLinker negativ verbucht. --}}
        @if ($credit_notes !== [])
            <x-card :title="__('resale.credit_notes.title')" padding="p-0" class="mt-4">
                <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.credit_notes.hint') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.invoices.voucher') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.reconcile.col.licence_months') }}</x-table.th>
                            <x-table.th>{{ __('resale.credit_notes.col_linked') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.invoices.assign') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($credit_notes as $creditRow)
                        @php
                            $line = $creditRow['line'];
                            $voucher = $line;
                            $creditFree = max(0.0, $creditRow['months'] - $creditRow['linked']);
                        @endphp
                        <tr @class(['opacity-60' => $creditFree <= 0.001])>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('finance.resale.vouchers.show', ['source' => $voucher->sourceKey, 'voucher' => $voucher->voucherKey]) }}" data-entry-modal-trigger class="link link-hover font-mono text-xs" title="{{ __('resale.voucher.open') }}">{{ $voucher->voucherNumber }}</a>
                                <span class="text-xs text-muted tabular-nums">{{ $voucher->voucherDate?->fdate() }} · {{ __('resale.link_ui.position', ['position' => $line->position]) }}</span>
                                @if ($voucher->servicePeriodLabel() !== null)
                                    <span class="block text-xs text-muted tabular-nums">{{ __('resale.reconcile.service_period') }} {{ $voucher->servicePeriodLabel() }}</span>
                                @endif
                                @if ($line->previewUrl !== null)
                                    <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$line->previewUrl" :title="__('resale.invoices.preview')" />
                                @endif
                            </td>
                            <td class="text-sm">{{ $line->label() }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap text-error">−<x-resale.licence-months :value="$creditRow['months']" :per-licence="$creditRow['per_licence']" /></td>
                            <td class="text-xs">
                                @if ($creditRow['linked'] > 0.001)
                                    <span class="text-success">{{ __('resale.credit_notes.linked_with', ['amount' => \App\Services\Reselling\Register\LicenseMonths::label($creditRow['linked'], $creditRow['per_licence']), 'periods' => implode(' · ', array_unique($creditRow['periods']))]) }}</span>
                                @else
                                    <span class="text-warning">{{ __('resale.credit_notes.unlinked') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($canManage && $creditFree > 0.001 && $allPeriods !== [])
                                    <form method="POST" action="{{ route('finance.resale.reconcile.assign', $customer) }}" class="flex items-center justify-end gap-1" title="{{ __('resale.credit_notes.sign_hint') }}">
                                        @csrf
                                        <input type="hidden" name="line_id" value="{{ $line->key }}">
                                        <select name="period_id" class="select select-xs select-bordered w-64" aria-label="{{ __('resale.field.period') }}">
                                            @foreach ($allPeriods as $target)
                                                <option value="{{ $target['period']->sqid }}">{{ $target['subscription']->label }} · {{ $target['period']->label() }} ({{ $target['period']->status->label() }})</option>
                                            @endforeach
                                        </select>
                                        {{-- Lizenzmonate statt Lizenzen der Position: keine Rundung über die Gutschrift hinaus (Pilot 2026-11-01). --}}
                                        <input type="number" name="months" step="0.01" min="0.01" value="{{ number_format($creditFree, 2, '.', '') }}"
                                               class="input input-xs input-bordered w-16 text-right" aria-label="{{ __('resale.link.months_field') }}" title="{{ __('resale.link.months_field') }}">
                                        <x-icon-btn icon="remove_circle" size="xs" tone="error" type="submit" :title="__('resale.credit_notes.action_link')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        {{-- Stücklizenzen ohne Rechnungsbezug (Feature 185, MVP-1428): verkauft, aber nach der Frist ohne Position im Spiegel und ohne Referenz. --}}
        @if ($unbilledLicenses->isNotEmpty())
            <x-card :title="__('resale.reconcile.licenses.title')" icon="key_off" :count="$unbilledLicenses->count()" padding="p-0" class="mt-4">
                <p class="px-4 py-2 text-xs text-muted border-b border-base-300">{{ __('resale.reconcile.licenses.hint', ['days' => \App\Services\Reselling\License\LicenseInvoiceLinks::UNBILLED_DAYS]) }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.license.field.product') }}</x-table.th>
                            <x-table.th>{{ __('resale.license.field.license') }}</x-table.th>
                            <x-table.th>{{ __('resale.license.field.holder') }}</x-table.th>
                            <x-table.th>{{ __('resale.license.field.sold_on') }}</x-table.th>
                            <x-table.th class="text-right"></x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($unbilledLicenses as $license)
                        <tr class="hover">
                            <td class="text-sm">{{ $license->unit->batch->product->name }}</td>
                            <td class="whitespace-nowrap text-sm"><a href="{{ route('finance.resale.licenses.batches.show', $license->unit->batch) }}" class="link link-hover">{{ $license->unit->label() }}</a></td>
                            <td class="text-sm">{{ $license->holderLabel() }}</td>
                            <td class="tabular-nums text-sm whitespace-nowrap">{{ $license->sold_on->fdate() }}</td>
                            <td class="text-right">
                                @can(\App\Enums\User\Permission::ResellingManage->value)
                                    <x-icon-btn icon="swap_horiz" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.assignments.reassign.create', $license)" :title="__('resale.license.action.reassign')" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
    </x-index-page>
@endsection
