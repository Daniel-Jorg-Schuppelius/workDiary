{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-protocols.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Defektprotokolle'))
@section('nav-title', __('Drilldown: Defektprotokolle'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.customers', array_filter(['project_id' => \App\Support\Sqid::encode(\App\Models\Project\Project::class, $projectId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId)]))" :back-label="__('Zur Kundenanalyse')">
            <x-slot:subtitle>
                {{ __('Kunde') }}: {{ $customer?->name ?? ('#' . $customerId) }} · {{ $label }}
            </x-slot:subtitle>
            <x-slot:actions>
                @include('reports.drilldown._export_actions', ['route' => 'reports.customers.drilldown.protocols', 'params' => ['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $customerId), 'project_id' => \App\Support\Sqid::encode(\App\Models\Project\Project::class, $projectId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId)]])
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.drilldown._protocols_table')
</x-page-shell>
@endsection
