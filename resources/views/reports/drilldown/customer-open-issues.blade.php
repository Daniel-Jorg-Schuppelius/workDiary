{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte'))
@section('nav-title', __('Drilldown: Offene Punkte'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.customers', array_filter(['project_id' => \App\Support\Sqid::encode(\App\Models\Project\Project::class, $projectId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId)]))" :back-label="__('Zur Kundenanalyse')">
            <x-slot:subtitle>
                {{ __('Kunde') }}: {{ $customer?->name ?? ('#' . $customerId) }} · {{ $label }}
                @if ($escalatedOnly)
                    · {{ __('Nur eskaliert') }}
                @endif
            </x-slot:subtitle>
            <x-slot:actions>
                @include('reports.drilldown._export_actions', ['route' => 'reports.customers.drilldown.open-issues', 'params' => ['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $customerId), 'project_id' => \App\Support\Sqid::encode(\App\Models\Project\Project::class, $projectId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId), 'escalated' => $escalatedOnly ? 1 : null]])
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.drilldown._open_issues_table')
</x-page-shell>
@endsection
