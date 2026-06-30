{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3): Liste der Stapel
  mit Status/Zeitraum/Summe/Hash und Kennzahl buchungsreifer offener Belege.
--}}

@extends('layouts.app')

@section('title', __('finance.datev.title'))
@section('nav-title', __('finance.datev.menu'))

@section('content')
    <x-index-page :subtitle="__('finance.datev.subtitle')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-icon-btn icon="settings" tone="ghost" size="sm"
                            :href="route('finance.datev.config')"
                            show-label>{{ __('finance.datev.action.configure') }}</x-icon-btn>
            @endif
            @if ($canCreate)
                @if ($importAvailable)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('finance.datev.create')"
                                show-label>{{ __('finance.datev.action.create') }}</x-icon-btn>
                @else
                    <span class="text-sm text-base-content/60">{{ \App\Services\Finance\FinancialFormatsSupport::unavailableMessage('finance.datev.error.unavailable') }}</span>
                @endif
            @endif
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-3 mb-4 sm:max-w-xs">
            <x-kpi-tile :label="__('finance.datev.field.open_ready')" :value="$openCount" tone="warning" />
        </div>

        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('finance.datev.field.batch_no') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.period') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.status') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('finance.datev.field.booking_count') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('finance.datev.field.total') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.hash') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($batches as $batch)
                <tr>
                    <td>#{{ $batch->batch_no }}</td>
                    <td>{{ $batch->period_from?->toDateString() }} – {{ $batch->period_to?->toDateString() }}</td>
                    <td><x-status-badge :tone="$batch->status->tone()" :label="$batch->status->label()" /></td>
                    <td class="text-right">{{ $batch->booking_count }}</td>
                    <td class="text-right">{{ number_format((float) $batch->total_amount, 2, ',', '.') }}</td>
                    <td class="font-mono text-xs">{{ $batch->file_hash ? \Illuminate\Support\Str::limit($batch->file_hash, 12, '…') : '—' }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('finance.datev.show', $batch)" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>'
                               :title="__('finance.datev.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$batches" standing />
    </x-index-page>
@endsection
