{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _badge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übergabestand eines Belegs an Lexware (Feature 158) — nur wenn die
  Organisation eine lokale Ergänzung aktiviert hat. Erwartet: $invoice.
--}}
@php
    $lexwareProfile = app(\App\Plugins\Lexoffice\Tariff\LexwareTariffService::class)->profile();
    $lexwareState = $lexwareProfile->localFeatures !== [] && $invoice->status !== \App\Models\Invoicing\Invoice::STATUS_DRAFT
        ? app(\App\Plugins\Lexoffice\Handover\LexofficeInvoiceHandoverService::class)->stateFor($invoice)
        : null;
    $lexwareActive = $lexwareProfile->localFeatures !== [];
@endphp
@if ($lexwareActive && $invoice->status !== \App\Models\Invoicing\Invoice::STATUS_DRAFT)
    <span class="inline-flex items-center gap-1 text-xs" title="{{ __('lexware.handover.title') }}">
        <x-icon name="outbox" size="1em" class="text-muted" />
        @if ($lexwareState)
            <x-status-badge size="xs" :tone="$lexwareState->status->tone()" :label="$lexwareState->status->label()" />
        @else
            <x-status-badge size="xs" tone="ghost" :label="\App\Enums\Lexoffice\LexofficeHandoverStatus::Pending->label()" />
        @endif
    </span>
@endif
