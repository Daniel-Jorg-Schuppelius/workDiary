{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-type-open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Auftragstyp Drilldown - Offene Punkte')
@section('pdf-heading', 'Auftragstyp Drilldown: Offene Punkte')

@section('pdf-meta')
    Auftragstyp: {{ $entryTypeLabel }}<br>
    Zeitraum: {{ $label }}
    @if ($escalatedOnly)
        <br>Filter: Nur eskalierte offene Punkte
    @endif
@endsection

@section('pdf-table')
    @include('reports.drilldown.pdf._open_issues_table')
@endsection
