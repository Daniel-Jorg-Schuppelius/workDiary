{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzkonto (Feature 185, MVP-1400): Aufteilung je Produkt, Raster
  Produkt × Halter × Lizenzjahr, Prüffälle an Bezügen und die Verträge samt
  Vorgängern. Daten liefert LicenseAccountOverview::detail().
--}}
@extends('layouts.app')
@section('title', $group->name())
@section('nav-title', __('resale.title.menu'))

@php
    $customer = $group->customer();
    $partner = $group->partner();
    $compact = \App\View\Components\Resale\LicenceMonths::class;
    $subtitle = $group->provider->label() . ' · ' . $group->account . ($partner !== null ? ' · ' . __('resale.holder.via', ['partner' => $partner->name]) : '');
@endphp

@section('content')
    <x-index-page :title="__('resale.account.show_title', ['name' => $group->name()])" :subtitle="$subtitle"
                  back-route="finance.resale.accounts.index" :back-label="__('resale.account.title')">
        <x-slot:actions>
            @if ($canManage && $products !== [])
                <x-icon-btn icon="call_split" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.accounts.allocations.create', $group->routeKey())"
                            show-label>{{ __('resale.account.allocation.add') }}</x-icon-btn>
            @endif
            @if ($customer !== null)
                <x-icon-btn icon="compare_arrows" tone="ghost" size="sm" :href="route('finance.resale.reconcile.show', $customer)" show-label>{{ __('resale.reconcile.title') }}</x-icon-btn>
                <x-icon-btn icon="person" tone="ghost" size="sm" :href="route('customers.show', $customer)" show-label>{{ __('resale.reconcile.action.customer') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-validation-errors class="mb-4" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            {{-- Bestand je Produkt mit Halteranteilen am Stichtag --}}
            <x-card :title="__('resale.account.section.products')" padding="p-0">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.account.col.licences') }}</x-table.th>
                            <x-table.th>{{ __('resale.account.col.holders') }}</x-table.th>
                            {{-- Soll-/Ist-Einkauf und Marge der letzten zwölf Monate (Feature 185, MVP-1421) --}}
                            <x-table.th class="text-right" :title="__('resale.account.economics_hint')">{{ __('resale.account.col.expected_purchase') }}</x-table.th>
                            <x-table.th class="text-right" :title="__('resale.account.economics_hint')">{{ __('resale.account.col.actual_purchase') }}</x-table.th>
                            <x-table.th class="text-right" :title="__('resale.account.economics_hint')">{{ __('resale.account.col.margin') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($products as $product)
                        @php $eco = $economics[$product['key']] ?? null; @endphp
                        <tr>
                            <td class="font-medium">{{ $product['label'] }}</td>
                            <td class="text-right tabular-nums">{{ $product['quantity'] }}</td>
                            <td class="text-sm">
                                @foreach ($product['holders'] as $holder)
                                    <span class="block whitespace-nowrap">{{ $holder['label'] }} <span class="text-muted tabular-nums">({{ $holder['quantity'] }})</span></span>
                                @endforeach
                                @if ($product['conflict'] > 0)
                                    <x-status-badge size="xs" tone="warning" class="mt-1">{{ __('resale.account.allocation.conflict', ['missing' => $product['conflict'], 'product' => $product['label']]) }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap text-sm">{{ $eco !== null && $eco['expected_purchase'] !== null ? $eco['expected_purchase']->withScale(2)->format() : '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap text-sm">
                                @if ($eco !== null && $eco['actual_purchase'] !== null)
                                    {{ $eco['actual_purchase']->withScale(2)->format() }}
                                    @if ($eco['with_actual'] < $eco['periods'])
                                        <span class="block text-xs text-muted">{{ __('resale.account.economics_partial', ['with' => $eco['with_actual'], 'periods' => $eco['periods']]) }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap text-sm {{ $eco !== null && $eco['margin'] !== null && $eco['margin']->isNegative() ? 'text-error' : '' }}">{{ $eco !== null && $eco['margin'] !== null ? $eco['margin']->withScale(2)->format() : '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('resale.account.grid.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>

            {{-- Gepflegte Aufteilung: fremde Halter je Produkt --}}
            <x-card :title="__('resale.account.section.allocation')" padding="p-0">
                <p class="px-4 pt-3 text-xs text-muted">{{ __('resale.account.allocation.rule') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.account.allocation.holder') }}</x-table.th>
                            <x-table.th>{{ __('resale.account.allocation.product') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.account.allocation.quantity') }}</x-table.th>
                            <x-table.th>{{ __('resale.account.allocation.validity') }}</x-table.th>
                            <x-table.th class="text-right"></x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($allocations as $allocation)
                        <tr class="hover">
                            <td class="text-sm">{{ $allocation->holderLabel() }}@if ($allocation->note)<span class="block text-xs text-muted">{{ $allocation->note }}</span>@endif</td>
                            <td class="text-sm">{{ $allocation->product_label }}</td>
                            <td class="text-right tabular-nums">{{ $allocation->quantity }}</td>
                            <td class="text-sm tabular-nums whitespace-nowrap">{{ $allocation->valid_from->fdate() }}@if ($allocation->valid_until) – {{ $allocation->valid_until->fdate() }}@endif</td>
                            <td class="text-right">
                                @if ($canManage)
                                    <div class="flex justify-end gap-1">
                                        <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.accounts.allocations.edit', $allocation->sqid)" :title="__('resale.action.edit')" />
                                        <form method="POST" action="{{ route('finance.resale.accounts.allocations.destroy', $allocation->sqid) }}" data-confirm-dialog data-confirm-message="{{ __('resale.account.allocation.confirm_delete') }}" data-confirm-tone="error">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn icon="delete" size="xs" tone="ghost" type="submit" :title="__('resale.action.delete')" />
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="5" :title="__('resale.account.allocation.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        {{-- Raster: je Vertrag bzw. Abtretung die Lizenzjahre mit Rechnung oder Lücke --}}
        <x-card :title="__('resale.account.section.grid')" padding="p-0" class="mb-4">
            <div class="overflow-x-auto">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            {{-- Erste Spalte haftet beim Querscrollen (wd-pin-col, wie Einsatz-/Qualifikationsmatrix); Jahresspalten mit schmalem Innenabstand. --}}
                            <x-table.th class="wd-pin-col">{{ __('resale.account.grid.row') }}</x-table.th>
                            @foreach ($years as $year)
                                <x-table.th class="text-center px-1.5">{{ $year }}</x-table.th>
                            @endforeach
                        </tr>
                    </x-slot:head>
                    @forelse ($rows as $row)
                        @php $subscription = $row['subscription']; @endphp
                        <tr @class(['hover', 'opacity-70' => $subscription->status === \App\Enums\Reselling\SubscriptionStatus::Superseded])>
                            <td class="text-sm wd-pin-col min-w-[14rem]">
                                <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium whitespace-nowrap">{{ $row['product'] }}</a>
                                <span class="block text-xs text-muted">
                                    {{ $row['holder'] }} · ×{{ $subscription->quantity }} · {{ $subscription->provider->label() }}
                                    {{-- Domain-Abos: Fremdkennung = Domain = Produktname, nicht doppelt zeigen. --}}
                                    @if ($subscription->external_id && $subscription->external_id !== $row['product']) · <span class="font-mono">{{ $subscription->external_id }}</span>@endif
                                </span>
                                @if ($subscription->isAssignment())
                                    <x-status-badge size="xs" tone="info" outline>{{ $subscription->isDerivedAssignment() ? __('resale.account.allocation.derived') : __('resale.transfer.section') }}</x-status-badge>
                                @endif
                                @if ($subscription->status === \App\Enums\Reselling\SubscriptionStatus::Superseded)
                                    <x-status-badge size="xs" outline>{{ __('resale.account.grid.predecessor') }}</x-status-badge>
                                @endif
                            </td>
                            @foreach ($years as $year)
                                {{-- nowrap: daisyUI-Badges haben feste Höhe, umbrechender Text liefe über den Hintergrund (Pilot 2026-10-29); je Beleg ein Badge, gestapelt. --}}
                                <td class="text-center align-top text-xs whitespace-nowrap px-1.5">
                                    @foreach ($row['cells'][$year] ?? [] as $cell)
                                        @php $period = $cell['period']; $upcoming = $period->starts_on->greaterThan($today); @endphp
                                        <div class="mb-1 flex flex-col items-center gap-0.5" title="{{ $period->label() }} · {{ $period->status->label() }}">
                                            @if ($subscription->is_own_holding)
                                                <x-status-badge size="xs" :label="$period->starts_on->format('d.m.')" />
                                            @elseif ($cell['vouchers'] !== [])
                                                @foreach ($cell['vouchers'] as $voucher)
                                                    <x-status-badge size="xs" :tone="$period->status->tone()" :label="$voucher" />
                                                @endforeach
                                            @elseif ($upcoming)
                                                <x-status-badge size="xs" outline :label="__('resale.account.grid.upcoming', ['date' => $period->starts_on->format('d.m.')])" />
                                            @elseif (in_array($period->status, [\App\Enums\Reselling\PeriodStatus::Open, \App\Enums\Reselling\PeriodStatus::Partial], true))
                                                <x-status-badge size="xs" tone="error" :label="__('resale.account.grid.missing')" />
                                            @else
                                                <x-status-badge size="xs" :tone="$period->status->tone()" :label="$period->status->label()" />
                                            @endif
                                            <span class="text-muted tabular-nums">{{ $period->starts_on->format('d.m.') }} · ×{{ $period->quantity }}</span>
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-table.empty :colspan="count($years) + 1" :title="__('resale.account.grid.empty')" compact />
                    @endforelse
                </x-table>
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Fällige Perioden ohne vollständige Rechnung --}}
            <x-card :title="__('resale.account.section.due')" padding="p-0">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                            <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.export.open_amount') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($due as $period)
                        @php $subscription = $period->subscription; @endphp
                        <tr class="hover">
                            <td class="text-sm">{{ $subscription->holderLabel() }}<span class="block text-xs text-muted">{{ $subscription->productName() }} · ×{{ $period->quantity }}</span></td>
                            <td class="text-sm tabular-nums whitespace-nowrap"><a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover">{{ $period->label() }}</a><span class="block text-xs"><x-status-badge size="xs" :tone="$period->status->tone()" :label="$period->status->label()" /></span></td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $period->openAmount()?->format() ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3" icon="task_alt" :title="__('resale.account.due_empty')" compact />
                    @endforelse
                </x-table>
            </x-card>

            {{-- Prüffälle an Bezügen: überbucht oder zeitlich unpassend --}}
            <x-card :title="__('resale.account.section.findings')" padding="p-0">
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.account.finding.kind') }}</x-table.th>
                            <x-table.th>{{ __('resale.account.finding.detail') }}</x-table.th>
                            <x-table.th class="text-right"></x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($findings as $finding)
                        @php $line = $finding['line']; @endphp
                        <tr class="hover">
                            <td class="align-top"><x-status-badge size="xs" :tone="$finding['kind'] === \App\Services\Reselling\Register\LinkAudit::OVERBOOKED ? 'error' : 'warning'" :label="__('resale.account.finding.' . $finding['kind'])" /></td>
                            <td class="text-sm">
                                <span class="font-mono text-xs">{{ $line->voucherNumber }}</span> · {{ __('resale.link_ui.position', ['position' => $line->position]) }} · {{ \Illuminate\Support\Str::limit($line->label(), 40) }}
                                @if ($finding['kind'] === \App\Services\Reselling\Register\LinkAudit::OVERBOOKED)
                                    <span class="block text-xs text-muted">{{ __('resale.account.finding.overbooked_text', ['used' => $compact::compact(\App\Services\Reselling\Register\LicenseMonths::ofLine($line) + $finding['excess']), 'available' => $compact::compact(\App\Services\Reselling\Register\LicenseMonths::ofLine($line))]) }}</span>
                                    <ul class="mt-1 space-y-0.5 text-xs">
                                        @foreach ($finding['links'] as $link)
                                            <li class="flex items-center justify-between gap-2">
                                                <span>{{ \App\Services\Reselling\Register\PeriodLinker::targetLabel($link) }} · {{ $compact::compact((float) $link->months) }} {{ __('resale.link.months') }}</span>
                                                @if ($canManage)
                                                    <form method="POST" action="{{ route('finance.resale.links.destroy', $link->sqid) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="block text-xs text-muted">{{ __('resale.account.finding.mismatch_text', ['service' => $line->servicePeriodLabel() ?? $line->voucherDate?->fdate() ?? '—', 'period' => $finding['period']->label()]) }}</span>
                                @endif
                            </td>
                            <td class="text-right align-top">
                                @if ($canManage && $finding['kind'] === \App\Services\Reselling\Register\LinkAudit::MISMATCH)
                                    <div class="flex justify-end gap-1">
                                        @if ($finding['suggested'] !== null)
                                            <form method="POST" action="{{ route('finance.resale.links.move', $finding['link']->sqid) }}">
                                                @csrf
                                                <input type="hidden" name="period_id" value="{{ $finding['suggested']->sqid }}">
                                                <x-icon-btn icon="move_down" size="xs" tone="primary" type="submit" show-label>{{ __('resale.account.finding.move', ['period' => $finding['suggested']->label()]) }}</x-icon-btn>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('finance.resale.links.destroy', $finding['link']->sqid) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3" icon="task_alt" :title="__('resale.account.finding.none')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        {{-- Serienvorlagen des Buchhaltungsprogramms: gemeinsames Teilstück mit der Arbeitsmappe (Phase 165, MVP-1444). --}}
        @include('finance.resale._plan_section', ['plan' => $plan, 'candidates' => \App\Services\Reselling\Plan\PlanSection::candidates($group->contracts()->merge($group->assignments())), 'storeRoute' => route('finance.resale.accounts.plan.store', $group->routeKey()), 'canManage' => $canManage])

        {{-- Verträge samt Vorgängern --}}
        <x-card :title="__('resale.account.section.contracts')" padding="p-0" class="mt-4">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.field.external_id') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.starts_on') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($group->contracts() as $contract)
                    <tr class="hover">
                        <td class="font-mono text-xs"><a href="{{ route('finance.resale.show', $contract->sqid) }}" class="link link-hover">{{ $contract->external_id ?? '—' }}</a></td>
                        <td class="text-sm">{{ $contract->productName() }}</td>
                        <td class="text-sm">{{ $contract->holderLabel() }}</td>
                        <td class="text-right tabular-nums">{{ $contract->quantity }}</td>
                        <td class="text-sm tabular-nums whitespace-nowrap">{{ $contract->starts_on->fdate() }}@if ($contract->ends_on) – {{ $contract->ends_on->fdate() }}@endif</td>
                        <td class="text-sm">{{ $contract->provider->label() }}</td>
                        <td><x-status-badge size="xs" :tone="$contract->status->tone()" :label="$contract->status->label()" /></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </x-index-page>
@endsection
