{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mandatsregister (Feature 120, MVP-609).
--}}

@extends('layouts.app')

@section('title', __('sepa.mandate.title'))
@section('nav-title', __('sepa.mandate.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('sepa.mandate.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('finance.mandates.create')"
                        show-label>{{ __('sepa.mandate.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('sepa.mandate.column.reference') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.mandate.column.customer') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.mandate.column.kind') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('sepa.mandate.column.signed_on') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('sepa.mandate.column.last_collected_on') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('sepa.mandate.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($mandates as $mandate)
                <tr class="hover">
                    <td class="font-mono text-xs">{{ $mandate->reference }}</td>
                    <td class="font-medium">{{ $mandate->customer?->name ?? '—' }}</td>
                    <td>{{ $mandate->kind->label() }}</td>
                    <td class="whitespace-nowrap">{{ optional($mandate->signed_on)->fdate() ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ optional($mandate->last_collected_on)->fdate() ?? '—' }}</td>
                    <td>
                        <x-status-badge :tone="$mandate->isUsable() ? 'success' : 'warning'" outline>{{ $mandate->status->label() }}</x-status-badge>
                        @unless ($mandate->isUsable())
                            <span class="text-xs text-muted">{{ __('sepa.mandate.not_usable') }}</span>
                        @endunless
                    </td>
                    <td class="text-right">
                        @if ($mandate->status === \App\Enums\Finance\MandateStatus::Active)
                            <div class="flex justify-end gap-1">
                                <x-action-form :action="route('finance.mandates.revoke', $mandate)" :confirm="__('sepa.mandate.confirm_revoke')">
                                    <x-icon-btn icon="block" size="xs" tone="ghost" type="submit"
                                                :label="__('sepa.mandate.action.revoke')" />
                                </x-action-form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="assignment" :title="__('sepa.mandate.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$mandates" standing />
    </x-index-page>
@endsection
