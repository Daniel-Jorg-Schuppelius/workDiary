{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bcm-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- BCM-Auswertung (MVP-944). Erwartet: $report --}}
@extends('layouts.app')

@section('title', __('crisis.bcm_report.title'))
@section('nav-title', __('crisis.bcm_report.title'))

@section('content')
<x-index-page :subtitle="__('crisis.bcm_report.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('crisis.bcm-report.pdf')" show-label>{{ __('PDF') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('crisis.index')" show-label>{{ __('crisis.bcm_report.back') }}</x-icon-btn>
    </x-slot:actions>
    <x-card>
        <div class="[&_table]:w-full [&_th]:text-left [&_th]:font-medium [&_td]:text-right [&_td]:tabular-nums [&_tr]:border-b [&_tr]:border-base-200">
            @include('crisis._bcm_report_rows')
        </div>
        <p class="mt-3 text-xs text-muted">{{ __('crisis.bcm_report.disclaimer') }}</p>
    </x-card>
</x-index-page>
@endsection
