{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-type-open-issues.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte (Auftragstyp)'))
@section('nav-title', __('Drilldown: Offene Punkte (Auftragstyp)'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.entry-types', array_filter(['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $customerId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId), 'entry_type_id' => \App\Support\Sqid::encode(\App\Models\Classification\EntryType::class, $entryTypeId), 'status' => $statusFilter]))" :back-label="__('Zur Auftragstypanalyse')">
            <x-slot:subtitle>
                {{ __('Auftragstyp') }}: {{ $entryType?->label ?? ('#' . $entryTypeId) }} · {{ $label }}
                @if ($escalatedOnly)
                    · {{ __('Nur eskaliert') }}
                @endif
            </x-slot:subtitle>
            <x-slot:actions>
                @include('reports.drilldown._export_actions', ['route' => 'reports.entry-types.drilldown.open-issues', 'params' => ['entry_type_id' => \App\Support\Sqid::encode(\App\Models\Classification\EntryType::class, $entryTypeId), 'customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $customerId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId), 'status' => $statusFilter, 'escalated' => $escalatedOnly ? 1 : null]])
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('reports.drilldown._open_issues_table')
</x-page-shell>
@endsection
