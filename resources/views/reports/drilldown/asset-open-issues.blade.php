{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : asset-open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte (Asset)'))
@section('nav-title', __('Drilldown: Offene Punkte (Asset)'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.assets', array_filter($filters, fn($v) => $v !== null && $v !== ''))" :back-label="__('Zur Produktanalyse')">
            <x-slot:subtitle>
                {{ __('Bereich') }}: {{ $scopeLabel }} · {{ $label }}
                @if ($escalatedOnly)
                    · {{ __('Nur eskaliert') }}
                @endif
            </x-slot:subtitle>
            <x-slot:actions>
                @include('reports.drilldown._export_actions', ['route' => 'reports.assets.drilldown.open-issues', 'params' => $filters + ['escalated' => $escalatedOnly ? 1 : null]])
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.drilldown._open_issues_table', ['showAsset' => true])
</x-page-shell>
@endsection
