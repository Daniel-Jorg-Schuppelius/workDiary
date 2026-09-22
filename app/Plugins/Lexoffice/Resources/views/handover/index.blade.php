{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übergabeliste an Lexware (Feature 158, MVP-833): ausgestellte lokale
     Belege im globalen Header-Zeitraum mit Rechnungs-, Versand- und
     Übergabestatus; Export als Paket, manuelle Bestätigung. --}}
@extends('layouts.app')

@section('title', __('lexware.handover.title'))
@section('nav-title', __('lexware.menu'))

@php
    use App\Enums\Lexoffice\LexofficeHandoverStatus;
@endphp

@section('content')
<x-index-page :subtitle="__('lexware.handover.subtitle', ['range' => $rangeLabel])">
    <x-slot:actions>
        <x-icon-btn icon="tune" size="sm" :href="route('lexoffice.plan.index')" show-label>{{ __('lexware.menu') }}</x-icon-btn>
    </x-slot:actions>

    <x-validation-errors />

    <x-filter-bar :action="route('lexoffice.handover.index')" :reset="$statusFilter !== null ? route('lexoffice.handover.index') : null">
        <x-filter-field :label="__('lexware.field.handover_state')" for="handover-status" class="w-56 shrink-0">
            <select id="handover-status" name="status" class="select select-sm select-bordered">
                <option value="">{{ __('lexware.handover.filter_all') }}</option>
                @foreach (LexofficeHandoverStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <form method="POST" action="{{ route('lexoffice.handover.export') }}" id="handover-export-form">
        @csrf
        <x-card padding="p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm">
                <span class="text-muted">{{ trans_choice('lexware.handover.count', $invoices->count(), ['count' => $invoices->count()]) }}</span>
                @if ($canExport)
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('lexware.action.export_selected') }}</button>
                @endif
            </div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th class="w-px"><span class="sr-only">{{ __('lexware.field.select') }}</span></th>
                        <th>{{ __('Nummer') }}</th>
                        <th>{{ __('lexware.field.issued_on') }}</th>
                        <th>{{ __('Kunde') }}</th>
                        <th class="text-right">{{ __('Betrag') }}</th>
                        <th>{{ __('lexware.field.invoice_status') }}</th>
                        <th>{{ __('lexware.field.dispatch_status') }}</th>
                        <th>{{ __('lexware.field.handover_state') }}</th>
                        <th class="text-right">{{ __('lexware.field.action') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($invoices as $invoice)
                    @php
                        $state = $states[$invoice->id] ?? null;
                        $lastDispatch = $invoice->dispatches->first();
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox" name="invoices[]" value="{{ $invoice->sqid }}" class="checkbox checkbox-sm" aria-label="{{ __('lexware.field.select') }} {{ $invoice->number }}">
                        </td>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="link font-mono">{{ $invoice->number }}</a></td>
                        <td class="tabular-nums">{{ $invoice->issued_on?->fdate() ?? '—' }}</td>
                        <td>{{ $invoice->customer?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($invoice->total?->toFloat() ?? 0.0, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
                        <td><x-status-badge size="sm" outline>{{ __('values.' . $invoice->status) }}</x-status-badge></td>
                        <td class="text-xs">
                            @if ($lastDispatch)
                                {{ __('lexware.handover.dispatched', ['channel' => $lastDispatch->channel, 'at' => $lastDispatch->created_at?->fdatetime()]) }}
                            @else
                                <span class="text-muted">{{ __('lexware.handover.not_dispatched') }}</span>
                            @endif
                        </td>
                        <td class="text-xs">
                            @if ($state)
                                <x-status-badge size="sm" :tone="$state->status->tone()" :label="$state->status->label()" />
                                @if ($state->exported_at)<span class="block text-muted">{{ __('lexware.handover.exported_at', ['at' => $state->exported_at->fdatetime(), 'name' => $state->exporter?->name ?? '—']) }}</span>@endif
                                @if ($state->confirmed_at)<span class="block text-muted">{{ __('lexware.handover.confirmed_at', ['at' => $state->confirmed_at->fdatetime(), 'name' => $state->confirmer?->name ?? '—']) }}</span>@endif
                                @if ($state->confirmation_note)<span class="block text-muted">{{ $state->confirmation_note }}</span>@endif
                            @else
                                <x-status-badge size="sm" tone="ghost" :label="LexofficeHandoverStatus::Pending->label()" />
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <x-icon-btn icon="picture_as_pdf" tone="outline" size="xs" :href="route('invoices.pdf', $invoice)" :label="__('PDF')" />
                                @if ($canExport)
                                    <x-icon-btn icon="folder_zip" tone="outline" size="xs" :href="route('lexoffice.handover.export-one', $invoice)" :label="__('lexware.action.export_one')" />
                                    @if ($state === null || ! $state->status->isSettled())
                                        <details class="inline-block text-left">
                                            <summary class="btn btn-xs btn-ghost">{{ __('lexware.action.confirm') }}</summary>
                                            <div class="mt-2 rounded-box border border-base-300 bg-base-100 p-3 shadow">
                                                <x-input-field name="confirmation_note" id="confirm-note-{{ $invoice->sqid }}" :label="__('lexware.field.confirmation_note')" form="confirm-form-{{ $invoice->sqid }}" />
                                                <button type="submit" form="confirm-form-{{ $invoice->sqid }}" class="btn btn-xs btn-primary mt-2">{{ __('lexware.action.confirm_go') }}</button>
                                            </div>
                                        </details>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon="outbox" :colspan="9" :title="__('lexware.handover.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
    </form>

    {{-- Bestätigungsformulare außerhalb des Export-Formulars (verschachtelte Formulare sind ungültig). --}}
    @if ($canExport)
        @foreach ($invoices as $invoice)
            <form method="POST" action="{{ route('lexoffice.handover.confirm', $invoice) }}" id="confirm-form-{{ $invoice->sqid }}" class="hidden">@csrf</form>
        @endforeach
    @endif

    <p class="text-xs text-muted">{{ __('lexware.handover.note') }}</p>
</x-index-page>
@endsection
