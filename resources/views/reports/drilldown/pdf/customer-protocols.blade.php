{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-protocols.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Kunden Drilldown - Defektprotokolle')
@section('pdf-heading', 'Kunden Drilldown: Defektprotokolle')

@section('pdf-meta')
    Kunde: {{ $customerName }}<br>
    Zeitraum: {{ $label }}
@endsection

@section('pdf-table')
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Typ</th>
                <th>Zeitpunkt</th>
                <th>{{ __('Erstellt von') }}</th>
                <th>Auftrag</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($protocols as $protocol)
                <tr>
                    <td>{{ $protocol->id }}</td>
                    <td>{{ $protocol->title }}</td>
                    <td>{{ $protocol->status->label() }}</td>
                    <td>{{ $protocol->type->label() }}</td>
                    <td>{{ $protocol->occurred_at->orgTz()->format('Y-m-d H:i') }}</td>
                    <td>{{ $protocol->creator?->name ?? '' }}</td>
                    <td>{{ $protocol->subject_id }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
