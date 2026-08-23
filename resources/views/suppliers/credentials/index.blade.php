{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Pflichtnachweise (Feature 117, MVP-606): Ampel je Lieferant. Der gefährliche
  Fall ist nicht das fehlende, sondern das ABGELAUFENE Dokument — es ist da,
  sieht vollständig aus und trägt trotzdem nicht mehr.
--}}

@extends('layouts.app')

@section('title', __('procurement.credentials.title'))
@section('nav-title', __('procurement.credentials.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('procurement.credentials.subtitle')">
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ $blockingEnabled ? __('procurement.credentials.blocking_on') : __('procurement.credentials.blocking_off') }}</span>
        </div>

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('procurement.credentials.column.supplier') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('procurement.credentials.column.status') }}</x-table.th>
                    <th>{{ __('procurement.credentials.column.details') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-medium">{{ $row['supplier']->displayLabel() }}</td>
                    <td><x-status-badge :tone="$row['status']->tone()">{{ $row['status']->label() }}</x-status-badge></td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($row['items'] as $item)
                                <span class="badge badge-sm badge-outline"
                                      title="{{ $item['credential']?->valid_until?->fdate() ?? __('procurement.credentials.no_document') }}">
                                    {{ $item['type']->name }}: {{ $item['status']->label() }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end">
                            <x-icon-btn icon="add" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('suppliers.credentials.create', $row['supplier'])"
                                        :label="__('procurement.credentials.action.add')" />
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="verified_user" :title="__('procurement.credentials.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
