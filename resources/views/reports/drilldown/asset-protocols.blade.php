{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : asset-protocols.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Defektprotokolle (Asset)'))
@section('nav-title', __('Drilldown: Defektprotokolle (Asset)'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.assets', array_filter($filters, fn($v) => $v !== null && $v !== ''))" :back-label="__('Zur Produktanalyse')">
            <x-slot:subtitle>
                {{ __('Bereich') }}: {{ $scopeLabel }} · {{ $label }}
            </x-slot:subtitle>
            <x-slot:actions>
                @include('reports.drilldown._export_actions', ['route' => 'reports.assets.drilldown.protocols', 'params' => $filters])
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.drilldown._protocols_table')
</x-page-shell>
@endsection
