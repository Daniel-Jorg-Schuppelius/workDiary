{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : create.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Liste in den Leistungsplan übernehmen (Feature 199, MVP-1640). Variablen: $services, $year. --}}
@extends('layouts.app')
@section('title', __('service_plan.import.title'))
@section('nav-title', __('service_plan.import.title'))

@section('content')
<x-index-page :subtitle="__('service_plan.import.subtitle')" back-route="service-plan.index" :back-label="__('service_plan.title')">

    <x-card :title="__('service_plan.import.upload')" :subtitle="__('service_plan.import.formats')">
        <form method="POST" action="{{ route('service-plan.import.preview') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input-field type="file" name="file" :label="__('service_plan.import.file')" accept=".csv,.txt,.xlsx" required span="2" />
                <x-input-field type="number" name="year" :label="__('service_plan.filter.year')" min="2000" max="2100" :value="old('year', $year)" required
                               :hint="__('service_plan.import.year_hint')" />
                <x-select-field name="service" :label="__('service_plan.import.fixed_service')" :hint="__('service_plan.import.fixed_service_hint')">
                    <option value="">{{ __('service_plan.import.service_from_column') }}</option>
                    @foreach ($services as $column)
                        <option value="{{ $column->service->param() }}" @selected(old('service') === $column->service->param())>{{ $column->label }} ({{ $column->kind->label() }})</option>
                    @endforeach
                </x-select-field>
                <x-checkbox-field name="create_orders" span="2" :label="__('service_plan.import.create_orders')" :hint="__('service_plan.import.create_orders_hint')" :checked="(bool) old('create_orders', false)" />
            </div>
            <x-validation-errors />
            <div class="flex justify-end">
                <x-icon-btn icon="arrow_forward" tone="primary" type="submit" show-label>{{ __('service_plan.import.to_preview') }}</x-icon-btn>
            </div>
        </form>
    </x-card>

    <x-card :title="__('service_plan.import.format_title')" icon="info">
        <ul class="list-disc space-y-1 pl-5 text-sm">
            <li>{{ __('service_plan.import.format.year_list') }}</li>
            <li>{{ __('service_plan.import.format.period_list') }}</li>
            <li>{{ __('service_plan.import.format.traits') }}</li>
            <li>{{ __('service_plan.import.format.states') }}</li>
            <li>{{ __('service_plan.import.format.matching') }}</li>
        </ul>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-icon-btn icon="download" tone="outline" size="sm" :href="route('service-plan.import.template', 'jahresliste')" show-label>{{ __('service_plan.import.template_year') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="outline" size="sm" :href="route('service-plan.import.template', 'periodenliste')" show-label>{{ __('service_plan.import.template_periods') }}</x-icon-btn>
        </div>
    </x-card>
</x-index-page>
@endsection
