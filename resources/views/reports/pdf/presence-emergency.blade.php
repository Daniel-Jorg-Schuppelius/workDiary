@extends('reports.pdf.layout')

@php
    $tz = \App\Support\Tz::current();
    $locale = app()->getLocale();
    $fmt = fn ($c) => $c?->setTimezone($tz)->locale($locale)->isoFormat('L LT');
    $atLabel = $fmt($snapshot['at']);
@endphp

@section('pdf-title', __('reporting.presence_emergency.title') . ' – ' . $atLabel)
@section('pdf-heading', __('reporting.presence_emergency.title'))

@section('pdf-meta')
    {{ __('reporting.presence_emergency.stand') }}: <strong>{{ $atLabel }}</strong> ·
    {{ $snapshot['is_live'] ? __('reporting.presence_emergency.live') : __('reporting.presence_emergency.reconstructed') }} ·
    {{ __('reporting.presence_emergency.generated') }}: {{ $fmt($generatedAt) }}
@endsection

@section('pdf-table')
    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('reporting.presence_emergency.group_present') }}</div>
                <div class="value">{{ count($snapshot['present']) + count($snapshot['present_unmapped']) }}</div></td>
            <td><div class="label">{{ __('reporting.presence_emergency.group_off_site') }}</div>
                <div class="value">{{ count($snapshot['off_site']) }}</div></td>
            <td><div class="label">{{ __('reporting.presence_emergency.group_absent') }}</div>
                <div class="value">{{ count($snapshot['absent']) }}</div></td>
            <td><div class="label">{{ __('reporting.presence_emergency.group_unaccounted') }}</div>
                <div class="value">{{ count($snapshot['unaccounted']) }}</div></td>
        </tr>
    </table>

    <h3>{{ __('reporting.presence_emergency.group_present') }}</h3>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('reporting.presence_emergency.col_name') }}</th>
                <th>{{ __('reporting.presence_emergency.col_since') }}</th>
                <th>{{ __('reporting.presence_emergency.col_site') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($snapshot['present'] as $row)
                <tr>
                    <td>{{ $row['user']->name }}{{ $row['on_break'] ? ' (' . __('reporting.presence_emergency.on_break') . ')' : '' }}</td>
                    <td>{{ $fmt($row['since']) }}</td>
                    <td>{{ $row['site_name'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center; padding:8px; color:#888;">{{ __('reporting.presence_emergency.empty_group') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($snapshot['present_unmapped'] !== [])
        <h3>{{ __('reporting.presence_emergency.group_present_unmapped') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('reporting.presence_emergency.col_name') }}</th>
                    <th>{{ __('reporting.presence_emergency.col_since') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($snapshot['present_unmapped'] as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ $fmt($row['since']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('reporting.presence_emergency.group_off_site') }}</h3>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('reporting.presence_emergency.col_name') }}</th>
                <th>{{ __('reporting.presence_emergency.col_since') }}</th>
                <th>{{ __('reporting.presence_emergency.col_context') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($snapshot['off_site'] as $row)
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td>{{ $fmt($row['since']) }}</td>
                    <td>{{ $row['context'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center; padding:8px; color:#888;">{{ __('reporting.presence_emergency.empty_group') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ __('reporting.presence_emergency.group_absent') }}</h3>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('reporting.presence_emergency.col_name') }}</th>
                <th>{{ __('reporting.presence_emergency.col_reason') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($snapshot['absent'] as $row)
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td>{{ __('reporting.presence_emergency.reason_' . $row['reason']) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="text-align:center; padding:8px; color:#888;">{{ __('reporting.presence_emergency.empty_group') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ __('reporting.presence_emergency.group_unaccounted') }}</h3>
    <table class="data">
        <thead>
            <tr><th>{{ __('reporting.presence_emergency.col_name') }}</th></tr>
        </thead>
        <tbody>
            @forelse ($snapshot['unaccounted'] as $row)
                <tr><td>{{ $row['user']->name }}</td></tr>
            @empty
                <tr><td style="text-align:center; padding:8px; color:#888;">{{ __('reporting.presence_emergency.empty_group') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top:10px; font-size:9px; color:#777;">{{ __('reporting.presence_emergency.deviation_note') }}</p>
@endsection
