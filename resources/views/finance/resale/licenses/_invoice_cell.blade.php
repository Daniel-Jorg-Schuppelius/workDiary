{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _invoice_cell.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsbezug eines Lizenzverkaufs (Feature 185, MVP-1427): Belegnummer mit
  Sprung ins Buchhaltungsprogramm bzw. Belegbild, wenn die Position im Spiegel
  liegt; sonst die Freitext-Referenz; ohne Bezug nach der Frist ein Hinweis.
  Erwartet: $assignment (ResaleLicenseAssignment|null), optional $reference (Stichtag).
--}}
@php
    $mirrorLine = $assignment?->mirrorLine();
    $overdue = $assignment !== null && \App\Services\Reselling\License\LicenseInvoiceLinks::isOverdue($assignment, $reference ?? null);
@endphp
@if ($assignment === null)
    <span class="text-muted">—</span>
@elseif ($mirrorLine !== null)
    <span class="inline-flex items-center gap-1">
        <span>{{ $mirrorLine->voucherNumber ?? $assignment->invoice_reference ?? '—' }}</span>
        @if ($mirrorLine->permalink)
            <x-external-link :url="$mirrorLine->permalink" plain class="btn btn-ghost btn-xs" :title="__('resale.mirror.open_source', ['source' => __('resale.mirror.source_' . $mirrorLine->sourceKey)])"><x-icon name="open_in_new" size="1rem" /></x-external-link>
        @endif
        @if ($mirrorLine->previewUrl !== null)
            <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost" data-entry-modal-trigger :href="$mirrorLine->previewUrl" :title="__('resale.invoices.preview')" />
        @endif
    </span>
    <span class="block text-xs text-muted">{{ $mirrorLine->label() }}</span>
@elseif ($assignment->hasInvoice())
    {{ $assignment->invoice_reference }}
@elseif ($overdue)
    <x-status-badge size="xs" tone="warning" outline :label="__('resale.license.unbilled_badge')" :title="__('resale.license.unbilled_hint', ['days' => \App\Services\Reselling\License\LicenseInvoiceLinks::UNBILLED_DAYS])" />
@else
    <span class="text-muted">—</span>
@endif
