{{--
  Created on   : Sat Oct 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : ebics-letter.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Initialisierungsbrief INI/HIA (MVP-124). Erwartet: $connection, $organization, $keys, $printedAt --}}
<x-pdf-layout pdf-type="report" :pdf-title="__('ebics.letter.title')">
    <h1>{{ __('ebics.letter.title') }}</h1>
    <div class="meta">{{ $organization->name }} · {{ $connection->bankAccount?->label }} · {{ $connection->bankAccount?->iban }}</div>

    <table>
        <tbody>
            <tr><th>{{ __('ebics.field.host_url') }}</th><td>{{ $connection->host_url }}</td></tr>
            <tr><th>{{ __('ebics.field.ebics_host') }}</th><td>{{ $connection->ebics_host }}</td></tr>
            <tr><th>{{ __('ebics.field.ebics_partner') }}</th><td>{{ $connection->ebics_partner }}</td></tr>
            <tr><th>{{ __('ebics.field.ebics_user') }}</th><td>{{ $connection->ebics_user }}</td></tr>
            <tr><th>{{ __('ebics.letter.sent_at') }}</th><td>{{ $connection->initialized_at?->fdatetime() }}</td></tr>
        </tbody>
    </table>

    @foreach ($keys as $key)
        <h2>{{ __('ebics.letter.key.' . $key['type']) }} ({{ $key['version'] }})</h2>
        <p>{{ __('ebics.letter.hash') }}</p>
        <p style="font-family: monospace; font-size: 10pt;">{{ implode(' ', str_split(strtoupper($key['hash']), 2)) }}</p>
        @if ($key['certificate_created_at'])
            <p style="font-size: 8pt;">{{ __('ebics.letter.certificate', ['date' => \Carbon\CarbonImmutable::parse($key['certificate_created_at'])->format('d.m.Y')]) }}</p>
        @endif
    @endforeach

    <p style="margin-top: 24pt;">{{ __('ebics.letter.confirmation') }}</p>
    <table style="margin-top: 36pt;">
        <tbody>
            <tr>
                <td style="border-top: 1px solid #000; width: 45%;">{{ __('ebics.letter.place_date') }}</td>
                <td style="width: 10%;"></td>
                <td style="border-top: 1px solid #000; width: 45%;">{{ __('ebics.letter.signature') }}</td>
            </tr>
        </tbody>
    </table>
    <p style="font-size: 8pt;">{{ __('ebics.letter.printed_at', ['date' => $printedAt->fdatetime()]) }}</p>
</x-pdf-layout>
