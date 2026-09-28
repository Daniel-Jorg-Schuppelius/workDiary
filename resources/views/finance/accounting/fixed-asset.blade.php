{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fixed-asset.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage im Detail (Feature 133, MVP-698): Stammdaten, Konten und der
  berechnete AfA-Plan je Geschäftsjahr mit Buchungsstand aus dem Journal.
--}}

@extends('layouts.app')

@section('title', $fixedAsset->displayNo())
@section('nav-title', $fixedAsset->displayNo() . ' · ' . $fixedAsset->name)

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="$fixedAsset->name"
                            :badge="$fixedAsset->status->label()" :badgeTone="$fixedAsset->status->tone()"
                            back-route="finance.accounting.fixed-assets.index" :back-label="__('Zurück')">
                <x-slot:actions>
                    @if ($canConfigure && ! $fixedAsset->isDisposed())
                        <x-icon-btn icon="edit" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('finance.accounting.fixed-assets.edit', $fixedAsset)"
                                    show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                        <x-icon-btn icon="logout" tone="warning" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('finance.accounting.fixed-assets.dispose-form', $fixedAsset)"
                                    show-label>{{ __('accounting.fixed_assets.action.dispose') }}</x-icon-btn>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <x-card :title="__('accounting.fixed_assets.section.master')" icon="precision_manufacturing">
                    @if ($frozen)
                        <p class="mb-3 text-xs text-muted">{{ __('accounting.fixed_assets.hint.frozen') }}</p>
                    @endif
                    <x-detail-grid>
                        <x-detail-grid.row :label="__('accounting.fixed_assets.column.no')" :value="$fixedAsset->displayNo()" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.column.name')" :value="$fixedAsset->name" />
                        @if ($fixedAsset->asset)
                            <x-detail-grid.row :label="__('accounting.fixed_assets.field.device')">
                                <a class="link link-hover" href="{{ route('assets.show', $fixedAsset->asset) }}">{{ $fixedAsset->asset->asset_no }} · {{ $fixedAsset->asset->name }}</a>
                            </x-detail-grid.row>
                        @endif
                        @if ($fixedAsset->assetClass !== null)
                            <x-detail-grid.row :label="__('accounting.fixed_assets.field.class')" :value="$fixedAsset->assetClass->name" />
                        @endif
                        {{-- Herkunft (MVP-999): Eingangsrechnung oder Auslage, aus der die Anlage entstand. --}}
                        @if ($fixedAsset->source instanceof \App\Models\Invoicing\IncomingEInvoice)
                            <x-detail-grid.row :label="__('accounting.fixed_assets.field.source')">
                                <a class="link link-primary" href="{{ route('finance.incoming-invoices.show', $fixedAsset->source->document_id) }}">{{ __('accounting.fixed_assets.source.incoming', ['number' => $fixedAsset->source->invoice_number ?? $fixedAsset->source->sqid]) }}</a>
                            </x-detail-grid.row>
                        @elseif ($fixedAsset->source instanceof \App\Models\Travel\Expense)
                            <x-detail-grid.row :label="__('accounting.fixed_assets.field.source')" :value="__('accounting.fixed_assets.source.expense', ['description' => $fixedAsset->source->description])" />
                        @endif
                        <x-detail-grid.row :label="__('accounting.fixed_assets.column.acquired_on')" :value="$fixedAsset->acquired_on->fdate()" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.column.cost')" :value="$fixedAsset->acquisition_cost?->format()" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.field.residual_value')" :value="$fixedAsset->residual_value?->format() ?? '—'" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.column.useful_life')" :value="__('accounting.fixed_assets.months', ['count' => $fixedAsset->useful_life_months])" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.field.method')" :value="$fixedAsset->depreciation_method->label() . ($fixedAsset->declining_rate !== null ? ' · ' . $fixedAsset->declining_rate->format() : '')" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.field.asset_account')" :value="$fixedAsset->assetAccount?->displayLabel() ?? __('accounting.fixed_assets.account_from_rule')" />
                        <x-detail-grid.row :label="__('accounting.fixed_assets.field.depreciation_account')" :value="$fixedAsset->depreciationAccount?->displayLabel() ?? __('accounting.fixed_assets.account_from_rule')" />
                        @if ($fixedAsset->disposed_on)
                            <x-detail-grid.row :label="__('accounting.fixed_assets.field.disposed_on')" :value="$fixedAsset->disposed_on->fdate()" />
                        @endif
                        @if ($fixedAsset->note)
                            <x-detail-grid.row :label="__('accounting.ledger.field.note')" :value="$fixedAsset->note" />
                        @endif
                        <x-detail-grid.row :label="__('accounting.fixed_assets.field.created_by')" :value="$fixedAsset->createdBy?->name ?? '—'" />
                    </x-detail-grid>
                </x-card>

                <x-card :title="__('accounting.fixed_assets.section.schedule')" icon="trending_down"
                        :subtitle="__('accounting.fixed_assets.hint.schedule')">
                    <x-table :bare="true">
                        <x-slot:head>
                            <tr>
                                <th>{{ __('accounting.fixed_assets.schedule.year') }}</th>
                                <th class="text-center">{{ __('accounting.fixed_assets.schedule.months') }}</th>
                                <th class="text-right">{{ __('accounting.fixed_assets.schedule.amount') }}</th>
                                @if ($specials->isNotEmpty())
                                    <th class="text-right">{{ __('accounting.fixed_assets.special.column') }}</th>
                                @endif
                                <th class="text-right">{{ __('accounting.fixed_assets.schedule.book_value_end') }}</th>
                                <th>{{ __('accounting.ledger.column.status') }}</th>
                            </tr>
                        </x-slot:head>
                        @forelse ($rows as $row)
                            @php($entry = $entries[$row->fiscalYear] ?? null)
                            <tr class="hover">
                                <td class="font-medium">{{ $row->label }}</td>
                                <td class="text-center">{{ $row->months }}</td>
                                <td class="text-right font-mono">{{ $row->amount->format() }}</td>
                                @if ($specials->isNotEmpty())
                                    <td class="text-right font-mono">{{ $row->special?->format() ?? '—' }}</td>
                                @endif
                                <td class="text-right font-mono">{{ $row->bookValueEnd->format() }}</td>
                                <td>
                                    @if ($entry)
                                        <a class="link link-hover" href="{{ route('finance.accounting.journal.show', $entry) }}">
                                            <x-posting-state :state="$entry->status->isPosted() ? 'posted' : 'ready'" />
                                        </a>
                                    @else
                                        <x-posting-state state="open" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="$specials->isNotEmpty() ? 6 : 5" :title="__('accounting.fixed_assets.schedule.empty')" compact />
                        @endforelse
                    </x-table>
                </x-card>

                @if ($specialAllowed || $specials->isNotEmpty())
                    {{-- Sonder-AfA § 7g (MVP-981): je Geschäftsjahr im Begünstigungszeitraum. --}}
                    <x-card :title="__('accounting.fixed_assets.special.title')" icon="bolt" :count="$specials->count()"
                            :subtitle="__('accounting.fixed_assets.special.hint')">
                        @if ($canConfigure && $specialAllowed)
                            <x-slot:actions>
                                <x-icon-btn icon="add" size="sm" data-entry-modal-trigger
                                            :href="route('finance.accounting.fixed-assets.special-form', $fixedAsset)"
                                            show-label>{{ __('accounting.fixed_assets.special.add') }}</x-icon-btn>
                            </x-slot:actions>
                        @endif
                        @forelse ($specials as $special)
                            <div class="flex items-center justify-between gap-2 py-1 text-sm">
                                <span>{{ $special->fiscal_year }} · <span class="font-mono">{{ $special->depreciation_amount->format() }}</span>@if ($special->note) <span class="text-muted">· {{ $special->note }}</span>@endif</span>
                                @if ($canConfigure)
                                    <x-action-form :action="route('finance.accounting.fixed-assets.special.destroy', [$fixedAsset, $special])" method="DELETE"
                                                   :confirm="__('accounting.fixed_assets.special.confirm_remove')" :confirm-label="__('Entfernen')">
                                        <x-icon-btn type="submit" icon="delete" size="sm" tone="error" :label="__('Entfernen')" />
                                    </x-action-form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-muted">{{ __('accounting.fixed_assets.special.none') }}</p>
                        @endforelse
                    </x-card>
                @endif
            </div>

            <div class="space-y-4">
                <x-card :title="__('accounting.fixed_assets.section.posting')" icon="inbox">
                    <p class="text-sm text-base-content/70">{{ __('accounting.fixed_assets.hint.posting') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-icon-btn icon="lock_clock" tone="outline" size="sm"
                                    :href="route('finance.accounting.closing.index')"
                                    show-label>{{ __('accounting.closing.menu') }}</x-icon-btn>
                        <x-icon-btn icon="inbox" tone="outline" size="sm"
                                    :href="route('finance.accounting.inbox.index', ['kind' => \App\Enums\Finance\PostingSourceKind::Depreciation->value])"
                                    show-label>{{ __('accounting.inbox.menu') }}</x-icon-btn>
                    </div>
                </x-card>
            </div>
        </div>
    </x-page-shell>
@endsection
