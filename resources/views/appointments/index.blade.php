{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Terminanfragen'))
@section('nav-title', __('Terminanfragen'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Portal-Anfragen entscheiden — erst die Bestätigung erzeugt den Dispositions-Eintrag.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('appointments.services.create')"
                        show-label>{{ __('Leistungsart anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-tab-nav class="w-fit" :items="[
        ['label' => __('Offen'), 'icon' => 'inbox', 'route' => 'appointments.index', 'active' => $tab === 'open', 'count' => $openCount],
        ['label' => __('Entschieden'), 'icon' => 'task_alt', 'route' => 'appointments.index', 'params' => ['tab' => 'decided'], 'active' => $tab === 'decided'],
        ['label' => __('Leistungsarten'), 'icon' => 'design_services', 'route' => 'appointments.index', 'params' => ['tab' => 'services'], 'active' => $tab === 'services', 'count' => $serviceCount],
    ]" />

    @if ($tab === 'open')
        @include('appointments._tab_open')
    @elseif ($tab === 'decided')
        @include('appointments._tab_decided')
    @else
        @include('appointments._tab_services')
    @endif
</x-index-page>
@endsection
