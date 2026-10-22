{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gutschriften im Gutschriftsverfahren (MVP-1280). Variablen: $credits, $status.
--}}
@extends('layouts.app')
@section('title', __('invoicing.self_billing.title'))
@section('nav-title', __('invoicing.self_billing.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('invoicing.self_billing.subtitle')" back-route="billing.feed" :back-label="__('Zurück')">
    <x-slot:actions>
        @can(\App\Enums\User\Permission::InvoiceCreate->value)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('invoices.self-billing.create')" show-label>{{ __('invoicing.self_billing.action.create') }}</x-icon-btn>
        @endcan
        <x-help-button topic="invoices.self-billing" />
    </x-slot:actions>

    <x-tab-nav class="w-fit" :items="array_merge(
        [['route' => 'invoices.self-billing.index', 'params' => [], 'active' => $status === null, 'icon' => 'list', 'label' => __('invoicing.self_billing.filter.all')]],
        array_map(fn (\App\Enums\Invoicing\SelfBillingStatus $case): array => ['route' => 'invoices.self-billing.index', 'params' => ['status' => $case->value], 'active' => $status === $case, 'icon' => 'filter_list', 'label' => $case->label()], \App\Enums\Invoicing\SelfBillingStatus::cases()),
    )" />

    @if ($credits->isEmpty())
        <x-empty-state framed icon="receipt_long" :title="__('invoicing.self_billing.empty')" :message="__('invoicing.self_billing.help.intro')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('invoicing.self_billing.field.number') }}</th>
                    <th>{{ __('invoicing.self_billing.field.supplier') }}</th>
                    <th>{{ __('invoicing.self_billing.field.service_period') }}</th>
                    <th class="text-right">{{ __('invoicing.self_billing.field.gross') }}</th>
                    <th>{{ __('invoicing.self_billing.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($credits as $credit)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('invoices.self-billing.show', $credit) }}" class="link link-hover font-medium">{{ $credit->number ?? __('invoicing.self_billing.draft_label') }}</a></td>
                    <td>{{ $credit->supplier?->company ?: $credit->supplier?->name }}</td>
                    <td class="whitespace-nowrap">{{ $credit->service_from?->fdate() ?? '—' }} – {{ $credit->service_until?->fdate() ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">{{ $credit->gross_amount?->format() ?? '—' }}</td>
                    <td><x-status-badge :tone="$credit->status->tone()" size="xs">{{ $credit->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm" target="_blank" :href="route('invoices.self-billing.pdf', $credit)" :label="__('invoicing.self_billing.action.pdf')" /></td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$credits" standing />
    @endif
</x-index-page>
@endsection
