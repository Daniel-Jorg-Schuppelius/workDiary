{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenliste aus einem Fremdsystem abgleichen (Feature 199, MVP-1640). --}}
@extends('layouts.app')
@section('title', __('service_plan.reconcile.title'))
@section('nav-title', __('service_plan.reconcile.title'))

@section('content')
<x-index-page :subtitle="__('service_plan.reconcile.subtitle')" back-route="service-plan.index" :back-label="__('service_plan.title')">
    <x-card :title="__('service_plan.import.upload')" :subtitle="__('service_plan.import.formats')">
        <form method="POST" action="{{ route('service-plan.reconcile.preview') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <x-input-field type="file" name="file" :label="__('service_plan.import.file')" accept=".csv,.txt,.xlsx" required />
            <x-validation-errors />
            <div class="flex justify-end">
                <x-icon-btn icon="arrow_forward" tone="primary" type="submit" show-label>{{ __('service_plan.import.to_preview') }}</x-icon-btn>
            </div>
        </form>
    </x-card>

    <x-card :title="__('service_plan.import.format_title')" icon="info">
        <ul class="list-disc space-y-1 pl-5 text-sm">
            <li>{{ __('service_plan.reconcile.format.columns') }}</li>
            <li>{{ __('service_plan.reconcile.format.matching') }}</li>
            <li>{{ __('service_plan.reconcile.format.confirm') }}</li>
        </ul>
        <div class="mt-3">
            <x-icon-btn icon="download" tone="outline" size="sm" :href="route('service-plan.reconcile.template')" show-label>{{ __('service_plan.reconcile.template') }}</x-icon-btn>
        </div>
    </x-card>
</x-index-page>
@endsection
