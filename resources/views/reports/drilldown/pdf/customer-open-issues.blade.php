{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Kunden Drilldown - Offene Punkte')
@section('pdf-heading', 'Kunden Drilldown: Offene Punkte')

@section('pdf-meta')
    Kunde: {{ $customerName }}<br>
    Zeitraum: {{ $label }}
    @if ($escalatedOnly)
        <br>Filter: Nur eskalierte offene Punkte
    @endif
@endsection

@section('pdf-table')
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Severity</th>
                <th>{{ __('Fällig') }}</th>
                <th>Zugewiesen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($issues as $issue)
                <tr>
                    <td>{{ $issue->id }}</td>
                    <td>{{ $issue->title }}</td>
                    <td>{{ $issue->status->label() }}</td>
                    <td>{{ $issue->severity->label() }}</td>
                    <td>{{ $issue->due_at?->format('Y-m-d') ?? '' }}</td>
                    <td>{{ $issue->assignee?->name ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
