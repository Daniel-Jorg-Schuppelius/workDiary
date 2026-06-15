@extends('layouts.app')
@section('title', __('safety.report.title'))
@section('nav-title', __('safety.report.nav'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('safety.report.subtitle')" />
    </x-slot:toolbar>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        <x-kpi-tile :label="__('safety.report.kpi.total')" :value="$total" />
        <x-kpi-tile :label="__('safety.report.kpi.open')" :value="$open" tone="warning" />
        <x-kpi-tile :label="__('safety.report.kpi.closed')" :value="$closed" tone="success" />
        <x-kpi-tile :label="__('safety.report.kpi.critical')" :value="$bySeverity['critical']" tone="error" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('safety.report.by_kind') }}</h3>
            <x-detail-grid>
                @foreach (\App\Enums\Safety\SafetyEventKind::cases() as $kind)
                    <x-detail-grid.row :label="$kind->label()" :value="(string) $byKind[$kind->value]" />
                @endforeach
            </x-detail-grid>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold">{{ __('safety.report.by_severity') }}</h3>
            <x-detail-grid>
                @foreach (\App\Enums\Safety\SafetyEventSeverity::cases() as $severity)
                    <x-detail-grid.row :label="$severity->label()" :value="(string) $bySeverity[$severity->value]" />
                @endforeach
            </x-detail-grid>
        </x-card>
    </div>
</x-page-shell>
@endsection
