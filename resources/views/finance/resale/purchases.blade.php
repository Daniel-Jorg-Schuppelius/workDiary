{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : purchases.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einkaufsbelege (Feature 152, MVP-762): Anbieterrechnungen positionsgenau
  (PDF-Import), Eingangsbelege aus dem Spiegel pro rata, Domain-Buchungen
  automatisch — je Zeile die Zuteilung auf Abo und Periode.
--}}
@extends('layouts.app')
@section('title', __('resale.purchase.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
    $money = static fn(float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.purchase.title')" :subtitle="__('resale.purchase.subtitle')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="picture_as_pdf" tone="primary" size="sm" data-entry-modal-trigger :href="route('finance.resale.purchases.import.create')" show-label>{{ __('resale.purchase.import.action') }}</x-icon-btn>
                <x-icon-btn icon="receipt" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.purchases.create')" show-label>{{ __('resale.purchase.action.allocate') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.report.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        @if ($byDocument->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-3 text-xs">
                @foreach ($byDocument as $doc)
                    <span class="badge badge-outline">{{ $doc->document_number }} · {{ \Carbon\CarbonImmutable::parse($doc->entry_date)->format('d.m.Y') }} · {{ $money((float) $doc->net) }} · {{ $doc->n }}</span>
                @endforeach
            </div>
        @endif

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date">{{ __('resale.purchase.field.date') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.purchase.field.document') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.holder') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                    <x-table.th>{{ __('resale.purchase.field.description') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.purchase.field.net') }}</x-table.th>
                    <x-table.th>{{ __('resale.purchase.field.source') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($entries as $entry)
                <tr class="hover">
                    <td class="whitespace-nowrap tabular-nums">{{ $entry->entry_date->format('d.m.Y') }}</td>
                    <td class="font-mono text-xs">{{ $entry->document_number ?? '—' }}</td>
                    <td class="text-sm">{{ $entry->provider->label() }}</td>
                    <td>
                        @if ($entry->subscription !== null)
                            <a href="{{ route('finance.resale.show', $entry->subscription->sqid) }}" class="link link-hover">{{ $entry->subscription->label }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $entry->subscription?->holderLabel() ?? '—' }}</td>
                    <td class="whitespace-nowrap tabular-nums text-sm">{{ $entry->period?->label() ?? '—' }}</td>
                    <td class="text-xs text-muted max-w-xs truncate" title="{{ $entry->description }}">{{ $entry->description }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap {{ $entry->net_amount->toFloat() < 0 ? 'text-success' : '' }}">{{ $entry->net_amount->format() }}</td>
                    <td><x-status-badge size="xs" tone="neutral" :label="$entry->sourceLabel()" /></td>
                    <td class="text-right">
                        @if ($canManage)
                            <form method="POST" action="{{ route('finance.resale.purchases.destroy', $entry->sqid) }}" data-confirm="{{ __('resale.purchase.confirm.remove') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" tone="ghost" type="submit" :title="__('resale.purchase.action.remove')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="10" icon="shopping_cart" :title="__('resale.purchase.empty')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$entries" standing />
    </x-index-page>
@endsection
