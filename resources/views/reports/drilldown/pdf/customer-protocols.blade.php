{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-protocols.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Drilldown: Defektprotokolle'))
@section('pdf-heading', __('Drilldown: Defektprotokolle'))

@section('pdf-meta')
    {{ __('Kunde') }}: {{ $customerName }}<br>
    {{ __('Zeitraum') }}: {{ $label }}
@endsection

@section('pdf-table')
    @include('reports.drilldown.pdf._protocols_table')
@endsection
