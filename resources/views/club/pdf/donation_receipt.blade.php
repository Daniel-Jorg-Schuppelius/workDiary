{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : donation_receipt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Zuwendungsbestätigung nach amtlichem Muster (BMF, Geldzuwendung/Mitgliedsbeitrag
  an Körperschaften nach § 5 Abs. 1 Nr. 9 KStG), Feature 159, MVP-1003. Bewusst
  deutschsprachig: Nur das amtliche Muster ist anerkannt. Sammelbestätigung mit
  Anlage je Zuwendung. Variablen: $receipt, $issuer, $amountInWords, $design
--}}
@php
    /** @var \App\Models\Club\ClubDonationReceipt $receipt */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $collective = $receipt->kind === \App\Enums\Club\ClubDonationReceiptKind::Collective;
    $exemption = (array) $receipt->exemption_snapshot;
    $donor = (array) $receipt->donor_snapshot;
    $donations = $receipt->donations;
    $hasFees = $donations->contains(fn ($d) => $d->kind === \App\Enums\Club\ClubDonationKind::MembershipFee);
    $allFees = $donations->every(fn ($d) => $d->kind === \App\Enums\Club\ClubDonationKind::MembershipFee);
    $waiver = $donations->contains('is_expense_waiver', true);
    $fmt = fn (\CommonToolkit\ValueObjects\Money $m): string => $m->format();
    $date = fn (?string $value): string => $value ? \Carbon\CarbonImmutable::parse($value)->format('d.m.Y') : '…';
    $title = $collective
        ? 'Sammelbestätigung über ' . ($allFees ? 'Mitgliedsbeiträge' : ($hasFees ? 'Geldzuwendungen/Mitgliedsbeiträge' : 'Geldzuwendungen'))
        : 'Bestätigung über ' . ($allFees ? 'Mitgliedsbeiträge' : 'Geldzuwendungen');
    $box = fn (bool $checked): string => $checked ? '☒' : '☐';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $receipt->displayNo() }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; line-height: 1.4; }
    h1 { font-size: 14px; margin: 12px 0 2px; }
    .small { font-size: 9px; color: #444; }
    .box { border: 1px solid #999; padding: 6px 8px; margin-top: 8px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 4px 6px; vertical-align: top; }
    .grid td { border: 1px solid #999; }
    .label { font-size: 9px; color: #555; display: block; }
    .list th { background: #f3f3f3; text-align: left; border-bottom: 1px solid #999; }
    .list td { border-bottom: 1px solid #ddd; }
    .num { text-align: right; }
    .signature { margin-top: 36px; border-top: 1px solid #555; width: 60%; padding-top: 2px; }
    @page { margin: 18mm; }
</style>
</head>
<body>
<div class="small">Aussteller (Bezeichnung und Anschrift der steuerbegünstigten Einrichtung)</div>
<div><strong>{{ $issuer['name'] }}</strong>@foreach ($issuer['lines'] as $line)<br>{{ $line }}@endforeach</div>
<div class="small" style="text-align: right;">Nr. {{ $receipt->displayNo() }}</div>

<h1>{{ $title }}</h1>
<div class="small">im Sinne des § 10b des Einkommensteuergesetzes an eine der in § 5 Abs. 1 Nr. 9 des Körperschaftsteuergesetzes bezeichneten Körperschaften, Personenvereinigungen oder Vermögensmassen</div>

<table class="grid" style="margin-top: 10px;">
    <tr><td colspan="3"><span class="label">Name und Anschrift des Zuwendenden</span>{{ $donor['name'] ?? '' }}@foreach ((array) ($donor['address'] ?? []) as $line)<br>{{ $line }}@endforeach</td></tr>
    <tr>
        <td style="width: 22%;"><span class="label">{{ $collective ? 'Gesamtbetrag der Zuwendung' : 'Betrag der Zuwendung' }} – in Ziffern –</span>{{ $fmt($receipt->total_amount) }}</td>
        <td><span class="label">– in Buchstaben –</span>{{ $amountInWords }}</td>
        <td style="width: 24%;"><span class="label">{{ $collective ? 'Zeitraum der Sammelbestätigung' : 'Tag der Zuwendung' }}</span>
            @if ($collective)
                01.01.{{ $receipt->year }} – 31.12.{{ $receipt->year }}
            @else
                {{ $donations->first()?->received_on->format('d.m.Y') }}
            @endif
        </td>
    </tr>
</table>

@unless ($collective)
    <p>Es handelt sich um den Verzicht auf Erstattung von Aufwendungen: Ja {{ $box($waiver) }} &nbsp; Nein {{ $box(! $waiver) }}</p>
@endunless

<div class="box">
    <p>{{ $box($exemption['exemption_kind'] === 'exemption_notice') }} Wir sind wegen Förderung {{ $exemption['purpose'] }} nach dem Freistellungsbescheid bzw. nach der Anlage zum Körperschaftsteuerbescheid des Finanzamtes {{ $exemption['tax_office'] }}, StNr. {{ $exemption['tax_number'] }}, vom {{ $exemption['exemption_kind'] === 'exemption_notice' ? $date($exemption['notice_date']) : '…' }} für den letzten Veranlagungszeitraum {{ $exemption['exemption_kind'] === 'exemption_notice' ? $exemption['assessment_period'] : '…' }} nach § 5 Abs. 1 Nr. 9 des Körperschaftsteuergesetzes von der Körperschaftsteuer und nach § 3 Nr. 6 des Gewerbesteuergesetzes von der Gewerbesteuer befreit.</p>
    <p>{{ $box($exemption['exemption_kind'] === 'assessment_60a') }} Die Einhaltung der satzungsmäßigen Voraussetzungen nach den §§ 51, 59, 60 und 61 AO wurde vom Finanzamt {{ $exemption['tax_office'] }}, StNr. {{ $exemption['tax_number'] }}, mit Bescheid vom {{ $exemption['exemption_kind'] === 'assessment_60a' ? $date($exemption['notice_date']) : '…' }} nach § 60a AO gesondert festgestellt. Wir fördern nach unserer Satzung {{ $exemption['purpose'] }}.</p>
</div>

<p>Es wird bestätigt, dass die Zuwendung nur zur Förderung {{ $exemption['purpose'] }} verwendet wird.</p>
@if ($hasFees)
    <p>Es wird bestätigt, dass es sich nicht um Mitgliedsbeiträge handelt, deren Abzug nach § 10b Abs. 1 des Einkommensteuergesetzes ausgeschlossen ist.</p>
@endif
@if ($collective)
    <p>Es wird bestätigt, dass über die in der Gesamtsumme enthaltenen Zuwendungen keine weiteren Bestätigungen, weder formelle Zuwendungsbestätigungen noch Beitragsquittungen oder ähnliches, ausgestellt wurden und werden.</p>
    <p>Ob es sich um den Verzicht auf Erstattung von Aufwendungen handelt, ist der Anlage zur Sammelbestätigung zu entnehmen.</p>
@endif

<div class="signature">{{ $receipt->issued_on->format('d.m.Y') }}@if (($exemption['signatory'] ?? '') !== ''), {{ $exemption['signatory'] }}@endif<br><span class="small">(Ort, Datum und Unterschrift des Zuwendungsempfängers)</span></div>

<p class="small" style="margin-top: 16px;"><strong>Hinweis:</strong> Wer vorsätzlich oder grob fahrlässig eine unrichtige Zuwendungsbestätigung erstellt oder veranlasst, dass Zuwendungen nicht zu den in der Zuwendungsbestätigung angegebenen steuerbegünstigten Zwecken verwendet werden, haftet für die entgangene Steuer (§ 10b Abs. 4 EStG, § 9 Abs. 3 KStG, § 9 Nr. 5 GewStG).<br>
Diese Bestätigung wird nicht als Nachweis für die steuerliche Berücksichtigung der Zuwendung anerkannt, wenn das Datum des Freistellungsbescheides länger als 5 Jahre bzw. das Datum der Feststellung der Einhaltung der satzungsmäßigen Voraussetzungen nach § 60a Abs. 1 AO länger als 3 Jahre seit Ausstellung des Bescheides zurückliegt (§ 63 Abs. 5 AO).</p>

@if ($collective)
    <div style="page-break-before: always;"></div>
    <h1>Anlage zur Sammelbestätigung {{ $receipt->displayNo() }}</h1>
    <table class="list">
        <thead><tr><th>Datum der Zuwendung</th><th>Art der Zuwendung</th><th>Verzicht auf die Erstattung von Aufwendungen</th><th class="num">Betrag</th></tr></thead>
        <tbody>
            @foreach ($donations as $donation)
                <tr>
                    <td>{{ $donation->received_on->format('d.m.Y') }}</td>
                    <td>{{ $donation->kind === \App\Enums\Club\ClubDonationKind::MembershipFee ? 'Mitgliedsbeitrag' : 'Geldzuwendung' }}</td>
                    <td>{{ $donation->is_expense_waiver ? 'ja' : 'nein' }}</td>
                    <td class="num">{{ $fmt($donation->amount) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="3"><strong>Gesamtsumme</strong></td><td class="num"><strong>{{ $fmt($receipt->total_amount) }}</strong></td></tr>
        </tbody>
    </table>
@endif
</body>
</html>
