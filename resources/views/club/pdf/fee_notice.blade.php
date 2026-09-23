{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fee_notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Beitragsmitteilung als PDF (Feature 159, MVP-850): Zeitraum, Aufschlüsselung,
  Fälligkeit, Zahlungsreferenz und Bankblock. Kein Steuerausweis — die
  Beleg-/Steuerzuordnung wird in der Buchhaltung konfiguriert.
  Variablen: $claim, $orgLegal, $footerText, $design (DesignContext)
--}}
@php
    /** @var \App\Models\Club\ClubFeeClaim $claim */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $accent = $design->accentColor();
    $payer = (array) ($claim->payer_snapshot ?? []);
    $legal = $orgLegal ?? [];
    $hasOrgBank = ! empty($legal['iban']) || ! empty($legal['bic']) || ! empty($legal['bank_name']);
    $isCorrection = $claim->isCorrection();
    $dunning ??= null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ __('club.fees.pdf.kind') }} {{ $claim->number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin: 0 0 4px; color: {{ $accent }}; }
    .header { display: table; width: 100%; margin-bottom: 16px; }
    .header .left, .header .right { display: table-cell; vertical-align: top; width: 50%; }
    .right { text-align: right; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #ccc; }
    th { background: #f3f3f3; text-align: left; }
    td.num, th.num { text-align: right; }
    tfoot td { border-top: 2px solid {{ $accent }}; font-weight: bold; border-bottom: none; }
    .banner { padding: 8px 12px; margin-bottom: 12px; font-weight: bold; border: 2px solid; color: #b91c1c; border-color: #b91c1c; background: #fee2e2; }
    .tpl-header { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #ddd; white-space: pre-line; }
    .tpl-footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 10px; color: #555; white-space: pre-line; }
    .bank-block { margin-top: 18px; padding: 8px 10px; border: 1px solid #ddd; font-size: 10px; }
    .bank-block table { width: 100%; margin: 0; }
    .bank-block table td { border: none; padding: 2px 6px; }
    .bank-block .label { color: #666; width: 25%; }
    .meta td { border: none; padding: 1px 4px; }
    @page { margin: 20mm; }
</style>
</head>
<body>
@if ($design->headerText() !== null)
    <div class="tpl-header">{{ $design->headerText() }}</div>
@endif
@if ($claim->status === \App\Enums\Club\ClubFeeClaimStatus::Cancelled)
    <p class="banner">{{ __('club.fees.pdf.cancelled', ['date' => $claim->cancelled_at?->translatedFormat('d.m.Y')]) }}</p>
@endif
@if ($dunning)
    {{-- Mahnstufe (MVP-851): Zahlungsziel und Gebühr sichtbar, kein neuer Beleg. --}}
    <p class="banner" style="color:#b45309;border-color:#b45309;background:#fef3c7;">{{ __('club.fees.pdf.dunning_level_' . min(3, $dunning->level)) }}@if ($dunning->pay_until) — {{ __('club.fees.pdf.pay_until', ['date' => $dunning->pay_until->translatedFormat('d.m.Y')]) }}@endif @if ($dunning->fee && ! $dunning->fee->isZero()) — {{ __('club.fees.pdf.dunning_fee', ['amount' => $dunning->fee->format()]) }}@endif</p>
    @if ($dunning->note)<p>{{ $dunning->note }}</p>@endif
@endif

<div class="header">
    <div class="left">
        @if ($design->show(\App\Enums\DocumentDesign\InformationBlock::RecipientAddress))
            <div>{{ $payer['name'] ?? $claim->account?->name }}</div>
            @if (! empty($payer['company']))<div>{{ $payer['company'] }}</div>@endif
            @if (! empty($payer['street']))<div>{{ $payer['street'] }}</div>@endif
            @if (! empty($payer['zip']) || ! empty($payer['city']))<div>{{ trim(($payer['zip'] ?? '') . ' ' . ($payer['city'] ?? '')) }}</div>@endif
        @endif
    </div>
    <div class="right">
        <h1>{{ $dunning ? __('club.fees.pdf.dunning_level_' . min(3, $dunning->level)) : ($isCorrection ? __('club.fees.pdf.kind_correction') : __('club.fees.pdf.kind')) }}</h1>
        <table class="meta" style="margin: 0; width: auto; margin-left: auto;">
            <tr><td>{{ __('club.fees.field.number') }}</td><td><strong>{{ $claim->number }}</strong></td></tr>
            <tr><td>{{ __('club.fees.field.issued_on') }}</td><td>{{ $claim->issued_on->translatedFormat('d.m.Y') }}</td></tr>
            <tr><td>{{ __('club.field.period') }}</td><td>{{ $claim->period_start->translatedFormat('d.m.Y') }} – {{ $claim->period_end->translatedFormat('d.m.Y') }}</td></tr>
            <tr><td>{{ __('club.fees.field.due_on') }}</td><td><strong>{{ $claim->due_on->translatedFormat('d.m.Y') }}</strong></td></tr>
            @if (! empty($payer['customer_number']))<tr><td>{{ __('club.fees.field.customer') }}</td><td>{{ $payer['customer_number'] }}</td></tr>@endif
            @if ($isCorrection && $claim->correctedClaim)<tr><td>{{ __('club.fees.label.corrects') }}</td><td>{{ $claim->correctedClaim->number }}</td></tr>@endif
        </table>
    </div>
</div>

<p>{{ $isCorrection ? __('club.fees.pdf.intro_correction', ['reason' => (string) $claim->reason]) : __('club.fees.pdf.intro') }}</p>

<table>
    <thead>
        <tr>
            <th>{{ __('club.field.member') }}</th>
            <th>{{ __('club.fees.field.position') }}</th>
            <th>{{ __('club.field.period') }}</th>
            <th class="num">{{ __('club.fees.field.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($claim->items as $item)
            <tr>
                <td>{{ $item->member?->fullName() ?? __('club.fees.label.whole_account') }}</td>
                <td>{{ $item->label }}@if (! empty($item->basis['active_days']) && ($item->basis['active_days'] !== $item->basis['period_days'])) <span style="color:#666;">({{ __('club.fees.label.basis_days', ['active' => $item->basis['active_days'], 'total' => $item->basis['period_days']]) }})</span>@endif</td>
                <td>{{ $item->period_start->translatedFormat('d.m.Y') }} – {{ $item->period_end->translatedFormat('d.m.Y') }}</td>
                <td class="num">{{ $item->amount->format() }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="3" class="num">{{ __('club.fees.label.total') }}</td><td class="num">{{ $claim->total->format() }}</td></tr>
        @if (! $claim->paid_amount->isZero())
            <tr><td colspan="3" class="num" style="font-weight:normal;">{{ __('club.fees.field.paid_amount') }}</td><td class="num" style="font-weight:normal;">{{ $claim->paid_amount->format() }}</td></tr>
            <tr><td colspan="3" class="num">{{ __('club.fees.field.open_amount') }}</td><td class="num">{{ $claim->openAmount()->format() }}</td></tr>
        @endif
    </tfoot>
</table>

@if ($hasOrgBank && $claim->total->isPositive() && $design->show(\App\Enums\DocumentDesign\InformationBlock::BankDetails))
    <div class="bank-block">
        <strong>{{ __('club.fees.pdf.pay_to') }}:</strong>
        <table>
            @if (! empty($legal['account_holder']))<tr><td class="label">{{ __('Kontoinhaber') }}</td><td>{{ $legal['account_holder'] }}</td></tr>@endif
            @if (! empty($legal['bank_name']))<tr><td class="label">{{ __('Bank') }}</td><td>{{ $legal['bank_name'] }}</td></tr>@endif
            @if (! empty($legal['iban']))<tr><td class="label">{{ __('IBAN') }}</td><td>{{ $legal['iban'] }}</td></tr>@endif
            @if (! empty($legal['bic']))<tr><td class="label">{{ __('BIC') }}</td><td>{{ $legal['bic'] }}</td></tr>@endif
            <tr><td class="label">{{ __('Verwendungszweck') }}</td><td>{{ $claim->number }}</td></tr>
        </table>
    </div>
@endif

@if ($footerText !== '')
    <p style="margin-top: 14px; white-space: pre-line;">{{ $footerText }}</p>
@endif
@if ($design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
</body>
</html>
