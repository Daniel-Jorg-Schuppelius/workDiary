{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : activity-report-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php $columns = $show_person ? 6 : 5; @endphp
<x-pdf-layout pdf-type="activity_report" :pdf-title="__('invoicing.activity_report.title_for', ['number' => $reference_value])">
    @push('pdf-styles')
        <style>
            .group-head td { background: #f3f4f6; font-weight: bold; }
            .sub td { border-top: 1px solid #999; }
            .note { font-size: 8pt; color: #6b7280; }
        </style>
    @endpush

    <h1>{{ __('invoicing.activity_report.title') }}</h1>
    <div class="meta">
        {{ $reference_label }}: <strong>{{ $reference_value }}</strong>
        @if ($reference_date) · {{ __('invoicing.activity_report.issued_on') }}: {{ $reference_date->fdate() }} @endif
        · {{ __('invoicing.activity_report.customer') }}: <strong>{{ $customer_name }}</strong>
        @if ($period_from)
            · {{ __('invoicing.activity_report.period') }}: {{ $period_from->fdate() }}@if ($period_to && ! $period_to->isSameDay($period_from)) – {{ $period_to->fdate() }}@endif
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('invoicing.activity_report.date') }}</th>
                @if ($show_person)<th>{{ __('invoicing.activity_report.person') }}</th>@endif
                <th>{{ __('invoicing.activity_report.from') }}</th>
                <th>{{ __('invoicing.activity_report.until') }}</th>
                <th class="right">{{ __('invoicing.activity_report.duration') }}</th>
                <th>{{ __('invoicing.activity_report.activity') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $group)
                <tr class="group-head">
                    <td colspan="{{ $columns }}">{{ $group['title'] }}</td>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['date']?->fdate() }}</td>
                        @if ($show_person)<td>{{ $row['person'] ?? '—' }}</td>@endif
                        <td>{{ $row['started_at']?->ftime() }}</td>
                        <td>{{ $row['ended_at']?->ftime() }}</td>
                        <td class="right">{{ \App\Support\Formats::duration((int) $row['minutes'], 'clock', withUnit: false) }}</td>
                        <td>{{ $row['description'] !== '' ? $row['description'] : '—' }}@if ($row['task']) <span class="note">({{ $row['task'] }})</span>@endif</td>
                    </tr>
                @endforeach
                <tr class="sub">
                    <td colspan="{{ $columns - 2 }}" class="right">{{ __('invoicing.activity_report.subtotal') }}</td>
                    <td class="right"><strong>{{ \App\Support\Formats::duration((int) $group['minutes'], 'clock') }}</strong></td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="right">{{ __('invoicing.activity_report.total') }}:</td>
            <td class="right" style="width:80pt;"><strong>{{ \App\Support\Formats::duration((int) $total_minutes, 'clock') }}</strong></td>
        </tr>
    </table>

    @if ($shown < $total)
        <p class="note">{{ __('invoicing.activity_report.truncated', ['shown' => $shown, 'total' => $total]) }}</p>
    @endif

    @if ($approvals !== [])
        <h2>{{ __('invoicing.activity_report.approvals') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('invoicing.activity_report.date') }}</th>
                    @if ($show_person)<th>{{ __('invoicing.activity_report.person') }}</th>@endif
                    <th>{{ __('invoicing.activity_report.signed_by') }}</th>
                    <th>{{ __('invoicing.activity_report.signed_at') }}</th>
                    <th>{{ __('invoicing.activity_report.signature_hash') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($approvals as $approval)
                    <tr>
                        <td>{{ $approval['date']?->fdate() }}</td>
                        @if ($show_person)<td>{{ $approval['person'] ?? '—' }}</td>@endif
                        <td>{{ $approval['signer'] ?: '—' }}@if ($approval['role']) ({{ $approval['role'] }})@endif</td>
                        <td>{{ $approval['signed_at']?->fdatetime() }}</td>
                        <td class="note">{{ \Illuminate\Support\Str::limit((string) $approval['hash'], 16, '…') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">{{ __('invoicing.activity_report.footer', ['datetime' => now()->fdatetime()]) }}</p>
</x-pdf-layout>
