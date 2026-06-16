{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Vorschau eines DATEV-Buchungsstapels (Feature 045, Priorität 2): Buchungssätze
  (Soll/Haben/Konto/Gegenkonto/Steuerschlüssel/Betrag/Beleg), Summen,
  Preflight-Warnungen sowie Aktionen Finalisieren und Download.
--}}

@extends('layouts.app')

@section('title', __('finance.datev.title') . ' #' . $batch->batch_no)
@section('nav-title', __('finance.datev.menu'))

@section('content')
    <x-index-page :subtitle="$batch->period_from?->toDateString() . ' – ' . $batch->period_to?->toDateString()">
        <x-slot:actions>
            @if ($batch->file_path)
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('finance.datev.download', $batch)"
                            show-label>{{ __('finance.datev.action.download') }}</x-icon-btn>
            @endif
            @if ($canFinalize)
                <form method="POST" action="{{ route('finance.datev.finalize', $batch) }}" class="inline">
                    @csrf
                    <x-icon-btn type="submit" icon="lock" tone="primary" size="sm"
                                show-label
                                :disabled="! $importAvailable || $preflight['errors'] !== []">
                        {{ __('finance.datev.action.finalize') }}
                    </x-icon-btn>
                </form>
            @endif
        </x-slot:actions>

        @if (! $importAvailable)
            <div class="alert alert-warning text-sm mb-4">{{ \App\Services\Finance\FinancialFormatsSupport::unavailableMessage('finance.datev.error.unavailable') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <x-card>
                <div class="text-sm text-base-content/60">{{ __('finance.datev.field.status') }}</div>
                <div><x-status-badge :tone="$batch->status->tone()" :label="$batch->status->label()" /></div>
            </x-card>
            <x-card>
                <div class="text-sm text-base-content/60">{{ __('finance.datev.field.booking_count') }}</div>
                <div class="text-xl font-semibold">{{ $preflight['count'] }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-base-content/60">{{ __('finance.datev.field.total') }}</div>
                <div class="text-xl font-semibold">{{ number_format($preflight['total'], 2, ',', '.') }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-base-content/60">{{ __('finance.datev.field.lock_flag') }}</div>
                <div>{{ $batch->finalized_locked ? __('finance.datev.lock.on') : __('finance.datev.lock.off') }}</div>
            </x-card>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-4 text-xs">
            <span class="badge badge-ghost">{{ __('finance.datev.format.label') }}: {{ __('finance.datev.format.value') }}</span>
            @if ($batch->file_hash)
                {{-- Ein finalisierter Stapel hat die Write→Read-Validierung zwingend bestanden. --}}
                <span class="badge badge-success badge-outline gap-1">
                    <x-icon name="check_circle" class="w-3 h-3" />{{ __('finance.datev.format.verified') }}
                </span>
            @endif
        </div>

        @if ($batch->file_hash)
            <div class="text-xs text-base-content/60 mb-4 font-mono break-all">
                {{ __('finance.datev.field.hash') }}: {{ $batch->file_hash }}
            </div>
        @endif

        {{-- Konvertierungs-Hinweis (Kriterium 045): abgeleitete/vereinfachte Felder vor der Übergabe sichtbar machen. --}}
        <div class="alert alert-info text-sm mb-3 block">
            <div class="font-medium mb-1">{{ __('finance.datev.loss.title') }}</div>
            <p class="text-xs text-base-content/70 mb-1">{{ __('finance.datev.loss.hint') }}</p>
            <ul class="list-disc pl-5 text-xs">
                <li>{{ __('finance.datev.loss.booking_date') }}</li>
                <li>{{ __('finance.datev.loss.expense_account') }}</li>
                <li>{{ __('finance.datev.loss.missing_tax_key') }}</li>
            </ul>
        </div>

        @if ($preflight['errors'] !== [])
            <div class="alert alert-error text-sm mb-3">
                <ul class="list-disc pl-5">@foreach ($preflight['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($preflight['warnings'] !== [])
            <div class="alert alert-warning text-sm mb-3">
                <ul class="list-disc pl-5">@foreach ($preflight['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
            </div>
        @endif

        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('finance.datev.field.document_ref') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.soll_haben') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.account') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.contra_account') }}</x-table.th>
                    <x-table.th>{{ __('finance.datev.field.tax_key') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('finance.datev.field.amount') }}</x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($batch->sources as $source)
                <tr>
                    <td class="font-mono text-xs">{{ $source->document_ref }}</td>
                    <td>{{ $source->soll_haben }}</td>
                    <td class="font-mono">{{ $source->debtor_account }}</td>
                    <td class="font-mono">{{ $source->revenue_account }}</td>
                    <td>{{ $source->tax_key ?? '—' }}</td>
                    <td class="text-right">{{ number_format((float) $source->amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-base-content/60 py-6">{{ __('finance.datev.empty_sources') }}</td></tr>
            @endforelse
        </x-table>
    </x-index-page>
@endsection
