{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : on_call_a4.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.print')

@section('content')
@php
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var \Illuminate\Support\Collection<int, \App\Models\OnCallShift> $shifts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\EmergencyAssignment> $assignments */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */
@endphp

@include('print._header', [
    'title'     => $title,
    'subtitle'  => $subtitle ?? null,
    'org'       => $org ?? null,
    'extraMeta' => ($anonymous ? __('Anonymisiert') . ' · ' : '')
                . __('Bereitschaft') . ': ' . $shifts->count() . ' · '
                . __('Notdienste') . ': ' . $assignments->count(),
])

<h2>{{ __('Bereitschaft') }}</h2>
@if ($shifts->isEmpty())
    <p class="muted">{{ __('Keine Bereitschaften im gewählten Zeitraum.') }}</p>
@else
    <table>
        <colgroup>
            <col style="width: 40mm;">
            <col style="width: 32mm;">
            <col style="width: 32mm;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Beginn') }}</th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Notiz') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shifts as $s)
                <tr>
                    <td><strong>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($s->user?->name) : ($s->user?->name ?? '—') }}</strong></td>
                    <td>{{ $s->start_at?->fdatetime() }}</td>
                    <td>{{ $s->end_at?->fdatetime() }}</td>
                    <td>{{ $s->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>{{ __('Notdienste') }}</h2>
@if ($assignments->isEmpty())
    <p class="muted">{{ __('Keine Notdienste im gewählten Zeitraum.') }}</p>
@else
    <table>
        <colgroup>
            <col style="width: 40mm;">
            <col style="width: 32mm;">
            <col style="width: 32mm;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Beginn') }}</th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Grund') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assignments as $a)
                <tr>
                    <td><strong>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($a->user?->name) : ($a->user?->name ?? '—') }}</strong></td>
                    <td>{{ $a->start_at?->fdatetime() }}</td>
                    <td>{{ $a->end_at?->fdatetime() }}</td>
                    <td>{{ $a->reason }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    <span>{{ $org ?? '' }}</span>
    <span>{{ now()->fdatetime() }}</span>
</div>
@endsection
