{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _girocode.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- GiroCode des Eingangs (Feature 208, MVP-1663): Lesung, gelesene Werte, Gegenprobe. Die volle IBAN sieht nur, wer Zahlungen freigeben darf. --}}
@php
    $giroReading = (array) ($incoming->girocode ?? []);
    $giroStatus = is_string($giroReading['status'] ?? null) ? $giroReading['status'] : null;
    // Vorgemerkt beim Eingang; der Bestand vor Feature 208 hat keine Lesung und zeigt keine Karte.
    $giroAwaited = $giroStatus === \App\Services\Invoicing\GiroCode\GiroCodeReader::PENDING
        && ! \App\Services\Invoicing\Split\IncomingInvoiceSplitter::isHolding($incoming);
    if ($giroAwaited) {
        $giroStatus = null;
    }
    $giroFull = \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::FinancePaymentRelease->value);
    $giroNotes = array_values(array_diff(
        app(\App\Services\Invoicing\GiroCode\GiroCodeCheck::class)->findings($incoming),
        [...\App\Services\Invoicing\GiroCode\GiroCodeCheck::BLOCKING, \App\Services\Invoicing\GiroCode\GiroCodeCheck::UNREADABLE, \App\Services\Invoicing\GiroCode\GiroCodeCheck::INVALID],
    ));
    $giroReasons = [
        'tool_missing' => __('incoming_pdf.girocode.reason.tool_missing'),
        'too_large' => __('incoming_pdf.girocode.reason.too_large'),
        'scan_failed' => __('incoming_pdf.girocode.reason.scan_failed'),
        'changed' => __('incoming_pdf.girocode.reason.changed'),
        'page_size' => __('incoming_pdf.girocode.reason.page_size'),
    ];
@endphp
@if (($giroStatus !== null && $giroStatus !== \App\Services\Invoicing\GiroCode\GiroCodeReader::PENDING) || $giroAwaited)
    <x-card :title="__('incoming_pdf.girocode.title')" icon="qr_code_2">
        @if ($giroStatus === null)
            <p class="text-sm text-muted">{{ __('incoming_pdf.girocode.pending') }}</p>
        @else
            <p class="text-sm">
                @if ($giroStatus === \App\Services\Invoicing\GiroCode\GiroCodeReader::FOUND)
                    {{ __('incoming_pdf.girocode.status.found', ['page' => (int) ($giroReading['page'] ?? 1)]) }}
                @elseif ($giroStatus === \App\Services\Invoicing\GiroCode\GiroCodeReader::UNREADABLE)
                    {{ __('incoming_pdf.girocode.status.unreadable') }}
                    @if (isset($giroReasons[$giroReading['reason'] ?? '']))
                        <span class="text-muted">{{ $giroReasons[$giroReading['reason']] }}</span>
                    @endif
                @elseif ($giroStatus === \App\Services\Invoicing\GiroCode\GiroCodeReader::INVALID)
                    {{ __('incoming_pdf.girocode.status.invalid') }}
                @else
                    {{ __('incoming_pdf.girocode.status.none') }}
                @endif
            </p>
            @if ($giroStatus === \App\Services\Invoicing\GiroCode\GiroCodeReader::FOUND)
                <x-detail-grid>
                    <x-detail-grid.row :label="__('incoming_pdf.girocode.field.name')">{{ $giroReading['name'] ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('incoming_pdf.girocode.field.iban')">
                        <span class="font-mono">{{ $giroFull ? \CommonToolkit\Helper\Data\BankHelper::formatIBAN((string) ($giroReading['iban'] ?? '')) : \App\Services\Invoicing\GiroCode\GiroCodeCheck::maskIban((string) ($giroReading['iban'] ?? '')) }}</span>
                    </x-detail-grid.row>
                    @if (is_string($giroReading['bic'] ?? null))
                        <x-detail-grid.row :label="__('incoming_pdf.girocode.field.bic')"><span class="font-mono">{{ $giroReading['bic'] }}</span></x-detail-grid.row>
                    @endif
                    @if (is_string($giroReading['amount'] ?? null))
                        <x-detail-grid.row :label="__('incoming_pdf.girocode.field.amount')">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $giroReading['amount'], 2, withThousandsSeparator: true) }} EUR</x-detail-grid.row>
                    @endif
                    @if (is_string($giroReading['reference'] ?? null) || is_string($giroReading['remittance'] ?? null))
                        <x-detail-grid.row :label="__('incoming_pdf.girocode.field.reference')">{{ $giroReading['reference'] ?? $giroReading['remittance'] }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
                @if (($giroReading['filled_iban'] ?? false) === true)
                    <p class="mt-2 text-xs text-muted">{{ __('incoming_pdf.girocode.iban_applied') }}</p>
                @endif
            @endif
            <div class="mt-2 flex flex-col gap-2">
                @include('finance.incoming-invoices._girocode_warning', ['incoming' => $incoming])
                @foreach ($giroNotes as $note)
                    <div role="status" class="alert alert-info text-sm">
                        <x-icon name="info" />
                        <span>{{ __('incoming_pdf.girocode.finding.' . $note) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@endif
