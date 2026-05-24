@extends('layouts.app')

@section('title', __('Supportbericht') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Supportbericht'))

@php
    /** @var array{total_estimated_kb:int, top_sections: list<array{key:string, kb:int}>} $preview */
    /** @var bool $canExportWithSamples */
@endphp

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar
            :title="__('Supportbericht')"
            :subtitle="__('Anonymisiertes Bundle zur Fehlerdiagnose')"
            :badge="$preview['total_estimated_kb'] . ' KB'"
            badge-tone="primary"
        >
            {{ __('Inhalt: Diagnose-Bericht, Logs (gefiltert), failed_jobs (Klassen), Migrations-Stand, Tabellen-Counts, Audit-Counts der letzten 24h. Keine Kundendaten, keine Anhänge, keine Secrets.') }}
        </x-page-toolbar>
    </x-slot:toolbar>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Inhalts-Übersicht') }}</h2>
            <p class="text-sm text-base-content/70">
                {{ __('Geschätzte Bundle-Größe vor ZIP-Kompression: :kb KB.', ['kb' => $preview['total_estimated_kb']]) }}
            </p>

            <ul class="space-y-1 text-sm">
                @foreach ($preview['top_sections'] as $section)
                    <li class="flex items-center justify-between gap-3 border-b border-base-200/70 pb-1 last:border-0">
                        <span class="font-mono text-xs text-base-content/80">{{ $section['key'] }}</span>
                        <span class="text-xs text-base-content/60">{{ $section['kb'] }} KB</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </article>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Bericht generieren') }}</h2>

            @if ($errors->any())
                <div class="alert alert-error">
                    <ul class="text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.support.report.generate') }}" class="space-y-3">
                @csrf

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="include_schema" value="1" class="checkbox checkbox-sm">
                    <span class="text-sm">{{ __('Schema-Dump (DDL ohne Daten) einbeziehen') }}</span>
                </label>

                <label class="flex items-start gap-2 {{ $canExportWithSamples ? '' : 'opacity-50' }}">
                    <input type="checkbox" name="include_samples" value="1" class="checkbox checkbox-sm"
                           {{ $canExportWithSamples ? '' : 'disabled' }}>
                    <span class="text-sm">
                        {{ __('Anonymisierte Sample-Aufträge (10 Stück) einbeziehen') }}
                        @unless ($canExportWithSamples)
                            <span class="block text-xs text-base-content/60">{{ __('Erfordert Plattform-Admin-Berechtigung.') }}</span>
                        @endunless
                    </span>
                </label>

                <div>
                    <label for="report-password" class="text-sm font-medium">{{ __('ZIP-Passwort (optional)') }}</label>
                    <input id="report-password" type="password" name="password" autocomplete="new-password"
                           class="input input-bordered input-sm w-full max-w-sm">
                    <p class="mt-1 text-xs text-base-content/60">
                        {{ __('Wird auf das ZIP-Archiv angewendet. Out-of-Band an den Support weitergeben (nicht in derselben E-Mail).') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-base-200/70 pt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-icon name="archive" />
                        {{ __('Bericht generieren und herunterladen') }}
                    </button>
                    <span class="text-xs text-base-content/60">
                        {{ __('Der Bericht wird erst beim Klick erzeugt. Vorher werden keine Daten geschrieben.') }}
                    </span>
                </div>
            </form>
        </div>
    </article>
</x-page-shell>
@endsection
